<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaSiteAvailabilityPeriod extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'available', 'full', 'closed', 'cancelled'];
    public const OPERATIONAL_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    protected $fillable = [
        'pkpa_program_site_id',
        'start_date',
        'end_date',
        'minimum_students',
        'maximum_students',
        'reserved_slots',
        'operational_days',
        'daily_start_time',
        'daily_end_time',
        'status',
        'notes',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'minimum_students' => 'integer',
            'maximum_students' => 'integer',
            'reserved_slots' => 'integer',
            'operational_days' => 'array',
        ];
    }

    public function programSite(): BelongsTo
    {
        return $this->belongsTo(PkpaProgramSite::class, 'pkpa_program_site_id');
    }

    public function rotationAssignments(): HasMany
    {
        return $this->hasMany(PkpaRotationAssignment::class, 'pkpa_site_availability_period_id');
    }

    public function plannedAvailableSlots(): int
    {
        return max(0, (int) $this->maximum_students - (int) $this->reserved_slots);
    }
}
