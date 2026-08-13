<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpScore extends Model
{
    protected $fillable = ['kp_assignment_id', 'kp_exam_id', 'kp_assessment_component_id', 'assessor_user_id', 'assessor_type', 'score', 'weighted_score', 'note', 'status', 'submitted_at', 'locked_at'];

    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'weighted_score' => 'decimal:2', 'submitted_at' => 'datetime', 'locked_at' => 'datetime'];
    }

    public function assignment() { return $this->belongsTo(KpAssignment::class, 'kp_assignment_id'); }
    public function exam() { return $this->belongsTo(KpExam::class, 'kp_exam_id'); }
    public function component() { return $this->belongsTo(KpAssessmentComponent::class, 'kp_assessment_component_id'); }
    public function assessor() { return $this->belongsTo(User::class, 'assessor_user_id'); }

    public function statusLabel(): string
    {
        return ['draft' => 'Draf', 'submitted' => 'Dikirim', 'locked' => 'Dikunci'][$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return ['draft' => 'bg-slate-100 text-slate-700', 'submitted' => 'bg-sky-50 text-sky-700', 'locked' => 'bg-emerald-50 text-emerald-700'][$this->status] ?? 'bg-slate-100 text-slate-700';
    }

    public function calculateWeightedScore(): float
    {
        $this->loadMissing('component');

        return round(((float) $this->score * (float) $this->component->weight) / 100, 2);
    }
}
