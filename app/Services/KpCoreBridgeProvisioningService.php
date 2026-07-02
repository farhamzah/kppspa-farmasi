<?php

namespace App\Services;

use App\Models\Core\CoreUser;
use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\CoreRoleTranslator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class KpCoreBridgeProvisioningService
{
    /**
     * @return list<string>
     */
    public function emailsWithKpAccess(int $limit = 0): array
    {
        $query = CoreUser::query()
            ->whereHas('appAccesses', function ($query): void {
                $query
                    ->where('app_code', 'kp-farmasi')
                    ->where('is_active', true);
            })
            ->whereNotNull('email')
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query
            ->pluck('email')
            ->map(fn ($email) => $this->normalize((string) $email))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function syncAll(bool $execute = false, int $limit = 0): array
    {
        $emails = $this->emailsWithKpAccess($limit);
        $summary = [
            'mode' => $execute ? 'execute KP bridge write' : 'dry-run only; no writes performed',
            'total' => count($emails),
            'created' => 0,
            'synced' => 0,
            'skipped' => 0,
            'blocked' => 0,
            'rows' => [],
        ];

        foreach ($emails as $email) {
            $report = $execute ? $this->execute($email) : $this->plan($email);
            $action = (string) $report['action'];

            if ($report['blockers'] !== []) {
                $summary['blocked']++;
            } else {
                match ($action) {
                    'create', 'created' => $summary['created']++,
                    'link', 'update', 'synced' => $summary['synced']++,
                    default => $summary['skipped']++,
                };
            }

            $summary['rows'][] = [
                'email' => $email,
                'action' => $action,
                'roles' => $report['kp_roles'],
                'blockers' => $report['blockers'],
            ];
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function plan(string $email): array
    {
        $email = $this->normalize($email);
        $warnings = [];
        $blockers = [];

        $coreUser = CoreUser::query()
            ->with(['appAccesses', 'roles'])
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if (! $coreUser) {
            $blockers[] = "Core user {$email} tidak ditemukan.";

            return $this->result($email, null, null, [], [], 'blocker', $warnings, $blockers);
        }

        if (! $coreUser->active) {
            $blockers[] = "Core user {$email} tidak aktif.";
        }

        if ($coreUser->must_change_password) {
            $blockers[] = "Core user {$email} harus mengganti password di Core Profile Portal sebelum dipakai login KP.";
        }

        $coreAppAccessRoles = $coreUser->appAccesses
            ->where('app_code', 'kp-farmasi')
            ->where('is_active', true)
            ->pluck('role_slug')
            ->filter()
            ->values()
            ->all();
        $coreRoleNames = $coreUser->roles
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
        $coreAccessRoles = collect($coreAppAccessRoles)
            ->merge($coreRoleNames)
            ->unique()
            ->values()
            ->all();

        $kpRoles = CoreRoleTranslator::coreRolesToKp($coreAccessRoles);

        if ($coreAppAccessRoles === []) {
            $blockers[] = "Core user {$email} belum punya app access aktif untuk kp-farmasi.";
        }

        if ($kpRoles === []) {
            $blockers[] = "Core app access {$email} belum punya role yang bisa diterjemahkan ke role KP.";
        }

        $missingLocalRoles = collect($kpRoles)
            ->reject(fn (string $role) => Role::query()->where('name', $role)->exists())
            ->values()
            ->all();

        if ($missingLocalRoles) {
            $blockers[] = 'Role lokal KP belum tersedia: '.implode(', ', $missingLocalRoles).'.';
        }

        $legacyByCore = User::query()->where('core_user_id', $coreUser->id)->first();
        $legacyByEmail = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();
        $legacyUser = $legacyByCore ?: $legacyByEmail;
        $coreStudent = $this->coreStudentFor($coreUser->id, $email);
        $coreLecturer = $this->coreLecturerFor($coreUser->id, $email);
        $legacyStudent = $this->legacyStudentFor($legacyUser, $coreStudent);
        $legacyLecturer = $this->legacyLecturerFor($legacyUser, $coreLecturer);

        if ($legacyByCore && $legacyByEmail && ! $legacyByCore->is($legacyByEmail)) {
            $blockers[] = "Ada dua legacy KP user berbeda untuk core_user_id {$coreUser->id} dan email {$email}.";
        }

        if ($legacyByEmail && filled($legacyByEmail->core_user_id) && (int) $legacyByEmail->core_user_id !== (int) $coreUser->id) {
            $blockers[] = "Legacy KP user {$email} sudah terhubung ke Core user lain.";
        }

        $action = match (true) {
            (bool) $blockers => 'blocker',
            ! $legacyUser => 'create',
            (int) ($legacyUser->core_user_id ?? 0) !== (int) $coreUser->id => 'link',
            $this->rolesNeedSync($legacyUser, $kpRoles) || $legacyUser->status !== 'active' => 'update',
            default => 'skip',
        };

        if ($legacyUser && $legacyUser->status !== 'active') {
            $warnings[] = "Legacy KP user {$email} tidak aktif dan akan diaktifkan saat execute.";
        }

        return $this->result($email, $coreUser, $legacyUser, $coreAccessRoles, $kpRoles, $action, $warnings, $blockers, $coreStudent, $legacyStudent, $coreLecturer, $legacyLecturer);
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(string $email): array
    {
        $plan = $this->plan($email);

        if ($plan['blockers'] !== []) {
            return $plan;
        }

        DB::transaction(function () use (&$plan): void {
            $legacyUser = $plan['legacy_user_id']
                ? User::query()->findOrFail($plan['legacy_user_id'])
                : User::query()->create([
                    'name' => $plan['core_user']['name'],
                    'email' => $plan['email'],
                    'password' => Hash::make(Str::random(64)),
                    'status' => 'active',
                    'must_change_password' => false,
                    'profile_completed' => true,
                ]);

            $legacyUser->forceFill([
                'name' => $plan['core_user']['name'],
                'email' => $plan['email'],
                'status' => 'active',
                'must_change_password' => false,
                'profile_completed' => true,
                'core_user_id' => $plan['core_user']['id'],
                'core_synced_at' => now(),
                'core_sync_status' => 'synced',
                'core_sync_note' => 'Provisioned from Core user for KP auth bridge.',
            ])->save();

            $roleIds = Role::query()
                ->whereIn('name', $plan['kp_roles'])
                ->pluck('id')
                ->all();

            $legacyUser->roles()->sync($roleIds);
            $this->syncLegacyStudentProfile($legacyUser, $plan);
            $this->syncLegacyLecturerProfile($legacyUser, $plan);

            $plan['legacy_user_id'] = $legacyUser->id;
            $plan['legacy_status'] = $legacyUser->status;
            $plan['legacy_student_id'] = $legacyUser->student?->id;
            $plan['legacy_lecturer_id'] = $legacyUser->lecturer?->id;
            $plan['action'] = $plan['action'] === 'create' ? 'created' : 'synced';
        });

        return $plan;
    }

    private function rolesNeedSync(User $legacyUser, array $kpRoles): bool
    {
        if ($kpRoles === []) {
            return false;
        }

        $existing = $legacyUser->roles()->pluck('name')->all();

        return collect($kpRoles)->diff($existing)->isNotEmpty()
            || collect($existing)->diff($kpRoles)->isNotEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    private function result(string $email, ?CoreUser $coreUser, ?User $legacyUser, array $coreAccessRoles, array $kpRoles, string $action, array $warnings, array $blockers, ?object $coreStudent = null, ?Student $legacyStudent = null, ?object $coreLecturer = null, ?Lecturer $legacyLecturer = null): array
    {
        return [
            'email' => $email,
            'action' => $action,
            'core_user' => $coreUser ? [
                'id' => $coreUser->id,
                'name' => $coreUser->name,
                'email' => $coreUser->email,
                'active' => (bool) $coreUser->active,
                'must_change_password' => (bool) $coreUser->must_change_password,
            ] : null,
            'legacy_user_id' => $legacyUser?->id,
            'legacy_status' => $legacyUser?->status,
            'core_student' => $coreStudent ? [
                'id' => $coreStudent->id,
                'student_number' => $coreStudent->student_number ?? null,
                'study_program_name' => $coreStudent->study_program_name ?? null,
                'class_name' => $coreStudent->class_name ?? null,
                'semester' => $coreStudent->semester ?? null,
            ] : null,
            'legacy_student_id' => $legacyStudent?->id,
            'core_lecturer' => $coreLecturer ? [
                'id' => $coreLecturer->id,
                'lecturer_number' => $coreLecturer->lecturer_number,
                'nidn' => $coreLecturer->nidn ?? null,
                'nip' => $coreLecturer->nip ?? null,
                'department_name' => $coreLecturer->department_name ?? null,
                'study_program_name' => $coreLecturer->study_program_name ?? null,
            ] : null,
            'legacy_lecturer_id' => $legacyLecturer?->id,
            'core_app_access_roles' => $coreAccessRoles,
            'kp_roles' => $kpRoles,
            'warnings' => array_values(array_unique($warnings)),
            'blockers' => array_values(array_unique($blockers)),
        ];
    }

    private function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    private function coreStudentFor(int $coreUserId, string $email): ?object
    {
        if (! Schema::connection('core')->hasTable('students')) {
            return null;
        }

        $query = DB::connection('core')
            ->table('students');

        if (Schema::connection('core')->hasTable('study_programs')) {
            $query->leftJoin('study_programs', 'study_programs.id', '=', 'students.study_program_id');
        }

        $select = ['students.*'];
        $select[] = Schema::connection('core')->hasTable('study_programs')
            ? 'study_programs.name as study_program_name'
            : DB::raw('NULL as study_program_name');

        return $query
            ->where(function ($query) use ($coreUserId, $email): void {
                $query
                    ->where('students.user_id', $coreUserId)
                    ->orWhereRaw('LOWER(TRIM(students.email)) = ?', [$email]);
            })
            ->select($select)
            ->first();
    }

    private function coreLecturerFor(int $coreUserId, string $email): ?object
    {
        if (! Schema::connection('core')->hasTable('lecturers')) {
            return null;
        }

        $query = DB::connection('core')
            ->table('lecturers');

        if (Schema::connection('core')->hasTable('departments')) {
            $query->leftJoin('departments', 'departments.id', '=', 'lecturers.department_id');
        }

        if (Schema::connection('core')->hasTable('study_programs')) {
            $query->leftJoin('study_programs', 'study_programs.id', '=', 'lecturers.study_program_id');
        }

        $select = ['lecturers.*'];
        $select[] = Schema::connection('core')->hasTable('departments')
            ? 'departments.name as department_name'
            : DB::raw('NULL as department_name');
        $select[] = Schema::connection('core')->hasTable('study_programs')
            ? 'study_programs.name as study_program_name'
            : DB::raw('NULL as study_program_name');

        return $query
            ->where(function ($query) use ($coreUserId, $email): void {
                $query
                    ->where('lecturers.user_id', $coreUserId)
                    ->orWhereRaw('LOWER(TRIM(lecturers.email)) = ?', [$email]);
            })
            ->select($select)
            ->first();
    }

    private function legacyLecturerFor(?User $legacyUser, ?object $coreLecturer): ?Lecturer
    {
        if (! $coreLecturer) {
            return null;
        }

        $query = Lecturer::query()
            ->where('core_lecturer_id', $coreLecturer->id);

        if ($legacyUser) {
            $query->orWhere('user_id', $legacyUser->id);
        }

        if (filled($coreLecturer->lecturer_number)) {
            $query->orWhere('nidn_nip', $coreLecturer->lecturer_number);
        }

        return $query->first();
    }

    private function legacyStudentFor(?User $legacyUser, ?object $coreStudent): ?Student
    {
        if (! $coreStudent) {
            return null;
        }

        $query = Student::query()
            ->where('core_student_id', $coreStudent->id);

        if ($legacyUser) {
            $query->orWhere('user_id', $legacyUser->id);
        }

        if (filled($coreStudent->student_number ?? null)) {
            $query->orWhere('nim', $coreStudent->student_number);
        }

        return $query->first();
    }

    private function syncLegacyStudentProfile(User $legacyUser, array $plan): void
    {
        if (! $plan['core_student']) {
            return;
        }

        $coreStudent = (object) $plan['core_student'];
        $legacyStudent = Student::query()
            ->where('core_student_id', $coreStudent->id)
            ->orWhere('user_id', $legacyUser->id)
            ->when(filled($coreStudent->student_number ?? null), fn ($query) => $query->orWhere('nim', $coreStudent->student_number))
            ->first();

        $attributes = [
            'user_id' => $legacyUser->id,
            'nim' => $coreStudent->student_number ?? null,
            'study_program' => $coreStudent->study_program_name ?? null,
            'semester' => $coreStudent->semester ?? null,
            'class_name' => $coreStudent->class_name ?? null,
            'phone' => null,
            'address' => null,
            'status' => 'active',
            'core_student_id' => $coreStudent->id,
            'core_synced_at' => now(),
            'core_sync_status' => 'synced',
            'core_sync_note' => 'Provisioned from Core student for KP auth bridge.',
            'profile_completed_at' => now(),
        ];

        if ($legacyStudent) {
            $legacyStudent->forceFill($attributes)->save();

            return;
        }

        Student::query()->create($attributes);
    }

    private function syncLegacyLecturerProfile(User $legacyUser, array $plan): void
    {
        if (! $plan['core_lecturer']) {
            return;
        }

        $coreLecturer = (object) $plan['core_lecturer'];
        $legacyLecturer = Lecturer::query()
            ->where('core_lecturer_id', $coreLecturer->id)
            ->orWhere('user_id', $legacyUser->id)
            ->orWhere('nidn_nip', $coreLecturer->lecturer_number)
            ->first();

        $attributes = [
            'user_id' => $legacyUser->id,
            'nidn_nip' => $coreLecturer->nidn ?: $coreLecturer->lecturer_number,
            'employee_number' => $coreLecturer->nip ?: null,
            'study_program' => $coreLecturer->study_program_name,
            'department' => $coreLecturer->department_name,
            'phone' => null,
            'address' => null,
            'status' => 'active',
            'core_lecturer_id' => $coreLecturer->id,
            'core_synced_at' => now(),
            'core_sync_status' => 'synced',
            'core_sync_note' => 'Provisioned from Core lecturer for KP auth bridge.',
            'profile_completed_at' => now(),
        ];

        if ($legacyLecturer) {
            $legacyLecturer->forceFill($attributes)->save();

            return;
        }

        Lecturer::query()->create($attributes);
    }
}
