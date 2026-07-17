<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaGradeRelease extends Model
{
    protected $fillable = [
        'pkpa_rotation_grade_result_id', 'pkpa_rotation_assessment_id', 'release_number',
        'status', 'released_at', 'withdrawn_at', 'released_by_core_user_id',
        'withdrawn_by_core_user_id', 'withdrawal_reason', 'student_visible_snapshot',
    ];

    protected function casts(): array
    {
        return ['release_number' => 'integer', 'released_at' => 'datetime', 'withdrawn_at' => 'datetime', 'student_visible_snapshot' => 'array'];
    }

    public function gradeResult(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationGradeResult::class, 'pkpa_rotation_grade_result_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationAssessment::class, 'pkpa_rotation_assessment_id');
    }
}
