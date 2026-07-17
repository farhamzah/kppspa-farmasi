<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaPlacementPublication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_program_id',
        'pkpa_placement_plan_id',
        'publication_number',
        'revision_number',
        'code',
        'title',
        'status',
        'is_current',
        'current_key',
        'published_at',
        'effective_at',
        'withdrawn_at',
        'withdrawal_reason',
        'summary',
        'validation_snapshot',
        'published_by_core_user_id',
        'withdrawn_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'publication_number' => 'integer',
            'revision_number' => 'integer',
            'is_current' => 'boolean',
            'published_at' => 'datetime',
            'effective_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'summary' => 'array',
            'validation_snapshot' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PkpaPlacementPlan::class, 'pkpa_placement_plan_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PkpaPublishedAssignment::class, 'pkpa_placement_publication_id');
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(PkpaScheduleAcknowledgement::class, 'pkpa_placement_publication_id');
    }

    public function rotationRuns(): HasMany
    {
        return $this->hasMany(PkpaRotationRun::class, 'current_placement_publication_id');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(PkpaPlacementChangeRequest::class, 'pkpa_placement_publication_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true)->where('status', 'published');
    }

    public function isVisibleToPortal(): bool
    {
        return in_array($this->status, ['published', 'withdrawn', 'superseded'], true);
    }
}
