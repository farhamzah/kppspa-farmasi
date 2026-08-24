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
        $items = collect($this->coreClient->searchStudents($this->buildParams($query, $limit))['data'] ?? []);

        return $items
            ->map(fn ($student) => $this->studentResolver->normalizeStudent((array) $student))
            ->filter(fn (array $student) => $student['account_status'] === 'active')
            ->filter(fn (array $student) => $this->studentHasRole($student))
            ->filter(fn (array $student) => $this->hasAccess($student['core_user_id']))
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

                $allowedRoles = $type === 'internal'
                    ? ['pembimbing_dalam', 'dosen', 'lecturer', 'academic', 'faculty']
                    : ['pembimbing_lapangan', 'field_supervisor', 'preseptor', 'preceptor'];

                if (! $this->personHasRole($person, $allowedRoles)) {
                    return null;
                }

                if (! $this->hasAccess($person['core_user_id'])) {
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
        return collect($person['roles'] ?? [])
            ->map(fn ($role) => is_array($role) ? ($role['slug'] ?? $role['name'] ?? $role['code'] ?? null) : $role)
            ->filter()
            ->map(fn ($role) => str($role)->lower()->replace(['-', ' '], '_')->toString())
            ->contains(fn ($role) => in_array($role, $allowedRoles, true));
    }

    private function studentHasRole(array $student): bool
    {
        return collect($student['roles'] ?? [])
            ->map(fn ($role) => is_array($role) ? ($role['slug'] ?? $role['name'] ?? $role['code'] ?? null) : $role)
            ->filter()
            ->map(fn ($role) => str($role)->lower()->replace(['-', ' '], '_')->toString())
            ->contains(fn ($role) => in_array($role, ['mahasiswa', 'student'], true));
    }
}
