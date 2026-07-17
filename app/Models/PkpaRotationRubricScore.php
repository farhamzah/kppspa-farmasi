<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaRotationRubricScore extends Model
{
    protected $fillable = [
        'pkpa_rotation_component_score_id', 'source_rubric_id', 'source_criterion_id',
        'source_level_id', 'criterion_code_snapshot', 'criterion_name_snapshot',
        'criterion_weight_snapshot', 'level_code_snapshot', 'level_label_snapshot',
        'level_points_snapshot', 'score_value', 'comments', 'status',
        'created_by_core_user_id', 'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return ['criterion_weight_snapshot' => 'decimal:4', 'level_points_snapshot' => 'decimal:4', 'score_value' => 'decimal:4'];
    }

    public function componentScore(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationComponentScore::class, 'pkpa_rotation_component_score_id');
    }
}
