<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaRotationSupervisorHistory extends Model
{
    protected $fillable = [
        'pkpa_rotation_run_id',
        'supervisor_type',
        'core_user_id',
        'name_snapshot',
        'role_snapshot',
        'source_published_assignment_supervisor_id',
        'effective_start_date',
        'effective_end_date',
        'status',
        'active_key',
        'change_reason',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return ['effective_start_date' => 'date', 'effective_end_date' => 'date'];
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }

    public function sourceSupervisor(): BelongsTo
    {
        return $this->belongsTo(PkpaPublishedAssignmentSupervisor::class, 'source_published_assignment_supervisor_id');
    }
}
