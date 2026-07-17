<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PkpaPlacementValidationRun extends Model
{
    protected $fillable = [
        'pkpa_placement_plan_id',
        'scope_type',
        'scope_payload',
        'status',
        'total_assignments',
        'valid_assignments',
        'warning_count',
        'error_count',
        'started_at',
        'completed_at',
        'created_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'scope_payload' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PkpaPlacementPlan::class, 'pkpa_placement_plan_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(PkpaPlacementValidationIssue::class, 'placement_validation_run_id');
    }
}
