<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PkpaRotationAssessmentAssessor extends Model
{
    protected $fillable = [
        'pkpa_rotation_assessment_id', 'pkpa_assessment_component_id', 'assessor_type',
        'core_user_id', 'name_snapshot', 'role_snapshot', 'source_rotation_supervisor_history_id',
        'status', 'assigned_at', 'submitted_at', 'locked_at', 'created_by_core_user_id', 'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'submitted_at' => 'datetime', 'locked_at' => 'datetime'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationAssessment::class, 'pkpa_rotation_assessment_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PkpaAssessmentComponent::class, 'pkpa_assessment_component_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(PkpaRotationComponentScore::class, 'assessor_assignment_id');
    }
}
