<?php

namespace App\Services;

class PkpaCoreStudentResolver
{
    public function __construct(private readonly CoreFarmasiClient $coreClient)
    {
    }

    public function resolve(array $criteria): array
    {
        $coreUserId = $criteria['core_user_id'] ?? null;
        $studentNumber = $criteria['student_number'] ?? $criteria['npm'] ?? null;
        $query = $criteria['q'] ?? null;

        $student = null;
        if (filled($coreUserId)) {
            $student = $this->resolveByCoreUserId((string) $coreUserId);
        }

        if (! $student && filled($studentNumber)) {
            $student = collect($this->coreClient->searchStudents(['q' => $studentNumber, 'student_number' => $studentNumber, 'limit' => 10])['data'] ?? [])
                ->first(fn ($item) => $this->studentNumber($item) === (string) $studentNumber);
        }

        if (! $student && filled($studentNumber)) {
            $student = $this->findStudentUserByNumber((string) $studentNumber);
        }

        if (! $student && filled($query)) {
            $student = collect($this->coreClient->searchStudents(['q' => $query, 'limit' => 10])['data'] ?? [])->first();
        }

        if (! $student) {
            return ['ok' => false, 'reason' => 'not_found', 'message' => 'Mahasiswa tidak ditemukan di Core Farmasi.'];
        }

        $normalized = $this->normalizeStudent($student);

        if (filled($coreUserId) && (string) $normalized['core_user_id'] !== (string) $coreUserId) {
            return ['ok' => false, 'reason' => 'identity_mismatch', 'message' => 'Core user ID tidak cocok dengan data mahasiswa Core.', 'student' => $normalized];
        }

        if (filled($studentNumber) && filled($normalized['student_number']) && (string) $normalized['student_number'] !== (string) $studentNumber) {
            return ['ok' => false, 'reason' => 'identity_mismatch', 'message' => 'NPM dan Core user ID mengarah ke data berbeda.', 'student' => $normalized];
        }

        if ($normalized['account_status'] !== 'active') {
            return ['ok' => false, 'reason' => 'inactive', 'message' => 'Akun Core mahasiswa tidak aktif.', 'student' => $normalized];
        }

        if (! $this->hasStudentRole($normalized)) {
            return ['ok' => false, 'reason' => 'invalid_role', 'message' => 'User Core tidak memiliki role mahasiswa.', 'student' => $normalized];
        }

        $access = $this->coreClient->checkUserAppAccess($normalized['core_user_id']);
        if (($access['has_access'] ?? false) !== true) {
            return ['ok' => false, 'reason' => 'app_access_denied', 'message' => 'Mahasiswa belum memiliki app access MY PKPA.', 'student' => $normalized];
        }

        return ['ok' => true, 'student' => $normalized + ['app_access' => $access]];
    }

    public function normalizeStudent(array $student): array
    {
        $user = is_array($student['user'] ?? null) ? $student['user'] : [];
        $roles = $this->collectRoles($student, $user);

        return [
            'core_user_id' => $this->extractCoreUserId($student, $user),
            'student_number' => $this->studentNumber($student),
            'name' => $user['name'] ?? $student['name'] ?? $student['full_name'] ?? $student['student_name'] ?? null,
            'email' => $user['email'] ?? $student['email'] ?? null,
            'study_program' => $student['study_program'] ?? $student['study_program_name'] ?? null,
            'cohort' => $student['cohort'] ?? $student['cohort_year'] ?? $student['angkatan'] ?? null,
            'academic_status' => $student['academic_status'] ?? $student['student_status'] ?? null,
            'account_status' => (($student['active'] ?? $user['active'] ?? true) === true) ? 'active' : 'inactive',
            'roles' => is_array($roles) ? $roles : [],
        ];
    }

    private function resolveByCoreUserId(string $coreUserId): ?array
    {
        $user = $this->coreClient->getUser($coreUserId);

        if (is_array($user)) {
            $matchedStudent = $this->findStudentProfileForCoreUser($coreUserId, $user);
            if (is_array($matchedStudent)) {
                $matchedStudent['user'] = is_array($matchedStudent['user'] ?? null)
                    ? array_replace($matchedStudent['user'], $user)
                    : $user;

                return $matchedStudent;
            }

            return $user;
        }

        $student = $this->coreClient->getStudent($coreUserId);
        if (! is_array($student)) {
            return null;
        }

        $normalized = $this->normalizeStudent($student);

        return $normalized['core_user_id'] === $coreUserId ? $student : null;
    }

    private function findStudentProfileForCoreUser(string $coreUserId, array $user): ?array
    {
        $queries = array_values(array_filter([
            $user['email'] ?? null,
            $user['name'] ?? null,
            $coreUserId,
        ], fn ($value) => filled($value)));

        foreach ($queries as $query) {
            $matches = $this->coreClient->searchStudents([
                'q' => $query,
                'student_number' => $query,
                'limit' => 20,
            ])['data'] ?? [];

            $matched = collect($matches)->first(function ($item) use ($coreUserId) {
                return $this->normalizeStudent((array) $item)['core_user_id'] === $coreUserId;
            });

            if (is_array($matched)) {
                return $matched;
            }
        }

        return null;
    }

    private function findStudentUserByNumber(string $studentNumber): ?array
    {
        $matches = $this->coreClient->searchUsers([
            'q' => $studentNumber,
            'limit' => 20,
        ])['data'] ?? [];

        $matched = collect($matches)->first(function ($item) use ($studentNumber) {
            $normalized = $this->normalizeStudent((array) $item);

            if ($normalized['student_number'] !== $studentNumber) {
                return false;
            }

            return $this->hasStudentRole($normalized);
        });

        return is_array($matched) ? $matched : null;
    }

    private function extractCoreUserId(array $student, array $user): string
    {
        foreach ([
            $student['core_user_id'] ?? null,
            $user['core_user_id'] ?? null,
            $user['id'] ?? null,
            $user['user_id'] ?? null,
            $student['user_id'] ?? null,
            $student['id'] ?? null,
        ] as $candidate) {
            if (filled($candidate)) {
                return (string) $candidate;
            }
        }

        return '';
    }

    private function studentNumber(array $student): ?string
    {
        return isset($student['nim']) ? (string) $student['nim']
            : (isset($student['npm']) ? (string) $student['npm']
            : (isset($student['student_number']) ? (string) $student['student_number']
            : ($this->identityNumber($student) ?: null)));
    }

    private function hasStudentRole(array $student): bool
    {
        $roles = collect($student['roles'] ?? [])
            ->map(fn ($role) => is_array($role) ? ($role['slug'] ?? $role['name'] ?? $role['code'] ?? null) : $role)
            ->filter()
            ->map(fn ($role) => str($role)->lower()->replace(['-', ' '], '_')->toString());

        return $roles->contains(fn ($role) => in_array($role, ['mahasiswa', 'student'], true));
    }

    private function collectRoles(array $student, array $user): array
    {
        return collect([
            $student['roles'] ?? [],
            $user['roles'] ?? [],
            $this->appAccessRoles($student),
            $this->appAccessRoles($user),
        ])->flatMap(fn ($roles) => is_array($roles) ? $roles : [])
            ->filter()
            ->values()
            ->all();
    }

    private function appAccessRoles(array $payload): array
    {
        $appCode = (string) config('core_farmasi.app_code', 'kppspa-farmasi');

        return collect($payload['app_accesses'] ?? [])
            ->filter(fn ($access) => is_array($access) && ($access['app_code'] ?? null) === $appCode)
            ->map(fn ($access) => $access['role_slug'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    private function identityNumber(array $student): ?string
    {
        $identityType = str((string) ($student['identity_type'] ?? ''))
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();

        if (! in_array($identityType, ['student', 'mahasiswa'], true)) {
            return null;
        }

        return filled($student['identity_number'] ?? null)
            ? (string) $student['identity_number']
            : null;
    }
}
