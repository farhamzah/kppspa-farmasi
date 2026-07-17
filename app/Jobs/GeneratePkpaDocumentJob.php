<?php

namespace App\Jobs;

use App\Models\PkpaDocumentGenerationJob;
use App\Models\PkpaGeneratedDocument;
use App\Models\User;
use App\Services\PkpaDocumentGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GeneratePkpaDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $documentId,
        public int $actorId,
        public array $formats,
    ) {
        $this->afterCommit();
    }

    public function handle(PkpaDocumentGenerationService $service): void
    {
        $document = PkpaGeneratedDocument::findOrFail($this->documentId);
        $job = PkpaDocumentGenerationJob::where('generation_key', $document->generation_key)->first();
        $job?->update(['status' => 'running', 'started_at' => now(), 'attempt_count' => ($job->attempt_count ?? 0) + 1]);

        $service->generate($document, $this->formats, User::findOrFail($this->actorId));
    }

    public function failed(Throwable $exception): void
    {
        $document = PkpaGeneratedDocument::find($this->documentId);
        $message = str($exception->getMessage())->replaceMatches('/(password|token|secret|authorization)[^,\s]*/i', '[redacted]')->limit(500)->toString();
        if ($document) {
            $document->update(['status' => 'failed']);
            PkpaDocumentGenerationJob::where('generation_key', $document->generation_key)->update([
                'status' => 'failed',
                'failure_message' => $message,
                'finished_at' => now(),
            ]);
        }
    }
}
