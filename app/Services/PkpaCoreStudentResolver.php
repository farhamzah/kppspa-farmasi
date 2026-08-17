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
            $student = $this->coreClient->getStudent($coreUserId) ?: $this->coreClient->getUser($coreUserId);
        }

        if (! $student && filled($studentNumber)) {
            $student = collect($this->coreClient->searchStudents(['q' => $studentNumber, 'student_number' => $studentNumber, 'limit' => 10])['data'] ?? [])
                ->first(fn ($item) => $this->studentNumber($item) === (string) $studentNumber);
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
        $roles = $student['roles'] ?? $user['roles'] ?? [];

        return [
            'core_user_id' => (string) ($student['core_user_id'] ?? $student['user_id'] ?? $student['id'] ?? $user['id'] ?? ''),
            'student_number' => $this->studentNumber($student),
            'name' => $student['name'] ?? $student['full_name'] ?? $student['student_name'] ?? $user['name'] ?? null,
            'email' => $student['email'] ?? $user['email'] ?? null,
            'study_program' => $student['study_program'] ?? $student['study_program_name'] ?? null,
            'cohort' => $student['cohort'] ?? $student['cohort_year'] ?? $student['angkatan'] ?? null,
            'academic_status' => $student['academic_status'] ?? $student['student_status'] ?? null,
            'account_status' => (($student['active'] ?? $user['active'] ?? true) === true) ? 'active' : 'inactive',
            'roles' => is_array($roles) ? $roles : [],
        ];
    }

    private function studentNumber(array $student): ?string
    {
        return isset($student['nim']) ? (string) $student['nim']
            : (isset($student['npm']) ? (string) $student['npm']
            : (isset($student['student_number']) ? (string) $student['student_number'] : null));
    }

    private function hasStudentRole(array $student): bool
    {
        $roles = collect($student['roles'] ?? [])
            ->map(fn ($role) => is_array($role) ? ($role['slug'] ?? $role['name'] ?? $role['code'] ?? null) : $role)
            ->filter()
            ->map(fn ($role) => str($role)->lower()->replace(['-', ' '], '_')->toString());

        return $roles->contains(fn ($role) => in_array($role, ['mahasiswa', 'student'], true));
    }
}
