<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpFinalReport extends Model
{
    protected $fillable = [
        'kp_assignment_id',
        'current_version',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'final_document_url',
        'final_document_label',
        'approved_at',
        'internal_review_status',
        'internal_reviewed_by',
        'internal_reviewed_at',
        'internal_review_note',
        'field_review_status',
        'field_reviewed_by',
        'field_reviewed_at',
        'field_review_note',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'internal_reviewed_at' => 'datetime',
            'field_reviewed_at' => 'datetime',
        ];
    }

    public function assignment() { return $this->belongsTo(KpAssignment::class, 'kp_assignment_id'); }
    public function reviewedBy() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function internalReviewedBy() { return $this->belongsTo(User::class, 'internal_reviewed_by'); }
    public function fieldReviewedBy() { return $this->belongsTo(User::class, 'field_reviewed_by'); }
    public function files() { return $this->hasMany(KpFinalReportFile::class, 'kp_final_report_id'); }
    public function logs() { return $this->hasMany(KpFinalReportLog::class, 'kp_final_report_id'); }
    public function latestFile() { return $this->hasOne(KpFinalReportFile::class, 'kp_final_report_id')->latestOfMany('version'); }

    public function statusLabel(): string
    {
        return [
            'draft' => 'Draft',
            'menunggu_review' => 'Menunggu Review',
            'revisi' => 'Revisi',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
        ][$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return [
            'draft' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'menunggu_review' => 'bg-amber-100 text-amber-800 ring-amber-200',
            'revisi' => 'bg-blue-100 text-blue-800 ring-blue-200',
            'disetujui' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            'ditolak' => 'bg-red-100 text-red-800 ring-red-200',
        ][$this->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
    }

    public function canBeEditedByStudent(): bool
    {
        return in_array($this->status, ['draft', 'revisi', 'ditolak'], true);
    }

    public function canBeSubmitted(): bool
    {
        return $this->canBeEditedByStudent() && ($this->files()->exists() || filled($this->final_document_url));
    }

    public function isApproved(): bool
    {
        return $this->status === 'disetujui'
            && $this->internal_review_status === 'disetujui'
            && $this->field_review_status === 'disetujui';
    }

    public function internalReviewStatusLabel(): string
    {
        return $this->reviewStatusLabel($this->internal_review_status);
    }

    public function fieldReviewStatusLabel(): string
    {
        return $this->reviewStatusLabel($this->field_review_status);
    }

    public function progressLabel(): string
    {
        return match ($this->status) {
            'menunggu_review' => 'Menunggu review pembimbing',
            'revisi' => 'Perlu revisi laporan',
            'disetujui' => 'Siap pengajuan sidang',
            'ditolak' => 'Laporan ditolak',
            default => 'Draft laporan',
        };
    }

    private function reviewStatusLabel(?string $status): string
    {
        return [
            'pending' => 'Belum Review',
            'disetujui' => 'Disetujui',
            'revisi' => 'Revisi',
            'ditolak' => 'Ditolak',
        ][$status ?: 'pending'] ?? ucfirst((string) $status);
    }
}
