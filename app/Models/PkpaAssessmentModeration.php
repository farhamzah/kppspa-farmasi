<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaAssessmentModeration extends Model
{
    protected $fillable = [
        'pkpa_rotation_assessment_id', 'status', 'moderation_type', 'reason',
        'original_total_score', 'proposed_total_score', 'final_total_score',
        'component_adjustments', 'review_notes', 'requested_by_core_user_id',
        'moderated_by_core_user_id', 'approved_by_core_user_id',
        'requested_at', 'moderated_at', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'original_total_score' => 'decimal:4',
            'proposed_total_score' => 'decimal:4',
            'final_total_score' => 'decimal:4',
            'component_adjustments' => 'array',
            'requested_at' => 'datetime',
            'moderated_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationAssessment::class, 'pkpa_rotation_assessment_id');
    }
}
