<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaPlacementPlan extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'validating', 'validated', 'needs_revision', 'locked', 'archived'];
    public const VALIDATION_STATUSES = ['not_validated', 'validating', 'valid', 'warning', 'error', 'stale'];

    protected $fillable = [
        'pkpa_program_id',
        'code',
        'name',
        'version_number',
        'status',
        'is_current',
        'current_key',
        'description',
        'validation_status',
        'validation_summary',
        'last_validated_at',
        'created_by_core_user_id',
        'updated_by_core_user_id',
        'validated_by_core_user_id',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'is_current' => 'boolean',
            'validation_summary' => 'array',
            'last_validated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PkpaRotationAssignment::class, 'pkpa_placement_plan_id');
    }

    public function actionBatches(): HasMany
    {
        return $this->hasMany(PkpaPlacementActionBatch::class, 'pkpa_placement_plan_id');
    }

    public function validationRuns(): HasMany
    {
        return $this->hasMany(PkpaPlacementValidationRun::class, 'pkpa_placement_plan_id');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(PkpaPlacementPublication::class, 'pkpa_placement_plan_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true)->whereNotNull('current_key');
    }

    public function isEditable(): bool
    {
        return ! in_array($this->status, ['locked', 'archived'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'validating' => 'Sedang divalidasi',
            'validated' => 'Tervalidasi',
            'needs_revision' => 'Perlu revisi',
            'locked' => 'Dikunci',
            'archived' => 'Diarsipkan',
            default => 'Draft',
        };
    }
}
