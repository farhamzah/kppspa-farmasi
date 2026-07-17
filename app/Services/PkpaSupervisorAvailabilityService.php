<?php

namespace App\Services;

use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\PkpaSupervisorUnavailabilityPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaSupervisorAvailabilityService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function createForInternal(PkpaInternalSupervisorEligibility $eligibility, array $data, ?User $actor): PkpaSupervisorUnavailabilityPeriod
    {
        $this->validateDates($data);

        return DB::transaction(function () use ($eligibility, $data, $actor) {
            $period = $eligibility->unavailabilityPeriods()->create([
                'supervisor_type' => 'internal',
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'],
                'status' => $data['status'] ?? 'active',
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'supervisor_unavailability_created', $period, null, $period->only(['supervisor_type', 'start_date', 'end_date']));

            return $period;
        });
    }

    public function createForField(PkpaSiteFieldSupervisor $supervisor, array $data, ?User $actor): PkpaSupervisorUnavailabilityPeriod
    {
        $this->validateDates($data);

        return DB::transaction(function () use ($supervisor, $data, $actor) {
            $period = $supervisor->unavailabilityPeriods()->create([
                'supervisor_type' => 'field',
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'],
                'status' => $data['status'] ?? 'active',
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'supervisor_unavailability_created', $period, null, $period->only(['supervisor_type', 'start_date', 'end_date']));

            return $period;
        });
    }

    public function cancel(PkpaSupervisorUnavailabilityPeriod $period, ?User $actor): PkpaSupervisorUnavailabilityPeriod
    {
        $old = $period->only(['status']);
        $period->update(['status' => 'cancelled', 'updated_by_core_user_id' => $actor?->core_user_id]);
        $this->audit->record($actor, 'supervisor_unavailability_cancelled', $period, $old, ['status' => 'cancelled']);

        return $period->refresh();
    }

    private function validateDates(array $data): void
    {
        if (blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Alasan ketidaktersediaan wajib diisi.']);
        }

        if (Carbon::parse($data['start_date'])->gt(Carbon::parse($data['end_date']))) {
            throw ValidationException::withMessages(['end_date' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.']);
        }
    }
}
