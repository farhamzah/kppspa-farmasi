<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaEnrollmentRequirement;
use App\Models\PkpaPlacementActionBatch;
use App\Models\PkpaPlacementActionBatchItem;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaRotationAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PkpaPlacementBulkActionService
{
    public function __construct(
        private readonly PkpaRotationAssignmentService $assignmentService,
        private readonly PkpaAuditService $audit,
    ) {
    }

    public function preview(PkpaPlacementPlan $plan, array $payload, ?User $actor): PkpaPlacementActionBatch
    {
        return DB::transaction(function () use ($plan, $payload, $actor) {
            $batch = PkpaPlacementActionBatch::create([
                'pkpa_placement_plan_id' => $plan->id,
                'action_type' => $payload['action_type'] ?? 'assign_complete',
                'status' => 'previewed',
                'description' => $payload['description'] ?? 'Preview penempatan massal',
                'request_summary' => $this->safePayload($payload),
                'created_by_core_user_id' => $actor?->core_user_id,
            ]);

            foreach ($this->targetRequirements($plan, $payload) as $requirement) {
                $messages = [];
                try {
                    $requirement->loadMissing('enrollment', 'programDomain', 'enrollment.activeGroupMembership');
                    $programSite = \App\Models\PkpaProgramSite::findOrFail($payload['pkpa_program_site_id']);
                    $availability = \App\Models\PkpaSiteAvailabilityPeriod::findOrFail($payload['pkpa_site_availability_period_id']);
                    $messages = $this->assignmentService->validateAssignment($plan, $requirement, $programSite, $availability, $payload);
                } catch (Throwable $exception) {
                    $messages['errors'][] = $exception instanceof ValidationException ? collect($exception->errors())->flatten()->join(' ') : 'Data preview tidak valid.';
                }

                PkpaPlacementActionBatchItem::create([
                    'placement_action_batch_id' => $batch->id,
                    'pkpa_enrollment_id' => $requirement->pkpa_enrollment_id,
                    'pkpa_enrollment_requirement_id' => $requirement->id,
                    'before_snapshot' => $this->assignmentSnapshot($plan, $requirement),
                    'after_snapshot' => $this->safePayload($payload),
                    'result_status' => empty($messages['errors']) ? 'valid' : 'invalid',
                    'validation_messages' => $messages,
                ]);
            }

            $batch->update(['affected_count' => $batch->items()->count()]);
            $this->audit->record($actor, 'placement_bulk_preview_created', $batch, null, ['affected_count' => $batch->affected_count]);

            return $batch->fresh('items');
        });
    }

    public function apply(PkpaPlacementActionBatch $batch, ?User $actor, bool $validOnly = false): PkpaPlacementActionBatch
    {
        return DB::transaction(function () use ($batch, $actor, $validOnly) {
            $batch = PkpaPlacementActionBatch::with('plan', 'items')->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'previewed') {
                throw ValidationException::withMessages(['batch' => 'Batch hanya dapat diterapkan dari status previewed.']);
            }
            if (! $batch->plan->isEditable()) {
                throw ValidationException::withMessages(['plan' => 'Rancangan terkunci.']);
            }
            if (! $validOnly && $batch->items->contains(fn ($item) => $item->result_status !== 'valid')) {
                throw ValidationException::withMessages(['batch' => 'Masih ada baris invalid. Pilih mode terapkan baris valid jika ingin melanjutkan sebagian.']);
            }

            $applied = 0;
            foreach ($batch->items as $item) {
                if ($item->result_status !== 'valid') {
                    continue;
                }
                try {
                    $payload = $item->after_snapshot;
                    $requirement = PkpaEnrollmentRequirement::findOrFail($item->pkpa_enrollment_requirement_id);
                    $assignment = $this->assignmentService->save($batch->plan, $requirement, $payload + ['planning_source' => $payload['planning_source'] ?? 'selection_bulk'], $actor);
                    $item->update([
                        'pkpa_rotation_assignment_id' => $assignment->id,
                        'after_snapshot' => $assignment->fresh('supervisors')->toArray(),
                        'result_status' => 'applied',
                    ]);
                    $applied++;
                } catch (Throwable $exception) {
                    $item->update([
                        'result_status' => 'failed',
                        'validation_messages' => ['errors' => [$exception instanceof ValidationException ? collect($exception->errors())->flatten()->join(' ') : 'Gagal menerapkan baris.']],
                    ]);
                    if (! $validOnly) {
                        throw $exception;
                    }
                }
            }

            $batch->update([
                'status' => $applied === $batch->items()->count() ? 'applied' : ($applied > 0 ? 'partially_applied' : 'failed'),
                'affected_count' => $applied,
            ]);
            $this->audit->record($actor, 'placement_bulk_action_applied', $batch, null, ['affected_count' => $applied, 'valid_only' => $validOnly]);

            return $batch->fresh('items');
        });
    }

    public function undo(PkpaPlacementActionBatch $batch, ?User $actor): PkpaPlacementActionBatch
    {
        return DB::transaction(function () use ($batch, $actor) {
            $batch = PkpaPlacementActionBatch::with('plan', 'items.assignment')->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if (! in_array($batch->status, ['applied', 'partially_applied'], true)) {
                throw ValidationException::withMessages(['batch' => 'Hanya batch applied yang dapat di-undo.']);
            }
            if (! $batch->plan->isEditable()) {
                throw ValidationException::withMessages(['plan' => 'Rancangan terkunci.']);
            }

            $reverted = 0;
            foreach ($batch->items as $item) {
                if (! $item->assignment || ! in_array($item->result_status, ['applied'], true)) {
                    continue;
                }
                $current = $item->assignment->fresh();
                $expectedVersion = $item->after_snapshot['row_version'] ?? null;
                if ($expectedVersion && $current->row_version !== $expectedVersion) {
                    $item->update(['result_status' => 'undo_blocked', 'validation_messages' => ['errors' => ['Assignment sudah berubah setelah batch diterapkan.']]]);
                    continue;
                }
                $this->assignmentService->deleteDraft($current, $actor);
                $item->update(['result_status' => 'reverted']);
                $reverted++;
            }

            $batch->update([
                'status' => 'reverted',
                'reverted_by_core_user_id' => $actor?->core_user_id,
                'reverted_at' => now(),
            ]);
            $this->audit->record($actor, 'placement_bulk_action_undone', $batch, null, ['reverted' => $reverted]);

            return $batch->fresh('items');
        });
    }

    private function targetRequirements(PkpaPlacementPlan $plan, array $payload)
    {
        $query = PkpaEnrollmentRequirement::query()
            ->where('practice_domain_id', $payload['practice_domain_id'])
            ->whereHas('enrollment', fn ($query) => $query
                ->where('pkpa_program_id', $plan->pkpa_program_id)
                ->whereIn('status', ['active', 'on_hold']));

        if (! empty($payload['enrollment_ids'])) {
            $query->whereIn('pkpa_enrollment_id', $payload['enrollment_ids']);
        }
        if (! empty($payload['student_group_id'])) {
            $query->whereHas('enrollment.activeGroupMembership', fn ($group) => $group->where('pkpa_student_group_id', $payload['student_group_id']));
        }
        if (($payload['overwrite_mode'] ?? 'empty_only') === 'empty_only') {
            $query->whereDoesntHave('rotationAssignments', fn ($assignment) => $assignment
                ->where('pkpa_placement_plan_id', $plan->id)
                ->whereNotIn('status', ['cancelled', 'superseded']));
        } elseif (($payload['overwrite_mode'] ?? 'empty_only') === 'overwrite_draft') {
            $query->whereDoesntHave('rotationAssignments', fn ($assignment) => $assignment
                ->where('pkpa_placement_plan_id', $plan->id)
                ->where('status', 'valid'));
        }

        return $query->with('enrollment')->get();
    }

    private function assignmentSnapshot(PkpaPlacementPlan $plan, PkpaEnrollmentRequirement $requirement): ?array
    {
        return PkpaRotationAssignment::with('supervisors')
            ->where('pkpa_placement_plan_id', $plan->id)
            ->where('pkpa_enrollment_requirement_id', $requirement->id)
            ->first()
            ?->toArray();
    }

    private function safePayload(array $payload): array
    {
        return collect($payload)->reject(fn ($value, string $key) => str_contains(strtolower($key), 'password')
            || str_contains(strtolower($key), 'token')
            || str_contains(strtolower($key), 'secret'))->all();
    }
}
