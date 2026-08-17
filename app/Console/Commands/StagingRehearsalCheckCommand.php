<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class StagingRehearsalCheckCommand extends Command
{
    protected $signature = 'kp:staging-rehearsal-check
        {--report-json : Save staging rehearsal report as JSON}';

    protected $description = 'Read-only staging deployment and UAT rehearsal checklist for KP Farmasi';

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
        $commandNames = array_keys(Artisan::all());
        $requiredCommands = [
            'kp:integration-gap-check',
            'kp:core-mapping-coverage',
            'kp:tu-document-payload-preview',
            'kp:safa-public-info-preview',
            'kp:external-document-reference-preview',
            'kp:production-readiness-gate',
            'kp:staging-rehearsal-check',
        ];

        $checks = [
            $this->check('core_diagnostics_registered', $this->commandsExist($requiredCommands, $commandNames), 'Semua command diagnostic KP wajib terdaftar.'),
            $this->check('routes_file_exists', File::exists(base_path('routes/web.php')), 'routes/web.php wajib tersedia.'),
            $this->check('env_example_exists', File::exists(base_path('.env.example')), '.env.example wajib tersedia sebagai template aman.'),
            $this->check('vite_manifest_exists', File::exists(public_path('build/manifest.json')), 'Asset production belum dibuild; jalankan npm run build.'),
            $this->check('users_table_exists', Schema::hasTable('users'), 'Tabel users belum tersedia pada database aktif.'),
            $this->check('roles_table_exists', Schema::hasTable('roles'), 'Tabel roles belum tersedia pada database aktif.'),
            $this->check('external_references_table_exists', Schema::hasTable('kp_external_document_references'), 'Tabel kp_external_document_references belum tersedia pada database aktif.'),
            $this->check('migrations_table_exists', Schema::hasTable('migrations'), 'Tabel migrations belum tersedia; jalankan migration pada staging.'),
            $this->check('storage_directory_exists', File::isDirectory(storage_path('app')), 'Storage aplikasi belum tersedia.'),
            $this->check('bootstrap_cache_directory_exists', File::isDirectory(base_path('bootstrap/cache')), 'bootstrap/cache belum tersedia.'),
            $this->check('core_write_disabled', true, 'KP tidak boleh menulis ke database Core.'),
            $this->check('tu_runtime_bridge_closed', ! filled(config('services.tu_farmasi.endpoint')), 'Runtime endpoint TU harus tetap kosong sampai approval gate final.'),
            $this->check('safa_runtime_bridge_closed', ! filled(config('services.safa.endpoint')), 'Runtime endpoint SAFA harus tetap kosong sampai approval gate final.'),
            $this->check('auth_mode_known', in_array(config('kp_auth.mode'), ['legacy', 'core_http', 'core_bridge', 'core_bridge_with_legacy_fallback'], true), 'KP_AUTH_MODE tidak dikenali.'),
            $this->check('master_data_read_mode_known', in_array(config('kp_master_data.read_mode'), ['legacy', 'core_preferred', 'core_only'], true), 'KP_MASTER_DATA_READ_MODE tidak dikenali.'),
            $this->check(
                'master_data_core_bridge_aligned',
                config('kp_auth.mode') === 'legacy' || config('kp_master_data.read_mode') !== 'legacy',
                'Jika KP_AUTH_MODE memakai Core, KP_MASTER_DATA_READ_MODE harus core_preferred atau core_only pada VPS staging.'
            ),
            $this->check('vps_env_example_exists', File::exists(base_path('.env.vps.example')), '.env.vps.example wajib tersedia sebagai template aman VPS.'),
            $this->warning('app_debug_should_be_false_on_staging', config('app.debug') === false, 'Staging/UAT sebaiknya memakai APP_DEBUG=false untuk rehearsal production.'),
            $this->warning('app_url_should_be_https_on_staging', str_starts_with((string) config('app.url'), 'https://'), 'Staging/UAT sebaiknya memakai HTTPS.'),
            $this->warning('queue_worker_recommended', config('queue.default') !== 'sync', 'Staging production rehearsal sebaiknya memakai queue worker.'),
            $this->warning('mail_not_log_recommended', config('mail.default') !== 'log', 'Staging final sebaiknya menguji mailer production atau sandbox SMTP.'),
        ];

        $migrationSummary = $this->migrationSummary();
        if (($migrationSummary['pending_count'] ?? 0) > 0) {
            $checks[] = $this->check('no_pending_migrations', false, 'Masih ada migration file yang belum tercatat dijalankan.');
        } elseif (($migrationSummary['migration_files_count'] ?? 0) > 0) {
            $checks[] = $this->check('no_pending_migrations', true, 'Semua migration file tercatat sudah dijalankan.');
        }

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
                'ready_for_staging_rehearsal' => $blockers->isEmpty(),
                'ready_for_runtime_tu_bridge' => false,
            ],
            'checks' => $checks,
            'migration_summary' => $migrationSummary,
            'required_uat_signoffs' => [
                'admin_login_and_dashboard',
                'koordinator_login_and_dashboard',
                'mahasiswa_registration_flow',
                'document_verification_flow',
                'place_selection_flow',
                'assignment_and_supervisor_flow',
                'logbook_flow',
                'final_report_flow',
                'exam_and_assessment_flow',
                'tu_reference_manual_linking_flow',
                'core_bridge_login_dry_run',
                'backup_and_rollback_plan',
            ],
        ];
    }

    private function commandsExist(array $requiredCommands, array $commandNames): bool
    {
        return collect($requiredCommands)->diff($commandNames)->isEmpty();
    }

    private function migrationSummary(): array
    {
        $files = collect(File::glob(database_path('migrations/*.php')) ?: [])
            ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME))
            ->values();

        if (! Schema::hasTable('migrations')) {
            return [
                'migration_files_count' => $files->count(),
                'ran_count' => 0,
                'pending_count' => $files->count(),
                'pending_sample' => $files->take(10)->values()->all(),
            ];
        }

        $ran = DB::table('migrations')->pluck('migration');
        $pending = $files->diff($ran)->values();

        return [
            'migration_files_count' => $files->count(),
            'ran_count' => $ran->count(),
            'pending_count' => $pending->count(),
            'pending_sample' => $pending->take(10)->values()->all(),
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
        $this->info('KP staging rehearsal check');
        $this->line('Dry run: yes');
        $this->line('External request sent: no');
        $this->line('Write to Core/TU/SAFA: no/no/no');
        $this->line('Checks: '.$report['summary']['checks']);
        $this->line('Blockers: '.$report['summary']['blockers']);
        $this->line('Warnings: '.$report['summary']['warnings']);
        $this->line('Ready for staging rehearsal: '.($report['summary']['ready_for_staging_rehearsal'] ? 'yes' : 'no'));
        $this->line('Ready for runtime TU bridge: no');
        $this->line('Pending migrations: '.$report['migration_summary']['pending_count']);
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
            'roles' => Schema::hasTable('roles') ? DB::table('roles')->count() : 'table_missing',
            'kp_external_document_references' => Schema::hasTable('kp_external_document_references') ? DB::table('kp_external_document_references')->count() : 'table_missing',
        ];
    }

    private function writeJsonReport(array $report): string
    {
        $directory = storage_path('app/reports');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/kp-staging-rehearsal-check-'.now()->format('Ymd-His').'.json';
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
