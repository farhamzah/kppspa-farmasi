<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaFinalGradeRelease extends Model
{
    protected $fillable = ['pkpa_final_grade_result_id', 'pkpa_graduation_decision_id', 'pkpa_enrollment_id', 'release_number', 'status', 'released_at', 'withdrawn_at', 'released_by_core_user_id', 'withdrawn_by_core_user_id', 'withdrawal_reason', 'student_visible_snapshot'];

    protected function casts(): array
    {
        return ['release_number' => 'integer', 'released_at' => 'datetime', 'withdrawn_at' => 'datetime', 'student_visible_snapshot' => 'array'];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(PkpaFinalGradeResult::class, 'pkpa_final_grade_result_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollment::class, 'pkpa_enrollment_id');
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(PkpaGraduationDecision::class, 'pkpa_graduation_decision_id');
    }
}
