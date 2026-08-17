<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PkpaHypercareStatusCommand extends Command
{
    protected $signature = 'pkpa:hypercare-status {--json : Output JSON}';

    protected $description = 'Read-only MY PKPA hypercare health summary.';

    public function handle(): int
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1') !== []),
            'private_storage' => $this->check(fn () => is_dir(storage_path('app/private')) && is_writable(storage_path('app/private'))),
            'queue_tables' => $this->check(fn () => Schema::hasTable('jobs') && Schema::hasTable('failed_jobs')),
            'failed_jobs' => $this->check(fn () => Schema::hasTable('failed_jobs') && DB::table('failed_jobs')->count() === 0),
            'document_jobs_failed' => $this->check(fn () => ! Schema::hasTable('pkpa_document_generation_jobs') || DB::table('pkpa_document_generation_jobs')->where('status', 'failed')->count() === 0),
            'document_orphans_possible' => $this->check(fn () => Schema::hasTable('pkpa_generated_document_versions')),
            'integrity_tables' => $this->check(fn () => Schema::hasTable('pkpa_enrollments') && Schema::hasTable('pkpa_document_types')),
            'app_debug_off_when_production' => $this->check(fn () => ! app()->environment('production') || config('app.debug') === false),
        ];

        $failed = collect($checks)->filter(fn (array $check) => $check['status'] !== 'ok')->count();
        $payload = [
            'status' => $failed === 0 ? 'ok' : 'attention',
            'checked_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'queue_connection' => config('queue.default'),
            'mail_mailer' => config('mail.default'),
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('MY PKPA Hypercare Status: '.$payload['status']);
            foreach ($checks as $name => $check) {
                $this->line("- {$name}: {$check['status']}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function check(callable $callback): array
    {
        try {
            return ['status' => $callback() ? 'ok' : 'attention'];
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'message' => str($exception->getMessage())
                    ->replaceMatches('/(password|token|secret|authorization)[^,\s]*/i', '[redacted]')
                    ->limit(160)
                    ->toString(),
            ];
        }
    }
}
