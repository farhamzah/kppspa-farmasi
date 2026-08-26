<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaProgram;
use Illuminate\Support\Collection;

class PkpaCoreDirectorySearchService
{
    public function __construct(
        private readonly CoreFarmasiClient $coreClient,
        private readonly PkpaSupervisorCoreResolver $supervisorResolver,
        private readonly PkpaCoreStudentResolver $studentResolver,
    ) {
    }

    public function searchInternalSupervisors(?string $query = null, int $limit = 10): array
    {
        $items = collect($this->coreClient->searchLecturers($this->buildParams($query, $limit))['data'] ?? []);

        return $this->filterSupervisors($items, 'internal')
            ->map(fn (array $person) => [
                'core_user_id' => $person['core_user_id'],
                'label' => $person['name'] ?: $person['core_user_id'],
                'name' => $person['name'],
                'email' => $person['email'],
                'identifier' => $person['lecturer_id'] ?: $person['professional_id'] ?: $person['core_user_id'],
                'role_snapshot' => $person['role_snapshot'],
                'account_status' => $person['account_status'],
            ])
            ->values()
            ->all();
    }

    public function searchFieldSupervisors(?string $query = null, int $limit = 10): array
    {
        $items = collect($this->coreClient->searchUsers($this->buildParams($query, $limit))['data'] ?? []);

        return $this->filterSupervisors($items, 'field')
            ->map(fn (array $person) => [
                'core_user_id' => $person['core_user_id'],
                'label' => $person['name'] ?: $person['core_user_id'],
                'name' => $person['name'],
                'email' => $person['email'],
                'identifier' => $person['professional_id'] ?: $person['lecturer_id'] ?: $person['core_user_id'],
                'role_snapshot' => $person['role_snapshot'],
                'account_status' => $person['account_status'],
            ])
            ->values()
            ->all();
    }

    public function searchStudents(?string $query = null, int $limit = 10, ?PkpaProgram $program = null): array
    {
        $items = collect($this->coreClient->searchStudents($this->buildStudentParams($query, $limit))['data'] ?? [])
            ->merge($this->fallbackStudentDirectoryItems($query, $limit));

        return $items
            ->map(function ($student) {
                $student = (array) $student;
                $normalized = $this->studentResolver->normalizeStudent($student);

                if (isset($student['_app_access'])) {
                    $normalized['_app_access'] = $student['_app_access'];
                }

                return $normalized;
            })
            ->filter(fn (array $student) => $student['account_status'] === 'active')
            ->filter(fn (array $student) => $this->studentHasRole($student))
            ->map(function (array $student) use ($program) {
                $access = is_array($student['_app_access'] ?? null)
                    ? $student['_app_access']
                    : $this->coreClient->checkUserAppAccess($student['core_user_id']);

                return $student + [
                    'has_app_access' => ($access['has_access'] ?? false) === true,
                    'program_match_score' => $this->programMatchScore($student, $program),
                ];
            })
            ->filter(fn (array $student) => $student['has_app_access'] === true)
            ->when($program, fn (Collection $students) => $this->excludeExistingProgramEnrollments($students, $program))
            ->unique('core_user_id')
            ->sortBy([
                ['program_match_score', 'desc'],
                ['name', 'asc'],
            ])
            ->take($limit)
            ->map(fn (array $student) => [
                'core_user_id' => $student['core_user_id'],
                'label' => $student['name'] ?: $student['core_user_id'],
                'name' => $student['name'],
                'email' => $student['email'],
                'student_number' => $student['student_number'],
                'study_program' => $student['study_program'],
                'cohort' => $student['cohort'],
                'account_status' => $student['account_status'],
            ])
            ->values()
            ->all();
    }

    private function fallbackStudentDirectoryItems(?string $query, int $limit): Collection
    {
        $directoryUsers = collect($this->coreClient->searchUsers($this->buildParams($query, max($limit * 2, 10)))['data'] ?? []);
        $appAccessUsers = collect($this->coreClient->listAppAccessUsers([
            'limit' => max($limit * 10, 100),
        ])['data'] ?? [])
            ->map(fn ($item) => $this->normalizeStudentAppAccessItem((array) $item))
            ->filter()
            ->when(filled($query), fn (Collection $items) => $items->filter(fn (array $item) => $this->matchesStudentQuery($item, (string) $query)));

        return $directoryUsers->merge($appAccessUsers);
    }

    private function normalizeStudentAppAccessItem(array $item): ?array
    {
        $user = is_array($item['user'] ?? null) ? $item['user'] : [];
        $studentProfile = is_array(data_get($item, 'profiles.student')) ? data_get($item, 'profiles.student') : [];
        $accessRoles = collect($item['roles'] ?? [])
            ->map(fn ($role) => is_array($role) ? ($role['slug'] ?? $role['name'] ?? $role['code'] ?? null) : $role)
            ->filter()
            ->values();

        if ($user === [] && $studentProfile === []) {
            return null;
        }

        $coreUserId = $user['id']
            ?? $item['user_id']
            ?? $studentProfile['user_id']
            ?? $item['core_user_id']
            ?? null;

        if (! filled($coreUserId)) {
            return null;
        }

        $payload = array_replace($studentProfile, [
            'core_user_id' => (string) $coreUserId,
            'name' => $studentProfile['name'] ?? $user['name'] ?? $item['name'] ?? null,
            'email' => $studentProfile['email'] ?? $user['email'] ?? $item['email'] ?? null,
            'active' => $studentProfile['active'] ?? $user['active'] ?? $item['active'] ?? true,
            'user' => $user,
            'roles' => array_values(array_filter(array_merge(
                is_array($studentProfile['roles'] ?? null) ? $studentProfile['roles'] : [],
                is_array($user['roles'] ?? null) ? $user['roles'] : [],
                is_array($item['roles'] ?? null) ? $item['roles'] : [],
            ))),
            'app_accesses' => $accessRoles
                ->map(fn ($role) => [
                    'app_code' => (string) config('core_farmasi.app_code', 'kppspa-farmasi'),
                    'role_slug' => $role,
                ])->all(),
            '_app_access' => [
                'has_access' => true,
                'app_code' => (string) config('core_farmasi.app_code', 'kppspa-farmasi'),
                'user_id' => (string) $coreUserId,
                'roles' => $accessRoles->map(fn ($role) => ['slug' => $role])->all(),
            ],
        ]);

        return $payload;
    }

    private function matchesStudentQuery(array $item, string $query): bool
    {
        $keyword = str($query)->lower()->trim()->toString();
        if ($keyword === '') {
            return true;
        }

        return collect([
            $item['name'] ?? null,
            $item['email'] ?? null,
            $item['student_number'] ?? $item['nim'] ?? $item['npm'] ?? null,
            $item['core_user_id'] ?? null,
            data_get($item, 'user.id'),
        ])->filter()
            ->map(fn ($value) => str((string) $value)->lower()->toString())
            ->contains(fn (string $value) => str_contains($value, $keyword));
    }

    private function excludeExistingProgramEnrollments(Collection $students, PkpaProgram $program): Collection
    {
        $coreUserIds = $students
            ->pluck('core_user_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        if ($coreUserIds === []) {
            return $students;
        }

        $existing = PkpaEnrollment::withTrashed()
            ->where('pkpa_program_id', $program->id)
            ->whereIn('core_user_id', $coreUserIds)
            ->pluck('core_user_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return $students->reject(fn (array $student) => in_array((string) ($student['core_user_id'] ?? ''), $existing, true));
    }

    private function programMatchScore(array $student, ?PkpaProgram $program): int
    {
        if (! $program) {
            return 0;
        }

        $year = $this->extractProgramYear($program);
        if (! $year) {
            return 0;
        }

        $yearSuffix = substr((string) $year, -2);
        $email = str((string) ($student['email'] ?? ''))->lower()->toString();
        $studentNumber = preg_replace('/\D+/', '', (string) ($student['student_number'] ?? ''));

        if ($email !== '' && str_contains($email, 'ap'.$yearSuffix.'.')) {
            return 2;
        }

        if ($studentNumber !== '' && str_starts_with($studentNumber, $yearSuffix)) {
            return 1;
        }

        return 0;
    }

    private function extractProgramYear(PkpaProgram $program): ?int
    {
        $candidates = [
            $program->code,
            $program->name,
            $program->cohort_name,
            $program->academic_year,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            if (preg_match('/\b(20\d{2})\b/', $candidate, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    private function filterSupervisors(Collection $items, string $type): Collection
    {
        return $items
            ->map(function ($item) use ($type) {
                $person = $this->supervisorResolver->normalize((array) $item);

                if ($person['account_status'] !== 'active') {
                    return null;
                }

                $access = $this->coreClient->checkUserAppAccess($person['core_user_id']);
                if (($access['has_access'] ?? false) !== true) {
                    return null;
                }

                $allowedRoles = $type === 'internal'
                    ? ['pembimbing_dalam', 'dosen', 'lecturer', 'academic', 'faculty']
                    : ['pembimbing_lapangan', 'field_supervisor', 'preseptor', 'preceptor'];

                if (! $this->personHasRole($person, $allowedRoles) && ! $this->accessHasRole($access, $type)) {
                    return null;
                }

                return $person;
            })
            ->filter()
            ->unique('core_user_id');
    }

    private function buildParams(?string $query, int $limit): array
    {
        return array_filter([
            'q' => filled($query) ? trim((string) $query) : null,
            'limit' => max(1, min($limit, 20)),
        ], fn ($value) => filled($value));
    }

    private function buildStudentParams(?string $query, int $limit): array
    {
        $query = filled($query) ? trim((string) $query) : null;

        return array_filter([
            'q' => $query,
            'student_number' => $query,
            'limit' => max(1, min($limit, 20)),
        ], fn ($value) => filled($value));
    }

    private function hasAccess(string $coreUserId): bool
    {
        if (blank($coreUserId)) {
            return false;
        }

        $access = $this->coreClient->checkUserAppAccess($coreUserId);

        return ($access['has_access'] ?? false) === true;
    }

    private function personHasRole(array $person, array $allowedRoles): bool
    {
        return $this->normalizeRoles($person['roles'] ?? [])
            ->contains(fn ($role) => in_array($role, $allowedRoles, true));
    }

    private function studentHasRole(array $student): bool
    {
        return $this->normalizeRoles($student['roles'] ?? [])
            ->contains(fn ($role) => in_array($role, ['mahasiswa', 'student'], true));
    }

    private function accessHasRole(array $access, string $type): bool
    {
        $allowedRoles = $type === 'internal'
            ? ['pembimbing_dalam', 'koordinator_kp', 'admin_kp', 'admin_kppspa']
            : ['pembimbing_lapangan'];

        return $this->normalizeRoles($access['roles'] ?? [])
            ->contains(fn ($role) => in_array($role, $allowedRoles, true));
    }

    private function normalizeRoles(array $roles): Collection
    {
        return collect($roles)
            ->map(fn ($role) => is_array($role) ? ($role['slug'] ?? $role['name'] ?? $role['code'] ?? null) : $role)
            ->filter()
            ->map(fn ($role) => str($role)->lower()->replace(['-', ' '], '_')->toString())
            ->values();
    }
}
