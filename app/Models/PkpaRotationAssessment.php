<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRotationAssessment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_rotation_run_id', 'source_assessment_scheme_id', 'scheme_code_snapshot',
        'scheme_name_snapshot', 'scheme_version_snapshot', 'maximum_score_snapshot',
        'minimum_passing_score_snapshot', 'rounding_precision_snapshot', 'rounding_mode_snapshot',
        'status', 'completion_status', 'moderation_status', 'grade_release_status',
        'started_at', 'submitted_at', 'moderated_at', 'finalized_at', 'released_at', 'locked_at',
        'row_version', 'created_by_core_user_id', 'updated_by_core_user_id', 'finalized_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'scheme_version_snapshot' => 'integer',
            'maximum_score_snapshot' => 'decimal:4',
            'minimum_passing_score_snapshot' => 'decimal:4',
            'rounding_precision_snapshot' => 'integer',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'moderated_at' => 'datetime',
            'finalized_at' => 'datetime',
            'released_at' => 'datetime',
            'locked_at' => 'datetime',
            'row_version' => 'integer',
        ];
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(PkpaAssessmentScheme::class, 'source_assessment_scheme_id');
    }

    public function assessors(): HasMany
    {
        return $this->hasMany(PkpaRotationAssessmentAssessor::class, 'pkpa_rotation_assessment_id');
    }

    public function componentScores(): HasMany
    {
        return $this->hasMany(PkpaRotationComponentScore::class, 'pkpa_rotation_assessment_id');
    }

    public function gradeResult(): HasOne
    {
        return $this->hasOne(PkpaRotationGradeResult::class, 'pkpa_rotation_assessment_id')->latestOfMany();
    }
}
