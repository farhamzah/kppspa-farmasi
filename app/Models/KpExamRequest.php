<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpExamRequest extends Model
{
    protected $fillable = ['kp_assignment_id', 'requested_by', 'status', 'request_note', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function assignment() { return $this->belongsTo(KpAssignment::class, 'kp_assignment_id'); }
    public function requestedBy() { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewedBy() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function exam() { return $this->hasOne(KpExam::class, 'kp_exam_request_id'); }
    public function logs() { return $this->hasMany(KpExamLog::class, 'kp_exam_request_id'); }

    public function statusLabel(): string
    {
        return [
            'draft' => 'Draf',
            'diajukan' => 'Diajukan',
            'disetujui' => 'Disetujui',
            'dijadwalkan' => 'Dijadwalkan',
            'revisi' => 'Revisi',
            'ditolak' => 'Ditolak',
            'dibatalkan' => 'Dibatalkan',
        ][$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return [
            'diajukan' => 'bg-amber-100 text-amber-800 ring-amber-200',
            'disetujui', 'dijadwalkan' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            'revisi' => 'bg-blue-100 text-blue-800 ring-blue-200',
            'ditolak', 'dibatalkan' => 'bg-red-100 text-red-800 ring-red-200',
            'draft' => 'bg-slate-100 text-slate-700 ring-slate-200',
        ][$this->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
    }

    public function canBeScheduled(): bool
    {
        return in_array($this->status, ['diajukan', 'disetujui'], true);
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, ['ditolak', 'dibatalkan'], true);
    }
}
