<?php

namespace App\Console\Commands;

use App\Models\PkpaGeneratedDocumentVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PkpaDocumentOrphanAuditCommand extends Command
{
    protected $signature = 'pkpa:document-orphan-audit {--json : Output JSON}';

    protected $description = 'Dry-run audit for missing PKPA generated document files.';

    public function handle(): int
    {
        $missing = [];
        PkpaGeneratedDocumentVersion::query()->select(['id', 'disk', 'path'])->chunkById(100, function ($versions) use (&$missing) {
            foreach ($versions as $version) {
                if (! Storage::disk($version->disk)->exists($version->path)) {
                    $missing[] = ['id' => $version->id, 'disk' => $version->disk, 'path' => $version->path];
                }
            }
        });

        $payload = [
            'mode' => 'dry-run',
            'checked_versions' => PkpaGeneratedDocumentVersion::count(),
            'missing_count' => count($missing),
            'missing' => $missing,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('MY PKPA Document Orphan Audit - dry-run');
        $this->line('Checked versions: '.$payload['checked_versions']);
        $this->line('Missing files: '.$payload['missing_count']);

        return self::SUCCESS;
    }
}
