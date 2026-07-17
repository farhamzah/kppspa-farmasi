<?php

namespace App\Services;

use App\Models\PkpaRotationRun;
use App\Models\PkpaRotationSpecialTask;
use App\Models\PkpaSpecialTaskReview;
use App\Models\PkpaSpecialTaskSubmission;
use App\Models\PkpaSpecialTaskTemplate;
use App\Models\User;
use App\Services\Concerns\AuthorizesPkpaRotationActors;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaSpecialTaskService
{
    use AuthorizesPkpaRotationActors;

    public function __construct(
        private readonly PkpaAuditService $audit,
        private readonly PkpaAcademicFileService $files,
        private readonly PkpaAcademicNotificationService $notifications
    )
    {
    }

    public function saveTemplate($programDomain, array $data, ?User $actor): PkpaSpecialTaskTemplate
    {
        if (! $this->isCoordinator($actor)) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat mengelola template tugas.']);
        }

        return tap(PkpaSpecialTaskTemplate::updateOrCreate(
            ['pkpa_program_domain_id' => $programDomain->id, 'code' => $data['code']],
            array_merge($data, ['created_by_core_user_id' => $actor?->core_user_id, 'updated_by_core_user_id' => $actor?->core_user_id])
        ), fn ($template) => $this->audit->record($actor, 'pkpa_special_task_template_saved', $template));
    }

    public function assignFromTemplates(PkpaRotationRun $run, ?User $actor): int
    {
        if (! $this->isCoordinator($actor)) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat membuat penugasan.']);
        }
        $run->loadMissing('requirement.programDomain');
        $templates = PkpaSpecialTaskTemplate::where('pkpa_program_domain_id', $run->requirement?->pkpa_program_domain_id)->where('status', 'active')->get();
        $created = 0;
        foreach ($templates as $template) {
            $task = PkpaRotationSpecialTask::firstOrCreate(
                ['pkpa_rotation_run_id' => $run->id, 'source_special_task_template_id' => $template->id],
                [
                    'task_code_snapshot' => $template->code,
                    'task_title_snapshot' => $template->title,
                    'task_description_snapshot' => $template->description,
                    'instructions_snapshot' => $template->instructions,
                    'submission_type_snapshot' => $template->submission_type,
                    'is_required_snapshot' => $template->is_required,
                    'field_supervisor_review_required_snapshot' => $template->field_supervisor_review_required,
                    'internal_supervisor_review_required_snapshot' => $template->internal_supervisor_review_required,
                    'due_date' => is_null($template->due_offset_days) ? null : $run->scheduled_start_date->copy()->addDays($template->due_offset_days),
                    'status' => 'assigned',
                    'assigned_at' => now(),
                    'created_by_core_user_id' => $actor?->core_user_id,
                    'updated_by_core_user_id' => $actor?->core_user_id,
                ]
            );
            $created += $task->wasRecentlyCreated ? 1 : 0;
        }
        $this->audit->record($actor, 'pkpa_special_tasks_assigned', $run, null, ['created' => $created]);
        if ($created > 0) {
            $this->notifications->notifyRun($run, 'pkpa_special_tasks_assigned', $run, ['student', 'field_supervisor', 'internal_supervisor']);
        }

        return $created;
    }

    public function saveSubmission(PkpaRotationSpecialTask $task, array $data, ?UploadedFile $file, ?User $actor): PkpaSpecialTaskSubmission
    {
        $run = $task->rotationRun()->firstOrFail();
        $this->ensureStudentOwnsRun($run, $actor);
        if (in_array($task->status, ['approved', 'cancelled'], true)) {
            throw ValidationException::withMessages(['task' => 'Tugas yang sudah final tidak dapat diubah.']);
        }
        $version = (int) $task->submissions()->max('version_number') + 1;
        $payload = [
            'version_number' => $version,
            'title' => $data['title'] ?? $task->task_title_snapshot,
            'description' => $data['description'] ?? null,
            'submission_notes' => $data['submission_notes'] ?? null,
            'status' => ($data['submit'] ?? false) ? 'submitted' : 'draft',
            'submitted_by_core_user_id' => $actor?->core_user_id,
            'submitted_at' => ($data['submit'] ?? false) ? now() : null,
        ];
        if ($file) {
            $payload = array_merge($payload, $this->files->store($file, 'pkpa-academic/tasks/'.$task->id));
        }
        $submission = $task->submissions()->create($payload);
        if ($submission->status === 'submitted') {
            $task->update(['status' => 'submitted']);
        }
        $this->audit->record($actor, 'pkpa_special_task_submitted', $submission);
        if ($submission->status === 'submitted') {
            $this->notifications->notifyRun($run, 'pkpa_special_task_submitted', $submission, ['field_supervisor', 'internal_supervisor']);
        }

        return $submission;
    }

    public function review(PkpaSpecialTaskSubmission $submission, string $action, ?string $comments, ?User $actor): PkpaRotationSpecialTask
    {
        $task = $submission->task()->with('rotationRun.supervisorHistories')->firstOrFail();
        $run = $task->rotationRun;
        if ($task->field_supervisor_review_required_snapshot) {
            $this->ensureFieldSupervisor($run, $actor);
            $reviewer = 'field_supervisor';
        } else {
            $this->ensureInternalSupervisor($run, $actor);
            $reviewer = 'internal_supervisor';
        }
        if (! in_array($action, ['approved', 'revision_requested', 'rejected', 'marked_reviewed'], true)) {
            throw ValidationException::withMessages(['action' => 'Aksi review tugas tidak valid.']);
        }
        if (in_array($action, ['revision_requested', 'rejected'], true) && blank($comments)) {
            throw ValidationException::withMessages(['comments' => 'Catatan wajib untuk revisi atau penolakan.']);
        }
        PkpaSpecialTaskReview::create([
            'pkpa_special_task_submission_id' => $submission->id,
            'reviewer_type' => $reviewer,
            'reviewer_core_user_id' => $actor?->core_user_id ?? 'system',
            'action' => $action,
            'comments' => $comments,
            'reviewed_at' => now(),
        ]);
        $task->update(['status' => $action === 'marked_reviewed' ? 'submitted' : $action, 'completed_at' => $action === 'approved' ? now() : null]);
        $this->audit->record($actor, 'pkpa_special_task_reviewed', $submission, null, ['action' => $action]);
        $this->notifications->notifyRun($run, 'pkpa_special_task_'.$action, $submission, ['student', 'internal_supervisor']);

        return $task->refresh();
    }
}
