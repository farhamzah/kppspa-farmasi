<?php

namespace App\Services;

use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaRotationOperationRule;
use App\Models\PkpaRotationRun;
use App\Models\PkpaRotationStatusHistory;
use App\Models\PkpaRotationSupervisorHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaRotationRunService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function createFromPublication(PkpaPlacementPublication $publication, ?User $actor): array
    {
        if (! config('my_pspa.rotation_operations_enabled')) {
            throw ValidationException::withMessages(['operation' => 'Operasional rotasi sedang dinonaktifkan.']);
        }
        if (! $actor?->hasAnyRole(['admin', 'koordinator_kp'])) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat membentuk runtime rotasi.']);
        }
        if (! $publication->is_current || $publication->status !== 'published') {
            throw ValidationException::withMessages(['publication' => 'Runtime hanya dapat dibuat dari publikasi resmi yang current.']);
        }

        $created = 0;
        $existing = 0;

        DB::transaction(function () use ($publication, $actor, &$created, &$existing) {
            $publication->loadMissing('assignments.supervisors');
            foreach ($publication->assignments as $assignment) {
                if ($assignment->student_core_user_id === '' || $assignment->enrollment?->status === 'cancelled') {
                    continue;
                }
                $run = PkpaRotationRun::where('origin_published_assignment_id', $assignment->id)->lockForUpdate()->first();
                if ($run) {
                    $existing++;
                    continue;
                }

                $run = $this->createRun($publication, $assignment, $actor);
                $this->createSupervisorHistories($run, $assignment, $actor, 'Pembentukan runtime awal dari publikasi resmi.');
                $created++;
            }
        });

        return compact('created', 'existing');
    }

    public function activate(PkpaRotationRun $run, ?User $actor): PkpaRotationRun
    {
        if (! $actor?->hasRole('koordinator_kp')) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Koordinator PKPA yang dapat mengaktifkan rotasi.']);
        }
        if (! in_array($run->status, ['scheduled', 'ready'], true)) {
            throw ValidationException::withMessages(['status' => 'Rotasi tidak siap diaktifkan.']);
        }
        $run->loadMissing('requirement.programDomain');
        $programDomainId = $run->requirement?->pkpa_program_domain_id;
        if (! $programDomainId || ! PkpaRotationOperationRule::where('pkpa_program_domain_id', $programDomainId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['operation_rule' => 'Aturan operasional wahana harus aktif sebelum rotasi diaktifkan.']);
        }

        return $this->transition($run, 'active', $actor, 'Aktivasi rotasi operasional.', [
            'operational_status' => 'on_track',
            'actual_start_date' => now()->toDateString(),
            'started_at' => now(),
            'activated_by_core_user_id' => $actor?->core_user_id,
        ]);
    }

    public function hold(PkpaRotationRun $run, string $reason, ?User $actor): PkpaRotationRun
    {
        if (! $actor?->hasRole('koordinator_kp') || blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'On hold wajib dilakukan Koordinator dengan alasan.']);
        }

        return $this->transition($run, 'on_hold', $actor, $reason, ['operational_status' => 'blocked', 'paused_at' => now()]);
    }

    public function resume(PkpaRotationRun $run, string $reason, ?User $actor): PkpaRotationRun
    {
        if (! $actor?->hasRole('koordinator_kp') || blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Resume wajib dilakukan Koordinator dengan alasan.']);
        }

        return $this->transition($run, 'active', $actor, $reason, ['operational_status' => 'on_track', 'resumed_at' => now()]);
    }

    public function markAwaitingReview(PkpaRotationRun $run, ?User $actor): PkpaRotationRun
    {
        return $this->transition($run, 'awaiting_operational_review', $actor, 'Checklist operasional siap ditinjau.', ['operational_status' => 'ready_for_review']);
    }

    public function operationalComplete(PkpaRotationRun $run, string $reason, ?User $actor): PkpaRotationRun
    {
        if (! $actor?->hasRole('koordinator_kp') || blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Operational complete wajib dilakukan Koordinator dengan alasan.']);
        }
        if ($run->status !== 'awaiting_operational_review') {
            throw ValidationException::withMessages(['status' => 'Rotasi belum siap untuk operational complete.']);
        }

        return $this->transition($run, 'operational_complete', $actor, $reason, [
            'operational_status' => 'ready_for_review',
            'operational_completed_at' => now(),
            'operational_completed_by_core_user_id' => $actor?->core_user_id,
        ]);
    }

    public function transition(PkpaRotationRun $run, string $toStatus, ?User $actor, ?string $reason = null, array $extra = []): PkpaRotationRun
    {
        return DB::transaction(function () use ($run, $toStatus, $actor, $reason, $extra) {
            $run = PkpaRotationRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            $from = $run->status;
            $run->update(array_merge($extra, [
                'status' => $toStatus,
                'updated_by_core_user_id' => $actor?->core_user_id,
                'row_version' => $run->row_version + 1,
            ]));
            PkpaRotationStatusHistory::create([
                'pkpa_rotation_run_id' => $run->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'reason' => $reason,
                'metadata' => $extra,
                'changed_by_core_user_id' => $actor?->core_user_id,
                'changed_at' => now(),
            ]);
            $this->audit->record($actor, 'rotation_run_status_changed', $run, ['status' => $from], ['status' => $toStatus, 'reason' => $reason]);

            return $run->refresh();
        });
    }

    private function createRun(PkpaPlacementPublication $publication, PkpaPublishedAssignment $assignment, ?User $actor): PkpaRotationRun
    {
        $run = PkpaRotationRun::create([
            'pkpa_program_id' => $publication->pkpa_program_id,
            'pkpa_enrollment_id' => $assignment->pkpa_enrollment_id,
            'pkpa_enrollment_requirement_id' => $assignment->pkpa_enrollment_requirement_id,
            'current_placement_publication_id' => $publication->id,
            'origin_published_assignment_id' => $assignment->id,
            'current_published_assignment_id' => $assignment->id,
            'practice_domain_id' => $assignment->practice_domain_id,
            'practice_domain_option_id' => $assignment->practice_domain_option_id,
            'practice_site_id' => $assignment->practice_site_id,
            'student_core_user_id' => $assignment->student_core_user_id,
            'scheduled_start_date' => $assignment->start_date,
            'scheduled_end_date' => $assignment->end_date,
            'status' => 'ready',
            'operational_status' => 'not_started',
            'publication_sync_status' => 'current',
            'current_key' => 'REQUIREMENT:'.$assignment->pkpa_enrollment_requirement_id,
            'created_by_core_user_id' => $actor?->core_user_id,
            'updated_by_core_user_id' => $actor?->core_user_id,
        ]);
        PkpaRotationStatusHistory::create([
            'pkpa_rotation_run_id' => $run->id,
            'from_status' => null,
            'to_status' => 'ready',
            'reason' => 'Runtime dibuat dari publikasi resmi.',
            'changed_by_core_user_id' => $actor?->core_user_id,
            'changed_at' => now(),
        ]);
        $this->audit->record($actor, 'rotation_run_created', $run, null, $run->only(['current_published_assignment_id', 'student_core_user_id']));

        return $run;
    }

    private function createSupervisorHistories(PkpaRotationRun $run, PkpaPublishedAssignment $assignment, ?User $actor, string $reason): void
    {
        foreach ($assignment->supervisors as $supervisor) {
            PkpaRotationSupervisorHistory::create([
                'pkpa_rotation_run_id' => $run->id,
                'supervisor_type' => $supervisor->supervisor_type,
                'core_user_id' => $supervisor->core_user_id,
                'name_snapshot' => $supervisor->name_snapshot,
                'role_snapshot' => $supervisor->role_snapshot,
                'source_published_assignment_supervisor_id' => $supervisor->id,
                'effective_start_date' => $run->scheduled_start_date,
                'effective_end_date' => $run->scheduled_end_date,
                'status' => 'active',
                'active_key' => 'RUN:'.$run->id.':'.$supervisor->supervisor_type,
                'change_reason' => $reason,
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
        }
    }
}
