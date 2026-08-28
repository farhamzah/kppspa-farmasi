<?php

namespace App\Services;

use App\Models\PkpaPracticeSite;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaFieldSupervisorService
{
    public function __construct(
        private readonly PkpaSupervisorCoreResolver $resolver,
        private readonly PkpaAuditService $audit,
    ) {
    }

    public function create(PkpaPracticeSite $site, array $data, ?User $actor): PkpaSiteFieldSupervisor
    {
        $resolved = $this->resolver->resolveField($data);
        if (! ($resolved['ok'] ?? false)) {
            throw ValidationException::withMessages(['core_user_id' => $resolved['message'] ?? 'Preseptor tidak valid.']);
        }
        $this->validatePayload($data);
        $person = $resolved['person'];

        if (PkpaSiteFieldSupervisor::withTrashed()->where('practice_site_id', $site->id)->where('core_user_id', $person['core_user_id'])->exists()) {
            throw ValidationException::withMessages(['core_user_id' => 'Preseptor sudah terhubung ke tempat ini.']);
        }

        return DB::transaction(function () use ($site, $data, $actor, $person) {
            $supervisor = $site->fieldSupervisors()->create($this->payload($person, $data, $actor));
            $this->audit->record($actor, 'field_supervisor_created', $supervisor, null, $supervisor->only(['practice_site_id', 'core_user_id', 'status']));

            return $supervisor;
        });
    }

    public function sync(PkpaSiteFieldSupervisor $supervisor, ?User $actor): PkpaSiteFieldSupervisor
    {
        $resolved = $this->resolver->resolveField(['core_user_id' => $supervisor->core_user_id]);
        if (! ($resolved['ok'] ?? false) && ! isset($resolved['person'])) {
            $supervisor->update(['last_core_sync_status' => 'failed', 'last_core_sync_message' => $resolved['message'] ?? 'Core tidak tersedia.']);
            $this->audit->record($actor, 'field_supervisor_sync_failed', $supervisor, null, ['core_user_id' => $supervisor->core_user_id]);
            return $supervisor->refresh();
        }

        $person = $resolved['person'];
        $old = $supervisor->only(['name_snapshot', 'email_snapshot', 'professional_id_snapshot', 'core_account_status_snapshot', 'role_snapshot']);
        $supervisor->update([
            'name_snapshot' => $person['name'],
            'email_snapshot' => $person['email'],
            'professional_id_snapshot' => $person['professional_id'],
            'core_account_status_snapshot' => $person['account_status'],
            'role_snapshot' => $person['role_snapshot'],
            'last_core_synced_at' => now(),
            'last_core_sync_status' => ($resolved['ok'] ?? false) ? 'success' : 'partial',
            'last_core_sync_message' => ($resolved['ok'] ?? false) ? null : ($resolved['message'] ?? 'Perlu ditinjau.'),
            'updated_by_core_user_id' => $actor?->core_user_id,
        ]);
        $this->audit->record($actor, 'field_supervisor_synced', $supervisor, $old, $supervisor->only(array_keys($old)));

        return $supervisor->refresh();
    }

    private function validatePayload(array $data): void
    {
        if (filled($data['effective_start_date'] ?? null) && filled($data['effective_end_date'] ?? null) && $data['effective_end_date'] < $data['effective_start_date']) {
            throw ValidationException::withMessages(['effective_end_date' => 'Masa efektif selesai harus setelah mulai.']);
        }
        if (($data['maximum_active_students'] ?? 0) < 0) {
            throw ValidationException::withMessages(['maximum_active_students' => 'Batas mahasiswa tidak boleh negatif.']);
        }
    }

    private function payload(array $person, array $data, ?User $actor): array
    {
        return [
            'core_user_id' => $person['core_user_id'],
            'name_snapshot' => $person['name'],
            'email_snapshot' => $person['email'],
            'professional_id_snapshot' => $person['professional_id'],
            'core_account_status_snapshot' => $person['account_status'],
            'role_snapshot' => $person['role_snapshot'],
            'position_title' => $data['position_title'] ?? null,
            'is_primary_contact' => (bool) ($data['is_primary_contact'] ?? false),
            'maximum_active_students' => $data['maximum_active_students'] ?? null,
            'effective_start_date' => $data['effective_start_date'] ?? null,
            'effective_end_date' => $data['effective_end_date'] ?? null,
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'last_core_synced_at' => now(),
            'last_core_sync_status' => 'success',
            'created_by_core_user_id' => $actor?->core_user_id,
            'updated_by_core_user_id' => $actor?->core_user_id,
        ];
    }
}
