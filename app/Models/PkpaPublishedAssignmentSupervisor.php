<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaPublishedAssignmentSupervisor extends Model
{
    protected $fillable = [
        'pkpa_published_assignment_id',
        'source_assignment_supervisor_id',
        'supervisor_type',
        'core_user_id',
        'name_snapshot',
        'email_snapshot',
        'role_snapshot',
        'position_snapshot',
        'is_primary',
        'status',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PkpaPublishedAssignment::class, 'pkpa_published_assignment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'core_user_id', 'core_user_id');
    }

    public function displayName(): string
    {
        $this->loadMissing('user.lecturer', 'user.fieldSupervisor');

        if ($this->user) {
            $role = $this->supervisor_type === 'internal' ? 'pembimbing_dalam' : 'pembimbing_lapangan';
            $name = user_display_name($this->user, $role);

            if (filled($name)) {
                return $name;
            }
        }

        return $this->name_snapshot ?: 'Pembimbing';
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }
}
