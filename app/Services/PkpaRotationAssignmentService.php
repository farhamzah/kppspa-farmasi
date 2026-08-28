<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaEnrollmentRequirement;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaProgramSite;
use App\Models\PkpaRotationAssignment;
use App\Models\PkpaRotationAssignmentSupervisor;
use App\Models\PkpaSiteAvailabilityPeriod;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaRotationAssignmentService
{
    public function __construct(
        private readonly PkpaPlacementDurationService $durationService,
        private readonly PkpaPlacementCapacityService $capacityService,
        private readonly PkpaPlacementSupervisorService $supervisorService,
        private readonly PkpaPlacementPlanService $planService,
        private readonly PkpaAuditService $audit,
    ) {
    }

    public function save(PkpaPlacementPlan $plan, PkpaEnrollmentRequirement $requirement, array $data, ?User $actor): PkpaRotationAssignment
    {
        return DB::transaction(function () use ($plan, $requirement, $data, $actor) {
            $plan = PkpaPlacementPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if (! $plan->isEditable()) {
                throw ValidationException::withMessages(['plan' => 'Rancangan terkunci. Buat versi baru untuk mengubah penempatan.']);
            }

            $requirement->loadMissing('enrollment.activeGroupMembership.group', 'programDomain.practiceDomain');
            $this->assertRequirementFitsPlan($plan, $requirement);

            $programSite = PkpaProgramSite::with('practiceSite')->findOrFail($data['pkpa_program_site_id']);
            $availability = PkpaSiteAvailabilityPeriod::findOrFail($data['pkpa_site_availability_period_id']);
            $this->assertSiteFitsRequirement($plan, $requirement, $programSite, $availability);
            $this->assertDateWindow($plan, $availability, $data['start_date'], $data['end_date']);

            $assignment = PkpaRotationAssignment::where('pkpa_placement_plan_id', $plan->id)
                ->where('pkpa_enrollment_requirement_id', $requirement->id)
                ->lockForUpdate()
                ->first();

            if ($assignment && isset($data['row_version']) && (int) $data['row_version'] !== $assignment->row_version) {
                throw ValidationException::withMessages(['row_version' => 'Data sudah berubah oleh pengguna lain. Muat ulang sebelum menyimpan.']);
            }

            $duration = $this->durationService->calculate($requirement->programDomain, $availability, $data['start_date'], $data['end_date']);
            $messages = $this->validateAssignment($plan, $requirement, $programSite, $availability, $data, $assignment?->id);
            if ($messages['errors']) {
                throw ValidationException::withMessages(['assignment' => implode(' ', $messages['errors'])]);
            }

            $old = $assignment?->toArray();
            $payload = [
                'pkpa_placement_plan_id' => $plan->id,
                'pkpa_enrollment_id' => $requirement->pkpa_enrollment_id,
                'pkpa_enrollment_requirement_id' => $requirement->id,
                'pkpa_program_domain_id' => $requirement->pkpa_program_domain_id,
                'practice_domain_id' => $requirement->practice_domain_id,
                'selected_practice_domain_option_id' => $programSite->practice_domain_option_id,
                'pkpa_program_site_id' => $programSite->id,
                'pkpa_site_availability_period_id' => $availability->id,
                'practice_site_id' => $programSite->practice_site_id,
                'student_group_id_snapshot' => $requirement->enrollment->activeGroupMembership?->pkpa_student_group_id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'planned_duration_value' => $duration['planned_duration_value'],
                'planned_duration_unit' => $duration['planned_duration_unit'],
                'calculated_effective_days' => $duration['effective_days'],
                'calculated_practice_hours' => $duration['practice_hours'],
                'status' => count($messages['warnings']) || count($duration['warnings']) ? 'needs_attention' : 'valid',
                'planning_source' => $data['planning_source'] ?? 'individual',
                'validation_status' => count($messages['warnings']) || count($duration['warnings']) ? 'warning' : 'valid',
                'last_validated_at' => now(),
                'notes' => $data['notes'] ?? null,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ];

            if ($assignment) {
                $assignment->fill($payload);
                $assignment->row_version++;
                $assignment->save();
            } else {
                $assignment = PkpaRotationAssignment::create($payload + [
                    'created_by_core_user_id' => $actor?->core_user_id,
                    'row_version' => 1,
                ]);
            }

            $this->syncSupervisors($assignment, $data, $actor);
            $requirement->update([
                'status' => 'planned',
                'selected_practice_domain_option_id' => $programSite->practice_domain_option_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->planService->markStale($plan, $actor);
            $this->audit->record($actor, $old ? 'rotation_assignment_updated' : 'rotation_assignment_created', $assignment, $old, $assignment->fresh()->toArray());

            return $assignment->fresh(['supervisors']);
        });
    }

    public function deleteDraft(PkpaRotationAssignment $assignment, ?User $actor): void
    {
        DB::transaction(function () use ($assignment, $actor) {
            $assignment = PkpaRotationAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if (! $assignment->plan->isEditable()) {
                throw ValidationException::withMessages(['plan' => 'Rancangan terkunci.']);
            }
            if (! in_array($assignment->status, ['draft', 'needs_attention', 'valid'], true)) {
                throw ValidationException::withMessages(['assignment' => 'Penempatan ini tidak dapat dihapus pada Tahap 04.']);
            }

            $old = $assignment->toArray();
            $requirement = $assignment->requirement;
            $assignment->supervisors()->delete();
            $assignment->delete();

            if ($requirement && ! $requirement->rotationAssignments()->whereHas('plan', fn ($q) => $q->where('id', $assignment->pkpa_placement_plan_id))->whereNotIn('status', ['cancelled', 'superseded'])->exists()) {
                $requirement->update(['status' => 'pending', 'updated_by_core_user_id' => $actor?->core_user_id]);
            }

            $this->planService->markStale($assignment->plan, $actor);
            $this->audit->record($actor, 'rotation_assignment_deleted', $assignment, $old, null);
        });
    }

    public function validateAssignment(PkpaPlacementPlan $plan, PkpaEnrollmentRequirement $requirement, PkpaProgramSite $programSite, PkpaSiteAvailabilityPeriod $availability, array $data, ?int $excludeAssignmentId = null): array
    {
        $errors = [];
        $warnings = [];

        if ($this->hasStudentOverlap($plan, $requirement->enrollment, $data['start_date'], $data['end_date'], $excludeAssignmentId)) {
            $errors[] = 'Jadwal mahasiswa bentrok dengan penempatan wahana lain.';
        }

        $capacity = $this->capacityService->usage($plan, $availability, $data['start_date'], $data['end_date'], $excludeAssignmentId);
        if ($capacity['is_full']) {
            $errors[] = 'Kapasitas availability penuh.';
        }

        if (! empty($data['internal_supervisor_eligibility_id'])) {
            $supervisor = PkpaInternalSupervisorEligibility::findOrFail($data['internal_supervisor_eligibility_id']);
            $result = $this->supervisorService->validateInternal($plan, $supervisor, $requirement->practice_domain_id, $data['start_date'], $data['end_date'], $excludeAssignmentId);
            $errors = array_merge($errors, $result['errors']);
            $warnings = array_merge($warnings, $result['warnings']);
        } else {
            $errors[] = 'Pembimbing Dalam wajib dipilih.';
        }

        if (! empty($data['site_field_supervisor_id'])) {
            $supervisor = PkpaSiteFieldSupervisor::findOrFail($data['site_field_supervisor_id']);
            $result = $this->supervisorService->validateField($plan, $supervisor, $programSite->practice_site_id, $data['start_date'], $data['end_date'], $excludeAssignmentId);
            $errors = array_merge($errors, $result['errors']);
            $warnings = array_merge($warnings, $result['warnings']);
        } else {
            $errors[] = 'Pembimbing Lapangan wajib dipilih.';
        }

        return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
    }

    private function assertRequirementFitsPlan(PkpaPlacementPlan $plan, PkpaEnrollmentRequirement $requirement): void
    {
        $enrollment = $requirement->enrollment;
        if (! $enrollment || $enrollment->pkpa_program_id !== $plan->pkpa_program_id) {
            throw ValidationException::withMessages(['requirement' => 'Requirement tidak berasal dari program rancangan.']);
        }
        if (! in_array($enrollment->status, ['active', 'on_hold'], true)) {
            throw ValidationException::withMessages(['enrollment' => 'Enrollment tidak aktif untuk penempatan.']);
        }
        if ($requirement->pkpa_enrollment_id !== $enrollment->id) {
            throw ValidationException::withMessages(['requirement' => 'Requirement dan enrollment tidak konsisten.']);
        }
    }

    private function assertSiteFitsRequirement(PkpaPlacementPlan $plan, PkpaEnrollmentRequirement $requirement, PkpaProgramSite $programSite, PkpaSiteAvailabilityPeriod $availability): void
    {
        if ($programSite->pkpa_program_id !== $plan->pkpa_program_id || $programSite->practice_domain_id !== $requirement->practice_domain_id || $programSite->pkpa_program_domain_id !== $requirement->pkpa_program_domain_id) {
            throw ValidationException::withMessages(['pkpa_program_site_id' => 'Tempat tidak sesuai program atau wahana requirement.']);
        }
        if (! $programSite->is_active || ! in_array($programSite->status, ['ready', 'active'], true)) {
            throw ValidationException::withMessages(['pkpa_program_site_id' => 'Tempat program tidak aktif.']);
        }
        if (! $programSite->practiceSite?->is_active || $programSite->practiceSite?->status !== 'active') {
            throw ValidationException::withMessages(['pkpa_program_site_id' => 'Tempat praktik tidak aktif.']);
        }
        if ($programSite->practiceSite?->cooperation_end_date && $programSite->practiceSite->cooperation_end_date->lt(now()->startOfDay())) {
            throw ValidationException::withMessages(['pkpa_program_site_id' => 'Kerja sama tempat sudah berakhir.']);
        }
        if ($availability->pkpa_program_site_id !== $programSite->id || ! in_array($availability->status, ['available', 'full'], true)) {
            throw ValidationException::withMessages(['pkpa_site_availability_period_id' => 'Availability tidak aktif atau bukan milik tempat ini.']);
        }
        if ($requirement->selection_mode === 'choose_one' && ! $programSite->practice_domain_option_id) {
            throw ValidationException::withMessages(['selected_practice_domain_option_id' => 'Wahana Pemerintahan wajib memilih Dinas Kesehatan, Puskesmas, atau Loka BPOM melalui tempat.']);
        }
        if ($requirement->selection_mode === 'direct' && $programSite->practice_domain_option_id) {
            throw ValidationException::withMessages(['selected_practice_domain_option_id' => 'Wahana direct tidak boleh memakai option Pemerintahan.']);
        }
    }

    private function assertDateWindow(PkpaPlacementPlan $plan, PkpaSiteAvailabilityPeriod $availability, string $startDate, string $endDate): void
    {
        if ($startDate > $endDate) {
            throw ValidationException::withMessages(['end_date' => 'Tanggal selesai harus setelah tanggal mulai.']);
        }
        if ($startDate < $availability->start_date->toDateString() || $endDate > $availability->end_date->toDateString()) {
            throw ValidationException::withMessages(['start_date' => 'Tanggal harus berada di dalam availability.']);
        }
        if ($plan->program?->start_date && $startDate < $plan->program->start_date->toDateString()) {
            throw ValidationException::withMessages(['start_date' => 'Tanggal mulai di luar rentang program.']);
        }
        if ($plan->program?->end_date && $endDate > $plan->program->end_date->toDateString()) {
            throw ValidationException::withMessages(['end_date' => 'Tanggal selesai di luar rentang program.']);
        }
    }

    private function hasStudentOverlap(PkpaPlacementPlan $plan, PkpaEnrollment $enrollment, string $startDate, string $endDate, ?int $excludeAssignmentId): bool
    {
        return PkpaRotationAssignment::query()
            ->activeForCapacity()
            ->where('pkpa_placement_plan_id', $plan->id)
            ->where('pkpa_enrollment_id', $enrollment->id)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->when($excludeAssignmentId, fn ($query) => $query->whereKeyNot($excludeAssignmentId))
            ->exists();
    }

    private function syncSupervisors(PkpaRotationAssignment $assignment, array $data, ?User $actor): void
    {
        $assignment->supervisors()->where('status', 'active')->delete();

        $internal = PkpaInternalSupervisorEligibility::find($data['internal_supervisor_eligibility_id'] ?? null);
        if ($internal) {
            $internalUser = User::query()->with('lecturer')->where('core_user_id', $internal->core_user_id)->first();
            PkpaRotationAssignmentSupervisor::create([
                'pkpa_rotation_assignment_id' => $assignment->id,
                'supervisor_type' => 'internal',
                'internal_supervisor_eligibility_id' => $internal->id,
                'core_user_id' => $internal->core_user_id,
                'name_snapshot' => $internalUser ? user_display_name($internalUser, 'pembimbing_dalam') : $internal->name_snapshot,
                'role_snapshot' => $internal->role_snapshot,
                'effective_start_date' => $internal->effective_start_date,
                'effective_end_date' => $internal->effective_end_date,
                'status' => 'active',
                'is_primary' => true,
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
        }

        $field = PkpaSiteFieldSupervisor::find($data['site_field_supervisor_id'] ?? null);
        if ($field) {
            $fieldUser = User::query()->with(['lecturer', 'fieldSupervisor'])->where('core_user_id', $field->core_user_id)->first();
            PkpaRotationAssignmentSupervisor::create([
                'pkpa_rotation_assignment_id' => $assignment->id,
                'supervisor_type' => 'field',
                'site_field_supervisor_id' => $field->id,
                'core_user_id' => $field->core_user_id,
                'name_snapshot' => $fieldUser ? user_display_name($fieldUser, 'pembimbing_lapangan') : $field->name_snapshot,
                'role_snapshot' => $field->role_snapshot,
                'effective_start_date' => $field->effective_start_date,
                'effective_end_date' => $field->effective_end_date,
                'status' => 'active',
                'is_primary' => true,
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
        }
    }
}
