<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaAssessmentRubric extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_assessment_component_id', 'code', 'name', 'description', 'scoring_method', 'maximum_score', 'instructions', 'status', 'created_by_core_user_id', 'updated_by_core_user_id'];

    protected function casts(): array
    {
        return ['maximum_score' => 'decimal:4'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PkpaAssessmentComponent::class, 'pkpa_assessment_component_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(PkpaAssessmentRubricCriterion::class, 'pkpa_assessment_rubric_id')->orderBy('sort_order');
    }
}
