<?php

namespace App\Console\Commands;

use App\Models\Core\CoreUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AuthBridgeSmokeTestCommand extends Command
{
    protected $signature = 'kp:auth-bridge-smoke-test
        {--mode=core_http : Mode to validate: core_http, core_bridge, or core_bridge_with_legacy_fallback}
        {--email= : User email to validate}
        {--password= : Optional password to verify against Core hash}
        {--no-write : Run as read-only checklist}
        {--report-json : Save smoke-test report as JSON}';

    protected $description = 'Read-only smoke test for KP Core auth bridge readiness';

    public function handle(): int
    {
        $report = $this->buildReport();
        $this->renderReport($report);

        if ($this->option('report-json')) {
            $path = $this->writeJsonReport($report);
            $this->info("JSON report written: {$path}");
        }

        return $report['result'] === 'PASS' ? self::SUCCESS : self::FAILURE;
    }

    private function buildReport(): array
    {
        $mode = (string) $this->option('mode');
        $email = strtolower(trim((string) $this->option('email')));
        $password = (string) ($this->option('password') ?? '');
        $checks = [];
        $warnings = [];
        $failures = [];

        $appCode = (string) config('core_farmasi.app_code', 'kppspa-farmasi');

        $this->check(in_array($mode, ['core_http', 'core_bridge', 'core_bridge_with_legacy_fallback'], true), 'requested_mode_allowed', $checks, $failures, "Unsupported smoke-test mode {$mode}.");
        $this->check($email !== '', 'email_provided', $checks, $failures, 'Missing --email.');
        $checks['current_auth_mode'] = config('kp_auth.mode', 'legacy');
        $checks['requested_mode'] = $mode;
        $checks['no_write'] = (bool) $this->option('no-write');

        $kpConnected = $this->connectionOk(DB::connection(), 'kp_db_connected', $checks, $failures);
        $coreConnected = $this->connectionOk(DB::connection('core'), 'core_db_connected', $checks, $failures);
        $coreUser = null;
        $legacyUser = null;

        if ($coreConnected && $email !== '') {
            $coreUser = CoreUser::query()
                ->with(['roles', 'appAccesses'])
                ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                ->first();

            $this->check((bool) $coreUser, 'core_user_found', $checks, $failures, "Core user not found for {$email}.");

            if ($coreUser) {
                $this->check((bool) $coreUser->active, 'core_user_active', $checks, $failures, "Core user {$email} is inactive.");
                $checks['core_user_id'] = $coreUser->id;
                $checks['core_roles'] = $coreUser->roles->pluck('name')->values()->all();

                if ($password !== '') {
                    $this->check(Hash::check($password, $coreUser->password), 'core_password_verified', $checks, $failures, 'Core password verification failed.');
                } else {
                    $checks['core_password_verified'] = 'not_checked';
                    $warnings[] = 'Password was not provided; Core password hash was not verified.';
                }

                $kpAccesses = $coreUser->appAccesses
                    ->where('app_code', $appCode)
                    ->where('is_active', true)
                    ->pluck('role_slug')
                    ->values()
                    ->all();
                $checks['core_kp_app_access_roles'] = $kpAccesses;
                $this->check(count($kpAccesses) > 0, 'core_kp_app_access_active', $checks, $failures, "Core user {$email} has no active {$appCode} app access.");

                $legacyUser = User::query()->where('core_user_id', $coreUser->id)->first();
                $this->check((bool) $legacyUser, 'legacy_user_found', $checks, $failures, "Legacy KP user not found for Core user {$coreUser->id}.");
            }
        }

        if ($kpConnected && $legacyUser) {
            $this->check($legacyUser->status === 'active', 'legacy_user_active', $checks, $failures, "Legacy KP user {$legacyUser->email} is inactive.");
            $legacyRoles = $legacyUser->roles()->pluck('name')->values()->all();
            $checks['legacy_user_id'] = $legacyUser->id;
            $checks['legacy_roles'] = $legacyRoles;
            $this->check(count($legacyRoles) > 0, 'legacy_roles_available', $checks, $failures, "Legacy KP user {$legacyUser->email} has no roles.");
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'mode' => $mode,
            'email' => $email,
            'password_provided' => $password !== '',
            'password_value_stored' => false,
            'checks' => $checks,
            'warnings' => array_values(array_unique($warnings)),
            'failures' => array_values(array_unique($failures)),
            'result' => $failures ? 'FAIL' : 'PASS',
        ];
    }

    private function connectionOk($connection, string $key, array &$checks, array &$failures): bool
    {
        try {
            $connection->getPdo();
            $checks[$key] = true;

            return true;
        } catch (Throwable $exception) {
            $checks[$key] = false;
            $failures[] = "{$key}: ".$exception->getMessage();

            return false;
        }
    }

    private function check(bool $condition, string $key, array &$checks, array &$failures, string $failureMessage): void
    {
        $checks[$key] = $condition;

        if (! $condition) {
            $failures[] = $failureMessage;
        }
    }

    private function renderReport(array $report): void
    {
        $this->info('KP Core auth bridge smoke test');
        $this->line('Mode under test: '.$report['mode']);
        $this->line('Email: '.$report['email']);
        $this->line('Password provided: '.($report['password_provided'] ? 'yes' : 'no'));
        $this->line('Password value stored in report: no');

        $this->newLine();
        $this->line('Checks:');
        foreach ($report['checks'] as $key => $value) {
            $this->line('  '.$key.': '.$this->formatValue($value));
        }

        $this->newLine();
        $this->line('Warnings:');
        $report['warnings']
            ? collect($report['warnings'])->each(fn ($warning) => $this->warn("  - {$warning}"))
            : $this->line('  none');

        $this->newLine();
        $this->line('Failures:');
        $report['failures']
            ? collect($report['failures'])->each(fn ($failure) => $this->error("  - {$failure}"))
            : $this->line('  none');

        $this->newLine();
        $report['result'] === 'PASS' ? $this->info('Result: PASS') : $this->error('Result: FAIL');
    }

    private function writeJsonReport(array $report): string
    {
        $directory = storage_path('app/reports');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/kp-core-auth-bridge-smoke-test-'.now()->format('Ymd-His').'.json';
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return $value ? implode(', ', $value) : 'none';
        }

        return (string) $value;
    }
}
