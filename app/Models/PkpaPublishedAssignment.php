<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PkpaPublishedAssignment extends Model
{
    protected $fillable = [
        'pkpa_placement_publication_id',
        'source_rotation_assignment_id',
        'pkpa_enrollment_id',
        'pkpa_enrollment_requirement_id',
        'practice_domain_id',
        'practice_domain_option_id',
        'practice_site_id',
        'program_site_id',
        'availability_period_id',
        'student_core_user_id',
        'student_number_snapshot',
        'student_name_snapshot',
        'student_group_snapshot',
        'practice_domain_name_snapshot',
        'practice_domain_option_name_snapshot',
        'practice_site_name_snapshot',
        'practice_site_address_snapshot',
        'start_date',
        'end_date',
        'duration_value_snapshot',
        'duration_unit_snapshot',
        'effective_days_snapshot',
        'practice_hours_snapshot',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'duration_value_snapshot' => 'decimal:2',
            'effective_days_snapshot' => 'integer',
            'practice_hours_snapshot' => 'integer',
        ];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(PkpaPlacementPublication::class, 'pkpa_placement_publication_id');
    }

    public function sourceAssignment(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationAssignment::class, 'source_rotation_assignment_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollment::class, 'pkpa_enrollment_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollmentRequirement::class, 'pkpa_enrollment_requirement_id');
    }

    public function practiceDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeDomain::class, 'practice_domain_id');
    }

    public function practiceSite(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeSite::class, 'practice_site_id');
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(PkpaPublishedAssignmentSupervisor::class, 'pkpa_published_assignment_id');
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(PkpaScheduleAcknowledgement::class, 'pkpa_published_assignment_id');
    }

    public function rotationRuns(): HasMany
    {
        return $this->hasMany(PkpaRotationRun::class, 'current_published_assignment_id');
    }

    public function scopeForStudent(Builder $query, ?string $coreUserId): Builder
    {
        if (blank($coreUserId)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('student_core_user_id', $coreUserId);
    }

    public function scopeForSupervisor(Builder $query, string $type, ?string $coreUserId): Builder
    {
        if (blank($coreUserId)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('supervisors', fn ($supervisor) => $supervisor
            ->where('supervisor_type', $type)
            ->where('core_user_id', $coreUserId)
            ->where('status', 'assigned'));
    }
}
