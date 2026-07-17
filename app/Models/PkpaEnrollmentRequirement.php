<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PkpaEnrollmentRequirement extends Model
{
    public const STATUSES = ['pending', 'planned', 'in_progress', 'completed', 'failed', 'repeating', 'waived', 'cancelled'];

    protected $fillable = [
        'pkpa_enrollment_id',
        'pkpa_program_domain_id',
        'practice_domain_id',
        'selection_mode',
        'required_option_count',
        'selected_practice_domain_option_id',
        'status',
        'completion_percentage',
        'completed_at',
        'waived_at',
        'waiver_reason',
        'notes',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'completion_percentage' => 'integer',
            'completed_at' => 'datetime',
            'waived_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollment::class, 'pkpa_enrollment_id');
    }

    public function completions()
    {
        return $this->hasMany(PkpaEnrollmentRequirementCompletion::class, 'pkpa_enrollment_requirement_id');
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

    public function rotationAssignments(): HasMany
    {
        return $this->hasMany(PkpaRotationAssignment::class, 'pkpa_enrollment_requirement_id');
    }

    public function modeLabel(): string
    {
        return $this->selection_mode === 'choose_one' ? 'Pilih satu' : 'Langsung';
    }
}
