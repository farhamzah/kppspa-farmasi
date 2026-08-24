<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRotationRun extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_program_id',
        'pkpa_enrollment_id',
        'pkpa_enrollment_requirement_id',
        'current_placement_publication_id',
        'origin_published_assignment_id',
        'current_published_assignment_id',
        'practice_domain_id',
        'practice_domain_option_id',
        'practice_site_id',
        'student_core_user_id',
        'scheduled_start_date',
        'scheduled_end_date',
        'actual_start_date',
        'actual_end_date',
        'status',
        'operational_status',
        'publication_sync_status',
        'started_at',
        'paused_at',
        'resumed_at',
        'operational_completed_at',
        'cancelled_at',
        'cancellation_reason',
        'row_version',
        'created_by_core_user_id',
        'updated_by_core_user_id',
        'activated_by_core_user_id',
        'operational_completed_by_core_user_id',
        'current_key',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_date' => 'date',
            'scheduled_end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'resumed_at' => 'datetime',
            'operational_completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'row_version' => 'integer',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollment::class, 'pkpa_enrollment_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollmentRequirement::class, 'pkpa_enrollment_requirement_id');
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(PkpaPlacementPublication::class, 'current_placement_publication_id');
    }

    public function originAssignment(): BelongsTo
    {
        return $this->belongsTo(PkpaPublishedAssignment::class, 'origin_published_assignment_id');
    }

    public function currentAssignment(): BelongsTo
    {
        return $this->belongsTo(PkpaPublishedAssignment::class, 'current_published_assignment_id');
    }

    public function practiceDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeDomain::class, 'practice_domain_id');
    }

    public function practiceSite(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeSite::class, 'practice_site_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PkpaRotationStatusHistory::class, 'pkpa_rotation_run_id');
    }

    public function supervisorHistories(): HasMany
    {
        return $this->hasMany(PkpaRotationSupervisorHistory::class, 'pkpa_rotation_run_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(PkpaAttendanceRecord::class, 'pkpa_rotation_run_id');
    }

    public function logbookEntries(): HasMany
    {
        return $this->hasMany(PkpaLogbookEntry::class, 'pkpa_rotation_run_id');
    }

    public function progressSnapshots(): HasMany
    {
        return $this->hasMany(PkpaRotationProgressSnapshot::class, 'pkpa_rotation_run_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(PkpaRotationPublicationSyncLog::class, 'pkpa_rotation_run_id');
    }

    public function competencyRecords(): HasMany
    {
        return $this->hasMany(PkpaRotationCompetencyRecord::class, 'pkpa_rotation_run_id');
    }

    public function specialTasks(): HasMany
    {
        return $this->hasMany(PkpaRotationSpecialTask::class, 'pkpa_rotation_run_id');
    }

    public function rotationReport()
    {
        return $this->hasOne(PkpaRotationReport::class, 'pkpa_rotation_run_id');
    }

    public function guidanceSessions(): HasMany
    {
        return $this->hasMany(PkpaRotationGuidanceSession::class, 'pkpa_rotation_run_id');
    }

    public function academicReadinessReviews(): HasMany
    {
        return $this->hasMany(PkpaRotationAcademicReadinessReview::class, 'pkpa_rotation_run_id');
    }

    public function rotationAssessment()
    {
        return $this->hasOne(PkpaRotationAssessment::class, 'pkpa_rotation_run_id');
    }

    public function gradeResults(): HasMany
    {
        return $this->hasMany(PkpaRotationGradeResult::class, 'pkpa_rotation_run_id');
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

        return $query->whereHas('supervisorHistories', fn ($supervisor) => $supervisor
            ->where('supervisor_type', $type)
            ->where('core_user_id', $coreUserId)
            ->where('status', 'active'));
    }

    public function activeSupervisor(string $type): ?PkpaRotationSupervisorHistory
    {
        return $this->supervisorHistories->first(fn ($supervisor) => $supervisor->supervisor_type === $type && $supervisor->status === 'active');
    }

    public function studentDisplayName(): string
    {
        return (string) ($this->enrollment?->student_name_snapshot ?: $this->student_core_user_id ?: '-');
    }

    public function studentDisplaySecondary(): string
    {
        $parts = array_values(array_filter([
            $this->enrollment?->student_number,
            $this->student_core_user_id,
        ], fn ($value) => filled($value)));

        return $parts !== [] ? implode(' / ', $parts) : '-';
    }
}
