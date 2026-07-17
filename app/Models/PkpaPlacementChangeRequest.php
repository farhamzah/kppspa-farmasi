<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaPlacementChangeRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_program_id',
        'pkpa_placement_publication_id',
        'request_number',
        'request_type',
        'status',
        'reason',
        'impact_summary',
        'requested_by_core_user_id',
        'reviewed_by_core_user_id',
        'approved_by_core_user_id',
        'rejected_by_core_user_id',
        'requested_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'impact_summary' => 'array',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(PkpaPlacementPublication::class, 'pkpa_placement_publication_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PkpaPlacementChangeRequestItem::class, 'pkpa_placement_change_request_id');
    }
}
