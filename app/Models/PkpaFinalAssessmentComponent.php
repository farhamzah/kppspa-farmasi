<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaFinalAssessmentComponent extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_final_assessment_scheme_id', 'code', 'name', 'description', 'component_type', 'source_practice_domain_id', 'source_program_assessment_template_id', 'weight_percentage', 'maximum_raw_score', 'minimum_required_score', 'is_required', 'calculation_method', 'score_selection_policy', 'allow_missing', 'sort_order', 'status', 'created_by_core_user_id', 'updated_by_core_user_id'];

    protected function casts(): array
    {
        return ['weight_percentage' => 'decimal:4', 'maximum_raw_score' => 'decimal:4', 'minimum_required_score' => 'decimal:4', 'is_required' => 'boolean', 'allow_missing' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(PkpaFinalAssessmentScheme::class, 'pkpa_final_assessment_scheme_id');
    }
}
