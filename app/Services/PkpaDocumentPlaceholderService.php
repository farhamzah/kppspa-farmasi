<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class PkpaDocumentPlaceholderService
{
    public const PLACEHOLDERS = [
        'program.name',
        'program.code',
        'program.academic_year',
        'publication.number',
        'student.name',
        'student.npm',
        'student.group',
        'rotation.domain',
        'rotation.option',
        'rotation.site',
        'rotation.start_date',
        'rotation.end_date',
        'internal_supervisor.name',
        'field_supervisor.name',
        'final_grade.score',
        'graduation.decision',
        'generated_document.number',
        'generated_document.date',
    ];

    public function validateContent(?string $content, ?array $allowed = null): array
    {
        $content ??= '';
        $allowed ??= self::PLACEHOLDERS;
        preg_match_all('/{{\s*([a-zA-Z0-9_.]+)\s*}}/', $content, $matches);
        $found = array_values(array_unique($matches[1] ?? []));
        $unknown = array_values(array_diff($found, $allowed));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'template_content' => 'Placeholder tidak dikenal: '.implode(', ', $unknown),
            ]);
        }

        if (preg_match('/<\?(php)?|@php|eval\s*\(|shell_exec|passthru|proc_open/i', $content)) {
            throw ValidationException::withMessages([
                'template_content' => 'Template tidak boleh berisi kode eksekusi.',
            ]);
        }

        return $found;
    }

    public function render(?string $content, array $context, string $fallback = '-'): string
    {
        $content ??= $this->defaultContent();
        $this->validateContent($content);

        return preg_replace_callback('/{{\s*([a-zA-Z0-9_.]+)\s*}}/', function (array $match) use ($context, $fallback) {
            $value = data_get($context, $match[1], $fallback);
            if (is_array($value) || is_object($value)) {
                $value = $fallback;
            }

            return e((string) ($value ?? $fallback));
        }, $content);
    }

    public function defaultContent(): string
    {
        return implode("\n", [
            'Dokumen Internal MY PKPA',
            'Program: {{ program.name }}',
            'Mahasiswa: {{ student.name }}',
            'NPM: {{ student.npm }}',
            'Wahana: {{ rotation.domain }}',
            'Tempat: {{ rotation.site }}',
            'Nomor: {{ generated_document.number }}',
            'Tanggal: {{ generated_document.date }}',
        ]);
    }
}
