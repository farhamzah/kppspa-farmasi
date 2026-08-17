<?php

namespace App\Services;

use App\Models\PkpaNotificationDelivery;
use App\Models\PkpaPlacementPublication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PkpaPlacementNotificationService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function createPublicationNotifications(PkpaPlacementPublication $publication, string $eventType = 'placement_published'): void
    {
        $publication->loadMissing('assignments.supervisors');
        $recipients = collect();

        foreach ($publication->assignments as $assignment) {
            $recipients->push([
                'core_user_id' => $assignment->student_core_user_id,
                'email' => $this->emailForCoreUser($assignment->student_core_user_id),
                'type' => 'student',
            ]);
            foreach ($assignment->supervisors as $supervisor) {
                $recipients->push([
                    'core_user_id' => $supervisor->core_user_id,
                    'email' => $supervisor->email_snapshot ?: $this->emailForCoreUser($supervisor->core_user_id),
                    'type' => $supervisor->supervisor_type,
                ]);
            }
        }

        $recipients->unique(fn ($recipient) => $recipient['type'].':'.$recipient['core_user_id'])
            ->each(function (array $recipient) use ($publication, $eventType) {
                $this->createDelivery($eventType, $publication, $recipient, 'database');
                $this->createDelivery($eventType, $publication, $recipient, 'mail');
            });
    }

    public function createDelivery(string $eventType, Model $entity, array $recipient, string $channel): PkpaNotificationDelivery
    {
        $key = implode(':', [$eventType, $entity::class, $entity->getKey(), $recipient['type'] ?? 'user', $recipient['core_user_id'], $channel]);

        return PkpaNotificationDelivery::firstOrCreate(
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

    public function sendPending(?User $actor = null): array
    {
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        PkpaNotificationDelivery::whereIn('status', ['pending', 'failed'])->get()->each(function (PkpaNotificationDelivery $delivery) use (&$sent, &$skipped, &$failed, $actor) {
            if ($delivery->channel === 'database' && ! config('my_pkpa.database_notifications_enabled')) {
                $delivery->update(['status' => 'skipped', 'last_attempt_at' => now(), 'attempt_count' => $delivery->attempt_count + 1]);
                $skipped++;
                return;
            }
            if ($delivery->channel === 'mail' && ! config('my_pkpa.email_notifications_enabled')) {
                $delivery->update(['status' => 'skipped', 'last_attempt_at' => now(), 'attempt_count' => $delivery->attempt_count + 1, 'failure_code' => 'email_disabled']);
                $skipped++;
                return;
            }
            if ($delivery->channel === 'mail' && blank($delivery->recipient_email_snapshot)) {
                $delivery->update(['status' => 'skipped', 'last_attempt_at' => now(), 'attempt_count' => $delivery->attempt_count + 1, 'failure_code' => 'email_missing']);
                $skipped++;
                return;
            }

            try {
                if ($delivery->channel === 'mail') {
                    Mail::raw($this->mailText($delivery), fn ($message) => $message
                        ->to($delivery->recipient_email_snapshot)
                        ->subject($this->subjectFor($delivery))
                        ->from(config('mail.from.address'), config('my_pkpa.notification_from_name')));
                }

                $delivery->update([
                    'status' => 'sent',
                    'attempt_count' => $delivery->attempt_count + 1,
                    'last_attempt_at' => now(),
                    'sent_at' => now(),
                    'failed_at' => null,
                    'failure_code' => null,
                    'failure_message' => null,
                ]);
                $this->audit->record($actor, 'placement_notification_sent', $delivery, null, ['channel' => $delivery->channel]);
                $sent++;
            } catch (Throwable $exception) {
                $delivery->update([
                    'status' => 'failed',
                    'attempt_count' => $delivery->attempt_count + 1,
                    'last_attempt_at' => now(),
                    'failed_at' => now(),
                    'failure_code' => 'send_failed',
                    'failure_message' => str($exception->getMessage())->limit(180)->toString(),
                ]);
                $this->audit->record($actor, 'placement_notification_failed', $delivery, null, ['channel' => $delivery->channel, 'failure_code' => 'send_failed']);
                $failed++;
            }
        });

        return compact('sent', 'skipped', 'failed');
    }

    private function emailForCoreUser(string $coreUserId): ?string
    {
        return User::where('core_user_id', $coreUserId)->value('email');
    }

    private function subjectFor(PkpaNotificationDelivery $delivery): string
    {
        return $delivery->event_type === 'placement_revised'
            ? 'Revisi Jadwal Penempatan PKPA MY PKPA'
            : 'Jadwal Penempatan PKPA MY PKPA Telah Dipublikasikan';
    }

    private function mailText(PkpaNotificationDelivery $delivery): string
    {
        return "Jadwal Penempatan PKPA telah dipublikasikan melalui MY PKPA.\n\nSilakan masuk ke portal MY PKPA untuk melihat detail resmi. Email ini tidak memuat password atau token login.";
    }
}
