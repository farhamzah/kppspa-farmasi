<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PkpaRotationGradeResult extends Model
{
    protected $fillable = [
        'pkpa_rotation_assessment_id', 'pkpa_rotation_run_id', 'pkpa_enrollment_id',
        'pkpa_enrollment_requirement_id', 'practice_domain_id', 'assessment_scheme_id',
        'raw_total_score', 'moderated_total_score', 'final_score', 'maximum_score',
        'minimum_passing_score_snapshot', 'result_status', 'calculation_snapshot',
        'component_snapshot', 'finalized_at', 'released_at', 'finalized_by_core_user_id',
        'released_by_core_user_id', 'row_version',
    ];

    protected function casts(): array
    {
        return [
            'raw_total_score' => 'decimal:4',
            'moderated_total_score' => 'decimal:4',
            'final_score' => 'decimal:4',
            'maximum_score' => 'decimal:4',
            'minimum_passing_score_snapshot' => 'decimal:4',
            'calculation_snapshot' => 'array',
            'component_snapshot' => 'array',
            'finalized_at' => 'datetime',
            'released_at' => 'datetime',
            'row_version' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationAssessment::class, 'pkpa_rotation_assessment_id');
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(PkpaGradeRelease::class, 'pkpa_rotation_grade_result_id');
    }
}
