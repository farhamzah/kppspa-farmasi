<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PkpaRotationComponentScore extends Model
{
    protected $fillable = [
        'pkpa_rotation_assessment_id', 'pkpa_assessment_component_id', 'assessor_assignment_id',
        'component_code_snapshot', 'component_name_snapshot', 'component_type_snapshot',
        'weight_percentage_snapshot', 'calculation_method_snapshot', 'raw_score', 'normalized_score',
        'weighted_score', 'status', 'comments', 'submitted_at', 'locked_at', 'calculated_at',
        'source_summary', 'row_version', 'created_by_core_user_id', 'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'weight_percentage_snapshot' => 'decimal:4',
            'raw_score' => 'decimal:4',
            'normalized_score' => 'decimal:4',
            'weighted_score' => 'decimal:4',
            'submitted_at' => 'datetime',
            'locked_at' => 'datetime',
            'calculated_at' => 'datetime',
            'source_summary' => 'array',
            'row_version' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationAssessment::class, 'pkpa_rotation_assessment_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PkpaAssessmentComponent::class, 'pkpa_assessment_component_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationAssessmentAssessor::class, 'assessor_assignment_id');
    }

    public function rubricScores(): HasMany
    {
        return $this->hasMany(PkpaRotationRubricScore::class, 'pkpa_rotation_component_score_id');
    }
}
