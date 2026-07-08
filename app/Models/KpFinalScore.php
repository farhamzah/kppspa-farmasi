<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpFinalScore extends Model
{
    protected $fillable = ['kp_assignment_id', 'final_score', 'final_grade', 'attendance_score_override', 'attendance_note', 'attendance_overridden_by', 'attendance_overridden_at', 'status', 'calculated_at', 'finalized_by', 'finalized_at', 'published_at', 'note'];

    protected function casts(): array
    {
        return ['final_score' => 'decimal:2', 'attendance_score_override' => 'decimal:2', 'calculated_at' => 'datetime', 'attendance_overridden_at' => 'datetime', 'finalized_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function assignment() { return $this->belongsTo(KpAssignment::class, 'kp_assignment_id'); }
    public function finalizedBy() { return $this->belongsTo(User::class, 'finalized_by'); }
    public function attendanceOverriddenBy() { return $this->belongsTo(User::class, 'attendance_overridden_by'); }
    public function logs() { return $this->hasMany(KpScoreLog::class, 'kp_final_score_id'); }

    public function statusLabel(): string
    {
        return ['draft' => 'Draft', 'calculated' => 'Calculated', 'final' => 'Final', 'locked' => 'Locked', 'published' => 'Published'][$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'published' => 'bg-emerald-50 text-emerald-700',
            'locked', 'final' => 'bg-cyan-50 text-cyan-700',
            'calculated' => 'bg-sky-50 text-sky-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function gradeBadgeClass(): string
    {
        return match ($this->final_grade) {
            'A' => 'bg-emerald-100 text-emerald-800',
            'B' => 'bg-cyan-100 text-cyan-800',
            'C' => 'bg-amber-100 text-amber-800',
            'D' => 'bg-orange-100 text-orange-800',
            'E' => 'bg-rose-100 text-rose-800',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function isVisibleToStudent(): bool
    {
        return $this->status === 'published';
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['locked', 'published'], true);
    }
}
