<?php

namespace App\Services;

use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaRotationPublicationSyncLog;
use App\Models\PkpaRotationRun;
use App\Models\PkpaRotationSupervisorHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaRotationPublicationSyncService
{
    public function __construct(private readonly PkpaAuditService $audit) {}

    public function sync(PkpaPlacementPublication $publication, ?User $actor, array $supervisorContexts = []): array
    {
        if (! $actor?->hasAnyRole(['admin', 'koordinator_kp'])) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat sinkronisasi publikasi.']);
        }
        if (! $publication->is_current || $publication->status !== 'published') {
            throw ValidationException::withMessages(['publication' => 'Sinkronisasi hanya dari publikasi resmi current.']);
        }

        $stats = ['applied' => 0, 'review_required' => 0, 'ignored' => 0];
        DB::transaction(function () use ($publication, $actor, $supervisorContexts, &$stats) {
            $publication->loadMissing('assignments.supervisors');
            $assignments = $publication->assignments->keyBy('pkpa_enrollment_requirement_id');
            PkpaRotationRun::where('pkpa_program_id', $publication->pkpa_program_id)->get()->each(function (PkpaRotationRun $run) use ($assignments, $publication, $actor, $supervisorContexts, &$stats) {
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
                    $this->apply($run, $publication, $new, $actor, $changeType, $supervisorContexts[$run->pkpa_enrollment_requirement_id] ?? []);
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

    private function apply(PkpaRotationRun $run, PkpaPlacementPublication $publication, PkpaPublishedAssignment $assignment, ?User $actor, string $changeType, array $supervisorContext = []): void
    {
        $before = $run->only(['current_published_assignment_id', 'practice_site_id', 'scheduled_start_date', 'scheduled_end_date']);
        $run->update([
            'current_placement_publication_id' => $publication->id,
            'current_published_assignment_id' => $assignment->id,
            'practice_site_id' => $assignment->practice_site_id,
            'scheduled_start_date' => $assignment->start_date?->toDateString(),
            'scheduled_end_date' => $assignment->end_date?->toDateString(),
            'publication_sync_status' => 'current',
            'updated_by_core_user_id' => $actor?->core_user_id,
            'row_version' => $run->row_version + 1,
        ]);
        if (in_array($changeType, ['supervisor', 'site_or_date'], true)) {
            $this->replaceSupervisors($run->refresh(), $assignment, $actor, $supervisorContext);
        }
        $this->log($run, $assignment, $changeType, 'applied', 'medium', 'Perubahan publikasi diterapkan ke runtime.', $actor, $before, $run->refresh()->only(array_keys($before)));
        $this->audit->record($actor, 'pkpa_rotation_publication_synced', $run, $before, ['change_type' => $changeType]);
    }

    private function replaceSupervisors(PkpaRotationRun $run, PkpaPublishedAssignment $assignment, ?User $actor, array $context = []): void
    {
        $currentByType = $run->supervisorHistories()->where('status', 'active')->get()->keyBy('supervisor_type');
        $nextByType = $assignment->supervisors->keyBy('supervisor_type');

        foreach ($currentByType->keys()->merge($nextByType->keys())->unique() as $type) {
            $current = $currentByType->get($type);
            $supervisor = $nextByType->get($type);
            if ($current && $supervisor && (string) $current->core_user_id === (string) $supervisor->core_user_id) {
                continue;
            }

            $effectiveStart = $type === 'internal' && filled($context['effective_date'] ?? null)
                ? Carbon::parse($context['effective_date'])
                : ($run->scheduled_start_date ?: now()->startOfDay());
            if ($current) {
                $current->update([
                    'status' => 'ended',
                    'active_key' => null,
                    'effective_end_date' => $effectiveStart->copy()->subDay()->toDateString(),
                    'change_reason' => $context['reason'] ?? 'Sinkronisasi publikasi current.',
                    'updated_by_core_user_id' => $actor?->core_user_id,
                ]);
            }
            if (! $supervisor) {
                continue;
            }

            PkpaRotationSupervisorHistory::create([
                'pkpa_rotation_run_id' => $run->id,
                'supervisor_type' => $supervisor->supervisor_type,
                'core_user_id' => $supervisor->core_user_id,
                'name_snapshot' => $supervisor->name_snapshot,
                'role_snapshot' => $supervisor->role_snapshot,
                'source_published_assignment_supervisor_id' => $supervisor->id,
                'effective_start_date' => $effectiveStart->toDateString(),
                'effective_end_date' => $run->scheduled_end_date?->toDateString(),
                'status' => 'active',
                'active_key' => 'RUN:'.$run->id.':'.$supervisor->supervisor_type,
                'change_reason' => $context['reason'] ?? 'Sinkronisasi publikasi current.',
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
        }

        $this->refreshDependentSupervisorSnapshots($run->refresh(['supervisorHistories']), $actor, $context);
    }

    private function refreshDependentSupervisorSnapshots(PkpaRotationRun $run, ?User $actor, array $context = []): void
    {
        $internal = $run->supervisorHistories->first(fn ($history) => $history->supervisor_type === 'internal' && $history->status === 'active');
        $field = $run->supervisorHistories->first(fn ($history) => $history->supervisor_type === 'field' && $history->status === 'active');
        if (! $internal) {
            return;
        }

        $portfolio = $run->currentPortfolio()->first();
        if ($portfolio && ! in_array($portfolio->status, ['locked', 'published', 'superseded', 'cancelled'], true)) {
            $snapshot = $portfolio->placement_snapshot ?? [];
            $snapshot['practice_site'] = $run->practiceSite?->name;
            $snapshot['address'] = $run->practiceSite?->address;
            $snapshot['internal_supervisor'] = $internal->display_name;
            $snapshot['internal_supervisor_core_user_id'] = $internal->core_user_id;
            $snapshot['field_supervisor'] = $field?->display_name;
            $snapshot['field_supervisor_core_user_id'] = $field?->core_user_id;
            $portfolio->update(['placement_snapshot' => $snapshot]);
        }

        $assessment = $run->rotationAssessment()->with('assessors.scores')->first();
        if (! $assessment || in_array($assessment->status, ['submitted', 'finalized', 'locked'], true)) {
            return;
        }
        foreach ($assessment->assessors->where('assessor_type', 'internal_supervisor') as $assessor) {
            if ((string) $assessor->core_user_id === (string) $internal->core_user_id || $assessor->submitted_at) {
                continue;
            }

            $scores = $assessor->scores->filter(fn ($score) => ! in_array($score->status, ['submitted', 'approved', 'locked'], true));
            $hasDraftContent = $scores->contains(fn ($score) => $score->status === 'draft'
                || filled($score->raw_score)
                || filled($score->comments)
                || filled($score->source_summary));
            if ($hasDraftContent && ! ($context['transfer_partial_assessment'] ?? false)) {
                continue;
            }

            $newAssessor = $assessment->assessors()->firstOrCreate([
                'pkpa_assessment_component_id' => $assessor->pkpa_assessment_component_id,
                'assessor_type' => 'internal_supervisor',
                'core_user_id' => $internal->core_user_id,
            ], [
                'pkpa_assessment_component_id' => $assessor->pkpa_assessment_component_id,
                'name_snapshot' => $internal->display_name,
                'role_snapshot' => $internal->role_snapshot,
                'source_rotation_supervisor_history_id' => $internal->id,
                'status' => $hasDraftContent ? 'in_progress' : 'assigned',
                'assigned_at' => now(),
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $assessor->update(['status' => 'replaced', 'updated_by_core_user_id' => $actor?->core_user_id]);

            foreach ($scores as $score) {
                $before = $score->only(['assessor_assignment_id', 'status', 'raw_score', 'source_summary']);
                $summary = $score->source_summary ?? [];
                if ($hasDraftContent) {
                    $history = $summary['supervisor_transfer_history'] ?? [];
                    $history[] = [
                        'from_core_user_id' => $assessor->core_user_id,
                        'from_name' => $assessor->name_snapshot,
                        'to_core_user_id' => $internal->core_user_id,
                        'to_name' => $internal->display_name,
                        'transferred_at' => now()->toIso8601String(),
                        'transferred_by_core_user_id' => $actor?->core_user_id,
                        'reason' => $context['reason'] ?? 'Penggantian Pembimbing Dalam.',
                    ];
                    $summary['supervisor_transfer_history'] = $history;
                    $summary['requires_replacement_supervisor_review'] = true;
                }
                $score->update([
                    'assessor_assignment_id' => $newAssessor->id,
                    'source_summary' => $summary ?: null,
                    'updated_by_core_user_id' => $actor?->core_user_id,
                ]);
                $this->audit->record($actor, 'pkpa_assessment_draft_transferred', $score, $before, [
                    'assessor_assignment_id' => $newAssessor->id,
                    'from_core_user_id' => $assessor->core_user_id,
                    'to_core_user_id' => $internal->core_user_id,
                ]);
            }
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
