<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaNotificationDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PkpaFinalNotificationService
{
    public function notifyEnrollment(PkpaEnrollment $enrollment, string $eventType, Model $entity, array $recipientTypes = ['student']): void
    {
        if (! config('my_pspa.final_database_notifications_enabled')) {
            return;
        }

        collect($recipientTypes)->map(function (string $type) use ($enrollment) {
            if ($type !== 'student') {
                return null;
            }
            return [
                'type' => 'student',
                'core_user_id' => $enrollment->core_user_id,
                'email' => User::where('core_user_id', $enrollment->core_user_id)->value('email'),
            ];
        })->filter()->each(function (array $recipient) use ($eventType, $entity) {
            $key = implode(':', [$eventType, $entity::class, $entity->getKey(), $recipient['type'], $recipient['core_user_id'], 'database']);
            PkpaNotificationDelivery::firstOrCreate(
                ['notification_key' => substr(hash('sha256', $key), 0, 64)],
                [
                    'event_type' => $eventType,
                    'entity_type' => $entity::class,
                    'entity_id' => $entity->getKey(),
                    'recipient_core_user_id' => $recipient['core_user_id'],
                    'recipient_email_snapshot' => $recipient['email'],
                    'channel' => 'database',
                    'status' => 'pending',
                ]
            );
        });
    }
}
