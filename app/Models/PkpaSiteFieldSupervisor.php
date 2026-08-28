<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaSiteFieldSupervisor extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'active', 'inactive', 'suspended', 'expired'];

    protected $fillable = [
        'practice_site_id',
        'core_user_id',
        'name_snapshot',
        'email_snapshot',
        'professional_id_snapshot',
        'core_account_status_snapshot',
        'role_snapshot',
        'position_title',
        'is_primary_contact',
        'maximum_active_students',
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
            'is_primary_contact' => 'boolean',
            'maximum_active_students' => 'integer',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'last_core_synced_at' => 'datetime',
        ];
    }

    public function practiceSite(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeSite::class, 'practice_site_id');
    }

    public function unavailabilityPeriods(): HasMany
    {
        return $this->hasMany(PkpaSupervisorUnavailabilityPeriod::class, 'site_field_supervisor_id')->orderBy('start_date');
    }

    public function assignmentSupervisors(): HasMany
    {
        return $this->hasMany(PkpaRotationAssignmentSupervisor::class, 'site_field_supervisor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'core_user_id', 'core_user_id');
    }

    public function displayName(): string
    {
        $this->loadMissing('user.fieldSupervisor');

        if ($this->user) {
            $name = user_display_name($this->user, 'pembimbing_lapangan');

            if (filled($name)) {
                return $name;
            }
        }

        return $this->name_snapshot ?: 'Preseptor';
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }
}
