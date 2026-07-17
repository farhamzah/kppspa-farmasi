<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaPlacementValidationIssue extends Model
{
    protected $fillable = [
        'placement_validation_run_id',
        'pkpa_rotation_assignment_id',
        'pkpa_enrollment_id',
        'pkpa_enrollment_requirement_id',
        'issue_code',
        'severity',
        'category',
        'message',
        'suggested_action',
        'context',
        'is_resolved',
        'resolved_at',
        'resolved_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PkpaPlacementValidationRun::class, 'placement_validation_run_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationAssignment::class, 'pkpa_rotation_assignment_id');
    }
}
