<?php

namespace App\Services;

use App\Models\PkpaPlacementPlan;
use App\Models\PkpaRotationAssignment;
use App\Models\PkpaSiteAvailabilityPeriod;

class PkpaPlacementCapacityService
{
    public function usage(PkpaPlacementPlan $plan, PkpaSiteAvailabilityPeriod $availability, ?string $startDate = null, ?string $endDate = null, ?int $excludeAssignmentId = null): array
    {
        $query = PkpaRotationAssignment::query()
            ->activeForCapacity()
            ->where('pkpa_placement_plan_id', $plan->id)
            ->where('pkpa_site_availability_period_id', $availability->id);

        if ($startDate && $endDate) {
            $query->whereDate('start_date', '<=', $endDate)->whereDate('end_date', '>=', $startDate);
        }

        if ($excludeAssignmentId) {
            $query->whereKeyNot($excludeAssignmentId);
        }

        $used = $query->count();
        $usable = max(0, (int) $availability->maximum_students - (int) $availability->reserved_slots);

        return [
            'maximum' => (int) $availability->maximum_students,
            'reserved' => (int) $availability->reserved_slots,
            'used' => $used,
            'usable' => $usable,
            'available' => max(0, $usable - $used),
            'after_action' => max(0, $usable - $used - 1),
            'is_full' => $used >= $usable,
        ];
    }
}
