<?php

namespace App\Services;

use App\Models\Core\CoreLecturer;
use App\Models\Core\CoreStudent;
use App\Models\Core\CoreUser;
use App\Models\Core\CoreUserAppAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CoreIdentityService
{
    public function findUserByEmail(string $email): ?CoreUser
    {
        return CoreUser::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$this->normalize($email)])
            ->first();
    }

    public function findStudentByNim(string $nim): ?CoreStudent
    {
        return CoreStudent::query()
            ->with(['user', 'studyProgram'])
            ->whereRaw('LOWER(TRIM(student_number)) = ?', [$this->normalize($nim)])
            ->first();
    }

    public function findLecturerByNumberOrEmail(?string $number, ?string $email): ?CoreLecturer
    {
        return CoreLecturer::query()
            ->with(['user', 'studyProgram', 'department'])
            ->when($this->normalize($number) !== '', fn ($query) => $query->whereRaw('LOWER(TRIM(lecturer_number)) = ?', [$this->normalize($number)]))
            ->when($this->normalize($number) === '' && $this->normalize($email) !== '', fn ($query) => $query->whereRaw('LOWER(TRIM(email)) = ?', [$this->normalize($email)]))
            ->first();
    }

    public function userHasAppAccess(int $coreUserId, string $appCode, ?string $roleSlug = null): bool
    {
        return CoreUserAppAccess::query()
            ->where('user_id', $coreUserId)
            ->where('app_code', $appCode)
            ->where('is_active', true)
            ->when($roleSlug !== null, fn ($query) => $query->where('role_slug', $roleSlug))
            ->exists();
    }

    public function getUserRoles(int $coreUserId): Collection
    {
        $user = CoreUser::query()->with('roles')->find($coreUserId);

        return $user?->roles ?? collect();
    }

    public function getKpUsersSummary(): array
    {
        return [
            'users' => CoreUser::query()->count(),
            'students' => CoreStudent::query()->count(),
            'lecturers' => CoreLecturer::query()->count(),
            'kp_app_accesses' => CoreUserAppAccess::query()->where('app_code', $this->appCode())->count(),
            'kp_active_app_accesses' => CoreUserAppAccess::query()->where('app_code', $this->appCode())->where('is_active', true)->count(),
            'local_field_supervisor_profiles' => DB::table('field_supervisors')->count(),
        ];
    }

    private function appCode(): string
    {
        return (string) config('core_farmasi.app_code', 'kppspa-farmasi');
    }

    private function normalize(?string $value): string
    {
        return strtolower(trim((string) $value));
    }
}
