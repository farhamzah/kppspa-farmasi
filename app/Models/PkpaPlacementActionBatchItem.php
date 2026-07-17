<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaPlacementActionBatchItem extends Model
{
    protected $fillable = [
        'placement_action_batch_id',
        'pkpa_enrollment_id',
        'pkpa_enrollment_requirement_id',
        'pkpa_rotation_assignment_id',
        'before_snapshot',
        'after_snapshot',
        'result_status',
        'validation_messages',
    ];

    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'validation_messages' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PkpaPlacementActionBatch::class, 'placement_action_batch_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationAssignment::class, 'pkpa_rotation_assignment_id');
    }
}
