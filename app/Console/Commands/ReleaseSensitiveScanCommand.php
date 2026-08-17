<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ReleaseSensitiveScanCommand extends Command
{
    protected $signature = 'kp:release-sensitive-scan
        {--show-files : Show scanned file list}';

    protected $description = 'Scan release candidate files for sensitive paths, generated folders, and obvious secrets';

    private const FORBIDDEN_PATHS = [
        '.env',
        '.env.backup',
        '.env.production',
        'auth.json',
        'vendor/',
        'node_modules/',
        'public/build/',
        'public/hot',
        'public/storage/',
        'storage/app/public/',
        'storage/app/private/',
        'storage/framework/cache/',
        'storage/framework/sessions/',
        'storage/framework/views/',
        'storage/logs/',
    ];

    private const TEXT_EXTENSIONS = [
        'php',
        'blade.php',
        'md',
        'txt',
        'env',
        'envcheck',
        'example',
        'xml',
        'json',
        'yml',
        'yaml',
        'js',
        'css',
        'html',
    ];

    public function handle(): int
    {
        $files = $this->releaseCandidateFiles();
        $findings = [];

        foreach ($files as $file) {
            $normalized = $this->normalizePath($file);

            if ($this->isAllowedPlaceholderFile($normalized)) {
                continue;
            }

            foreach (self::FORBIDDEN_PATHS as $forbiddenPath) {
                if ($normalized === rtrim($forbiddenPath, '/') || str_starts_with($normalized, $forbiddenPath)) {
                    $findings[] = [
                        'file' => $normalized,
                        'type' => 'forbidden_path',
                        'message' => "File/folder {$forbiddenPath} tidak boleh ikut release.",
                    ];
                }
            }

            if ($this->isTextFile($normalized) && ! str_starts_with($normalized, 'tests/')) {
                foreach ($this->contentFindings($normalized) as $finding) {
                    $findings[] = $finding;
                }
            }
        }

        $this->info('KP release sensitive scan');
        $this->line('Files scanned: '.count($files));
        $this->line('Findings: '.count($findings));

        if ($this->option('show-files')) {
            $this->newLine();
            $this->line('Files:');
            foreach ($files as $file) {
                $this->line('  '.$this->normalizePath($file));
            }
        }

        if ($findings) {
            $this->newLine();
            $this->line('Findings:');
            foreach ($findings as $finding) {
                $this->error("  [{$finding['type']}] {$finding['file']}: {$finding['message']}");
            }

            return self::FAILURE;
        }

        $this->line('Release candidate looks free of tracked sensitive files and obvious secrets.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function releaseCandidateFiles(): array
    {
        $output = [];
        $code = 1;
        exec('git -C '.escapeshellarg(base_path()).' ls-files --cached --others --exclude-standard', $output, $code);

        if ($code === 0) {
            $rootFiles = collect(File::files(base_path()))
                ->map(fn ($file) => str_replace('\\', '/', $file->getFilename()))
                ->reject(fn (string $file) => str_starts_with($file, '.env') && $file !== '.env.production.example')
                ->reject(fn (string $file) => in_array($file, ['.phpunit.result.cache'], true));

            return collect($output)
                ->merge($rootFiles)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return collect(File::allFiles(base_path()))
            ->map(fn ($file) => str_replace('\\', '/', $file->getRelativePathname()))
            ->reject(fn (string $file) => str_starts_with($file, '.git/'))
            ->values()
            ->all();
    }

    /**
     * @return list<array{file:string,type:string,message:string}>
     */
    private function contentFindings(string $file): array
    {
        $path = base_path(str_replace('/', DIRECTORY_SEPARATOR, $file));

        if (! File::exists($path) || File::size($path) > 512 * 1024) {
            return [];
        }

        $contents = (string) File::get($path);
        $findings = [];

        if (preg_match('/-----BEGIN (RSA |OPENSSH |EC |DSA )?PRIVATE KEY-----/', $contents)) {
            $findings[] = [
                'file' => $file,
                'type' => 'private_key',
                'message' => 'Private key block tidak boleh ikut release.',
            ];
        }

        foreach (preg_split('/\R/', $contents) ?: [] as $lineNumber => $line) {
            if ($this->looksLikeSecretAssignment($line)) {
                $findings[] = [
                    'file' => $file,
                    'type' => 'secret_assignment',
                    'message' => 'Kemungkinan secret pada baris '.($lineNumber + 1).'.',
                ];
            }
        }

        return $findings;
    }

    private function looksLikeSecretAssignment(string $line): bool
    {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//')) {
            return false;
        }

        if (preg_match('/password\s*=\s*Hash::make\(Str::random\(/i', $line)) {
            return false;
        }

        if (! preg_match('/\b(db_password|password|passwd|secret|token|private_key|access_key|api_key|client_secret)\b\s*[:=]\s*["\']?([^"\'\s#]+)["\']?/i', $line, $matches)) {
            return false;
        }

        $value = trim($matches[2] ?? '');

        if ($value === '' || in_array(strtolower($value), ['null', 'false', 'true', 'changeme', 'placeholder', 'example'], true)) {
            return false;
        }

        return strlen($value) >= 16 || preg_match('/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/', $value);
    }

    private function isTextFile(string $file): bool
    {
        foreach (self::TEXT_EXTENSIONS as $extension) {
            if (str_ends_with($file, '.'.$extension) || str_ends_with($file, $extension)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $file): string
    {
        return ltrim(str_replace('\\', '/', $file), './');
    }

    private function isAllowedPlaceholderFile(string $file): bool
    {
        return in_array($file, [
            'storage/app/private/.gitignore',
            'storage/app/public/.gitignore',
            'storage/framework/cache/.gitignore',
            'storage/framework/cache/data/.gitignore',
            'storage/framework/sessions/.gitignore',
            'storage/framework/views/.gitignore',
            'storage/logs/.gitignore',
        ], true);
    }
}
