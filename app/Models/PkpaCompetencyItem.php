<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaCompetencyItem extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_competency_set_id', 'pkpa_competency_category_id', 'code', 'title', 'description', 'achievement_criteria', 'evidence_instructions', 'is_required', 'evidence_required', 'minimum_evidence_count', 'verification_required', 'sort_order', 'is_active', 'created_by_core_user_id', 'updated_by_core_user_id'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'evidence_required' => 'boolean', 'verification_required' => 'boolean', 'minimum_evidence_count' => 'integer', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function competencySet(): BelongsTo
    {
        return $this->belongsTo(PkpaCompetencySet::class, 'pkpa_competency_set_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PkpaCompetencyCategory::class, 'pkpa_competency_category_id');
    }
}
