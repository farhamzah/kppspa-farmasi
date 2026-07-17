<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaPlacementChangeRequestItem extends Model
{
    protected $fillable = [
        'pkpa_placement_change_request_id',
        'old_published_assignment_id',
        'pkpa_enrollment_id',
        'pkpa_enrollment_requirement_id',
        'change_type',
        'before_snapshot',
        'proposed_snapshot',
        'applied_published_assignment_id',
        'validation_status',
        'validation_messages',
    ];

    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
            'proposed_snapshot' => 'array',
            'validation_messages' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PkpaPlacementChangeRequest::class, 'pkpa_placement_change_request_id');
    }

    public function oldAssignment(): BelongsTo
    {
        return $this->belongsTo(PkpaPublishedAssignment::class, 'old_published_assignment_id');
    }
}
