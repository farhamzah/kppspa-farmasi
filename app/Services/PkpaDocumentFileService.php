<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class PkpaDocumentFileService
{
    public const OFFICE_MIMES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function validateUpload(UploadedFile $file): void
    {
        $name = $file->getClientOriginalName();
        $lower = strtolower($name);
        if (preg_match('/\.(php|phar|phtml|exe|bat|cmd|js|vbs|sh)(\.|$)/', $lower)) {
            throw ValidationException::withMessages(['file' => 'File executable tidak diizinkan.']);
        }
        if (preg_match('/\.(pdf|docx|xlsx)\.[a-z0-9]+$/', $lower)) {
            throw ValidationException::withMessages(['file' => 'Nama file berlapis tidak diizinkan.']);
        }
        $this->validateBytes(file_get_contents($file->getRealPath()), $file->getClientOriginalExtension());
    }

    public function validateBytes(string $bytes, string $format): void
    {
        $format = strtolower($format);
        if ($format === 'pdf' && ! str_starts_with($bytes, '%PDF')) {
            throw ValidationException::withMessages(['file' => 'PDF tidak valid.']);
        }
        if (in_array($format, ['docx', 'xlsx'], true)) {
            $tmp = tempnam(sys_get_temp_dir(), 'pkpa-office-');
            file_put_contents($tmp, $bytes);
            $zip = new ZipArchive();
            $opened = $zip->open($tmp) === true;
            $hasContentTypes = $opened && $zip->locateName('[Content_Types].xml') !== false;
            if ($opened) {
                $zip->close();
            }
            @unlink($tmp);
            if (! $hasContentTypes) {
                throw ValidationException::withMessages(['file' => strtoupper($format).' tidak valid sebagai Office Open XML.']);
            }
        }
    }

    public function safeDownloadName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'dokumen';

        return str($name)->replace('..', '.')->limit(140, '')->toString();
    }

    public function sanitizeSpreadsheetCell(mixed $value): string
    {
        $text = (string) $value;
        return preg_match('/^[=+\-@]/', $text) ? "'".$text : $text;
    }
}
