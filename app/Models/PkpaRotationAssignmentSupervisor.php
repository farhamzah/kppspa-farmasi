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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'core_user_id', 'core_user_id');
    }

    public function displayName(): string
    {
        $this->loadMissing('user.lecturer', 'user.fieldSupervisor', 'internalEligibility.user', 'fieldSupervisor.user');

        if ($this->user) {
            $role = $this->supervisor_type === 'internal' ? 'pembimbing_dalam' : 'pembimbing_lapangan';
            $name = user_display_name($this->user, $role);

            if (filled($name)) {
                return $name;
            }
        }

        if ($this->supervisor_type === 'internal' && $this->internalEligibility) {
            return $this->internalEligibility->display_name;
        }

        if ($this->supervisor_type === 'field' && $this->fieldSupervisor) {
            return $this->fieldSupervisor->display_name;
        }

        return $this->name_snapshot ?: 'Pembimbing';
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }
}
