<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PkpaMasterAudit extends Model
{
    protected $fillable = [
        'actor_core_user_id',
        'actor_user_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}
