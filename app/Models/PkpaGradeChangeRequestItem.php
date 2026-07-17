<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaGradeChangeRequestItem extends Model
{
    protected $fillable = ['pkpa_grade_change_request_id', 'pkpa_rotation_component_score_id', 'field_name', 'before_value', 'after_value', 'reason', 'metadata'];

    protected function casts(): array
    {
        return ['before_value' => 'decimal:4', 'after_value' => 'decimal:4', 'metadata' => 'array'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PkpaGradeChangeRequest::class, 'pkpa_grade_change_request_id');
    }
}
