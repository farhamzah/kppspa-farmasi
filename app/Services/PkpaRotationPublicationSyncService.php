<?php

namespace App\Services;

use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaRotationPublicationSyncLog;
use App\Models\PkpaRotationRun;
use App\Models\PkpaRotationSupervisorHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaRotationPublicationSyncService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function sync(PkpaPlacementPublication $publication, ?User $actor): array
    {
        if (! $actor?->hasAnyRole(['admin', 'koordinator_kp'])) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat sinkronisasi publikasi.']);
        }
        if (! $publication->is_current || $publication->status !== 'published') {
            throw ValidationException::withMessages(['publication' => 'Sinkronisasi hanya dari publikasi resmi current.']);
        }

        $stats = ['applied' => 0, 'review_required' => 0, 'ignored' => 0];
        DB::transaction(function () use ($publication, $actor, &$stats) {
            $publication->loadMissing('assignments.supervisors');
            $assignments = $publication->assignments->keyBy('pkpa_enrollment_requirement_id');
            PkpaRotationRun::where('pkpa_program_id', $publication->pkpa_program_id)->get()->each(function (PkpaRotationRun $run) use ($assignments, $publication, $actor, &$stats) {
                $new = $assignments->get($run->pkpa_enrollment_requirement_id);
                if (! $new) {
                    $this->log($run, null, 'withdrawn', 'review_required', 'high', 'Assignment tidak lagi ada pada publikasi current.', $actor);
                    $run->update(['publication_sync_status' => 'review_required']);
                    $stats['review_required']++;
                    return;
                }

                $changeType = $this->changeType($run, $new);
                if ($changeType === 'none') {
                    $this->log($run, $new, 'none', 'ignored', 'low', 'Tidak ada perubahan operasional.', $actor);
                    $stats['ignored']++;
                    return;
                }

                if (in_array($run->status, ['scheduled', 'ready'], true) || $changeType === 'supervisor') {
                    $this->apply($run, $publication, $new, $actor, $changeType);
                    $stats['applied']++;
                    return;
                }

                $this->log($run, $new, $changeType, 'review_required', 'high', 'Rotasi sudah berjalan, perubahan perlu review Koordinator.', $actor);
                $run->update(['publication_sync_status' => 'review_required']);
                $stats['review_required']++;
            });
        });

        return $stats;
    }

    private function apply(PkpaRotationRun $run, PkpaPlacementPublication $publication, PkpaPublishedAssignment $assignment, ?User $actor, string $changeType): void
    {
        $before = $run->only(['current_published_assignment_id', 'practice_site_id', 'scheduled_start_date', 'scheduled_end_date']);
        $run->update([
            'current_placement_publication_id' => $publication->id,
            'current_published_assignment_id' => $assignment->id,
            'practice_site_id' => $assignment->practice_site_id,
            'scheduled_start_date' => $assignment->start_date,
            'scheduled_end_date' => $assignment->end_date,
            'publication_sync_status' => 'current',
            'updated_by_core_user_id' => $actor?->core_user_id,
            'row_version' => $run->row_version + 1,
        ]);
        if (in_array($changeType, ['supervisor', 'site_or_date'], true)) {
            $this->replaceSupervisors($run->refresh(), $assignment, $actor);
        }
        $this->log($run, $assignment, $changeType, 'applied', 'medium', 'Perubahan publikasi diterapkan ke runtime.', $actor, $before, $run->refresh()->only(array_keys($before)));
        $this->audit->record($actor, 'pkpa_rotation_publication_synced', $run, $before, ['change_type' => $changeType]);
    }

    private function replaceSupervisors(PkpaRotationRun $run, PkpaPublishedAssignment $assignment, ?User $actor): void
    {
        $run->supervisorHistories()->where('status', 'active')->update(['status' => 'ended', 'active_key' => null, 'updated_by_core_user_id' => $actor?->core_user_id]);
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
                'change_reason' => 'Sinkronisasi publikasi current.',
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
        }
    }

    private function changeType(PkpaRotationRun $run, PkpaPublishedAssignment $assignment): string
    {
        if ((int) $run->practice_site_id !== (int) $assignment->practice_site_id
            || $run->scheduled_start_date->toDateString() !== $assignment->start_date->toDateString()
            || $run->scheduled_end_date->toDateString() !== $assignment->end_date->toDateString()) {
            return 'site_or_date';
        }

        $current = $run->supervisorHistories()->where('status', 'active')->orderBy('supervisor_type')->pluck('core_user_id', 'supervisor_type')->all();
        $next = $assignment->supervisors()->orderBy('supervisor_type')->pluck('core_user_id', 'supervisor_type')->all();

        return $current === $next ? 'none' : 'supervisor';
    }

    private function log(PkpaRotationRun $run, ?PkpaPublishedAssignment $assignment, string $changeType, string $status, string $impact, string $message, ?User $actor, ?array $before = null, ?array $after = null): void
    {
        PkpaRotationPublicationSyncLog::create([
            'pkpa_rotation_run_id' => $run->id,
            'old_published_assignment_id' => $run->current_published_assignment_id,
            'new_published_assignment_id' => $assignment?->id,
            'change_type' => $changeType,
            'status' => $status,
            'impact_level' => $impact,
            'message' => $message,
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'processed_by_core_user_id' => $actor?->core_user_id,
            'processed_at' => now(),
        ]);
    }
}
