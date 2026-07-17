<?php

namespace App\Services;

use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaProgram;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaInternalSupervisorService
{
    public function __construct(
        private readonly PkpaSupervisorCoreResolver $resolver,
        private readonly PkpaAuditService $audit,
    ) {
    }

    public function create(PkpaProgram $program, PkpaPracticeDomain $domain, array $data, ?User $actor): PkpaInternalSupervisorEligibility
    {
        if (! $program->domains()->where('practice_domain_id', $domain->id)->exists()) {
            throw ValidationException::withMessages(['practice_domain_id' => 'Wahana tidak tersedia pada program.']);
        }
        $this->validatePayload($data);
        $resolved = $this->resolver->resolveInternal($data);
        if (! ($resolved['ok'] ?? false)) {
            throw ValidationException::withMessages(['core_user_id' => $resolved['message'] ?? 'Pembimbing Dalam tidak valid.']);
        }
        $person = $resolved['person'];
        if (PkpaInternalSupervisorEligibility::withTrashed()->where('pkpa_program_id', $program->id)->where('practice_domain_id', $domain->id)->where('core_user_id', $person['core_user_id'])->exists()) {
            throw ValidationException::withMessages(['core_user_id' => 'Eligibility pembimbing sudah ada untuk program dan wahana ini.']);
        }

        return DB::transaction(function () use ($program, $domain, $data, $actor, $person) {
            $eligibility = PkpaInternalSupervisorEligibility::create($this->payload($person, $data, $actor) + [
                'pkpa_program_id' => $program->id,
                'practice_domain_id' => $domain->id,
            ]);
            $this->audit->record($actor, 'internal_supervisor_eligibility_created', $eligibility, null, $eligibility->only(['pkpa_program_id', 'practice_domain_id', 'core_user_id']));

            return $eligibility;
        });
    }

    public function sync(PkpaInternalSupervisorEligibility $eligibility, ?User $actor): PkpaInternalSupervisorEligibility
    {
        $resolved = $this->resolver->resolveInternal(['core_user_id' => $eligibility->core_user_id]);
        if (! ($resolved['ok'] ?? false) && ! isset($resolved['person'])) {
            $eligibility->update(['last_core_sync_status' => 'failed', 'last_core_sync_message' => $resolved['message'] ?? 'Core tidak tersedia.']);
            return $eligibility->refresh();
        }
        $person = $resolved['person'];
        $old = $eligibility->only(['name_snapshot', 'email_snapshot', 'lecturer_id_snapshot', 'core_account_status_snapshot', 'role_snapshot']);
        $eligibility->update([
            'name_snapshot' => $person['name'],
            'email_snapshot' => $person['email'],
            'lecturer_id_snapshot' => $person['lecturer_id'],
            'core_account_status_snapshot' => $person['account_status'],
            'role_snapshot' => $person['role_snapshot'],
            'last_core_synced_at' => now(),
            'last_core_sync_status' => ($resolved['ok'] ?? false) ? 'success' : 'partial',
            'last_core_sync_message' => ($resolved['ok'] ?? false) ? null : ($resolved['message'] ?? 'Perlu ditinjau.'),
            'updated_by_core_user_id' => $actor?->core_user_id,
        ]);
        $this->audit->record($actor, 'internal_supervisor_eligibility_synced', $eligibility, $old, $eligibility->only(array_keys($old)));

        return $eligibility->refresh();
    }

    public function deactivate(PkpaInternalSupervisorEligibility $eligibility, ?User $actor): PkpaInternalSupervisorEligibility
    {
        $old = $eligibility->only(['status']);
        $eligibility->update(['status' => 'inactive', 'updated_by_core_user_id' => $actor?->core_user_id]);
        $this->audit->record($actor, 'internal_supervisor_eligibility_deactivated', $eligibility, $old, ['status' => 'inactive']);

        return $eligibility->refresh();
    }

    private function validatePayload(array $data): void
    {
        if (filled($data['effective_start_date'] ?? null) && filled($data['effective_end_date'] ?? null) && $data['effective_end_date'] < $data['effective_start_date']) {
            throw ValidationException::withMessages(['effective_end_date' => 'Masa efektif selesai harus setelah mulai.']);
        }
        foreach (['maximum_active_students', 'maximum_students_per_program'] as $field) {
            if (($data[$field] ?? 0) < 0) {
                throw ValidationException::withMessages([$field => 'Batas beban tidak boleh negatif.']);
            }
        }
    }

    private function payload(array $person, array $data, ?User $actor): array
    {
        return [
            'core_user_id' => $person['core_user_id'],
            'name_snapshot' => $person['name'],
            'email_snapshot' => $person['email'],
            'lecturer_id_snapshot' => $person['lecturer_id'],
            'core_account_status_snapshot' => $person['account_status'],
            'role_snapshot' => $person['role_snapshot'],
            'maximum_active_students' => $data['maximum_active_students'] ?? null,
            'maximum_students_per_program' => $data['maximum_students_per_program'] ?? null,
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
