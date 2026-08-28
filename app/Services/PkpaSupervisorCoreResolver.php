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
                ? ($this->coreClient->getAppAccessUser($coreUserId)
                    ?: $this->coreClient->getUser($coreUserId)
                    ?: $this->coreClient->getLecturer($coreUserId))
                : ($this->coreClient->getAppAccessUser($coreUserId)
                    ?: $this->coreClient->getUser($coreUserId));
        }

        if (! $person && filled($query)) {
            $collection = $type === 'internal'
                ? $this->coreClient->searchLecturers(['q' => $query, 'limit' => 10])
                : $this->coreClient->listAppAccessUsers(['q' => $query, 'role' => 'pembimbing-lapangan', 'limit' => 10]);
            $person = collect($collection['data'] ?? [])->first();
        }

        if (! $person) {
            return ['ok' => false, 'reason' => 'not_found', 'message' => 'User pembimbing tidak ditemukan di Core Farmasi.'];
        }

        $normalized = $this->normalize($person);
        $access = $this->coreClient->checkUserAppAccess($normalized['core_user_id']);
        $normalized = $normalized + ['app_access' => $access];

        if (filled($coreUserId) && (string) $normalized['core_user_id'] !== (string) $coreUserId) {
            return ['ok' => false, 'reason' => 'identity_mismatch', 'message' => 'Core user ID tidak cocok dengan data Core.', 'person' => $normalized];
        }

        if ($normalized['account_status'] !== 'active') {
            return ['ok' => false, 'reason' => 'inactive', 'message' => 'Akun Core pembimbing tidak aktif.', 'person' => $normalized];
        }

        $allowedRoles = $type === 'internal' ? self::INTERNAL_ROLES : self::FIELD_ROLES;
        if (! $this->hasRole($normalized, $allowedRoles) && ! $this->accessHasRole($access, $allowedRoles)) {
            return ['ok' => false, 'reason' => 'invalid_role', 'message' => 'Role Core pembimbing tidak sesuai.', 'person' => $normalized];
        }

        if (($access['has_access'] ?? false) !== true) {
            return ['ok' => false, 'reason' => 'app_access_denied', 'message' => 'Pembimbing belum memiliki app access MY PKPA.', 'person' => $normalized];
        }

        return ['ok' => true, 'person' => $normalized];
    }

    public function normalize(array $person): array
    {
        $user = is_array($person['user'] ?? null) ? $person['user'] : [];
        $profiles = is_array($person['profiles'] ?? null) ? $person['profiles'] : [];
        $lecturerProfile = is_array($profiles['lecturer'] ?? null) ? $profiles['lecturer'] : [];
        $employeeProfile = is_array($profiles['employee'] ?? null) ? $profiles['employee'] : [];
        $roles = $this->collectRoles($person, $user);

        return [
            'core_user_id' => $this->extractCoreUserId($person, $user),
            'name' => $this->extractDisplayName($person, $user, $lecturerProfile, $employeeProfile),
            'email' => $user['email'] ?? $person['email'] ?? null,
            'lecturer_id' => $lecturerProfile['nidn'] ?? $lecturerProfile['nidn_nidk'] ?? $lecturerProfile['nidk'] ?? $lecturerProfile['lecturer_number'] ?? $employeeProfile['employee_number'] ?? $person['nidn'] ?? $person['nidn_nidk'] ?? $person['nidk'] ?? $person['lecturer_id'] ?? $person['employee_number'] ?? null,
            'professional_id' => $employeeProfile['professional_id'] ?? $employeeProfile['license_number'] ?? $employeeProfile['str_number'] ?? $employeeProfile['employee_number'] ?? $person['professional_id'] ?? $person['license_number'] ?? $person['str_number'] ?? $person['employee_number'] ?? null,
            'account_status' => (($person['active'] ?? $user['active'] ?? true) === true) ? 'active' : 'inactive',
            'roles' => $roles,
            'role_snapshot' => $this->roleSnapshot($roles),
        ];
    }

    private function extractCoreUserId(array $person, array $user): string
    {
        foreach ([
            $person['core_user_id'] ?? null,
            $user['core_user_id'] ?? null,
            $user['id'] ?? null,
            $user['user_id'] ?? null,
            $person['user_id'] ?? null,
            $person['id'] ?? null,
        ] as $candidate) {
            if (filled($candidate)) {
                return (string) $candidate;
            }
        }

        return '';
    }

    private function hasRole(array $person, array $allowed): bool
    {
        return $this->normalizeRoles($this->collectRoles($person))
            ->contains(fn ($role) => in_array($role, $this->normalizeRoles($allowed)->all(), true));
    }

    private function accessHasRole(array $access, array $allowed): bool
    {
        return $this->normalizeRoles($access['roles'] ?? [])
            ->contains(fn ($role) => in_array($role, $this->normalizeRoles($allowed)->all(), true));
    }

    private function normalizeRoles(array $roles): \Illuminate\Support\Collection
    {
        return collect($roles)
            ->map(fn ($role) => is_array($role) ? ($role['slug'] ?? $role['name'] ?? $role['code'] ?? null) : $role)
            ->filter()
            ->map(fn ($role) => str($role)->lower()->replace(['-', ' '], '_')->toString())
            ->values();
    }

    private function collectRoles(array ...$sources): array
    {
        return collect($sources)
            ->flatMap(function (array $source): array {
                $roles = $source['roles'] ?? [];
                $accessRoles = data_get($source, 'app_access.roles', []);

                return array_values(array_filter([
                    ... (is_array($roles) ? $roles : []),
                    ... (is_array($accessRoles) ? $accessRoles : []),
                ]));
            })
            ->values()
            ->all();
    }

    private function roleSnapshot(array $roles): ?string
    {
        return collect($roles)
            ->map(fn ($role) => is_array($role) ? ($role['slug'] ?? $role['name'] ?? $role['code'] ?? null) : $role)
            ->filter()
            ->implode(',');
    }

    private function extractDisplayName(array $person, array $user, array $lecturerProfile = [], array $employeeProfile = []): ?string
    {
        foreach ([
            $lecturerProfile['display_name_with_title'] ?? null,
            $lecturerProfile['formal_name'] ?? null,
            $this->composeTitledName($lecturerProfile['front_title'] ?? null, $lecturerProfile['name'] ?? $lecturerProfile['lecturer_name'] ?? null, $lecturerProfile['back_title'] ?? null),
            $employeeProfile['display_name_with_title'] ?? null,
            $employeeProfile['formal_name'] ?? null,
            $this->composeTitledName($employeeProfile['front_title'] ?? null, $employeeProfile['name'] ?? $employeeProfile['employee_name'] ?? null, $employeeProfile['back_title'] ?? null),
            $user['display_name_with_title'] ?? null,
            $user['formal_name'] ?? null,
            $this->composeTitledName($user['front_title'] ?? null, $user['name'] ?? null, $user['back_title'] ?? null),
            $person['display_name_with_title'] ?? null,
            $person['formal_name'] ?? null,
            $this->composeTitledName($person['front_title'] ?? null, $person['name'] ?? $person['full_name'] ?? $person['lecturer_name'] ?? null, $person['back_title'] ?? null),
            $user['name'] ?? null,
            $person['name'] ?? null,
            $person['full_name'] ?? null,
            $person['lecturer_name'] ?? null,
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
