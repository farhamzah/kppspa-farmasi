<?php

namespace App\Console\Commands;

use App\Exceptions\CoreMasterDataUnavailableException;
use App\Models\Core\CoreUser;
use App\Models\Core\CoreUserAppAccess;
use App\Models\FieldSupervisor;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;
use App\Services\KpMasterDataReadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class CoreModePreflightCommand extends Command
{
    protected $signature = 'kp:core-mode-preflight
        {--auth-mode= : Requested auth mode: legacy, core_http, core_bridge, core_bridge_with_legacy_fallback}
        {--master-data-mode= : Requested master data read mode: legacy, core_preferred, core_only}
        {--email= : Optional email to inspect}
        {--show-samples : Show sample display data}
        {--report-json : Save diagnostic report as JSON}';

    protected $description = 'Read-only preflight for staged KP Core mode cutover readiness';

    private array $allowedAuthModes = [
        'legacy',
        'core_http',
        'core_bridge',
        'core_bridge_with_legacy_fallback',
    ];

    public function handle(KpMasterDataReadService $masterData): int
    {
        $report = $this->buildReport($masterData);

        $this->renderReport($report);

        if ($this->option('report-json')) {
            $path = $this->writeJsonReport($report);
            $this->info("JSON report written: {$path}");
        }

        return $report['status'] === 'FAIL' ? self::FAILURE : self::SUCCESS;
    }

    private function buildReport(KpMasterDataReadService $masterData): array
    {
        $warnings = [];
        $blockers = [];
        $requestedAuthMode = (string) ($this->option('auth-mode') ?: config('kp_auth.mode', 'legacy'));
        $requestedMasterDataMode = (string) ($this->option('master-data-mode') ?: config('kp_master_data.read_mode', 'legacy'));
        $allowedMasterDataModes = config('kp_master_data.allowed_modes', ['legacy']);
        $email = strtolower(trim((string) $this->option('email')));
        $beforeCounts = $this->readOnlyCounts();

        $kpConnected = $this->connectionWorks(DB::connection(), 'KP DB', $blockers);
        $coreConnected = $this->connectionWorks(DB::connection('core'), 'Core DB', $blockers);

        if (! in_array($requestedAuthMode, $this->allowedAuthModes, true)) {
            $blockers[] = "Invalid requested auth mode: {$requestedAuthMode}.";
        }

        if (! in_array($requestedMasterDataMode, $allowedMasterDataModes, true)) {
            $blockers[] = "Invalid requested master data mode: {$requestedMasterDataMode}.";
        }

        $counts = [
            'core_users' => null,
            'core_students' => null,
            'core_lecturers' => null,
            'kp_app_accesses' => null,
            'legacy_users_mapped' => null,
            'legacy_students_mapped' => null,
            'legacy_lecturers_mapped' => null,
            'field_supervisors_mapped' => null,
        ];
        $adminValidation = null;
        $studentSample = null;
        $lecturerSample = null;
        $emailValidation = null;

        if ($kpConnected && $coreConnected) {
            $counts = [
                'core_users' => CoreUser::query()->count(),
                'core_students' => DB::connection('core')->table('students')->count(),
                'core_lecturers' => DB::connection('core')->table('lecturers')->count(),
                'kp_app_accesses' => CoreUserAppAccess::query()->where('app_code', $this->appCode())->where('is_active', true)->count(),
                'legacy_users_mapped' => User::query()->whereNotNull('core_user_id')->count(),
                'legacy_students_mapped' => Student::query()->whereNotNull('core_student_id')->count(),
                'legacy_lecturers_mapped' => Lecturer::query()->whereNotNull('core_lecturer_id')->count(),
                'field_supervisors_mapped' => FieldSupervisor::query()->whereNotNull('core_user_id')->count(),
            ];

            $adminValidation = $this->validateAdmin($blockers);

            if (in_array($requestedMasterDataMode, $allowedMasterDataModes, true)) {
                [$studentSample, $lecturerSample] = $this->sampleDisplayData($masterData, $requestedMasterDataMode, $warnings, $blockers);
            }

            if ($email !== '') {
                $emailValidation = $this->validateEmail($email, $blockers);
            }
        }

        foreach ($counts as $key => $count) {
            if ($count === 0 && in_array($key, ['core_users', 'kp_app_accesses', 'legacy_users_mapped'], true)) {
                $blockers[] = "Required count is zero: {$key}.";
            }
        }

        $afterCounts = $this->readOnlyCounts();
        if ($beforeCounts !== $afterCounts) {
            $blockers[] = 'Preflight changed database row counts; expected read-only behavior.';
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'status' => $blockers ? 'FAIL' : ($warnings ? 'WARN' : 'PASS'),
            'current_modes' => [
                'auth' => config('kp_auth.mode', 'legacy'),
                'master_data' => config('kp_master_data.read_mode', 'legacy'),
            ],
            'requested_modes' => [
                'auth' => $requestedAuthMode,
                'master_data' => $requestedMasterDataMode,
            ],
            'connections' => [
                'kp_db' => $kpConnected,
                'core_db' => $coreConnected,
            ],
            'counts' => $counts,
            'admin_validation' => $adminValidation,
            'email_validation' => $emailValidation,
            'samples' => [
                'student' => $studentSample,
                'lecturer' => $lecturerSample,
            ],
            'read_only_counts' => [
                'before' => $beforeCounts,
                'after' => $afterCounts,
                'unchanged' => $beforeCounts === $afterCounts,
            ],
            'warnings' => array_values(array_unique($warnings)),
            'blockers' => array_values(array_unique($blockers)),
        ];
    }

    private function connectionWorks($connection, string $label, array &$blockers): bool
    {
        try {
            $connection->getPdo();

            return true;
        } catch (Throwable $exception) {
            $blockers[] = "{$label} connection failed: {$exception->getMessage()}";

            return false;
        }
    }

    private function validateAdmin(array &$blockers): array
    {
        $admin = CoreUser::query()
            ->with(['roles', 'appAccesses'])
            ->whereRaw('LOWER(TRIM(email)) = ?', ['admin@sikp.test'])
            ->first();
        $roles = $admin?->roles->pluck('name')->values()->all() ?? [];
        $accesses = $admin?->appAccesses
            ->where('app_code', $this->appCode())
            ->where('is_active', true)
            ->pluck('role_slug')
            ->values()
            ->all() ?? [];
        $hasAdminKp = in_array('admin-kp', $roles, true) || in_array('admin-kp', $accesses, true);
        $hasAdminCore = in_array('admin-core', $roles, true) || in_array('admin-core', $accesses, true);

        if (! $admin) {
            $blockers[] = 'admin@sikp.test is missing in Core.';
        }

        if ($admin && ! $hasAdminKp) {
            $blockers[] = 'admin@sikp.test does not have admin-kp in Core.';
        }

        if ($admin && $hasAdminCore) {
            $blockers[] = 'admin@sikp.test has admin-core; KP admin must remain admin-kp only.';
        }

        return [
            'found' => (bool) $admin,
            'roles' => $roles,
            'kp_app_access_roles' => $accesses,
            'has_admin_kp' => $hasAdminKp,
            'has_admin_core' => $hasAdminCore,
        ];
    }

    private function sampleDisplayData(KpMasterDataReadService $masterData, string $mode, array &$warnings, array &$blockers): array
    {
        $student = Student::query()->with('user')->whereNotNull('core_student_id')->orderBy('id')->first()
            ?: Student::query()->with('user')->orderBy('id')->first();
        $lecturer = Lecturer::query()->with('user')->whereNotNull('core_lecturer_id')->orderBy('id')->first()
            ?: Lecturer::query()->with('user')->orderBy('id')->first();
        $studentData = null;
        $lecturerData = null;

        try {
            $studentData = $student ? $masterData->getStudentDisplayData($student, $mode) : null;
        } catch (CoreMasterDataUnavailableException $exception) {
            $blockers[] = $exception->getMessage();
        }

        try {
            $lecturerData = $lecturer ? $masterData->getLecturerDisplayData($lecturer, $mode) : null;
        } catch (CoreMasterDataUnavailableException $exception) {
            $blockers[] = $exception->getMessage();
        }

        foreach ([$studentData, $lecturerData] as $data) {
            if ($data?->error) {
                $warnings[] = $data->error;
            }
        }

        if (! $student) {
            $warnings[] = 'No legacy student sample found.';
        }

        if (! $lecturer) {
            $warnings[] = 'No legacy lecturer sample found.';
        }

        return [
            $studentData?->toArray(),
            $lecturerData?->toArray(),
        ];
    }

    private function validateEmail(string $email, array &$blockers): array
    {
        $coreUser = CoreUser::query()
            ->with('appAccesses')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();
        $legacyUser = $coreUser ? User::query()->where('core_user_id', $coreUser->id)->first() : null;
        $hasKpAccess = $coreUser
            ? $coreUser->appAccesses->where('app_code', $this->appCode())->where('is_active', true)->isNotEmpty()
            : false;

        if (! $coreUser) {
            $blockers[] = "Core user not found for {$email}.";
        }

        if ($coreUser && ! $coreUser->active) {
            $blockers[] = "Core user {$email} is inactive.";
        }

        if ($coreUser && $coreUser->must_change_password) {
            $blockers[] = "Core user {$email} must change password in Core Profile Portal before KP login.";
        }

        if ($coreUser && ! $hasKpAccess) {
            $blockers[] = "Core user {$email} has no active {$this->appCode()} app access.";
        }

        if ($coreUser && ! $legacyUser) {
            $blockers[] = "Legacy mapped user not found for {$email}.";
        }

        if ($legacyUser && $legacyUser->status !== 'active') {
            $blockers[] = "Legacy mapped user {$email} is inactive.";
        }

        return [
            'email' => $email,
            'core_user_exists' => (bool) $coreUser,
            'core_user_active' => (bool) ($coreUser?->active),
            'core_must_change_password' => (bool) ($coreUser?->must_change_password),
            'core_kp_app_access_active' => $hasKpAccess,
            'legacy_mapped_user_exists' => (bool) $legacyUser,
            'legacy_user_active' => $legacyUser?->status === 'active',
        ];
    }

    private function readOnlyCounts(): array
    {
        return [
            'kp_users' => $this->safeCount(DB::connection(), 'users'),
            'kp_students' => $this->safeCount(DB::connection(), 'students'),
            'kp_lecturers' => $this->safeCount(DB::connection(), 'lecturers'),
            'kp_field_supervisors' => $this->safeCount(DB::connection(), 'field_supervisors'),
            'core_users' => $this->safeCount(DB::connection('core'), 'users'),
            'core_students' => $this->safeCount(DB::connection('core'), 'students'),
            'core_lecturers' => $this->safeCount(DB::connection('core'), 'lecturers'),
            'core_user_app_accesses' => $this->safeCount(DB::connection('core'), 'user_app_accesses'),
        ];
    }

    private function appCode(): string
    {
        return (string) config('core_farmasi.app_code', 'kppspa-farmasi');
    }

    private function safeCount($connection, string $table): ?int
    {
        try {
            return $connection->table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }

    private function renderReport(array $report): void
    {
        $this->info('KP Core mode preflight');
        $this->line('Status: '.$report['status']);
        $this->line('Current auth mode: '.$report['current_modes']['auth']);
        $this->line('Current master data mode: '.$report['current_modes']['master_data']);
        $this->line('Requested auth mode: '.$report['requested_modes']['auth']);
        $this->line('Requested master data mode: '.$report['requested_modes']['master_data']);
        $this->line('KP DB connected: '.($report['connections']['kp_db'] ? 'yes' : 'no'));
        $this->line('Core DB connected: '.($report['connections']['core_db'] ? 'yes' : 'no'));

        $this->newLine();
        $this->line('Counts:');
        foreach ($report['counts'] as $key => $count) {
            $this->line("  {$key}: ".($count ?? 'n/a'));
        }

        $this->newLine();
        $this->line('Admin validation:');
        $this->line('  admin-kp: '.($report['admin_validation']['has_admin_kp'] ?? false ? 'yes' : 'no'));
        $this->line('  admin-core: '.($report['admin_validation']['has_admin_core'] ?? false ? 'yes' : 'no'));

        if ($report['email_validation']) {
            $this->newLine();
            $this->line('Email validation:');
            foreach ($report['email_validation'] as $key => $value) {
                $this->line("  {$key}: ".(is_bool($value) ? ($value ? 'yes' : 'no') : $value));
            }
        }

        if ($this->option('show-samples')) {
            $this->newLine();
            $this->line('Samples:');
            $this->line('  student: '.json_encode($report['samples']['student'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->line('  lecturer: '.json_encode($report['samples']['lecturer'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        $this->newLine();
        $this->line('Warnings:');
        $report['warnings']
            ? collect($report['warnings'])->each(fn ($warning) => $this->warn("  - {$warning}"))
            : $this->line('  none');

        $this->newLine();
        $this->line('Blockers:');
        $report['blockers']
            ? collect($report['blockers'])->each(fn ($blocker) => $this->error("  - {$blocker}"))
            : $this->line('  none');
    }

    private function writeJsonReport(array $report): string
    {
        $directory = storage_path('app/reports');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/kp-core-mode-preflight-'.now()->format('Ymd-His').'.json';
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
