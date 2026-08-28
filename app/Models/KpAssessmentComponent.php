<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpAssessmentComponent extends Model
{
    protected $fillable = ['kp_period_id', 'assessor_type', 'component_name', 'description', 'weight', 'max_score', 'sort_order', 'is_required', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'max_score' => 'decimal:2', 'is_required' => 'boolean'];
    }

    public function period() { return $this->belongsTo(KpPeriod::class, 'kp_period_id'); }
    public function scores() { return $this->hasMany(KpScore::class, 'kp_assessment_component_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }

    public function assessorTypeLabel(): string
    {
        return match ($this->assessor_type) {
            'pembimbing_dalam' => 'Pembimbing Dalam',
            'pembimbing_lapangan' => 'Preseptor',
            'penguji' => 'Penguji',
            default => ucfirst((string) $this->assessor_type),
        };
    }

    public function statusLabel(): string
    {
        return $this->status === 'aktif' ? 'Aktif' : 'Nonaktif';
    }

    public function statusBadgeClass(): string
    {
        return $this->status === 'aktif' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600';
    }
}
