<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaGraduationDecision extends Model
{
    protected $fillable = ['pkpa_enrollment_id', 'pkpa_final_grade_result_id', 'decision_number', 'decision_status', 'decision', 'readiness_snapshot', 'reason', 'decided_at', 'decided_by_core_user_id'];

    protected function casts(): array
    {
        return ['decision_number' => 'integer', 'readiness_snapshot' => 'array', 'decided_at' => 'datetime'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollment::class, 'pkpa_enrollment_id');
    }
}
