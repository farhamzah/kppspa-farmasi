<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaAssessmentComponent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_assessment_scheme_id', 'code', 'name', 'description', 'component_type',
        'assessor_type', 'calculation_method', 'weight_percentage', 'maximum_raw_score',
        'minimum_required_score', 'is_required', 'source_entity_type', 'source_status_requirement',
        'allow_manual_override', 'sort_order', 'status', 'created_by_core_user_id', 'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'weight_percentage' => 'decimal:4',
            'maximum_raw_score' => 'decimal:4',
            'minimum_required_score' => 'decimal:4',
            'is_required' => 'boolean',
            'allow_manual_override' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(PkpaAssessmentScheme::class, 'pkpa_assessment_scheme_id');
    }

    public function rubrics(): HasMany
    {
        return $this->hasMany(PkpaAssessmentRubric::class, 'pkpa_assessment_component_id');
    }
}
