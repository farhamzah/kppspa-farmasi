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
}
