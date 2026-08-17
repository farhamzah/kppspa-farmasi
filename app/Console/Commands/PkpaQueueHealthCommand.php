<?php

namespace App\Console\Commands;

use App\Models\PkpaDocumentGenerationJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PkpaQueueHealthCommand extends Command
{
    protected $signature = 'pkpa:queue-health {--json : Output JSON}';

    protected $description = 'Report safe queue and PKPA document generation job health.';

    public function handle(): int
    {
        $payload = [
            'queue_connection' => config('queue.default'),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'document_jobs' => [
                'queued' => PkpaDocumentGenerationJob::where('status', 'queued')->count(),
                'running' => PkpaDocumentGenerationJob::where('status', 'running')->count(),
                'failed' => PkpaDocumentGenerationJob::where('status', 'failed')->count(),
                'finished' => PkpaDocumentGenerationJob::where('status', 'finished')->count(),
            ],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('MY PKPA Queue Health');
        $this->line('Queue connection: '.$payload['queue_connection']);
        $this->line('Failed jobs: '.$payload['failed_jobs']);
        foreach ($payload['document_jobs'] as $status => $count) {
            $this->line("Document jobs {$status}: {$count}");
        }

        return self::SUCCESS;
    }
}
