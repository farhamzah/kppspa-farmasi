<?php

namespace App\Services;

use App\Models\KpAssignment;
use App\Models\KpFinalReport;
use App\Models\KpFinalReportFile;
use App\Models\KpReportGuidanceLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KpFinalReportService
{
    public function createOrGetReport(User $studentUser, KpAssignment $assignment): KpFinalReport
    {
        $this->ensureStudentOwnsAssignment($studentUser, $assignment);
        $this->ensureAssignmentAcceptsReport($assignment);

        return DB::transaction(function () use ($studentUser, $assignment) {
            $report = KpFinalReport::firstOrCreate(
                ['kp_assignment_id' => $assignment->id],
                ['current_version' => 1, 'status' => 'draft']
            );

            if ($report->wasRecentlyCreated) {
                $this->logActivity($studentUser, $report, 'created', null, 'draft', 'Draft laporan akhir dibuat.');
            }

            return $report;
        });
    }

    public function uploadFile(User $studentUser, KpFinalReport $report, UploadedFile $file, ?string $note = null): KpFinalReportFile
    {
        $report->loadMissing('assignment');
        $this->ensureStudentOwnsAssignment($studentUser, $report->assignment);

        if (! $report->canBeEditedByStudent()) {
            throw ValidationException::withMessages(['report' => 'Laporan yang sudah disetujui atau menunggu review tidak bisa diubah.']);
        }

        return DB::transaction(function () use ($studentUser, $report, $file, $note) {
            $hadFile = $report->files()->exists();
            $version = $hadFile ? $report->files()->max('version') + 1 : 1;
            $path = $file->store('kp-final-reports', 'local');

            $reportFile = $report->files()->create([
                'version' => $version,
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_disk' => 'local',
                'file_mime' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $studentUser->id,
                'uploaded_at' => now(),
                'note' => $note,
            ]);

            $oldStatus = $report->status;
            $report->update([
                'current_version' => $version,
                'status' => 'draft',
                'review_note' => $oldStatus === 'draft' ? $report->review_note : null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'approved_at' => null,
                'internal_review_status' => 'pending',
                'internal_reviewed_by' => null,
                'internal_reviewed_at' => null,
                'internal_review_note' => null,
                'field_review_status' => 'pending',
                'field_reviewed_by' => null,
                'field_reviewed_at' => null,
                'field_review_note' => null,
            ]);

            $this->logActivity($studentUser, $report->fresh(), $hadFile ? 'revision_uploaded' : 'uploaded', $oldStatus, 'draft', $note, ['version' => $version]);

            return $reportFile;
        });
    }

    public function submit(User $studentUser, KpFinalReport $report): KpFinalReport
    {
        $report->loadMissing('assignment');
        $this->ensureStudentOwnsAssignment($studentUser, $report->assignment);

        if (! $report->files()->exists() && blank($report->final_document_url)) {
            throw ValidationException::withMessages(['file' => 'Upload file atau link laporan final terlebih dahulu sebelum submit.']);
        }
        if (! $report->canBeEditedByStudent()) {
            throw ValidationException::withMessages(['report' => 'Laporan tidak bisa disubmit pada status saat ini.']);
        }

        $oldStatus = $report->status;
        $report->update([
            'status' => 'menunggu_review',
            'submitted_at' => now(),
            'internal_review_status' => 'pending',
            'internal_reviewed_by' => null,
            'internal_reviewed_at' => null,
            'internal_review_note' => null,
            'field_review_status' => 'pending',
            'field_reviewed_by' => null,
            'field_reviewed_at' => null,
            'field_review_note' => null,
        ]);
        $this->logActivity($studentUser, $report->fresh(), 'submitted', $oldStatus, 'menunggu_review', 'Laporan dikirim untuk review.');

        return $report->fresh();
    }

    public function approve(User $lecturerUser, KpFinalReport $report, ?string $note = null): KpFinalReport
    {
        $this->ensureLecturerCanReview($lecturerUser, $report);
        $this->ensureCanReview($report);

        return $this->reviewByRole($lecturerUser, $report, 'internal', 'disetujui', 'internal_approved', $note);
    }

    public function requestRevision(User $lecturerUser, KpFinalReport $report, string $note): KpFinalReport
    {
        $this->ensureLecturerCanReview($lecturerUser, $report);
        $this->ensureCanReview($report);

        return $this->reviewByRole($lecturerUser, $report, 'internal', 'revisi', 'internal_revision_requested', $note);
    }

    public function reject(User $lecturerUser, KpFinalReport $report, string $note): KpFinalReport
    {
        $this->ensureLecturerCanReview($lecturerUser, $report);
        $this->ensureCanReview($report);

        return $this->reviewByRole($lecturerUser, $report, 'internal', 'ditolak', 'internal_rejected', $note);
    }

    public function approveByFieldSupervisor(User $fieldUser, KpFinalReport $report, ?string $note = null): KpFinalReport
    {
        $this->ensureFieldSupervisorCanReview($fieldUser, $report);
        $this->ensureCanReview($report);

        return $this->reviewByRole($fieldUser, $report, 'field', 'disetujui', 'field_approved', $note);
    }

    public function requestRevisionByFieldSupervisor(User $fieldUser, KpFinalReport $report, string $note): KpFinalReport
    {
        $this->ensureFieldSupervisorCanReview($fieldUser, $report);
        $this->ensureCanReview($report);

        return $this->reviewByRole($fieldUser, $report, 'field', 'revisi', 'field_revision_requested', $note);
    }

    public function rejectByFieldSupervisor(User $fieldUser, KpFinalReport $report, string $note): KpFinalReport
    {
        $this->ensureFieldSupervisorCanReview($fieldUser, $report);
        $this->ensureCanReview($report);

        return $this->reviewByRole($fieldUser, $report, 'field', 'ditolak', 'field_rejected', $note);
    }

    public function saveFinalDocumentLink(User $studentUser, KpFinalReport $report, string $url, ?string $label = null): KpFinalReport
    {
        $report->loadMissing('assignment');
        $this->ensureStudentOwnsAssignment($studentUser, $report->assignment);

        if (! $report->canBeEditedByStudent()) {
            throw ValidationException::withMessages(['final_document_url' => 'Link final hanya bisa diubah pada status draft, revisi, atau ditolak.']);
        }

        $oldStatus = $report->status;
        $report->update([
            'final_document_url' => $url,
            'final_document_label' => $label,
            'status' => 'draft',
            'internal_review_status' => 'pending',
            'internal_reviewed_by' => null,
            'internal_reviewed_at' => null,
            'internal_review_note' => null,
            'field_review_status' => 'pending',
            'field_reviewed_by' => null,
            'field_reviewed_at' => null,
            'field_review_note' => null,
            'approved_at' => null,
        ]);

        $this->logActivity($studentUser, $report->fresh(), 'final_document_link_saved', $oldStatus, 'draft', $label, ['url' => $url]);

        return $report->fresh();
    }

    public function addGuidanceLog(User $studentUser, KpAssignment $assignment, array $data): KpReportGuidanceLog
    {
        $this->ensureStudentOwnsAssignment($studentUser, $assignment);
        $this->ensureAssignmentAcceptsReport($assignment);

        return $assignment->reportGuidanceLogs()->create([
            'guidance_date' => $data['guidance_date'],
            'topic' => $data['topic'],
            'student_note' => $data['student_note'] ?? null,
            'status' => 'menunggu_validasi',
            'submitted_at' => now(),
        ]);
    }

    public function approveGuidance(User $lecturerUser, KpReportGuidanceLog $guidance, ?string $note = null): KpReportGuidanceLog
    {
        $this->ensureLecturerCanReviewGuidance($lecturerUser, $guidance);

        $guidance->update([
            'status' => 'disetujui',
            'validated_by' => $lecturerUser->id,
            'validated_at' => now(),
            'validation_note' => $note,
        ]);

        return $guidance->fresh();
    }

    public function requestGuidanceRevision(User $lecturerUser, KpReportGuidanceLog $guidance, string $note): KpReportGuidanceLog
    {
        $this->ensureLecturerCanReviewGuidance($lecturerUser, $guidance);

        $guidance->update([
            'status' => 'revisi',
            'validated_by' => $lecturerUser->id,
            'validated_at' => now(),
            'validation_note' => $note,
        ]);

        return $guidance->fresh();
    }

    public function logActivity(User $user, KpFinalReport $report, string $action, ?string $oldStatus, ?string $newStatus, ?string $note = null, ?array $metadata = null): void
    {
        $report->logs()->create([
            'user_id' => $user->id,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'metadata' => $metadata,
        ]);
    }

    public function ensureStudentOwnsReport(User $studentUser, KpFinalReport $report): void
    {
        $report->loadMissing('assignment');
        $this->ensureStudentOwnsAssignment($studentUser, $report->assignment);
    }

    public function ensureLecturerCanReview(User $lecturerUser, KpFinalReport $report): void
    {
        $report->loadMissing('assignment');
        if (! $lecturerUser->lecturer || $lecturerUser->lecturer->id !== $report->assignment->internal_supervisor_id) {
            abort(403, 'Anda tidak berhak mereview laporan ini.');
        }
    }

    public function ensureFieldSupervisorCanReview(User $fieldUser, KpFinalReport $report): void
    {
        $report->loadMissing('assignment');
        if (! $fieldUser->fieldSupervisor || $fieldUser->fieldSupervisor->id !== $report->assignment->field_supervisor_id) {
            abort(403, 'Anda tidak berhak mereview laporan ini.');
        }
    }

    public function ensureStudentCanDownload(User $studentUser, KpFinalReportFile $file): void
    {
        $file->loadMissing('report.assignment');
        $this->ensureStudentOwnsAssignment($studentUser, $file->report->assignment);
    }

    public function ensureLecturerCanDownload(User $lecturerUser, KpFinalReportFile $file): void
    {
        $file->loadMissing('report.assignment');
        if (! $lecturerUser->lecturer || $lecturerUser->lecturer->id !== $file->report->assignment->internal_supervisor_id) {
            abort(403);
        }
    }

    public function ensureFieldSupervisorCanDownload(User $fieldUser, KpFinalReportFile $file): void
    {
        $file->loadMissing('report.assignment');
        if (! $fieldUser->fieldSupervisor || $fieldUser->fieldSupervisor->id !== $file->report->assignment->field_supervisor_id) {
            abort(403);
        }
    }

    private function reviewByRole(User $reviewer, KpFinalReport $report, string $role, string $reviewStatus, string $action, ?string $note): KpFinalReport
    {
        $oldStatus = $report->status;
        $prefix = $role === 'field' ? 'field' : 'internal';
        $oppositePrefix = $prefix === 'field' ? 'internal' : 'field';
        $oppositeStatus = $report->{$oppositePrefix.'_review_status'};
        $newStatus = match (true) {
            $reviewStatus === 'ditolak' => 'ditolak',
            $reviewStatus === 'revisi' => 'revisi',
            $reviewStatus === 'disetujui' && $oppositeStatus === 'disetujui' => 'disetujui',
            default => 'menunggu_review',
        };

        $payload = [
            'status' => $newStatus,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
            $prefix.'_review_status' => $reviewStatus,
            $prefix.'_reviewed_by' => $reviewer->id,
            $prefix.'_reviewed_at' => now(),
            $prefix.'_review_note' => $note,
            'approved_at' => $newStatus === 'disetujui' ? now() : null,
        ];

        $report->update($payload);
        $this->logActivity($reviewer, $report->fresh(), $action, $oldStatus, $newStatus, $note, ['reviewer_role' => $prefix]);

        return $report->fresh();
    }

    private function ensureLecturerCanReviewGuidance(User $lecturerUser, KpReportGuidanceLog $guidance): void
    {
        $guidance->loadMissing('assignment');
        if (! $lecturerUser->lecturer || $lecturerUser->lecturer->id !== $guidance->assignment->internal_supervisor_id) {
            abort(403, 'Anda tidak berhak memvalidasi bimbingan laporan ini.');
        }
    }

    private function ensureStudentOwnsAssignment(User $studentUser, KpAssignment $assignment): void
    {
        if (! $studentUser->student || $studentUser->student->id !== $assignment->student_id) {
            abort(403, 'Anda tidak berhak mengelola laporan ini.');
        }
    }

    private function ensureAssignmentAcceptsReport(KpAssignment $assignment): void
    {
        if (! in_array($assignment->status, ['aktif', 'berjalan'], true)) {
            throw ValidationException::withMessages(['assignment' => 'Laporan akhir hanya bisa dibuat jika penempatan KP aktif atau berjalan.']);
        }
    }

    private function ensureCanReview(KpFinalReport $report): void
    {
        if ($report->status !== 'menunggu_review') {
            throw ValidationException::withMessages(['report' => 'Laporan belum disubmit atau sudah selesai direview.']);
        }
    }
}
