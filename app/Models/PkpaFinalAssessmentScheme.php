<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaFinalAssessmentScheme extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_program_id', 'code', 'name', 'description', 'version_number', 'maximum_score', 'minimum_passing_score', 'rounding_precision', 'rounding_mode', 'require_all_wahana_completed', 'require_all_wahana_minimum_score', 'require_program_components_complete', 'remedial_policy_id', 'status', 'is_current', 'current_key', 'effective_start_date', 'effective_end_date', 'instructions', 'created_by_core_user_id', 'updated_by_core_user_id', 'activated_by_core_user_id', 'activated_at'];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'maximum_score' => 'decimal:4', 'minimum_passing_score' => 'decimal:4', 'rounding_precision' => 'integer', 'require_all_wahana_completed' => 'boolean', 'require_all_wahana_minimum_score' => 'boolean', 'require_program_components_complete' => 'boolean', 'is_current' => 'boolean', 'activated_at' => 'datetime'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(PkpaFinalAssessmentComponent::class, 'pkpa_final_assessment_scheme_id')->orderBy('sort_order');
    }
}
