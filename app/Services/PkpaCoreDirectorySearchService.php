<?php

namespace App\Services;

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

    public function searchStudents(?string $query = null, int $limit = 10): array
    {
        $items = collect($this->coreClient->searchStudents($this->buildStudentParams($query, $limit))['data'] ?? []);

        if ($items->isEmpty()) {
            $items = collect($this->coreClient->searchUsers($this->buildParams($query, max($limit * 2, 10)))['data'] ?? []);
        }

        return $items
            ->map(fn ($student) => $this->studentResolver->normalizeStudent((array) $student))
            ->filter(fn (array $student) => $student['account_status'] === 'active')
            ->filter(fn (array $student) => $this->studentHasRole($student))
            ->unique('core_user_id')
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
