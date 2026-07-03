<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class CoreProfileReadService
{
    /**
     * @return array<string, mixed>|null
     */
    public function officialProfileFor(User $user, string $profileType): ?array
    {
        try {
            if (! $this->shouldReadCore()) {
                return null;
            }

            if (! Schema::connection('core')->hasTable('users')) {
                return null;
            }

            $coreUser = $this->coreUserFor($user);

            if (! $coreUser) {
                return null;
            }

            $coreProfileType = $this->coreProfileTypeFor($profileType, $user);
            $linkedProfile = match ($coreProfileType) {
                'mahasiswa' => $this->studentProfileFor($user, $coreUser),
                'dosen' => $this->lecturerProfileFor($user, $coreUser),
                default => null,
            };

            $profilePhotoUrl = $this->profilePhotoUrl($coreUser->profile_photo_path ?? null);
            $displayName = $this->displayNameFor($coreUser, $linkedProfile, $coreProfileType ?? $profileType) ?? $user->name;

            return [
                'available' => true,
                'source' => 'core',
                'profile_type' => $profileType,
                'user' => [
                    'id' => $coreUser->id,
                    'name' => $displayName,
                    'email' => $coreUser->email ?? $user->email,
                    'username' => $coreUser->username ?? null,
                    'identity_type' => $coreUser->identity_type ?? null,
                    'identity_number_masked' => $this->mask($coreUser->identity_number ?? null),
                    'profile_photo_url' => $profilePhotoUrl,
                    'has_profile_photo' => filled($profilePhotoUrl),
                    'active' => (bool) ($coreUser->active ?? true),
                ],
                'linked_profile' => $linkedProfile,
                'sections' => $this->sectionsFor($coreUser, $linkedProfile, $coreProfileType ?? $profileType, $displayName),
                'notice' => $linkedProfile
                    ? 'Data resmi dibaca dari Core Farmasi dan ditampilkan read-only di KP.'
                    : 'Akun Core terbaca, tetapi profil resmi untuk role aktif ini belum tertaut.',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function profilePhotoUrlFor(User $user): ?string
    {
        try {
            if (! $this->shouldReadCore()) {
                return null;
            }

            if (! Schema::connection('core')->hasTable('users')) {
                return null;
            }

            $coreUser = $this->coreUserFor($user);

            if (! $coreUser || blank($coreUser->profile_photo_path ?? null)) {
                return null;
            }

            return $this->profilePhotoUrl($coreUser->profile_photo_path);
        } catch (Throwable) {
            return null;
        }
    }

    public function profilePhotoResponseFor(User $user): ?BinaryFileResponse
    {
        try {
            if (! $this->shouldReadCore()) {
                return null;
            }

            if (! Schema::connection('core')->hasTable('users')) {
                return null;
            }

            $coreUser = $this->coreUserFor($user);
            $path = $coreUser->profile_photo_path ?? null;

            if (blank($path)) {
                return null;
            }

            $file = $this->localProfilePhotoPath($path);

            if (! $file) {
                return null;
            }

            return response()->file($file, [
                'Cache-Control' => 'private, max-age=300',
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function coreUserFor(User $user): ?object
    {
        $query = DB::connection('core')->table('users');

        if (filled($user->core_user_id)) {
            $query->where('id', $user->core_user_id);
        } else {
            $query->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($user->email))]);
        }

        return $query->first();
    }

    private function shouldReadCore(): bool
    {
        return config('kp_master_data.read_mode', 'legacy') !== 'legacy'
            || config('core_farmasi.read_mode', 'legacy') !== 'legacy'
            || (bool) config('core_farmasi.enabled', false)
            || filled(config('core_farmasi.base_url'))
            || filled(config('core_farmasi.profile_url'))
            || filled(config('core_farmasi.storage_public_path'));
    }

    private function coreProfileTypeFor(string $profileType, User $user): ?string
    {
        if ($profileType === 'mahasiswa') {
            return 'mahasiswa';
        }

        if ($profileType === 'dosen') {
            return 'dosen';
        }

        $user->loadMissing('lecturer');

        return $user->lecturer || in_array($profileType, ['admin', 'koordinator_kp', 'pembimbing_dalam', 'pembimbing_lapangan', 'penguji'], true)
            ? 'dosen'
            : null;
    }

    private function studentProfileFor(User $user, object $coreUser): ?object
    {
        if (! Schema::connection('core')->hasTable('students')) {
            return null;
        }

        $query = DB::connection('core')->table('students');
        $select = ['students.*'];

        if ($this->canJoinStudyPrograms()) {
            $query->leftJoin('study_programs', 'study_programs.id', '=', 'students.study_program_id');
            $select[] = 'study_programs.name as study_program_name';
            $select[] = 'study_programs.code as study_program_code';

            if ($this->canJoinFacultiesFromStudyPrograms()) {
                $query->leftJoin('faculties', 'faculties.id', '=', 'study_programs.faculty_id');
                $select[] = 'faculties.name as faculty_name';
            } else {
                $select[] = DB::connection('core')->raw('NULL as faculty_name');
            }

            if (Schema::connection('core')->hasTable('departments')) {
                $query->leftJoin('departments', 'departments.id', '=', 'study_programs.department_id');
                $select[] = 'departments.name as department_name';
            } else {
                $select[] = DB::connection('core')->raw('NULL as department_name');
            }
        } else {
            $select[] = DB::connection('core')->raw('NULL as study_program_name');
            $select[] = DB::connection('core')->raw('NULL as study_program_code');
            $select[] = DB::connection('core')->raw('NULL as faculty_name');
            $select[] = DB::connection('core')->raw('NULL as department_name');
        }

        return $query
            ->where(function ($query) use ($user, $coreUser): void {
                $query->where('students.user_id', $coreUser->id)
                    ->orWhereRaw('LOWER(TRIM(students.email)) = ?', [strtolower(trim($user->email))]);

                if ($user->student?->core_student_id) {
                    $query->orWhere('students.id', $user->student->core_student_id);
                }
            })
            ->select($select)
            ->first();
    }

    private function lecturerProfileFor(User $user, object $coreUser): ?object
    {
        if (! Schema::connection('core')->hasTable('lecturers')) {
            return null;
        }

        $query = DB::connection('core')->table('lecturers');
        $select = ['lecturers.*'];

        if (Schema::connection('core')->hasTable('departments')) {
            $query->leftJoin('departments', 'departments.id', '=', 'lecturers.department_id');
            $select[] = 'departments.name as department_name';
        } else {
            $select[] = DB::connection('core')->raw('NULL as department_name');
        }

        if ($this->canJoinStudyPrograms()) {
            $query->leftJoin('study_programs', 'study_programs.id', '=', 'lecturers.study_program_id');
            $select[] = 'study_programs.name as study_program_name';
            $select[] = 'study_programs.code as study_program_code';

            if ($this->canJoinFacultiesFromStudyPrograms()) {
                $query->leftJoin('faculties', 'faculties.id', '=', 'study_programs.faculty_id');
                $select[] = 'faculties.name as faculty_name';
            } else {
                $select[] = DB::connection('core')->raw('NULL as faculty_name');
            }
        } else {
            $select[] = DB::connection('core')->raw('NULL as study_program_name');
            $select[] = DB::connection('core')->raw('NULL as study_program_code');
            $select[] = DB::connection('core')->raw('NULL as faculty_name');
        }

        return $query
            ->where(function ($query) use ($user, $coreUser): void {
                $query->where('lecturers.user_id', $coreUser->id)
                    ->orWhereRaw('LOWER(TRIM(lecturers.email)) = ?', [strtolower(trim($user->email))]);

                if ($user->lecturer?->core_lecturer_id) {
                    $query->orWhere('lecturers.id', $user->lecturer->core_lecturer_id);
                }
            })
            ->select($select)
            ->first();
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    private function sectionsFor(object $coreUser, ?object $profile, string $profileType, ?string $displayName = null): array
    {
        $sections = [
            'Identitas Core' => [
                'Nama' => $displayName ?: ($coreUser->name ?? null),
                'Email' => $coreUser->email ?? null,
                'Username' => $coreUser->username ?? null,
                'Tipe Identitas' => $coreUser->identity_type ?? null,
                'Nomor Identitas' => $this->mask($coreUser->identity_number ?? null),
                'Status Akun' => (bool) ($coreUser->active ?? true) ? 'Aktif' : 'Tidak aktif',
            ],
        ];

        if (! $profile) {
            return $sections;
        }

        if ($profileType === 'mahasiswa') {
            $sections['Profil Mahasiswa'] = [
                'NIM' => $profile->student_number ?? null,
                'Email Profil' => $profile->email ?? null,
                'Status Mahasiswa' => $profile->status ?? null,
                'Tempat Lahir' => $profile->birth_place ?? null,
                'Tanggal Lahir' => filled($profile->birth_date ?? null) ? 'Tercatat' : null,
                'Telepon' => $profile->phone ?? null,
                'Alamat' => $profile->address ?? null,
            ];
        }

        if ($profileType === 'dosen') {
            $sections['Profil Dosen'] = [
                'Nomor Utama' => $profile->lecturer_number ?? null,
                'NIDN' => $profile->nidn ?? null,
                'NIDK' => $profile->nidk ?? null,
                'NIP' => $profile->nip ?? null,
                'NUPTK' => $profile->nuptk ?? null,
                'NIK / No. KTP' => $this->mask($profile->national_id_number ?? null),
                'Email Profil' => $profile->email ?? null,
                'Telepon' => $profile->phone ?? null,
                'Alamat' => $profile->address ?? null,
            ];
        }

        $sections['Unit Akademik'] = [
            'Fakultas' => $profile->faculty_name ?? null,
            'Program Studi' => $profile->study_program_name ?? null,
            'Departemen' => $profile->department_name ?? null,
        ];

        return $sections;
    }

    private function displayNameFor(object $coreUser, ?object $profile, string $profileType): ?string
    {
        if ($profileType !== 'dosen') {
            return $coreUser->name ?? null;
        }

        return $profile?->display_name_with_title
            ?? $profile?->formal_name
            ?? $this->composeTitledName($profile?->front_title ?? null, $profile?->name ?? null, $profile?->back_title ?? null)
            ?? $coreUser->display_name_with_title
            ?? $coreUser->formal_name
            ?? $this->composeTitledName($coreUser->front_title ?? null, $coreUser->name ?? null, $coreUser->back_title ?? null)
            ?? $coreUser->name
            ?? null;
    }

    private function composeTitledName(?string $frontTitle, ?string $name, ?string $backTitle): ?string
    {
        if (blank($name)) {
            return null;
        }

        $display = trim(collect([$frontTitle, $name])->filter(fn ($value) => filled($value))->implode(' '));

        if (filled($backTitle)) {
            $display .= ', '.trim($backTitle);
        }

        return $display !== trim((string) $name) ? $display : null;
    }

    private function canJoinStudyPrograms(): bool
    {
        return Schema::connection('core')->hasTable('study_programs');
    }

    private function canJoinFacultiesFromStudyPrograms(): bool
    {
        return Schema::connection('core')->hasTable('faculties')
            && Schema::connection('core')->hasColumn('study_programs', 'faculty_id');
    }

    private function profilePhotoUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $baseUrl = $this->coreBaseUrl();

        if ($baseUrl) {
            return rtrim($baseUrl, '/').'/storage/'.ltrim($path, '/');
        }

        return $this->localProfilePhotoPath($path) ? route('profile.core-avatar.show') : null;
    }

    private function coreBaseUrl(): ?string
    {
        $baseUrl = config('core_farmasi.base_url');

        if (filled($baseUrl)) {
            return rtrim((string) $baseUrl, '/');
        }

        $profileUrl = config('core_farmasi.profile_url');

        if (blank($profileUrl)) {
            return null;
        }

        $parts = parse_url((string) $profileUrl);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port;
    }

    private function mask(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 2).str_repeat('*', max(0, $length - 4)).substr($value, -2);
    }

    private function localProfilePhotoPath(string $path): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $path), '/');

        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        foreach ($this->corePublicStorageRoots() as $root) {
            $base = realpath($root);

            if (! $base) {
                continue;
            }

            $candidate = realpath($base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));

            if ($candidate && str_starts_with($candidate, $base.DIRECTORY_SEPARATOR) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function corePublicStorageRoots(): array
    {
        return array_values(array_filter(array_unique([
            config('core_farmasi.storage_public_path'),
            base_path('../core-farmasi/storage/app/public'),
            '/var/www/core-farmasi/storage/app/public',
        ])));
    }
}
