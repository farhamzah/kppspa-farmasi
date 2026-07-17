<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PkpaRemedialAttempt extends Model
{
    protected $fillable = ['pkpa_remedial_case_id', 'attempt_number', 'source_rotation_run_id', 'remedial_rotation_run_id', 'source_grade_result_id', 'new_grade_result_id', 'source_program_assessment_id', 'new_program_assessment_id', 'status', 'started_at', 'completed_at', 'selected_score', 'selection_reason', 'created_by_core_user_id', 'updated_by_core_user_id'];

    protected function casts(): array
    {
        return ['attempt_number' => 'integer', 'selected_score' => 'decimal:4', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
