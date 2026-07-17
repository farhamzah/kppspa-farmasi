<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaCompetencySet extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_program_domain_id', 'code', 'name', 'description', 'version_number', 'status', 'is_current', 'current_key', 'effective_start_date', 'effective_end_date', 'instructions', 'created_by_core_user_id', 'updated_by_core_user_id', 'activated_by_core_user_id', 'activated_at'];

    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'version_number' => 'integer', 'effective_start_date' => 'date', 'effective_end_date' => 'date', 'activated_at' => 'datetime'];
    }

    public function programDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaProgramDomain::class, 'pkpa_program_domain_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(PkpaCompetencyCategory::class, 'pkpa_competency_set_id')->orderBy('sort_order')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PkpaCompetencyItem::class, 'pkpa_competency_set_id')->orderBy('sort_order')->orderBy('id');
    }
}
