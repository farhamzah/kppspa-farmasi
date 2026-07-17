<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRotationAssignment extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'valid', 'needs_attention', 'cancelled', 'superseded'];
    public const PLANNING_SOURCES = ['individual', 'group_bulk', 'selection_bulk', 'import', 'copied'];

    protected $fillable = [
        'pkpa_placement_plan_id',
        'pkpa_enrollment_id',
        'pkpa_enrollment_requirement_id',
        'pkpa_program_domain_id',
        'practice_domain_id',
        'selected_practice_domain_option_id',
        'pkpa_program_site_id',
        'pkpa_site_availability_period_id',
        'practice_site_id',
        'student_group_id_snapshot',
        'start_date',
        'end_date',
        'planned_duration_value',
        'planned_duration_unit',
        'calculated_effective_days',
        'calculated_practice_hours',
        'status',
        'planning_source',
        'validation_status',
        'last_validated_at',
        'notes',
        'row_version',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'planned_duration_value' => 'decimal:2',
            'calculated_effective_days' => 'integer',
            'calculated_practice_hours' => 'integer',
            'row_version' => 'integer',
            'last_validated_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PkpaPlacementPlan::class, 'pkpa_placement_plan_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollment::class, 'pkpa_enrollment_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollmentRequirement::class, 'pkpa_enrollment_requirement_id');
    }

    public function programDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaProgramDomain::class, 'pkpa_program_domain_id');
    }

    public function practiceDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeDomain::class, 'practice_domain_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeDomainOption::class, 'selected_practice_domain_option_id');
    }

    public function programSite(): BelongsTo
    {
        return $this->belongsTo(PkpaProgramSite::class, 'pkpa_program_site_id');
    }

    public function availabilityPeriod(): BelongsTo
    {
        return $this->belongsTo(PkpaSiteAvailabilityPeriod::class, 'pkpa_site_availability_period_id');
    }

    public function practiceSite(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeSite::class, 'practice_site_id');
    }

    public function groupSnapshot(): BelongsTo
    {
        return $this->belongsTo(PkpaStudentGroup::class, 'student_group_id_snapshot');
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(PkpaRotationAssignmentSupervisor::class, 'pkpa_rotation_assignment_id');
    }

    public function internalSupervisor(): HasMany
    {
        return $this->supervisors()->where('supervisor_type', 'internal')->where('status', 'active');
    }

    public function fieldSupervisor(): HasMany
    {
        return $this->supervisors()->where('supervisor_type', 'field')->where('status', 'active');
    }

    public function scopeActiveForCapacity($query)
    {
        return $query->whereNotIn('status', ['cancelled', 'superseded']);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'valid' => 'Valid',
            'needs_attention' => 'Peringatan',
            'cancelled' => 'Dibatalkan',
            'superseded' => 'Digantikan',
            default => 'Draft',
        };
    }
}
