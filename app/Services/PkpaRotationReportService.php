<?php

namespace App\Services;

use App\Models\PkpaRotationReport;
use App\Models\PkpaRotationReportTemplate;
use App\Models\PkpaRotationReportVersion;
use App\Models\PkpaRotationRun;
use App\Models\User;
use App\Services\Concerns\AuthorizesPkpaRotationActors;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class PkpaRotationReportService
{
    use AuthorizesPkpaRotationActors;

    public function __construct(
        private readonly PkpaAuditService $audit,
        private readonly PkpaAcademicFileService $files,
        private readonly PkpaAcademicNotificationService $notifications
    )
    {
    }

    public function saveTemplate($programDomain, array $data, ?User $actor): PkpaRotationReportTemplate
    {
        if (! $this->isCoordinator($actor)) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat mengelola template laporan.']);
        }
        if (($data['is_current'] ?? false) && ($data['status'] ?? 'draft') === 'active') {
            PkpaRotationReportTemplate::where('pkpa_program_domain_id', $programDomain->id)->where('is_current', true)->update(['is_current' => false, 'current_key' => null]);
            $data['current_key'] = 'PROGRAM_DOMAIN:'.$programDomain->id;
        }

        return tap(PkpaRotationReportTemplate::updateOrCreate(
            ['pkpa_program_domain_id' => $programDomain->id, 'code' => $data['code']],
            array_merge($data, ['created_by_core_user_id' => $actor?->core_user_id, 'updated_by_core_user_id' => $actor?->core_user_id])
        ), fn ($template) => $this->audit->record($actor, 'pkpa_rotation_report_template_saved', $template));
    }

    public function reportForRun(PkpaRotationRun $run, ?User $actor): PkpaRotationReport
    {
        $this->ensureStudentOwnsRun($run, $actor);
        $run->loadMissing('requirement.programDomain.activeReportTemplate');
        $template = $run->requirement?->programDomain?->activeReportTemplate;

        return PkpaRotationReport::firstOrCreate(
            ['pkpa_rotation_run_id' => $run->id],
            [
                'source_report_template_id' => $template?->id,
                'report_code' => 'REPORT-RUN-'.$run->id,
                'title' => $template?->name ?? 'Laporan Rotasi '.$run->practiceDomain?->name,
                'status' => 'draft',
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]
        );
    }

    public function uploadVersion(PkpaRotationReport $report, UploadedFile $file, array $data, ?User $actor): PkpaRotationReportVersion
    {
        $run = $report->rotationRun()->firstOrFail();
        $this->ensureStudentOwnsRun($run, $actor);
        if (in_array($report->status, ['approved', 'locked'], true)) {
            throw ValidationException::withMessages(['report' => 'Laporan yang sudah approved tidak dapat diedit langsung.']);
        }
        $version = (int) $report->versions()->max('version_number') + 1;
        $template = $report->source_report_template_id ? PkpaRotationReportTemplate::find($report->source_report_template_id) : null;
        $stored = $this->files->store($file, 'pkpa-academic/reports/'.$report->id, $template?->allowed_file_types, $template?->maximum_file_size_kb);
        $reportVersion = $report->versions()->create(array_merge($stored, [
            'version_number' => $version,
            'change_summary' => $data['change_summary'] ?? null,
            'submission_notes' => $data['submission_notes'] ?? null,
            'status' => 'draft',
            'uploaded_by_core_user_id' => $actor?->core_user_id,
        ]));
        $report->update(['current_version_id' => $reportVersion->id, 'row_version' => $report->row_version + 1]);
        $this->audit->record($actor, 'pkpa_rotation_report_version_uploaded', $reportVersion);

        return $reportVersion;
    }

    public function submit(PkpaRotationReport $report, ?User $actor): PkpaRotationReport
    {
        $run = $report->rotationRun()->firstOrFail();
        $this->ensureStudentOwnsRun($run, $actor);
        $version = $report->currentVersion()->first();
        if (! $version) {
            throw ValidationException::withMessages(['version' => 'Unggah versi laporan terlebih dahulu.']);
        }
        $version->update(['status' => 'submitted', 'submitted_at' => now()]);
        $report->update(['status' => 'submitted', 'submitted_at' => now(), 'locked_at' => now(), 'row_version' => $report->row_version + 1]);
        $this->audit->record($actor, 'pkpa_rotation_report_submitted', $report);
        $this->notifications->notifyRun($run, 'pkpa_rotation_report_submitted', $report, ['field_supervisor', 'internal_supervisor']);

        return $report->refresh();
    }

    public function fieldConfirm(PkpaRotationReport $report, ?string $comments, ?User $actor): PkpaRotationReport
    {
        $run = $report->rotationRun()->with('supervisorHistories')->firstOrFail();
        $this->ensureFieldSupervisor($run, $actor);
        if (! in_array($report->status, ['submitted', 'field_review'], true)) {
            throw ValidationException::withMessages(['report' => 'Laporan belum siap dikonfirmasi Preseptor.']);
        }
        $report->update(['status' => 'internal_review', 'field_confirmed_at' => now(), 'field_confirmed_by_core_user_id' => $actor?->core_user_id, 'row_version' => $report->row_version + 1]);
        $this->audit->record($actor, 'pkpa_rotation_report_field_confirmed', $report, null, ['comments' => $comments]);
        $this->notifications->notifyRun($run, 'pkpa_rotation_report_field_confirmed', $report, ['student', 'internal_supervisor']);

        return $report->refresh();
    }

    public function internalReview(PkpaRotationReport $report, string $action, ?string $comments, ?User $actor): PkpaRotationReport
    {
        $run = $report->rotationRun()->with('supervisorHistories')->firstOrFail();
        $this->ensureInternalSupervisor($run, $actor);
        if (! in_array($action, ['approved', 'revision_requested', 'rejected'], true)) {
            throw ValidationException::withMessages(['action' => 'Aksi review laporan tidak valid.']);
        }
        if (in_array($action, ['revision_requested', 'rejected'], true) && blank($comments)) {
            throw ValidationException::withMessages(['comments' => 'Catatan wajib untuk revisi atau penolakan laporan.']);
        }
        $report->update([
            'status' => $action,
            'internal_approved_at' => $action === 'approved' ? now() : null,
            'revision_requested_at' => $action === 'revision_requested' ? now() : null,
            'internal_approved_by_core_user_id' => $action === 'approved' ? $actor?->core_user_id : null,
            'locked_at' => $action === 'approved' ? now() : null,
            'row_version' => $report->row_version + 1,
        ]);
        $this->audit->record($actor, 'pkpa_rotation_report_internal_reviewed', $report, null, ['action' => $action, 'comments' => $comments]);
        $this->notifications->notifyRun($run, 'pkpa_rotation_report_'.$action, $report, ['student', 'field_supervisor']);

        return $report->refresh();
    }

    public function downloadVersion(PkpaRotationReportVersion $version, ?User $actor)
    {
        $report = $version->report()->with('rotationRun.supervisorHistories')->firstOrFail();
        $run = $report->rotationRun;
        if (! $this->isCoordinator($actor) && (! $actor || ($run->student_core_user_id !== $actor->core_user_id && ! $run->supervisorHistories->contains(fn ($s) => $s->core_user_id === $actor->core_user_id && $s->status === 'active')))) {
            throw ValidationException::withMessages(['authorization' => 'Akses file laporan rotasi tidak valid.']);
        }
        $this->audit->record($actor, 'pkpa_rotation_report_file_downloaded', $version);

        return $this->files->download($version->disk, $version->path, $version->original_filename);
    }
}
