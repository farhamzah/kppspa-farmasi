<?php

namespace App\Services;

class PkpaSupervisorCoreResolver
{
    private const INTERNAL_ROLES = ['pembimbing_dalam', 'dosen', 'lecturer', 'academic', 'faculty'];
    private const FIELD_ROLES = ['pembimbing_lapangan', 'field_supervisor', 'preseptor', 'preceptor'];

    public function __construct(private readonly CoreFarmasiClient $coreClient)
    {
    }

    public function resolveInternal(array $criteria): array
    {
        return $this->resolve($criteria, 'internal');
    }

    public function resolveField(array $criteria): array
    {
        return $this->resolve($criteria, 'field');
    }

    private function resolve(array $criteria, string $type): array
    {
        $coreUserId = $criteria['core_user_id'] ?? null;
        $query = $criteria['q'] ?? null;
        $person = null;

        if (filled($coreUserId)) {
            $person = $type === 'internal'
                ? ($this->coreClient->getLecturer($coreUserId) ?: $this->coreClient->getUser($coreUserId))
                : $this->coreClient->getUser($coreUserId);
        }

        if (! $person && filled($query)) {
            $collection = $type === 'internal'
                ? $this->coreClient->searchLecturers(['q' => $query, 'limit' => 10])
                : $this->coreClient->searchUsers(['q' => $query, 'limit' => 10]);
            $person = collect($collection['data'] ?? [])->first();
        }

        if (! $person) {
            return ['ok' => false, 'reason' => 'not_found', 'message' => 'User pembimbing tidak ditemukan di Core Farmasi.'];
        }

        $normalized = $this->normalize($person);
        if (filled($coreUserId) && (string) $normalized['core_user_id'] !== (string) $coreUserId) {
            return ['ok' => false, 'reason' => 'identity_mismatch', 'message' => 'Core user ID tidak cocok dengan data Core.', 'person' => $normalized];
        }

        if ($normalized['account_status'] !== 'active') {
            return ['ok' => false, 'reason' => 'inactive', 'message' => 'Akun Core pembimbing tidak aktif.', 'person' => $normalized];
        }

        $allowedRoles = $type === 'internal' ? self::INTERNAL_ROLES : self::FIELD_ROLES;
        if (! $this->hasRole($normalized, $allowedRoles)) {
            return ['ok' => false, 'reason' => 'invalid_role', 'message' => 'Role Core pembimbing tidak sesuai.', 'person' => $normalized];
        }

        $access = $this->coreClient->checkUserAppAccess($normalized['core_user_id']);
        if (($access['has_access'] ?? false) !== true) {
            return ['ok' => false, 'reason' => 'app_access_denied', 'message' => 'Pembimbing belum memiliki app access MY PSPA.', 'person' => $normalized];
        }

        return ['ok' => true, 'person' => $normalized + ['app_access' => $access]];
    }

    public function normalize(array $person): array
    {
        $user = is_array($person['user'] ?? null) ? $person['user'] : [];
        $roles = $person['roles'] ?? $user['roles'] ?? [];

        return [
            'core_user_id' => (string) ($person['core_user_id'] ?? $person['user_id'] ?? $person['id'] ?? $user['id'] ?? ''),
            'name' => $person['name'] ?? $person['full_name'] ?? $person['lecturer_name'] ?? $user['name'] ?? null,
            'email' => $person['email'] ?? $user['email'] ?? null,
            'lecturer_id' => $person['nidn'] ?? $person['nidn_nidk'] ?? $person['nidk'] ?? $person['lecturer_id'] ?? $person['employee_number'] ?? null,
            'professional_id' => $person['professional_id'] ?? $person['license_number'] ?? $person['str_number'] ?? $person['employee_number'] ?? null,
            'account_status' => (($person['active'] ?? $user['active'] ?? true) === true) ? 'active' : 'inactive',
            'roles' => is_array($roles) ? $roles : [],
            'role_snapshot' => $this->roleSnapshot(is_array($roles) ? $roles : []),
        ];
    }

    private function hasRole(array $person, array $allowed): bool
    {
        return collect($person['roles'] ?? [])
            ->map(fn ($role) => is_array($role) ? ($role['slug'] ?? $role['name'] ?? $role['code'] ?? null) : $role)
            ->filter()
            ->map(fn ($role) => str($role)->lower()->replace(['-', ' '], '_')->toString())
            ->contains(fn ($role) => in_array($role, $allowed, true));
    }

    private function roleSnapshot(array $roles): ?string
    {
        return collect($roles)
            ->map(fn ($role) => is_array($role) ? ($role['slug'] ?? $role['name'] ?? $role['code'] ?? null) : $role)
            ->filter()
            ->implode(',');
    }
}
