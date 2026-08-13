<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpDocument extends Model
{
    protected $fillable = [
        'kp_registration_id',
        'kp_document_requirement_id',
        'original_filename',
        'file_path',
        'file_disk',
        'file_mime',
        'file_size',
        'status',
        'review_note',
        'uploaded_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function registration()
    {
        return $this->belongsTo(KpRegistration::class, 'kp_registration_id');
    }

    public function requirement()
    {
        return $this->belongsTo(KpDocumentRequirement::class, 'kp_document_requirement_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'menunggu' => 'Menunggu Pemeriksaan',
            'disetujui' => 'Disetujui',
            'revisi' => 'Revisi',
            'ditolak' => 'Ditolak',
            default => 'Belum Unggah',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'menunggu' => 'bg-sky-50 text-sky-700',
            'disetujui' => 'bg-emerald-50 text-emerald-700',
            'revisi' => 'bg-amber-50 text-amber-700',
            'ditolak' => 'bg-rose-50 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function humanFileSize(): string
    {
        if (! $this->file_size) {
            return '-';
        }

        return $this->file_size >= 1048576
            ? round($this->file_size / 1048576, 2).' MB'
            : round($this->file_size / 1024, 2).' KB';
    }
}
