<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaRotationCompetencyReview extends Model
{
    protected $fillable = ['pkpa_rotation_competency_record_id', 'reviewer_type', 'reviewer_core_user_id', 'action', 'comments', 'reviewed_at', 'metadata'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function competencyRecord(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationCompetencyRecord::class, 'pkpa_rotation_competency_record_id');
    }
}
