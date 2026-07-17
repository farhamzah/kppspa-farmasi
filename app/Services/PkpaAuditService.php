<?php

namespace App\Services;

use App\Models\PkpaMasterAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PkpaAuditService
{
    public function record(?User $actor, string $action, Model|string $entity, ?array $oldValues = null, ?array $newValues = null): void
    {
        $entityType = $entity instanceof Model ? $entity::class : $entity;
        $entityId = $entity instanceof Model ? $entity->getKey() : null;

        PkpaMasterAudit::create([
            'actor_core_user_id' => $actor?->core_user_id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ? $this->safe($oldValues) : null,
            'new_values' => $newValues ? $this->safe($newValues) : null,
        ]);
    }

    private function safe(array $values): array
    {
        return collect($values)
            ->reject(fn ($value, string $key) => str_contains(strtolower($key), 'password')
                || str_contains(strtolower($key), 'token')
                || str_contains(strtolower($key), 'secret'))
            ->all();
    }
}
