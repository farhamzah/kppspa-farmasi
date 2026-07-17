<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PkpaAcademicFileService
{
    public function validateFile(UploadedFile $file, ?array $allowedMimes = null, ?int $maxKb = null): void
    {
        $allowedMimes ??= config('my_pspa.academic_allowed_mime_types', []);
        $maxKb ??= (int) config('my_pspa.academic_file_max_size_kb', 5120);
        $blockedExtensions = ['exe', 'bat', 'cmd', 'com', 'js', 'php', 'sh', 'msi'];

        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages(['file' => 'Ukuran file akademik melebihi batas.']);
        }
        if (in_array(strtolower($file->getClientOriginalExtension()), $blockedExtensions, true)) {
            throw ValidationException::withMessages(['file' => 'Jenis file executable tidak diizinkan.']);
        }
        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            throw ValidationException::withMessages(['file' => 'Tipe file akademik tidak diizinkan.']);
        }
    }

    public function store(UploadedFile $file, string $directory, ?array $allowedMimes = null, ?int $maxKb = null): array
    {
        $this->validateFile($file, $allowedMimes, $maxKb);
        $disk = config('my_pspa.academic_file_disk', 'local');
        $storedName = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs(trim($directory, '/'), $storedName, $disk);

        return [
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedName,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    public function download(string $disk, string $path, ?string $filename = null)
    {
        if (str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            throw ValidationException::withMessages(['file' => 'Path file tidak valid.']);
        }

        return Storage::disk($disk)->download($path, $filename);
    }
}
