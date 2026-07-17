<?php

namespace App\Services;

use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\PkpaSupervisorSyncLog;
use App\Models\User;

class PkpaSupervisorCoreSyncService
{
    public function __construct(
        private readonly PkpaFieldSupervisorService $fieldService,
        private readonly PkpaInternalSupervisorService $internalService,
    ) {
    }

    public function syncField(PkpaSiteFieldSupervisor $supervisor, ?User $actor): PkpaSiteFieldSupervisor
    {
        $synced = $this->fieldService->sync($supervisor, $actor);
        $this->log('field', $synced, $actor);

        return $synced;
    }

    public function syncInternal(PkpaInternalSupervisorEligibility $eligibility, ?User $actor): PkpaInternalSupervisorEligibility
    {
        $synced = $this->internalService->sync($eligibility, $actor);
        $this->log('internal', $synced, $actor);

        return $synced;
    }

    public function syncAllField(?User $actor): int
    {
        $count = 0;
        PkpaSiteFieldSupervisor::query()->each(function (PkpaSiteFieldSupervisor $supervisor) use ($actor, &$count) {
            $this->syncField($supervisor, $actor);
            $count++;
        });

        return $count;
    }

    public function syncAllInternal(?User $actor): int
    {
        $count = 0;
        PkpaInternalSupervisorEligibility::query()->each(function (PkpaInternalSupervisorEligibility $eligibility) use ($actor, &$count) {
            $this->syncInternal($eligibility, $actor);
            $count++;
        });

        return $count;
    }

    private function log(string $type, PkpaSiteFieldSupervisor|PkpaInternalSupervisorEligibility $target, ?User $actor): void
    {
        PkpaSupervisorSyncLog::create([
            'supervisor_type' => $type,
            'core_user_id' => $target->core_user_id,
            'target_type' => $target::class,
            'target_id' => $target->id,
            'status' => $target->last_core_sync_status ?: 'success',
            'message' => $target->last_core_sync_message,
            'synced_fields' => ['name', 'email', 'account_status', 'role'],
            'synced_by_core_user_id' => $actor?->core_user_id,
            'synced_at' => now(),
        ]);
    }
}
