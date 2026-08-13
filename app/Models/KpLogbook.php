<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpLogbook extends Model
{
    protected $fillable = [
        'kp_assignment_id',
        'activity_date',
        'start_time',
        'end_time',
        'activity_title',
        'activity_description',
        'learning_outcome',
        'obstacle',
        'solution',
        'evidence_original_filename',
        'evidence_path',
        'evidence_disk',
        'evidence_mime',
        'evidence_size',
        'evidence_url',
        'evidence_url_label',
        'status',
        'submitted_at',
        'validated_by',
        'validated_at',
        'validation_note',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(KpAssignment::class, 'kp_assignment_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(KpLogbookComment::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(KpLogbookLog::class);
    }

    public function statusLabel(): string
    {
        return [
            'draft' => 'Draf',
            'menunggu_validasi' => 'Menunggu Validasi',
            'disetujui' => 'Disetujui',
            'revisi' => 'Revisi',
            'ditolak' => 'Ditolak',
        ][$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return [
            'draft' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'menunggu_validasi' => 'bg-amber-100 text-amber-800 ring-amber-200',
            'disetujui' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            'revisi' => 'bg-blue-100 text-blue-800 ring-blue-200',
            'ditolak' => 'bg-red-100 text-red-800 ring-red-200',
        ][$this->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
    }

    public function canBeEditedByStudent(): bool
    {
        return in_array($this->status, ['draft', 'revisi'], true);
    }

    public function canBeSubmitted(): bool
    {
        return in_array($this->status, ['draft', 'revisi'], true);
    }

    public function canBeReviewed(): bool
    {
        return $this->status === 'menunggu_validasi';
    }

    public function hasEvidence(): bool
    {
        return $this->hasEvidenceFile() || $this->hasEvidenceLink();
    }

    public function hasEvidenceFile(): bool
    {
        return filled($this->evidence_path);
    }

    public function hasEvidenceLink(): bool
    {
        return filled($this->evidence_url);
    }

    public function evidenceLabel(): string
    {
        return $this->evidence_url_label
            ?: $this->evidence_original_filename
            ?: 'Bukti kegiatan';
    }

    public function evidenceExternalDownloadUrl(): ?string
    {
        if (! $this->hasEvidenceLink()) {
            return null;
        }

        $url = (string) $this->evidence_url;
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

    public function humanEvidenceSize(): string
    {
        if (! $this->evidence_size) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->evidence_size;
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return round($size, 1).' '.$units[$index];
    }

    public function activityDurationLabel(): string
    {
        if (! $this->start_time || ! $this->end_time) {
            return '-';
        }

        return substr((string) $this->start_time, 0, 5).' - '.substr((string) $this->end_time, 0, 5);
    }
}
