<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementValidationIssue;
use App\Models\PkpaPlacementValidationRun;
use App\Models\PkpaRotationAssignment;
use App\Models\PkpaRotationAssignmentSupervisor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PkpaPlacementValidationService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function validatePlan(PkpaPlacementPlan $plan, ?User $actor): PkpaPlacementValidationRun
    {
        return DB::transaction(function () use ($plan, $actor) {
            $plan->update(['status' => 'validating', 'validation_status' => 'validating']);
            $run = PkpaPlacementValidationRun::create([
                'pkpa_placement_plan_id' => $plan->id,
                'scope_type' => 'full_plan',
                'status' => 'running',
                'started_at' => now(),
                'created_by_core_user_id' => $actor?->core_user_id,
            ]);

            $assignments = $plan->assignments()->with(['enrollment', 'requirement.practiceDomain', 'programDomain', 'programSite.practiceSite', 'availabilityPeriod', 'supervisors'])->get();
            $validAssignments = 0;
            foreach ($assignments as $assignment) {
                $issuesBefore = $run->issues()->count();
                $this->validateAssignmentRecord($run, $assignment);
                if ($run->issues()->count() === $issuesBefore) {
                    $validAssignments++;
                    $assignment->update(['status' => 'valid', 'validation_status' => 'valid', 'last_validated_at' => now()]);
                } else {
                    $hasError = $run->issues()->where('pkpa_rotation_assignment_id', $assignment->id)->where('severity', 'error')->exists();
                    $assignment->update(['status' => 'needs_attention', 'validation_status' => $hasError ? 'error' : 'warning', 'last_validated_at' => now()]);
                }
            }

            $this->validateCompleteness($run, $plan);
            $this->validateStudentOverlaps($run, $plan);
            $this->validateCapacity($run, $plan);
            $this->validateSupervisorLoad($run, $plan);
            $warningCount = $run->issues()->where('severity', 'warning')->count();
            $errorCount = $run->issues()->where('severity', 'error')->count();
            $required = PkpaEnrollment::where('pkpa_program_id', $plan->pkpa_program_id)->where('status', 'active')->withCount('requirements')->get()->sum('requirements_count');
            $filled = $assignments->whereNotIn('status', ['cancelled', 'superseded'])->count();

            $run->update([
                'status' => $errorCount > 0 ? 'completed_with_errors' : 'completed',
                'total_assignments' => $assignments->count(),
                'valid_assignments' => $validAssignments,
                'warning_count' => $warningCount,
                'error_count' => $errorCount,
                'completed_at' => now(),
            ]);
            $plan->update([
                'status' => $errorCount > 0 ? 'needs_revision' : 'validated',
                'validation_status' => $errorCount > 0 ? 'error' : ($warningCount > 0 ? 'warning' : 'valid'),
                'validation_summary' => [
                    'participants' => PkpaEnrollment::where('pkpa_program_id', $plan->pkpa_program_id)->where('status', 'active')->count(),
                    'required_assignments' => $required,
                    'filled_assignments' => $filled,
                    'valid_assignments' => $validAssignments,
                    'warnings' => $warningCount,
                    'errors' => $errorCount,
                ],
                'last_validated_at' => now(),
                'validated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'placement_plan_validated', $plan, null, $plan->validation_summary);

            return $run->fresh('issues');
        });
    }

    private function validateAssignmentRecord(PkpaPlacementValidationRun $run, PkpaRotationAssignment $assignment): void
    {
        if (! $assignment->programSite || ! $assignment->programSite->is_active) {
            $this->issue($run, $assignment, 'SITE_INACTIVE', 'error', 'site', 'Tempat program tidak aktif.', 'Pilih tempat aktif.');
        }
        if (! $assignment->availabilityPeriod || ! in_array($assignment->availabilityPeriod->status, ['available', 'full'], true)) {
            $this->issue($run, $assignment, 'AVAILABILITY_INACTIVE', 'error', 'site', 'Availability tidak aktif.', 'Pilih availability aktif.');
        }
        if (! $assignment->start_date || ! $assignment->end_date || $assignment->start_date->gt($assignment->end_date)) {
            $this->issue($run, $assignment, 'DATE_INVALID', 'error', 'schedule', 'Tanggal penempatan tidak valid.', 'Perbaiki tanggal mulai dan selesai.');
        }
        if ($assignment->availabilityPeriod && ($assignment->start_date->lt($assignment->availabilityPeriod->start_date) || $assignment->end_date->gt($assignment->availabilityPeriod->end_date))) {
            $this->issue($run, $assignment, 'DATE_OUTSIDE_AVAILABILITY', 'error', 'schedule', 'Tanggal berada di luar availability.', 'Sesuaikan dengan availability.');
        }
        if ($assignment->calculated_effective_days !== null && $assignment->programDomain?->minimum_effective_days && $assignment->calculated_effective_days < $assignment->programDomain->minimum_effective_days) {
            $this->issue($run, $assignment, 'DURATION_SHORT', 'error', 'duration', 'Hari efektif kurang dari konfigurasi wahana.', 'Perpanjang tanggal penempatan.');
        }
        if (! $assignment->supervisors->firstWhere('supervisor_type', 'internal')) {
            $this->issue($run, $assignment, 'INTERNAL_SUPERVISOR_MISSING', 'error', 'supervisor', 'Pembimbing Dalam belum dipilih.', 'Pilih Pembimbing Dalam eligible.');
        }
        if (! $assignment->supervisors->firstWhere('supervisor_type', 'field')) {
            $this->issue($run, $assignment, 'FIELD_SUPERVISOR_MISSING', 'error', 'supervisor', 'Pembimbing Lapangan belum dipilih.', 'Pilih Pembimbing Lapangan dari tempat.');
        }
        if ($assignment->requirement?->selection_mode === 'choose_one' && ! $assignment->selected_practice_domain_option_id) {
            $this->issue($run, $assignment, 'GOVERNMENT_OPTION_MISSING', 'error', 'government_option', 'Pilihan Pemerintahan belum terisi.', 'Pilih tempat Loka POM atau Dinas Kesehatan.');
        }
    }

    private function validateCompleteness(PkpaPlacementValidationRun $run, PkpaPlacementPlan $plan): void
    {
        PkpaEnrollment::query()
            ->where('pkpa_program_id', $plan->pkpa_program_id)
            ->where('status', 'active')
            ->with('requirements.practiceDomain')
            ->get()
            ->each(function (PkpaEnrollment $enrollment) use ($run, $plan) {
                foreach ($enrollment->requirements as $requirement) {
                    $exists = PkpaRotationAssignment::where('pkpa_placement_plan_id', $plan->id)
                        ->where('pkpa_enrollment_requirement_id', $requirement->id)
                        ->whereNotIn('status', ['cancelled', 'superseded'])
                        ->exists();
                    if (! $exists) {
                        PkpaPlacementValidationIssue::create([
                            'placement_validation_run_id' => $run->id,
                            'pkpa_enrollment_id' => $enrollment->id,
                            'pkpa_enrollment_requirement_id' => $requirement->id,
                            'issue_code' => 'ASSIGNMENT_MISSING',
                            'severity' => 'error',
                            'category' => 'completeness',
                            'message' => $enrollment->student_name_snapshot.' belum ditempatkan pada '.$requirement->practiceDomain?->name.'.',
                            'suggested_action' => 'Buka sel matriks dan isi penempatan.',
                        ]);
                    }
                }
            });
    }

    private function validateStudentOverlaps(PkpaPlacementValidationRun $run, PkpaPlacementPlan $plan): void
    {
        $assignments = $plan->assignments()->activeForCapacity()->with('practiceDomain')->get()->groupBy('pkpa_enrollment_id');
        foreach ($assignments as $studentAssignments) {
            $items = $studentAssignments->values();
            for ($i = 0; $i < $items->count(); $i++) {
                for ($j = $i + 1; $j < $items->count(); $j++) {
                    $a = $items[$i];
                    $b = $items[$j];
                    if ($a->start_date && $a->end_date && $b->start_date && $b->end_date && $a->start_date->lte($b->end_date) && $a->end_date->gte($b->start_date)) {
                        $this->issue($run, $a, 'STUDENT_SCHEDULE_OVERLAP', 'error', 'schedule', 'Jadwal '.$a->practiceDomain?->name.' bentrok dengan '.$b->practiceDomain?->name.'.', 'Atur ulang salah satu tanggal rotasi.');
                    }
                }
            }
        }
    }

    private function validateCapacity(PkpaPlacementValidationRun $run, PkpaPlacementPlan $plan): void
    {
        $assignments = $plan->assignments()->activeForCapacity()->with('availabilityPeriod')->whereNotNull('pkpa_site_availability_period_id')->get()->groupBy('pkpa_site_availability_period_id');
        foreach ($assignments as $items) {
            $availability = $items->first()->availabilityPeriod;
            if (! $availability) {
                continue;
            }
            $usable = max(0, $availability->maximum_students - $availability->reserved_slots);
            foreach ($items as $assignment) {
                $overlapCount = $items
                    ->filter(fn ($other) => $other->start_date && $other->end_date && $assignment->start_date && $assignment->end_date && $other->start_date->lte($assignment->end_date) && $other->end_date->gte($assignment->start_date))
                    ->count();
                if ($overlapCount > $usable) {
                    $this->issue($run, $assignment, 'CAPACITY_OVERBOOKED', 'error', 'capacity', 'Kapasitas availability terlampaui pada rentang tanggal ini.', 'Pindahkan sebagian mahasiswa atau tambah availability.');
                    break;
                }
            }
        }
    }

    private function validateSupervisorLoad(PkpaPlacementValidationRun $run, PkpaPlacementPlan $plan): void
    {
        $supervisors = PkpaRotationAssignmentSupervisor::query()
            ->where('status', 'active')
            ->whereHas('assignment', fn ($query) => $query->where('pkpa_placement_plan_id', $plan->id)->whereNotIn('status', ['cancelled', 'superseded']))
            ->with(['assignment', 'internalEligibility', 'fieldSupervisor'])
            ->get()
            ->groupBy(fn ($supervisor) => $supervisor->supervisor_type.':'.$supervisor->core_user_id);

        foreach ($supervisors as $items) {
            $first = $items->first();
            $limit = $first->supervisor_type === 'internal'
                ? min($first->internalEligibility?->maximum_active_students ?? PHP_INT_MAX, $first->internalEligibility?->maximum_students_per_program ?? PHP_INT_MAX)
                : ($first->fieldSupervisor?->maximum_active_students ?? PHP_INT_MAX);

            if ($limit === PHP_INT_MAX) {
                $this->issue($run, $first->assignment, 'SUPERVISOR_LIMIT_MISSING', 'warning', 'supervisor', 'Batas beban pembimbing belum dikonfigurasi.', 'Lengkapi batas beban pembimbing.');
                continue;
            }

            foreach ($items as $item) {
                $assignment = $item->assignment;
                $overlapCount = $items->filter(function ($other) use ($assignment) {
                    $otherAssignment = $other->assignment;

                    return $assignment?->start_date && $assignment?->end_date && $otherAssignment?->start_date && $otherAssignment?->end_date
                        && $otherAssignment->start_date->lte($assignment->end_date)
                        && $otherAssignment->end_date->gte($assignment->start_date);
                })->count();
                if ($overlapCount > $limit) {
                    $this->issue($run, $assignment, 'SUPERVISOR_OVERLOAD', 'error', 'supervisor', 'Beban pembimbing melampaui batas pada rentang tanggal ini.', 'Pilih pembimbing lain atau atur ulang tanggal.');
                    break;
                }
            }
        }
    }

    private function issue(PkpaPlacementValidationRun $run, PkpaRotationAssignment $assignment, string $code, string $severity, string $category, string $message, string $suggestedAction): void
    {
        PkpaPlacementValidationIssue::create([
            'placement_validation_run_id' => $run->id,
            'pkpa_rotation_assignment_id' => $assignment->id,
            'pkpa_enrollment_id' => $assignment->pkpa_enrollment_id,
            'pkpa_enrollment_requirement_id' => $assignment->pkpa_enrollment_requirement_id,
            'issue_code' => $code,
            'severity' => $severity,
            'category' => $category,
            'message' => $message,
            'suggested_action' => $suggestedAction,
        ]);
    }
}
