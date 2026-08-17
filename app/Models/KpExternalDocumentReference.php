<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KpExternalDocumentReference extends Model
{
    public const STATUSES = ['draft', 'pending_external', 'linked', 'failed', 'archived'];

    public const SENSITIVE_REFERENCE_URL_MARKERS = [
        'access_token',
        'file_path',
        'signature',
        'storage/app',
        '/storage/',
        '/private/',
        'password',
        'private',
        'secret',
        'signed',
        'storage',
        'token',
        'c:\\',
        'e:\\',
    ];

    protected $fillable = [
        'uuid',
        'source_app',
        'external_app',
        'document_type',
        'service_code',
        'source_module',
        'source_reference_type',
        'source_reference_id',
        'external_document_id',
        'external_document_number',
        'external_status',
        'reference_url',
        'file_hash',
        'metadata',
        'last_payload_snapshot',
        'last_error',
        'synced_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_payload_snapshot' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $reference): void {
            $reference->uuid ??= (string) Str::uuid();
            $reference->source_app ??= (string) config('core_farmasi.app_code', 'kppspa-farmasi');
            $reference->external_app ??= 'tu-farmasi';
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isSafeReferenceUrl(): bool
    {
        if (! $this->reference_url) {
            return true;
        }

        return ! self::hasUnsafeReferenceUrl($this->reference_url);
    }

    public static function hasUnsafeReferenceUrl(?string $referenceUrl): bool
    {
        if (! $referenceUrl) {
            return false;
        }

        $url = strtolower(trim($referenceUrl));

        if (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://')) {
            return true;
        }

        foreach (self::SENSITIVE_REFERENCE_URL_MARKERS as $marker) {
            if (str_contains($url, $marker)) {
                return true;
            }
        }

        return (bool) preg_match('/^[a-z]:[\\\\\/]/i', $referenceUrl);
    }

    public function statusLabel(): string
    {
        return [
            'draft' => 'Draf lokal',
            'pending_external' => 'Menunggu TU',
            'linked' => 'Tertaut',
            'failed' => 'Gagal',
            'archived' => 'Diarsipkan',
        ][$this->external_status] ?? ucfirst((string) $this->external_status);
    }

    public function statusBadgeClass(): string
    {
        return [
            'draft' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'pending_external' => 'bg-amber-50 text-amber-700 ring-amber-100',
            'linked' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'failed' => 'bg-rose-50 text-rose-700 ring-rose-100',
            'archived' => 'bg-zinc-100 text-zinc-700 ring-zinc-200',
        ][$this->external_status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
    }
}
