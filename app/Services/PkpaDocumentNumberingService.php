<?php

namespace App\Services;

use App\Models\PkpaDocumentNumberingRule;
use App\Models\PkpaDocumentType;
use App\Models\PkpaGeneratedDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaDocumentNumberingService
{
    public function allocate(PkpaGeneratedDocument $document): string
    {
        if (filled($document->document_number)) {
            return $document->document_number;
        }

        return DB::transaction(function () use ($document) {
            $document = PkpaGeneratedDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if (filled($document->document_number)) {
                return $document->document_number;
            }

            $rule = $this->ruleFor($document->type, $document->pkpa_program_id);
            $rule->increment('current_sequence');
            $rule->refresh();

            $number = $this->format($rule, $document);
            if (PkpaGeneratedDocument::where('document_number', $number)->whereKeyNot($document->id)->exists()) {
                throw ValidationException::withMessages(['document_number' => 'Nomor dokumen sudah digunakan.']);
            }

            $document->update([
                'document_number' => $number,
                'document_date' => $document->document_date ?? now()->toDateString(),
            ]);

            return $number;
        });
    }

    public function ruleFor(PkpaDocumentType $type, ?int $programId): PkpaDocumentNumberingRule
    {
        $rule = PkpaDocumentNumberingRule::query()
            ->where('pkpa_document_type_id', $type->id)
            ->where('status', 'active')
            ->where(function ($query) use ($programId) {
                $query->where('pkpa_program_id', $programId)->orWhereNull('pkpa_program_id');
            })
            ->orderByRaw('pkpa_program_id is null')
            ->first();

        if (! $rule) {
            throw ValidationException::withMessages(['numbering_rule' => 'Aturan nomor dokumen aktif belum tersedia.']);
        }

        return $rule;
    }

    private function format(PkpaDocumentNumberingRule $rule, PkpaGeneratedDocument $document): string
    {
        $date = $document->document_date ?? now();
        $values = [
            '{sequence}' => str_pad((string) $rule->current_sequence, 4, '0', STR_PAD_LEFT),
            '{type}' => $document->type?->code,
            '{program}' => $document->program?->code,
            '{year}' => $date->format($rule->year_format ?: 'Y'),
            '{month}' => $date->format($rule->month_format ?: 'm'),
            '{prefix}' => $rule->prefix,
            '{suffix}' => $rule->suffix,
        ];

        return trim(strtr($rule->pattern, $values), " \t\n\r\0\x0B/");
    }
}
