<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRotationAssignmentSupervisor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_rotation_assignment_id',
        'supervisor_type',
        'internal_supervisor_eligibility_id',
        'site_field_supervisor_id',
        'core_user_id',
        'name_snapshot',
        'role_snapshot',
        'effective_start_date',
        'effective_end_date',
        'status',
        'is_primary',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'is_primary' => 'boolean',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationAssignment::class, 'pkpa_rotation_assignment_id');
    }

    public function internalEligibility(): BelongsTo
    {
        return $this->belongsTo(PkpaInternalSupervisorEligibility::class, 'internal_supervisor_eligibility_id');
    }

    public function fieldSupervisor(): BelongsTo
    {
        return $this->belongsTo(PkpaSiteFieldSupervisor::class, 'site_field_supervisor_id');
    }
}
