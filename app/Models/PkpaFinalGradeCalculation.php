<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaFinalGradeCalculation extends Model
{
    protected $fillable = ['pkpa_enrollment_id', 'pkpa_final_assessment_scheme_id', 'calculation_number', 'status', 'source_snapshot', 'component_results', 'raw_total_score', 'moderated_total_score', 'final_score', 'rounding_snapshot', 'blocking_issues', 'warning_issues', 'calculated_by_core_user_id', 'calculated_at'];

    protected function casts(): array
    {
        return ['calculation_number' => 'integer', 'source_snapshot' => 'array', 'component_results' => 'array', 'raw_total_score' => 'decimal:4', 'moderated_total_score' => 'decimal:4', 'final_score' => 'decimal:4', 'rounding_snapshot' => 'array', 'blocking_issues' => 'array', 'warning_issues' => 'array', 'calculated_at' => 'datetime'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollment::class, 'pkpa_enrollment_id');
    }
}
