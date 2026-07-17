<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PkpaNotificationDelivery extends Model
{
    protected $fillable = [
        'event_type',
        'entity_type',
        'entity_id',
        'recipient_core_user_id',
        'recipient_email_snapshot',
        'channel',
        'status',
        'attempt_count',
        'last_attempt_at',
        'sent_at',
        'failed_at',
        'failure_code',
        'failure_message',
        'notification_key',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
