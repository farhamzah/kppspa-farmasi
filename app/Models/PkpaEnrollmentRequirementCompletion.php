<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaEnrollmentRequirementCompletion extends Model
{
    protected $fillable = ['pkpa_enrollment_requirement_id', 'pkpa_enrollment_id', 'practice_domain_id', 'selected_practice_domain_option_id', 'rotation_run_id', 'rotation_grade_result_id', 'status', 'completion_basis', 'operational_complete_snapshot', 'academic_readiness_snapshot', 'grade_snapshot', 'completed_at', 'reopened_at', 'completion_reason', 'reopen_reason', 'completed_by_core_user_id', 'reopened_by_core_user_id'];

    protected function casts(): array
    {
        return ['operational_complete_snapshot' => 'array', 'academic_readiness_snapshot' => 'array', 'grade_snapshot' => 'array', 'completed_at' => 'datetime', 'reopened_at' => 'datetime'];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollmentRequirement::class, 'pkpa_enrollment_requirement_id');
    }
}
