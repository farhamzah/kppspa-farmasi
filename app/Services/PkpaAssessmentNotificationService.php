<?php

namespace App\Services;

use App\Models\PkpaNotificationDelivery;
use App\Models\PkpaRotationAssessment;
use App\Models\PkpaRotationRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PkpaAssessmentNotificationService
{
    public function notifyAssessment(PkpaRotationAssessment $assessment, string $eventType, Model $entity, array $recipientTypes): void
    {
        if (! config('my_pkpa.assessment_database_notifications_enabled') && ! config('my_pkpa.assessment_email_notifications_enabled')) {
            return;
        }

        $assessment->loadMissing('rotationRun.supervisorHistories');
        $run = $assessment->rotationRun;

        collect($recipientTypes)
            ->flatMap(fn (string $type) => $this->recipientsFor($run, $type))
            ->unique(fn (array $recipient) => $recipient['type'].':'.$recipient['core_user_id'])
            ->each(function (array $recipient) use ($eventType, $entity) {
                if (config('my_pkpa.assessment_database_notifications_enabled')) {
                    $this->createDelivery($eventType, $entity, $recipient, 'database');
                }
                if (config('my_pkpa.assessment_email_notifications_enabled')) {
                    $this->createDelivery($eventType, $entity, $recipient, 'mail');
                }
            });
    }

    private function recipientsFor(PkpaRotationRun $run, string $type): array
    {
        if ($type === 'student' && filled($run->student_core_user_id)) {
            return [[
                'type' => 'student',
                'core_user_id' => $run->student_core_user_id,
                'email' => $this->emailForCoreUser($run->student_core_user_id),
            ]];
        }

        $supervisorType = match ($type) {
            'field_supervisor' => 'field',
            'internal_supervisor' => 'internal',
            default => null,
        };
        if (! $supervisorType) {
            return [];
        }

        return $run->supervisorHistories
            ->where('supervisor_type', $supervisorType)
            ->where('status', 'active')
            ->filter(fn ($supervisor) => filled($supervisor->core_user_id))
            ->map(fn ($supervisor) => [
                'type' => $type,
                'core_user_id' => $supervisor->core_user_id,
                'email' => $this->emailForCoreUser($supervisor->core_user_id),
            ])
            ->values()
            ->all();
    }

    private function createDelivery(string $eventType, Model $entity, array $recipient, string $channel): void
    {
        $key = implode(':', [$eventType, $entity::class, $entity->getKey(), $recipient['type'], $recipient['core_user_id'], $channel]);

        PkpaNotificationDelivery::firstOrCreate(
            ['notification_key' => substr(hash('sha256', $key), 0, 64)],
            [
                'event_type' => $eventType,
                'entity_type' => $entity::class,
                'entity_id' => $entity->getKey(),
                'recipient_core_user_id' => $recipient['core_user_id'],
                'recipient_email_snapshot' => $recipient['email'] ?? null,
                'channel' => $channel,
                'status' => 'pending',
            ]
        );
    }

    private function emailForCoreUser(string $coreUserId): ?string
    {
        return User::where('core_user_id', $coreUserId)->value('email');
    }
}
