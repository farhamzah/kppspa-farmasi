<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaFinalGradeResult extends Model
{
    protected $fillable = ['pkpa_enrollment_id', 'pkpa_final_grade_calculation_id', 'pkpa_final_assessment_scheme_id', 'raw_total_score', 'moderated_total_score', 'final_score', 'maximum_score', 'minimum_passing_score_snapshot', 'result_status', 'source_snapshot', 'calculation_snapshot', 'finalized_at', 'released_at', 'finalized_by_core_user_id', 'released_by_core_user_id', 'row_version'];

    protected function casts(): array
    {
        return ['raw_total_score' => 'decimal:4', 'moderated_total_score' => 'decimal:4', 'final_score' => 'decimal:4', 'maximum_score' => 'decimal:4', 'minimum_passing_score_snapshot' => 'decimal:4', 'source_snapshot' => 'array', 'calculation_snapshot' => 'array', 'finalized_at' => 'datetime', 'released_at' => 'datetime', 'row_version' => 'integer'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollment::class, 'pkpa_enrollment_id');
    }
}
