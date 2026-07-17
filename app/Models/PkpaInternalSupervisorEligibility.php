<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaInternalSupervisorEligibility extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'active', 'inactive', 'suspended', 'expired'];

    protected $fillable = [
        'pkpa_program_id',
        'practice_domain_id',
        'core_user_id',
        'name_snapshot',
        'email_snapshot',
        'lecturer_id_snapshot',
        'core_account_status_snapshot',
        'role_snapshot',
        'maximum_active_students',
        'maximum_students_per_program',
        'effective_start_date',
        'effective_end_date',
        'status',
        'notes',
        'last_core_synced_at',
        'last_core_sync_status',
        'last_core_sync_message',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'maximum_active_students' => 'integer',
            'maximum_students_per_program' => 'integer',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'last_core_synced_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function practiceDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeDomain::class, 'practice_domain_id');
    }

    public function unavailabilityPeriods(): HasMany
    {
        return $this->hasMany(PkpaSupervisorUnavailabilityPeriod::class, 'internal_supervisor_eligibility_id')->orderBy('start_date');
    }

    public function assignmentSupervisors(): HasMany
    {
        return $this->hasMany(PkpaRotationAssignmentSupervisor::class, 'internal_supervisor_eligibility_id');
    }
}
