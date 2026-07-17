<?php

namespace App\Services;

use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaRotationAssignment;
use App\Models\PkpaSiteFieldSupervisor;

class PkpaPlacementSupervisorService
{
    public function validateInternal(PkpaPlacementPlan $plan, PkpaInternalSupervisorEligibility $supervisor, int $practiceDomainId, string $startDate, string $endDate, ?int $excludeAssignmentId = null): array
    {
        $errors = [];
        $warnings = [];

        if ($supervisor->pkpa_program_id !== $plan->pkpa_program_id || $supervisor->practice_domain_id !== $practiceDomainId) {
            $errors[] = 'Pembimbing Dalam tidak eligible untuk program atau wahana ini.';
        }
        if ($supervisor->status !== 'active' || $supervisor->core_account_status_snapshot === 'inactive') {
            $errors[] = 'Pembimbing Dalam tidak aktif di Core atau MY PSPA.';
        }
        if ($this->outsideEffectiveWindow($supervisor->effective_start_date?->toDateString(), $supervisor->effective_end_date?->toDateString(), $startDate, $endDate)) {
            $errors[] = 'Tanggal penempatan di luar masa efektif Pembimbing Dalam.';
        }
        if ($this->hasInternalUnavailability($supervisor, $startDate, $endDate)) {
            $errors[] = 'Pembimbing Dalam memiliki unavailability pada rentang tanggal ini.';
        }
        if (is_null($supervisor->maximum_active_students) || is_null($supervisor->maximum_students_per_program)) {
            $warnings[] = 'Batas beban Pembimbing Dalam belum lengkap.';
        } elseif ($this->internalLoad($plan, $supervisor, $startDate, $endDate, $excludeAssignmentId) >= min($supervisor->maximum_active_students, $supervisor->maximum_students_per_program)) {
            $errors[] = 'Beban Pembimbing Dalam melampaui batas.';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function validateField(PkpaPlacementPlan $plan, PkpaSiteFieldSupervisor $supervisor, int $practiceSiteId, string $startDate, string $endDate, ?int $excludeAssignmentId = null): array
    {
        $errors = [];
        $warnings = [];

        if ($supervisor->practice_site_id !== $practiceSiteId) {
            $errors[] = 'Pembimbing Lapangan bukan milik tempat praktik yang dipilih.';
        }
        if ($supervisor->status !== 'active' || $supervisor->core_account_status_snapshot === 'inactive') {
            $errors[] = 'Pembimbing Lapangan tidak aktif di Core atau MY PSPA.';
        }
        if ($this->outsideEffectiveWindow($supervisor->effective_start_date?->toDateString(), $supervisor->effective_end_date?->toDateString(), $startDate, $endDate)) {
            $errors[] = 'Tanggal penempatan di luar masa efektif Pembimbing Lapangan.';
        }
        if ($this->hasFieldUnavailability($supervisor, $startDate, $endDate)) {
            $errors[] = 'Pembimbing Lapangan memiliki unavailability pada rentang tanggal ini.';
        }
        if (is_null($supervisor->maximum_active_students)) {
            $warnings[] = 'Batas beban Pembimbing Lapangan belum dikonfigurasi.';
        } elseif ($this->fieldLoad($plan, $supervisor, $startDate, $endDate, $excludeAssignmentId) >= $supervisor->maximum_active_students) {
            $errors[] = 'Beban Pembimbing Lapangan melampaui batas.';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function internalLoad(PkpaPlacementPlan $plan, PkpaInternalSupervisorEligibility $supervisor, string $startDate, string $endDate, ?int $excludeAssignmentId = null): int
    {
        return PkpaRotationAssignment::query()
            ->activeForCapacity()
            ->where('pkpa_placement_plan_id', $plan->id)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->when($excludeAssignmentId, fn ($query) => $query->whereKeyNot($excludeAssignmentId))
            ->whereHas('supervisors', fn ($query) => $query
                ->where('supervisor_type', 'internal')
                ->where('status', 'active')
                ->where('internal_supervisor_eligibility_id', $supervisor->id))
            ->count();
    }

    public function fieldLoad(PkpaPlacementPlan $plan, PkpaSiteFieldSupervisor $supervisor, string $startDate, string $endDate, ?int $excludeAssignmentId = null): int
    {
        return PkpaRotationAssignment::query()
            ->activeForCapacity()
            ->where('pkpa_placement_plan_id', $plan->id)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->when($excludeAssignmentId, fn ($query) => $query->whereKeyNot($excludeAssignmentId))
            ->whereHas('supervisors', fn ($query) => $query
                ->where('supervisor_type', 'field')
                ->where('status', 'active')
                ->where('site_field_supervisor_id', $supervisor->id))
            ->count();
    }

    private function outsideEffectiveWindow(?string $effectiveStart, ?string $effectiveEnd, string $startDate, string $endDate): bool
    {
        return ($effectiveStart && $startDate < $effectiveStart) || ($effectiveEnd && $endDate > $effectiveEnd);
    }

    private function hasInternalUnavailability(PkpaInternalSupervisorEligibility $supervisor, string $startDate, string $endDate): bool
    {
        return $supervisor->unavailabilityPeriods()
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();
    }

    private function hasFieldUnavailability(PkpaSiteFieldSupervisor $supervisor, string $startDate, string $endDate): bool
    {
        return $supervisor->unavailabilityPeriods()
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();
    }
}
