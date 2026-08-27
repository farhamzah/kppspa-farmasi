<?php

namespace App\Services;

use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaInternalSupervisorService
{
    public function __construct(
        private readonly CoreFarmasiClient $coreClient,
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

    /**
     * @return array{created:int,updated:int,total:int}
     */
    public function bootstrapProgram(PkpaProgram $program, array $defaults, ?User $actor, bool $strict = false): array
    {
        $this->validatePayload($defaults);

        $domains = $program->domains()->where('is_active', true)->get();
        if ($domains->isEmpty()) {
            if ($strict) {
                throw ValidationException::withMessages(['pkpa_program_id' => 'Program belum memiliki wahana aktif.']);
            }

            return ['created' => 0, 'updated' => 0, 'total' => 0];
        }

        $supervisors = collect($this->coreClient->listAppAccessUsers(['limit' => 500])['data'] ?? [])
            ->filter(fn ($item) => collect($item['roles'] ?? [])
                ->map(fn ($role) => is_array($role) ? ($role['slug'] ?? $role['name'] ?? null) : $role)
                ->filter()
                ->contains(fn ($role) => str((string) $role)->lower()->replace('_', '-')->toString() === 'pembimbing-dalam'))
            ->filter(fn ($item) => (($item['user']['active'] ?? $item['active'] ?? true) === true))
            ->values();

        if ($supervisors->isEmpty()) {
            if ($strict) {
                throw ValidationException::withMessages(['pkpa_program_id' => 'Belum ada dosen pembimbing dalam aktif yang tersinkron di MY PKPA.']);
            }

            return ['created' => 0, 'updated' => 0, 'total' => 0];
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($program, $domains, $supervisors, $defaults, $actor, &$created, &$updated): void {
            foreach ($supervisors as $supervisor) {
                $payload = $this->payloadFromCoreAppUser($supervisor, $defaults, $actor);

                foreach ($domains as $domain) {
                    $eligibility = PkpaInternalSupervisorEligibility::withTrashed()
                        ->where('pkpa_program_id', $program->id)
                        ->where('practice_domain_id', $domain->practice_domain_id)
                        ->where('core_user_id', $payload['core_user_id'])
                        ->first();

                    if ($eligibility) {
                        if ($eligibility->trashed()) {
                            $eligibility->restore();
                        }

                        $eligibility->forceFill($payload)->save();
                        $this->audit->record($actor, 'internal_supervisor_eligibility_synced', $eligibility, null, $eligibility->only(['pkpa_program_id', 'practice_domain_id', 'core_user_id']));
                        $updated++;

                        continue;
                    }

                    $eligibility = PkpaInternalSupervisorEligibility::create($payload + [
                        'pkpa_program_id' => $program->id,
                        'practice_domain_id' => $domain->practice_domain_id,
                    ]);
                    $this->audit->record($actor, 'internal_supervisor_eligibility_created', $eligibility, null, $eligibility->only(['pkpa_program_id', 'practice_domain_id', 'core_user_id']));
                    $created++;
                }
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $created + $updated,
        ];
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
            'maximum_active_students' => $this->normalizeLimit($data['maximum_active_students'] ?? null),
            'maximum_students_per_program' => $this->normalizeLimit($data['maximum_students_per_program'] ?? null),
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

    public function siblingEligibilities(PkpaInternalSupervisorEligibility $eligibility): Collection
    {
        return PkpaInternalSupervisorEligibility::query()
            ->where('pkpa_program_id', $eligibility->pkpa_program_id)
            ->where('core_user_id', $eligibility->core_user_id)
            ->get();
    }

    private function payloadFromCoreAppUser(array $item, array $data, ?User $actor): array
    {
        $user = is_array($item['user'] ?? null) ? $item['user'] : $item;
        $lecturer = is_array(data_get($item, 'profiles.lecturer')) ? data_get($item, 'profiles.lecturer') : [];
        $roles = collect($item['roles'] ?? [])
            ->map(fn ($role) => is_array($role) ? ($role['slug'] ?? $role['name'] ?? null) : $role)
            ->filter()
            ->implode(',');

        return [
            'core_user_id' => (string) ($user['id'] ?? $user['core_user_id'] ?? $item['user_id'] ?? ''),
            'name_snapshot' => $this->extractDisplayName($item, $user, $lecturer) ?? 'Pembimbing Dalam',
            'email_snapshot' => $user['email'] ?? $item['email'] ?? null,
            'lecturer_id_snapshot' => $lecturer['nidn'] ?? $lecturer['lecturer_number'] ?? $lecturer['employee_number'] ?? $item['lecturer_id'] ?? null,
            'core_account_status_snapshot' => 'active',
            'role_snapshot' => $roles ?: 'pembimbing-dalam',
            'maximum_active_students' => $this->normalizeLimit($data['maximum_active_students'] ?? null),
            'maximum_students_per_program' => $this->normalizeLimit($data['maximum_students_per_program'] ?? null),
            'effective_start_date' => $data['effective_start_date'] ?? null,
            'effective_end_date' => $data['effective_end_date'] ?? null,
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'last_core_synced_at' => now(),
            'last_core_sync_status' => 'success',
            'last_core_sync_message' => 'Disiapkan otomatis untuk semua wahana aktif program.',
            'created_by_core_user_id' => $actor?->core_user_id,
            'updated_by_core_user_id' => $actor?->core_user_id,
        ];
    }

    private function normalizeLimit(mixed $value): ?int
    {
        $limit = is_null($value) || $value === '' ? null : (int) $value;

        return $limit && $limit > 0 ? $limit : null;
    }

    private function extractDisplayName(array $item, array $user, array $lecturer): ?string
    {
        foreach ([
            $lecturer['display_name_with_title'] ?? null,
            $lecturer['formal_name'] ?? null,
            $this->composeTitledName($lecturer['front_title'] ?? null, $lecturer['name'] ?? $lecturer['lecturer_name'] ?? null, $lecturer['back_title'] ?? null),
            $user['display_name_with_title'] ?? null,
            $user['formal_name'] ?? null,
            $this->composeTitledName($user['front_title'] ?? null, $user['name'] ?? null, $user['back_title'] ?? null),
            $item['display_name_with_title'] ?? null,
            $item['formal_name'] ?? null,
            $this->composeTitledName($item['front_title'] ?? null, $item['name'] ?? null, $item['back_title'] ?? null),
            $user['name'] ?? null,
            $item['name'] ?? null,
        ] as $candidate) {
            if (filled($candidate)) {
                return trim((string) $candidate);
            }
        }

        return null;
    }

    private function composeTitledName(mixed $frontTitle, mixed $name, mixed $backTitle): ?string
    {
        $name = filled($name) ? trim((string) $name) : null;

        if (! $name) {
            return null;
        }

        $front = filled($frontTitle) ? rtrim(trim((string) $frontTitle), '., ') . '.' : null;
        $back = filled($backTitle) ? trim((string) $backTitle) : null;

        return collect([$front, $name, $back])->filter(fn ($value) => filled($value))->implode(' ');
    }
}
