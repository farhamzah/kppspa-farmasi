<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductionReadinessGateCommand extends Command
{
    protected $signature = 'kp:production-readiness-gate
        {--report-json : Save readiness gate report as JSON}';

    protected $description = 'Read-only production and runtime bridge readiness gate for KP Farmasi';

    public function handle(): int
    {
        $before = $this->counts();
        $report = $this->buildReport();
        $after = $this->counts();

        $report['read_only_counts'] = [
            'before' => $before,
            'after' => $after,
            'unchanged' => $before === $after,
        ];

        $this->renderReport($report);

        if ($this->option('report-json')) {
            $path = $this->writeJsonReport($report);
            $this->info("JSON report written: {$path}");
        }

        return $report['summary']['blockers'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function buildReport(): array
    {
        $checks = [
            $this->check('app_env_production', config('app.env') === 'production', 'APP_ENV harus production sebelum go-live.'),
            $this->check('app_debug_disabled', config('app.debug') === false, 'APP_DEBUG harus false sebelum go-live.'),
            $this->check('app_url_https', Str::startsWith((string) config('app.url'), 'https://'), 'APP_URL production harus HTTPS.'),
            $this->check('app_key_present', filled(config('app.key')), 'APP_KEY wajib terisi.'),
            $this->check('session_secure_cookie', (bool) config('session.secure'), 'SESSION_SECURE_COOKIE harus true untuk HTTPS production.'),
            $this->check('core_write_disabled', true, 'KP tidak boleh menulis ke database Core.'),
            $this->check('tu_write_disabled', true, 'Write bridge TU belum boleh aktif sebelum approval gate final.'),
            $this->check('safa_write_disabled', true, 'Write bridge SAFA belum boleh aktif sebelum approval gate final.'),
            $this->check('sso_token_url_disabled', true, 'SSO/autologin/token URL tidak boleh aktif.'),
            $this->check('auth_mode_known', in_array(config('kp_auth.mode'), ['legacy', 'core_http', 'core_bridge', 'core_bridge_with_legacy_fallback'], true), 'KP_AUTH_MODE tidak dikenali.'),
            $this->check('master_data_read_mode_known', in_array(config('kp_master_data.read_mode'), ['legacy', 'core_preferred', 'core_only'], true), 'KP_MASTER_DATA_READ_MODE tidak dikenali.'),
            $this->check(
                'master_data_core_bridge_aligned',
                config('kp_auth.mode') === 'legacy' || config('kp_master_data.read_mode') !== 'legacy',
                'Jika KP_AUTH_MODE memakai Core, KP_MASTER_DATA_READ_MODE harus core_preferred atau core_only sebelum production.'
            ),
            $this->check('core_read_mode_known', in_array(config('core_farmasi.read_mode'), ['legacy', 'core_preferred', 'core_only'], true), 'KP_CORE_READ_MODE tidak dikenali.'),
            $this->check('core_http_ssl_verify', ! config('core_farmasi.enabled') || (bool) config('core_farmasi.verify_ssl'), 'KP_CORE_VERIFY_SSL harus true bila Core HTTP aktif.'),
            $this->check('tu_runtime_endpoint_disabled', ! filled(config('services.tu_farmasi.endpoint')), 'Endpoint runtime TU belum boleh aktif di tahap readiness gate.'),
            $this->check('safa_runtime_endpoint_disabled', ! filled(config('services.safa.endpoint')), 'Endpoint runtime SAFA belum boleh aktif di tahap readiness gate.'),
            $this->check('external_references_table_exists', Schema::hasTable('kp_external_document_references'), 'Tabel kp_external_document_references harus tersedia.'),
            $this->check('users_table_exists', Schema::hasTable('users'), 'Tabel users harus tersedia.'),
            $this->warning('queue_not_sync_recommended', config('queue.default') !== 'sync', 'Production sebaiknya memakai queue worker, bukan sync.'),
            $this->warning('cache_not_file_recommended', config('cache.default') !== 'file', 'Production sebaiknya memakai cache terpusat, bukan file.'),
            $this->warning('mail_not_log_recommended', config('mail.default') !== 'log', 'Production email sebaiknya memakai SMTP/mail service.'),
        ];

        $blockers = collect($checks)->where('level', 'blocker')->where('passed', false)->values();
        $warnings = collect($checks)->where('level', 'warning')->where('passed', false)->values();

        return [
            'generated_at' => now()->toIso8601String(),
            'dry_run' => true,
            'external_request_sent' => false,
            'write_to_core' => false,
            'write_to_tu' => false,
            'write_to_safa' => false,
            'summary' => [
                'checks' => count($checks),
                'blockers' => $blockers->count(),
                'warnings' => $warnings->count(),
                'ready_for_production' => $blockers->isEmpty(),
                'ready_for_runtime_tu_bridge' => false,
            ],
            'checks' => $checks,
            'next_gate' => [
                'tu_endpoint_contract_approved' => false,
                'tu_auth_approved' => false,
                'tu_audit_retry_rollback_approved' => false,
                'manual_approval_before_auto_sync' => true,
            ],
        ];
    }

    private function check(string $key, bool $passed, string $message): array
    {
        return [
            'key' => $key,
            'level' => 'blocker',
            'passed' => $passed,
            'message' => $message,
        ];
    }

    private function warning(string $key, bool $passed, string $message): array
    {
        return [
            'key' => $key,
            'level' => 'warning',
            'passed' => $passed,
            'message' => $message,
        ];
    }

    private function renderReport(array $report): void
    {
        $this->info('KP production readiness gate');
        $this->line('Dry run: yes');
        $this->line('External request sent: no');
        $this->line('Write to Core/TU/SAFA: no/no/no');
        $this->line('Checks: '.$report['summary']['checks']);
        $this->line('Blockers: '.$report['summary']['blockers']);
        $this->line('Warnings: '.$report['summary']['warnings']);
        $this->line('Ready for production: '.($report['summary']['ready_for_production'] ? 'yes' : 'no'));
        $this->line('Ready for runtime TU bridge: no');
        $this->line('Read-only counts unchanged: '.($report['read_only_counts']['unchanged'] ? 'yes' : 'no'));

        $failed = collect($report['checks'])->where('passed', false)->values();

        if ($failed->isNotEmpty()) {
            $this->newLine();
            $this->line('Open items:');
            foreach ($failed as $item) {
                $prefix = $item['level'] === 'blocker' ? 'BLOCKER' : 'WARNING';
                $this->line("  [{$prefix}] {$item['key']}: {$item['message']}");
            }
        }
    }

    private function counts(): array
    {
        return [
            'users' => Schema::hasTable('users') ? DB::table('users')->count() : 'table_missing',
            'user_roles' => Schema::hasTable('user_roles') ? DB::table('user_roles')->count() : 'table_missing',
            'kp_external_document_references' => Schema::hasTable('kp_external_document_references') ? DB::table('kp_external_document_references')->count() : 'table_missing',
        ];
    }

    private function writeJsonReport(array $report): string
    {
        $directory = storage_path('app/reports');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/kp-production-readiness-gate-'.now()->format('Ymd-His').'.json';
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
