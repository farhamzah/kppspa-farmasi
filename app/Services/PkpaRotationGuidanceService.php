<?php

namespace App\Services;

use App\Models\PkpaRotationGuidanceSession;
use App\Models\PkpaRotationRun;
use App\Models\User;
use App\Services\Concerns\AuthorizesPkpaRotationActors;

class PkpaRotationGuidanceService
{
    use AuthorizesPkpaRotationActors;

    public function __construct(
        private readonly PkpaAuditService $audit,
        private readonly PkpaAcademicNotificationService $notifications
    )
    {
    }

    public function record(PkpaRotationRun $run, array $data, ?User $actor): PkpaRotationGuidanceSession
    {
        $supervisorType = $data['supervisor_type'] ?? 'internal';
        $supervisorType === 'field' ? $this->ensureFieldSupervisor($run, $actor) : $this->ensureInternalSupervisor($run, $actor);
        $session = PkpaRotationGuidanceSession::create([
            'pkpa_rotation_run_id' => $run->id,
            'pkpa_rotation_report_id' => $data['pkpa_rotation_report_id'] ?? null,
            'report_version_id' => $data['report_version_id'] ?? null,
            'guidance_type' => $data['guidance_type'] ?? 'comment_only',
            'guidance_date' => $data['guidance_date'] ?? now()->toDateString(),
            'supervisor_type' => $supervisorType,
            'supervisor_core_user_id' => $actor?->core_user_id,
            'topic' => $data['topic'],
            'student_summary' => $data['student_summary'] ?? null,
            'supervisor_notes' => $data['supervisor_notes'] ?? null,
            'follow_up_actions' => $data['follow_up_actions'] ?? null,
            'status' => 'recorded',
            'created_by_core_user_id' => $actor?->core_user_id,
            'updated_by_core_user_id' => $actor?->core_user_id,
        ]);
        $this->audit->record($actor, 'pkpa_rotation_guidance_recorded', $session);
        $this->notifications->notifyRun($run, 'pkpa_rotation_guidance_recorded', $session, ['student']);

        return $session;
    }

    public function acknowledge(PkpaRotationGuidanceSession $session, ?User $actor): PkpaRotationGuidanceSession
    {
        $run = $session->rotationRun()->firstOrFail();
        $this->ensureStudentOwnsRun($run, $actor);
        $session->update(['status' => 'acknowledged', 'student_acknowledged_at' => now()]);

        return $session->refresh();
    }
}
