<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaCompetencyCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_competency_set_id', 'code', 'name', 'description', 'sort_order', 'is_active', 'created_by_core_user_id', 'updated_by_core_user_id'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function competencySet(): BelongsTo
    {
        return $this->belongsTo(PkpaCompetencySet::class, 'pkpa_competency_set_id');
    }
}
