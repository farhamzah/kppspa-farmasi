<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpReportGuidanceLog extends Model
{
    protected $fillable = [
        'kp_assignment_id',
        'guidance_date',
        'topic',
        'student_note',
        'status',
        'submitted_at',
        'validated_by',
        'validated_at',
        'validation_note',
    ];

    protected $casts = [
        'guidance_date' => 'date',
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
}
