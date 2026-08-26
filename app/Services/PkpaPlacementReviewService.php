<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementValidationIssue;
use App\Models\User;

class PkpaPlacementReviewService
{
    public function __construct(
        private readonly PkpaPlacementValidationService $validationService,
        private readonly PkpaAuditService $audit,
    ) {
    }

    public function review(PkpaPlacementPlan $plan, ?User $actor = null, bool $rerunValidation = true): array
    {
        $plan->loadMissing('program');
        $wasLocked = $plan->status === 'locked';
        if ($rerunValidation) {
            $this->validationService->validatePlan($plan, $actor);
            $plan->refresh();
            if ($wasLocked && $plan->validation_status !== 'invalid') {
                $plan->update(['status' => 'locked']);
                $plan->refresh();
            }
        }

        $activeEnrollments = PkpaEnrollment::where('pkpa_program_id', $plan->pkpa_program_id)->where('status', 'active')->withCount('requirements')->get();
        $requiredAssignments = (int) $activeEnrollments->sum('requirements_count');
        $filledAssignments = $plan->assignments()->whereHas('programDomain', fn ($query) => $query->where('is_active', true))->whereNotIn('status', ['cancelled', 'superseded'])->count();
        $errors = PkpaPlacementValidationIssue::whereHas('run', fn ($query) => $query->where('pkpa_placement_plan_id', $plan->id))
            ->where('severity', 'error')
            ->where('is_resolved', false)
            ->count();
        $warnings = PkpaPlacementValidationIssue::whereHas('run', fn ($query) => $query->where('pkpa_placement_plan_id', $plan->id))
            ->where('severity', 'warning')
            ->where('is_resolved', false)
            ->count();
        $assignmentWithGovernmentMissing = $plan->assignments()
            ->whereHas('programDomain', fn ($query) => $query->where('is_active', true))
            ->whereHas('requirement', fn ($query) => $query->where('selection_mode', 'choose_one'))
            ->whereNull('selected_practice_domain_option_id')
            ->count();

        $items = [
            'plan_validated' => ['label' => 'Plan tervalidasi', 'passed' => in_array($plan->validation_status, ['valid', 'warning'], true)],
            'plan_locked' => ['label' => 'Plan terkunci', 'passed' => $plan->status === 'locked'],
            'no_active_errors' => ['label' => 'Tidak ada error aktif', 'passed' => $errors === 0],
            'complete_assignments' => ['label' => 'Seluruh requirement memiliki assignment', 'passed' => $requiredAssignments > 0 && $filledAssignments === $requiredAssignments],
            'government_option' => ['label' => 'Pemerintahan memiliki option', 'passed' => $assignmentWithGovernmentMissing === 0],
            'supervisors_complete' => ['label' => 'Setiap assignment aktif memiliki PD dan PL', 'passed' => $plan->assignments()->whereHas('programDomain', fn ($query) => $query->where('is_active', true))->whereDoesntHave('supervisors', fn ($q) => $q->where('supervisor_type', 'internal'))->count() === 0
                && $plan->assignments()->whereHas('programDomain', fn ($query) => $query->where('is_active', true))->whereDoesntHave('supervisors', fn ($q) => $q->where('supervisor_type', 'field'))->count() === 0],
            'not_published_before' => ['label' => 'Plan belum pernah dipublikasikan current', 'passed' => $plan->publications()->where('status', 'published')->count() === 0],
        ];

        $ready = collect($items)->every(fn ($item) => $item['passed']);
        $summary = [
            'ready' => $ready,
            'participants' => $activeEnrollments->count(),
            'required_assignments' => $requiredAssignments,
            'filled_assignments' => $filledAssignments,
            'errors' => $errors,
            'warnings' => $warnings,
            'validated_at' => optional($plan->last_validated_at)->toDateTimeString(),
            'validated_by_core_user_id' => $plan->validated_by_core_user_id,
            'items' => $items,
        ];

        $this->audit->record($actor, 'placement_final_review_checked', $plan, null, $summary);

        return $summary;
    }
}
