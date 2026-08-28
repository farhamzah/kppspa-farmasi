<?php

namespace App\Services;

use App\Models\FieldSupervisor;
use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\CoreRoleTranslator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CoreHttpUserProjectionService
{
    /**
     * @param  array<string, mixed>  $coreUser
     * @param  array<string, mixed>  $access
     * @param  array<string, mixed>|null  $appUser
     * @return array<string, mixed>
     */
    public function project(array $coreUser, array $access, ?array $appUser = null): array
    {
        $normalized = $this->normalizeUser($appUser['user'] ?? $coreUser);
        $roles = $appUser['roles'] ?? $access['roles'] ?? $coreUser['roles'] ?? [];
        $kpRoles = CoreRoleTranslator::coreRolesToKp(is_iterable($roles) ? $roles : []);
        $warnings = [];
        $blockers = [];

        if ($normalized['core_user_id'] <= 0 || blank($normalized['email'])) {
            $blockers[] = 'Core user payload belum lengkap.';
        }

        if (($normalized['active'] ?? true) !== true) {
            $blockers[] = 'Akun Core tidak aktif.';
        }

        if ($kpRoles === []) {
            $blockers[] = 'Core app access belum memiliki role MY PKPA yang dikenali.';
        }

        if ($blockers !== []) {
            return $this->result(false, null, $kpRoles, $warnings, $blockers);
        }

        $profiles = is_array($appUser['profiles'] ?? null) ? $appUser['profiles'] : [];

        return DB::transaction(function () use ($normalized, $kpRoles, $profiles, &$warnings): array {
            $legacyUser = $this->resolveUser($normalized, $warnings);

            $legacyUser->forceFill([
                'core_user_id' => $normalized['core_user_id'],
                'name' => $normalized['name'],
                'email' => $normalized['email'],
                'status' => 'active',
                'must_change_password' => false,
                'avatar_path' => $normalized['profile_photo_url'] ?: $legacyUser->avatar_path,
                'avatar_disk' => $normalized['profile_photo_url'] ? 'remote' : $legacyUser->avatar_disk,
                'core_synced_at' => now(),
                'core_sync_status' => 'synced',
                'core_sync_note' => 'Synced from Core Farmasi HTTP app access.',
            ]);

            if (! $legacyUser->exists) {
                $legacyUser->password = Hash::make(Str::random(48));
            }

            $legacyUser->save();

            $roleIds = Role::query()
                ->whereIn('name', $kpRoles)
                ->pluck('id')
                ->all();

            if ($roleIds === []) {
                return $this->result(false, null, $kpRoles, $warnings, ['Role lokal MY PKPA belum tersedia.']);
            }

            $legacyUser->roles()->sync($roleIds);

            $this->syncStudentProfile($legacyUser, $profiles['student'] ?? null, $kpRoles, $warnings);
            $this->syncLecturerProfile($legacyUser, $profiles['lecturer'] ?? null, $kpRoles, $warnings);
            $this->syncFieldSupervisorProfile($legacyUser, $profiles['external_person'] ?? null, $kpRoles, $warnings);

            $legacyUser->refresh()->load(['roles', 'student', 'lecturer', 'fieldSupervisor']);
            $complete = $this->profileProjectionComplete($legacyUser, $kpRoles);
            $legacyUser->forceFill([
                'profile_completed' => $complete,
                'core_sync_status' => $warnings === [] ? 'synced' : 'warning',
                'core_sync_note' => $warnings === []
                    ? 'Synced from Core Farmasi HTTP app access.'
                    : implode(' ', array_unique($warnings)),
            ])->save();

            return $this->result(true, $legacyUser->fresh(['roles', 'student', 'lecturer', 'fieldSupervisor']), $kpRoles, $warnings, []);
        });
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    private function normalizeUser(array $user): array
    {
        return [
            'core_user_id' => (int) ($user['id'] ?? $user['user_id'] ?? $user['core_user_id'] ?? 0),
            'name' => (string) ($user['name'] ?? $user['full_name'] ?? $user['email'] ?? 'Core User'),
            'email' => strtolower(trim((string) ($user['email'] ?? ''))),
            'active' => ($user['active'] ?? true) === true,
            'profile_photo_url' => $user['profile_photo_url'] ?? $user['avatar_url'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  list<string>  $warnings
     */
    private function resolveUser(array $normalized, array &$warnings): User
    {
        $byCore = User::query()->where('core_user_id', $normalized['core_user_id'])->first();
        $byEmail = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$normalized['email']])->first();

        if ($byCore && $byEmail && ! $byCore->is($byEmail)) {
            $warnings[] = 'Ada akun lokal lain dengan email yang sama; memakai akun yang sudah terhubung ke Core.';

            return $byCore;
        }

        return $byCore ?: $byEmail ?: new User();
    }

    /**
     * @param  list<string>  $kpRoles
     * @param  list<string>  $warnings
     */
    private function syncStudentProfile(User $user, mixed $profile, array $kpRoles, array &$warnings): void
    {
        if (! in_array('mahasiswa', $kpRoles, true)) {
            return;
        }

        if (! is_array($profile)) {
            $warnings[] = 'Profil mahasiswa belum tersedia di Core; lengkapi profil di Core Farmasi.';

            return;
        }

        $studentNumber = $profile['student_number'] ?? $profile['nim'] ?? null;
        $studyProgram = data_get($profile, 'study_program.name') ?? $profile['study_program_name'] ?? $profile['study_program'] ?? null;
        $student = Student::query()
            ->where('core_student_id', $profile['id'] ?? 0)
            ->orWhere('user_id', $user->id)
            ->when(filled($studentNumber), fn ($query) => $query->orWhere('nim', $studentNumber))
            ->first();

        $attributes = [
            'user_id' => $user->id,
            'nim' => $studentNumber,
            'study_program' => is_array($studyProgram) ? ($studyProgram['name'] ?? null) : $studyProgram,
            'semester' => $profile['semester'] ?? null,
            'class_name' => $profile['student_class'] ?? $profile['class_name'] ?? null,
            'phone' => $profile['phone'] ?? null,
            'address' => $profile['address'] ?? null,
            'status' => ($profile['active'] ?? true) === true ? 'active' : 'inactive',
            'core_student_id' => $profile['id'] ?? null,
            'core_synced_at' => now(),
            'core_sync_status' => 'synced',
            'core_sync_note' => 'Synced from Core Farmasi HTTP app access.',
            'profile_completed_at' => filled($studentNumber) && filled($studyProgram) ? now() : null,
        ];

        $student ? $student->forceFill($attributes)->save() : Student::query()->create($attributes);

        if (blank($studentNumber) || blank($studyProgram)) {
            $warnings[] = 'Profil mahasiswa Core belum lengkap; lengkapi NPM dan program studi di Core Farmasi.';
        }
    }

    /**
     * @param  list<string>  $kpRoles
     * @param  list<string>  $warnings
     */
    private function syncLecturerProfile(User $user, mixed $profile, array $kpRoles, array &$warnings): void
    {
        if (collect($kpRoles)->intersect(['koordinator_kp', 'pembimbing_dalam', 'penguji'])->isEmpty()) {
            return;
        }

        if (! is_array($profile)) {
            $warnings[] = 'Profil dosen belum tersedia di Core; lengkapi profil dosen di Core Farmasi.';

            return;
        }

        $lecturerNumber = $this->normalizeOptionalIdentifier($profile['nidn'] ?? $profile['lecturer_number'] ?? null);
        $employeeNumber = $this->normalizeOptionalIdentifier($profile['nip'] ?? null);
        $department = data_get($profile, 'department.name') ?? $profile['department_name'] ?? null;
        $studyProgram = data_get($profile, 'study_program.name') ?? $profile['study_program_name'] ?? null;
        $lecturer = Lecturer::query()
            ->where('core_lecturer_id', $profile['id'] ?? 0)
            ->orWhere('user_id', $user->id)
            ->when(filled($lecturerNumber), fn ($query) => $query->orWhere('nidn_nip', $lecturerNumber))
            ->first();

        $attributes = [
            'user_id' => $user->id,
            'nidn_nip' => $lecturerNumber,
            'employee_number' => $employeeNumber,
            'study_program' => $studyProgram,
            'department' => $department,
            'phone' => $profile['phone'] ?? null,
            'address' => $profile['address'] ?? null,
            'status' => ($profile['active'] ?? true) === true ? 'active' : 'inactive',
            'core_lecturer_id' => $profile['id'] ?? null,
            'core_synced_at' => now(),
            'core_sync_status' => 'synced',
            'core_sync_note' => 'Synced from Core Farmasi HTTP app access.',
            'profile_completed_at' => filled($lecturerNumber) && (filled($department) || filled($studyProgram)) ? now() : null,
        ];

        $lecturer ? $lecturer->forceFill($attributes)->save() : Lecturer::query()->create($attributes);

        if (blank($lecturerNumber) || (blank($department) && blank($studyProgram))) {
            $warnings[] = 'Profil dosen Core belum lengkap; lengkapi nomor dosen dan unit di Core Farmasi.';
        }
    }

    private function normalizeOptionalIdentifier(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        if ($normalized === '' || in_array(strtolower($normalized), ['-', 'n/a', 'na', 'null', 'none'], true)) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $kpRoles
     * @param  list<string>  $warnings
     */
    private function syncFieldSupervisorProfile(User $user, mixed $profile, array $kpRoles, array &$warnings): void
    {
        if (! in_array('pembimbing_lapangan', $kpRoles, true)) {
            return;
        }

        if (! is_array($profile)) {
            $warnings[] = 'Profil preseptor belum tersedia di Core; lengkapi profil mitra di Core Farmasi.';

            return;
        }

        $displayName = $profile['display_name_with_title'] ?? $profile['formal_name'] ?? $profile['name'] ?? $user->name;
        $institution = $profile['institution_name'] ?? null;
        $position = $profile['position_title'] ?? $profile['profession'] ?? null;
        $supervisor = FieldSupervisor::query()
            ->where('core_user_id', $user->core_user_id)
            ->orWhere('user_id', $user->id)
            ->first();

        $attributes = [
            'user_id' => $user->id,
            'institution_name' => $institution,
            'position' => $position,
            'phone' => $profile['phone'] ?? null,
            'address' => $profile['address'] ?? null,
            'status' => ($profile['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'core_user_id' => $user->core_user_id,
            'core_external_person_id' => $profile['id'] ?? null,
            'core_display_name' => $displayName,
            'core_synced_at' => now(),
            'core_sync_status' => 'synced',
            'core_sync_note' => 'Synced from Core Farmasi HTTP app access.',
            'profile_completed_at' => filled($institution) && filled($position) && filled($profile['phone'] ?? null) ? now() : null,
        ];

        $supervisor ? $supervisor->forceFill($attributes)->save() : FieldSupervisor::query()->create($attributes);

        if (blank($institution) || blank($position)) {
            $warnings[] = 'Profil preseptor di Core belum lengkap; lengkapi institusi dan jabatan di Core Farmasi.';
        }
    }

    /**
     * @param  list<string>  $kpRoles
     */
    private function profileProjectionComplete(User $user, array $kpRoles): bool
    {
        if (in_array('mahasiswa', $kpRoles, true) && ! $user->student) {
            return false;
        }

        if (collect($kpRoles)->intersect(['koordinator_kp', 'pembimbing_dalam', 'penguji'])->isNotEmpty() && ! $user->lecturer) {
            return false;
        }

        if (in_array('pembimbing_lapangan', $kpRoles, true) && ! $user->fieldSupervisor) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $kpRoles
     * @param  list<string>  $warnings
     * @param  list<string>  $blockers
     * @return array<string, mixed>
     */
    private function result(bool $ok, ?User $user, array $kpRoles, array $warnings, array $blockers): array
    {
        return [
            'ok' => $ok,
            'legacy_user' => $user,
            'kp_roles' => array_values($kpRoles),
            'warnings' => array_values(array_unique($warnings)),
            'blockers' => array_values(array_unique($blockers)),
        ];
    }
}
