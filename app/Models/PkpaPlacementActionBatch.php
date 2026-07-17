<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PkpaPlacementActionBatch extends Model
{
    public const STATUSES = ['previewed', 'applied', 'partially_applied', 'failed', 'reverted'];
    public const ACTION_TYPES = ['assign_site', 'assign_dates', 'assign_supervisors', 'assign_complete', 'clear_assignment', 'copy_group_pattern'];

    protected $fillable = [
        'pkpa_placement_plan_id',
        'action_type',
        'status',
        'description',
        'affected_count',
        'request_summary',
        'created_by_core_user_id',
        'reverted_by_core_user_id',
        'reverted_at',
    ];

    protected function casts(): array
    {
        return [
            'affected_count' => 'integer',
            'request_summary' => 'array',
            'reverted_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PkpaPlacementPlan::class, 'pkpa_placement_plan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PkpaPlacementActionBatchItem::class, 'placement_action_batch_id');
    }
}
