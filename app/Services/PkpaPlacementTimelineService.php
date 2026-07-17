<?php

namespace App\Services;

use App\Models\PkpaPlacementPlan;

class PkpaPlacementTimelineService
{
    public function build(PkpaPlacementPlan $plan): array
    {
        $plan->loadMissing(['assignments.practiceDomain', 'assignments.selectedOption', 'assignments.practiceSite', 'assignments.enrollment']);

        return $plan->assignments
            ->whereNotIn('status', ['cancelled', 'superseded'])
            ->groupBy('pkpa_enrollment_id')
            ->map(function ($assignments) {
                $sorted = $assignments->sortBy('start_date')->values();
                $items = [];
                foreach ($sorted as $index => $assignment) {
                    $previous = $index > 0 ? $sorted[$index - 1] : null;
                    $items[] = [
                        'assignment' => $assignment,
                        'label' => trim(($assignment->selectedOption?->name ?: $assignment->practiceDomain?->name).' - '.($assignment->practiceSite?->name ?: 'Belum ada tempat')),
                        'has_overlap' => $previous && $assignment->start_date && $previous->end_date && $assignment->start_date->lte($previous->end_date),
                        'gap_days' => $previous && $assignment->start_date && $previous->end_date ? max(0, $previous->end_date->diffInDays($assignment->start_date) - 1) : null,
                    ];
                }

                return [
                    'enrollment' => $sorted->first()->enrollment,
                    'items' => $items,
                ];
            })
            ->values()
            ->all();
    }
}
