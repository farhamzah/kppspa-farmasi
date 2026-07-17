<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaRotationAcademicReadinessReview extends Model
{
    protected $fillable = ['pkpa_rotation_run_id', 'status', 'required_competency_count', 'verified_competency_count', 'required_task_count', 'approved_task_count', 'report_status', 'operational_complete', 'blocking_issues', 'snapshot', 'reviewed_by_core_user_id', 'reviewed_at'];

    protected function casts(): array
    {
        return ['required_competency_count' => 'integer', 'verified_competency_count' => 'integer', 'required_task_count' => 'integer', 'approved_task_count' => 'integer', 'operational_complete' => 'boolean', 'blocking_issues' => 'array', 'snapshot' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }
}
