<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaAssessmentRubricLevel extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_assessment_rubric_criterion_id', 'code', 'label', 'description', 'points', 'minimum_value', 'maximum_value', 'sort_order', 'status', 'created_by_core_user_id', 'updated_by_core_user_id'];

    protected function casts(): array
    {
        return ['points' => 'decimal:4', 'minimum_value' => 'decimal:4', 'maximum_value' => 'decimal:4', 'sort_order' => 'integer'];
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(PkpaAssessmentRubricCriterion::class, 'pkpa_assessment_rubric_criterion_id');
    }
}
