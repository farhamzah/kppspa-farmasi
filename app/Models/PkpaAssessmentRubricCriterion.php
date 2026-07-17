<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaAssessmentRubricCriterion extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_assessment_rubric_id', 'code', 'name', 'description', 'weight_percentage', 'maximum_points', 'is_required', 'sort_order', 'status', 'created_by_core_user_id', 'updated_by_core_user_id'];

    protected function casts(): array
    {
        return ['weight_percentage' => 'decimal:4', 'maximum_points' => 'decimal:4', 'is_required' => 'boolean', 'sort_order' => 'integer'];
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(PkpaAssessmentRubric::class, 'pkpa_assessment_rubric_id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(PkpaAssessmentRubricLevel::class, 'pkpa_assessment_rubric_criterion_id')->orderBy('sort_order');
    }
}
