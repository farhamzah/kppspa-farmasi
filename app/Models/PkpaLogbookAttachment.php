<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaLogbookAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_logbook_entry_id',
        'attachment_type',
        'original_filename',
        'stored_filename',
        'disk',
        'path',
        'mime_type',
        'file_size',
        'checksum',
        'external_url',
        'link_label',
        'status',
        'uploaded_by_core_user_id',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function logbookEntry(): BelongsTo
    {
        return $this->belongsTo(PkpaLogbookEntry::class, 'pkpa_logbook_entry_id');
    }

    public function isExternalLink(): bool
    {
        return $this->attachment_type === 'external_link' && filled($this->external_url);
    }

    public function isFileUpload(): bool
    {
        return ! $this->isExternalLink();
    }

    public function displayLabel(): string
    {
        return $this->link_label
            ?: $this->original_filename
            ?: 'Bukti logbook';
    }

    public function previewUrl(): ?string
    {
        return $this->isExternalLink() ? (string) $this->external_url : null;
    }

    public function externalDownloadUrl(): ?string
    {
        if (! $this->isExternalLink()) {
            return null;
        }

        $url = (string) $this->external_url;
        $fileId = null;

        if (preg_match('~/d/([^/]+)~', $url, $matches) === 1) {
            $fileId = $matches[1];
        } elseif (preg_match('~[?&]id=([^&]+)~', $url, $matches) === 1) {
            $fileId = $matches[1];
        }

        return $fileId
            ? 'https://drive.google.com/uc?export=download&id='.$fileId
            : $url;
    }

    public function humanFileSize(): string
    {
        if (! $this->file_size) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->file_size;
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return round($size, 1).' '.$units[$index];
    }
}
