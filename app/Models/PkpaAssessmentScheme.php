<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaAssessmentScheme extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_program_domain_id', 'code', 'name', 'description', 'version_number',
        'minimum_passing_score', 'maximum_score', 'rounding_precision', 'rounding_mode',
        'status', 'is_current', 'current_key', 'effective_start_date', 'effective_end_date',
        'instructions', 'hide_other_assessor_scores_until_submit', 'require_academic_readiness',
        'created_by_core_user_id', 'updated_by_core_user_id', 'activated_by_core_user_id', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'minimum_passing_score' => 'decimal:4',
            'maximum_score' => 'decimal:4',
            'rounding_precision' => 'integer',
            'is_current' => 'boolean',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'hide_other_assessor_scores_until_submit' => 'boolean',
            'require_academic_readiness' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    public function programDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaProgramDomain::class, 'pkpa_program_domain_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(PkpaAssessmentComponent::class, 'pkpa_assessment_scheme_id')->orderBy('sort_order');
    }
}
