<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PkpaSupervisorSyncLog extends Model
{
    public const STATUSES = ['success', 'partial', 'failed', 'not_found', 'inactive'];

    protected $fillable = [
        'supervisor_type',
        'core_user_id',
        'target_type',
        'target_id',
        'status',
        'message',
        'synced_fields',
        'synced_by_core_user_id',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_fields' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
