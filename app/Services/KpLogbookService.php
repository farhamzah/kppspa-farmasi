<?php

namespace App\Services;

use App\Models\KpAssignment;
use App\Models\KpLogbook;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class KpLogbookService
{
    public function createDraft(User $studentUser, KpAssignment $assignment, array $data): KpLogbook
    {
        $this->ensureStudentOwnsAssignment($studentUser, $assignment);
        $this->ensureAssignmentAcceptsLogbook($assignment);

        return DB::transaction(function () use ($studentUser, $assignment, $data) {
            $payload = $this->payload($data);

            if (isset($data['evidence']) && $data['evidence'] instanceof UploadedFile) {
                $payload += $this->storeEvidence($data['evidence']);
            }

            $logbook = $assignment->logbooks()->create($payload + ['status' => 'draft']);
            $this->logActivity($studentUser, $logbook, 'created', null, 'draft', 'Logbook dibuat sebagai draft.');

            if ($logbook->hasEvidence()) {
                $this->logActivity($studentUser, $logbook, 'evidence_uploaded', 'draft', 'draft', 'Bukti kegiatan ditambahkan.');
            }

            return $logbook;
        });
    }

    public function updateDraft(User $studentUser, KpLogbook $logbook, array $data): KpLogbook
    {
        $this->ensureStudentOwnsLogbook($studentUser, $logbook);

        if (! $logbook->canBeEditedByStudent()) {
            throw ValidationException::withMessages(['logbook' => 'Logbook yang sudah disetujui atau menunggu validasi tidak bisa diedit.']);
        }

        return DB::transaction(function () use ($studentUser, $logbook, $data) {
            $oldStatus = $logbook->status;
            $payload = $this->payload($data);
            $evidenceReplaced = false;
            $evidenceLinkChanged = array_key_exists('evidence_url', $payload)
                && ($logbook->evidence_url !== $payload['evidence_url']
                    || $logbook->evidence_url_label !== $payload['evidence_url_label']);

            if (isset($data['evidence']) && $data['evidence'] instanceof UploadedFile) {
                $this->deleteEvidence($logbook);
                $payload += $this->storeEvidence($data['evidence']);
                $evidenceReplaced = true;
            }

            $logbook->update($payload);
            $logbook = $logbook->fresh();

            $this->logActivity($studentUser, $logbook, 'updated', $oldStatus, $logbook->status, 'Logbook diperbarui.');
            if ($evidenceReplaced) {
                $this->logActivity($studentUser, $logbook, 'evidence_replaced', $logbook->status, $logbook->status, 'Bukti kegiatan diganti.');
            }
            if ($evidenceLinkChanged) {
                $this->logActivity($studentUser, $logbook, 'evidence_link_updated', $logbook->status, $logbook->status, 'Link bukti kegiatan diperbarui.');
            }

            return $logbook;
        });
    }

    public function submit(User $studentUser, KpLogbook $logbook): KpLogbook
    {
        $this->ensureStudentOwnsLogbook($studentUser, $logbook);

        if (! $logbook->canBeSubmitted()) {
            throw ValidationException::withMessages(['logbook' => 'Logbook ini tidak bisa disubmit pada status saat ini.']);
        }

        $oldStatus = $logbook->status;
        $logbook->update([
            'status' => 'menunggu_validasi',
            'submitted_at' => now(),
            'validation_note' => null,
        ]);

        $this->logActivity($studentUser, $logbook->fresh(), 'submitted', $oldStatus, 'menunggu_validasi', 'Logbook dikirim untuk validasi.');

        return $logbook->fresh();
    }

    public function approve(User $fieldSupervisorUser, KpLogbook $logbook, ?string $note = null): KpLogbook
    {
        $this->ensureFieldSupervisorCanReview($fieldSupervisorUser, $logbook);
        $this->ensureCanReview($logbook);

        return $this->review($fieldSupervisorUser, $logbook, 'disetujui', 'approved', $note);
    }

    public function requestRevision(User $fieldSupervisorUser, KpLogbook $logbook, string $note): KpLogbook
    {
        $this->ensureFieldSupervisorCanReview($fieldSupervisorUser, $logbook);
        $this->ensureCanReview($logbook);

        return $this->review($fieldSupervisorUser, $logbook, 'revisi', 'revision_requested', $note);
    }

    public function reject(User $fieldSupervisorUser, KpLogbook $logbook, string $note): KpLogbook
    {
        $this->ensureFieldSupervisorCanReview($fieldSupervisorUser, $logbook);
        $this->ensureCanReview($logbook);

        return $this->review($fieldSupervisorUser, $logbook, 'ditolak', 'rejected', $note);
    }

    public function addComment(User $user, KpLogbook $logbook, string $comment, string $visibility = 'visible_to_student'): void
    {
        $logbook->comments()->create([
            'user_id' => $user->id,
            'comment' => $comment,
            'visibility' => $visibility,
        ]);

        $this->logActivity($user, $logbook, 'comment_added', $logbook->status, $logbook->status, $comment, ['visibility' => $visibility]);
    }

    public function logActivity(User $user, KpLogbook $logbook, string $action, ?string $oldStatus, ?string $newStatus, ?string $note = null, ?array $metadata = null): void
    {
        $logbook->logs()->create([
            'user_id' => $user->id,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'metadata' => $metadata,
        ]);
    }

    public function ensureStudentOwnsLogbook(User $studentUser, KpLogbook $logbook): void
    {
        $logbook->loadMissing('assignment');
        $this->ensureStudentOwnsAssignment($studentUser, $logbook->assignment);
    }

    public function ensureFieldSupervisorCanReview(User $fieldSupervisorUser, KpLogbook $logbook): void
    {
        $logbook->loadMissing('assignment');

        if (! $fieldSupervisorUser->fieldSupervisor || $fieldSupervisorUser->fieldSupervisor->id !== $logbook->assignment->field_supervisor_id) {
            abort(403, 'Anda tidak berhak memvalidasi logbook ini.');
        }
    }

    public function ensureInternalSupervisorCanView(User $lecturerUser, KpLogbook $logbook): void
    {
        $logbook->loadMissing('assignment');

        if (! $lecturerUser->lecturer || $lecturerUser->lecturer->id !== $logbook->assignment->internal_supervisor_id) {
            abort(403, 'Anda tidak berhak melihat logbook ini.');
        }
    }

    public function ensureManagementCanView(User $user): void
    {
        if (! $user->hasAnyRole(['admin', 'koordinator_kp'])) {
            abort(403);
        }
    }

    public function deleteEvidence(KpLogbook $logbook): void
    {
        if ($logbook->evidence_path) {
            Storage::disk($logbook->evidence_disk ?: 'local')->delete($logbook->evidence_path);
        }
    }

    private function review(User $reviewer, KpLogbook $logbook, string $newStatus, string $action, ?string $note): KpLogbook
    {
        $oldStatus = $logbook->status;
        $logbook->update([
            'status' => $newStatus,
            'validated_by' => $reviewer->id,
            'validated_at' => now(),
            'validation_note' => $note,
        ]);

        $this->logActivity($reviewer, $logbook->fresh(), $action, $oldStatus, $newStatus, $note);

        return $logbook->fresh();
    }

    private function ensureStudentOwnsAssignment(User $studentUser, KpAssignment $assignment): void
    {
        if (! $studentUser->student || $studentUser->student->id !== $assignment->student_id) {
            abort(403, 'Anda tidak berhak mengelola logbook ini.');
        }
    }

    private function ensureAssignmentAcceptsLogbook(KpAssignment $assignment): void
    {
        if (! in_array($assignment->status, ['aktif', 'berjalan'], true)) {
            throw ValidationException::withMessages(['assignment' => 'Logbook hanya bisa dibuat jika penempatan KP aktif atau berjalan.']);
        }
    }

    private function ensureCanReview(KpLogbook $logbook): void
    {
        if (! $logbook->canBeReviewed()) {
            throw ValidationException::withMessages(['logbook' => 'Logbook belum disubmit atau sudah selesai direview.']);
        }
    }

    private function payload(array $data): array
    {
        $evidenceUrl = trim((string) ($data['evidence_url'] ?? ''));
        $evidenceLabel = trim((string) ($data['evidence_url_label'] ?? ''));

        return [
            'activity_date' => $data['activity_date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'activity_title' => $data['activity_title'],
            'activity_description' => $data['activity_description'],
            'learning_outcome' => $data['learning_outcome'] ?? null,
            'obstacle' => $data['obstacle'] ?? null,
            'solution' => $data['solution'] ?? null,
            'evidence_url' => $evidenceUrl !== '' ? $evidenceUrl : null,
            'evidence_url_label' => $evidenceUrl !== '' ? ($evidenceLabel !== '' ? $evidenceLabel : 'Link bukti kegiatan') : null,
        ];
    }

    private function storeEvidence(UploadedFile $file): array
    {
        $path = $file->store('kp-logbook-evidence', 'local');

        return [
            'evidence_original_filename' => $file->getClientOriginalName(),
            'evidence_path' => $path,
            'evidence_disk' => 'local',
            'evidence_mime' => $file->getClientMimeType(),
            'evidence_size' => $file->getSize(),
        ];
    }
}
