<?php

namespace App\Services;

use App\Models\PkpaProgramDomain;
use App\Models\PkpaSiteAvailabilityPeriod;
use Carbon\CarbonImmutable;

class PkpaPlacementDurationService
{
    public function calculate(PkpaProgramDomain $programDomain, ?PkpaSiteAvailabilityPeriod $availability, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();
        $effectiveDays = $this->effectiveDays($start, $end, $availability);
        $practiceHours = $this->practiceHours($effectiveDays, $availability);
        $warnings = [];

        if ($programDomain->duration_unit === 'practice_hours' && (! $availability?->daily_start_time || ! $availability?->daily_end_time)) {
            $warnings[] = 'Jam operasional availability belum lengkap, estimasi jam praktik tidak dapat dipastikan.';
        }

        return [
            'planned_duration_value' => $programDomain->duration_value,
            'planned_duration_unit' => $programDomain->duration_unit,
            'calendar_days' => $start->diffInDays($end) + 1,
            'effective_days' => $effectiveDays,
            'practice_hours' => $practiceHours,
            'meets_standard' => $this->meetsStandard($programDomain, $start, $end, $effectiveDays, $practiceHours),
            'warnings' => $warnings,
            'suggested_end_date' => $this->suggestEndDate($programDomain, $availability, $start)->toDateString(),
        ];
    }

    public function suggestEndDate(PkpaProgramDomain $programDomain, ?PkpaSiteAvailabilityPeriod $availability, CarbonImmutable|string $startDate): CarbonImmutable
    {
        $start = $startDate instanceof CarbonImmutable ? $startDate : CarbonImmutable::parse($startDate);
        $value = max(1, (int) ceil((float) $programDomain->duration_value));

        return match ($programDomain->duration_unit) {
            'weeks' => $start->addWeeks($value)->subDay(),
            'months' => $start->addMonthsNoOverflow($value)->subDay(),
            'working_days' => $this->addOperationalDays($start, $value, $availability),
            'practice_hours' => $this->addOperationalDays($start, max(1, (int) ceil($value / 8)), $availability),
            default => $start->addDays($value - 1),
        };
    }

    private function effectiveDays(CarbonImmutable $start, CarbonImmutable $end, ?PkpaSiteAvailabilityPeriod $availability): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        $operationalDays = $availability?->operational_days ?: PkpaSiteAvailabilityPeriod::OPERATIONAL_DAYS;
        $count = 0;
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            if (in_array(strtolower($day->englishDayOfWeek), $operationalDays, true)) {
                $count++;
            }
        }

        return $count;
    }

    private function practiceHours(int $effectiveDays, ?PkpaSiteAvailabilityPeriod $availability): ?int
    {
        if (! $availability?->daily_start_time || ! $availability?->daily_end_time) {
            return null;
        }

        $start = CarbonImmutable::parse($availability->daily_start_time);
        $end = CarbonImmutable::parse($availability->daily_end_time);

        return max(0, (int) floor($start->diffInMinutes($end) / 60) * $effectiveDays);
    }

    private function meetsStandard(PkpaProgramDomain $programDomain, CarbonImmutable $start, CarbonImmutable $end, int $effectiveDays, ?int $practiceHours): bool
    {
        $value = (float) $programDomain->duration_value;

        return match ($programDomain->duration_unit) {
            'working_days' => $effectiveDays >= $value,
            'weeks' => $start->diffInDays($end) + 1 >= ($value * 7),
            'months' => $end->gte($start->addMonthsNoOverflow((int) ceil($value))->subDay()),
            'practice_hours' => $practiceHours !== null && $practiceHours >= $value,
            default => $start->diffInDays($end) + 1 >= $value,
        };
    }

    private function addOperationalDays(CarbonImmutable $start, int $days, ?PkpaSiteAvailabilityPeriod $availability): CarbonImmutable
    {
        $operationalDays = $availability?->operational_days ?: PkpaSiteAvailabilityPeriod::OPERATIONAL_DAYS;
        $remaining = $days;
        $cursor = $start;

        while ($remaining > 1) {
            $cursor = $cursor->addDay();
            if (in_array(strtolower($cursor->englishDayOfWeek), $operationalDays, true)) {
                $remaining--;
            }
        }

        return $cursor;
    }
}
