<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldSupervisor extends Model
{
    protected $fillable = [
        'user_id',
        'institution_name',
        'position',
        'phone',
        'address',
        'status',
        'profile_completed_at',
        'core_user_id',
        'core_external_person_id',
        'core_display_name',
        'core_synced_at',
        'core_sync_status',
        'core_sync_note',
    ];

    protected function casts(): array
    {
        return [
            'profile_completed_at' => 'datetime',
            'core_synced_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fieldAssignments()
    {
        return $this->hasMany(KpAssignment::class, 'field_supervisor_id');
    }

    public function logbooks()
    {
        return $this->hasManyThrough(KpLogbook::class, KpAssignment::class, 'field_supervisor_id', 'kp_assignment_id');
    }

    public function places()
    {
        return $this->belongsToMany(KpPlace::class, 'kp_place_field_supervisors')->withPivot(['status'])->withTimestamps();
    }

    public function partnerPlacesLabel(): ?string
    {
        $this->loadMissing('places');

        $names = $this->places
            ->where('pivot.status', 'aktif')
            ->pluck('name')
            ->filter()
            ->values();

        return $names->isNotEmpty() ? $names->implode(', ') : null;
    }

    public function getPartnerPlacesLabelAttribute(): ?string
    {
        return $this->partnerPlacesLabel();
    }
}
