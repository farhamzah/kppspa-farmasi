<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRemedialCase extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_enrollment_id', 'pkpa_enrollment_requirement_id', 'pkpa_rotation_grade_result_id', 'pkpa_program_assessment_id', 'pkpa_remedial_policy_id', 'case_type', 'status', 'reason', 'policy_snapshot', 'opened_by_core_user_id', 'approved_by_core_user_id', 'closed_by_core_user_id', 'opened_at', 'approved_at', 'closed_at', 'cancelled_at', 'cancellation_reason'];

    protected function casts(): array
    {
        return ['policy_snapshot' => 'array', 'opened_at' => 'datetime', 'approved_at' => 'datetime', 'closed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PkpaRemedialAttempt::class, 'pkpa_remedial_case_id');
    }
}
