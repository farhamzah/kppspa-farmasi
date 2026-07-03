<?php

namespace App\Services;

use App\Models\KpAssignment;
use App\Models\KpExam;
use App\Models\KpExamRequest;
use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KpExamService
{
    public function submitRequest(User $studentUser, KpAssignment $assignment, ?string $note = null): KpExamRequest
    {
        $this->ensureStudentOwnsAssignment($studentUser, $assignment);
        $assignment->loadMissing('finalReport');

        if (! $assignment->isEligibleForExamRequest()) {
            $pending = collect($assignment->examEligibility()['items'])->first(fn (array $item): bool => ! $item['ready']);
            throw ValidationException::withMessages([
                'exam' => 'Pengajuan sidang belum bisa dilakukan. Lengkapi: '.($pending['label'] ?? 'syarat sidang').'.',
            ]);
        }
        if ($assignment->examRequest()->whereNotIn('status', ['ditolak', 'dibatalkan'])->exists()) {
            throw ValidationException::withMessages(['exam' => 'Pengajuan sidang untuk penempatan ini sudah ada.']);
        }

        return DB::transaction(function () use ($studentUser, $assignment, $note) {
            $request = KpExamRequest::create([
                'kp_assignment_id' => $assignment->id,
                'requested_by' => $studentUser->id,
                'status' => 'diajukan',
                'request_note' => $note,
                'submitted_at' => now(),
            ]);

            $this->logActivity($studentUser, $request, null, 'request_submitted', null, 'diajukan', $note);

            return $request;
        });
    }

    public function approveRequest(User $actor, KpExamRequest $request, ?string $note = null): KpExamRequest
    {
        $old = $request->status;
        $request->update(['status' => 'disetujui', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note]);
        $this->logActivity($actor, $request->fresh(), null, 'request_approved', $old, 'disetujui', $note);

        return $request->fresh();
    }

    public function requestRevision(User $actor, KpExamRequest $request, string $note): KpExamRequest
    {
        $old = $request->status;
        $request->update(['status' => 'revisi', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note]);
        $this->logActivity($actor, $request->fresh(), null, 'request_revision_requested', $old, 'revisi', $note);

        return $request->fresh();
    }

    public function rejectRequest(User $actor, KpExamRequest $request, string $note): KpExamRequest
    {
        $old = $request->status;
        $request->update(['status' => 'ditolak', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note]);
        $this->logActivity($actor, $request->fresh(), null, 'request_rejected', $old, 'ditolak', $note);

        return $request->fresh();
    }

    public function cancelRequest(User $actor, KpExamRequest $request, ?string $note = null): KpExamRequest
    {
        if (! in_array($request->status, ['draft', 'diajukan', 'revisi'], true)) {
            throw ValidationException::withMessages(['request' => 'Pengajuan sidang ini tidak bisa dibatalkan.']);
        }

        $old = $request->status;
        $request->update(['status' => 'dibatalkan', 'review_note' => $note]);
        $this->logActivity($actor, $request->fresh(), null, 'request_cancelled', $old, 'dibatalkan', $note);

        return $request->fresh();
    }

    public function scheduleExam(User $actor, KpExamRequest $request, array $data): KpExam
    {
        return DB::transaction(function () use ($actor, $request, $data) {
            $request = KpExamRequest::with('assignment')->lockForUpdate()->findOrFail($request->id);
            if (! $request->canBeScheduled()) {
                throw ValidationException::withMessages(['request' => 'Pengajuan ini tidak bisa dijadwalkan pada status saat ini.']);
            }
            if ($request->exam()->exists()) {
                throw ValidationException::withMessages(['request' => 'Sidang untuk pengajuan ini sudah dijadwalkan.']);
            }

            $assignment = $request->assignment;
            if (! $assignment->internal_supervisor_id) {
                throw ValidationException::withMessages(['supervisor_id' => 'Pembimbing dalam belum ditentukan.']);
            }

            $examiner = Lecturer::findOrFail($data['examiner_id']);
            $this->ensureExaminer($examiner, $assignment->internal_supervisor_id);

            $exam = KpExam::create($this->examPayload($request, $assignment, $actor, $data));
            $oldRequestStatus = $request->status;
            $request->update(['status' => 'dijadwalkan', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            $this->logActivity($actor, $request, $exam, 'exam_scheduled', $oldRequestStatus, 'dijadwalkan', $data['note'] ?? null, ['exam_date' => $data['exam_date']]);

            return $exam;
        });
    }

    public function rescheduleExam(User $actor, KpExam $exam, array $data): KpExam
    {
        return DB::transaction(function () use ($actor, $exam, $data) {
            $exam = KpExam::lockForUpdate()->findOrFail($exam->id);
            if (! $exam->canBeRescheduled()) {
                throw ValidationException::withMessages(['exam' => 'Sidang ini tidak bisa dijadwalkan ulang.']);
            }
            $examiner = Lecturer::findOrFail($data['examiner_id']);
            $this->ensureExaminer($examiner, $exam->supervisor_id);
            $oldStatus = $exam->status;
            $exam->update([
                'examiner_id' => $examiner->id,
                'exam_date' => $data['exam_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'mode' => $data['mode'],
                'room' => $data['room'] ?? null,
                'meeting_link' => $data['meeting_link'] ?? null,
                'status' => 'dijadwalkan',
                'note' => $data['note'] ?? null,
            ]);
            $this->logActivity($actor, $exam->request, $exam->fresh(), 'exam_rescheduled', $oldStatus, 'dijadwalkan', $data['note'] ?? null);

            return $exam->fresh();
        });
    }

    public function cancelExam(User $actor, KpExam $exam, string $reason): void
    {
        $old = $exam->status;
        $exam->update(['status' => 'dibatalkan', 'note' => $reason]);
        $this->logActivity($actor, $exam->request, $exam->fresh(), 'exam_cancelled', $old, 'dibatalkan', $reason);
    }

    public function completeExam(User $actor, KpExam $exam, ?string $note = null): void
    {
        $old = $exam->status;
        $exam->update(['status' => 'selesai', 'note' => $note]);
        $this->logActivity($actor, $exam->request, $exam->fresh(), 'exam_completed', $old, 'selesai', $note);
    }

    public function logActivity(User $user, KpExamRequest $request, ?KpExam $exam, string $action, ?string $oldStatus, ?string $newStatus, ?string $note = null, ?array $metadata = null): void
    {
        $request->logs()->create([
            'kp_exam_id' => $exam?->id,
            'user_id' => $user->id,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'metadata' => $metadata,
        ]);
    }

    private function ensureStudentOwnsAssignment(User $studentUser, KpAssignment $assignment): void
    {
        if (! $studentUser->student || $studentUser->student->id !== $assignment->student_id) {
            abort(403, 'Anda tidak berhak mengajukan sidang untuk penempatan ini.');
        }
    }

    private function ensureExaminer(Lecturer $examiner, int $supervisorId): void
    {
        if ($examiner->id === $supervisorId) {
            throw ValidationException::withMessages(['examiner_id' => 'Penguji tidak boleh sama dengan Pembimbing Dalam.']);
        }
        if (! $examiner->user?->hasRole('penguji')) {
            throw ValidationException::withMessages(['examiner_id' => 'Penguji harus memiliki role Penguji.']);
        }
    }

    private function examPayload(KpExamRequest $request, KpAssignment $assignment, User $actor, array $data): array
    {
        return [
            'kp_exam_request_id' => $request->id,
            'kp_assignment_id' => $assignment->id,
            'supervisor_id' => $assignment->internal_supervisor_id,
            'examiner_id' => $data['examiner_id'],
            'exam_date' => $data['exam_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'mode' => $data['mode'],
            'room' => $data['room'] ?? null,
            'meeting_link' => $data['meeting_link'] ?? null,
            'status' => 'dijadwalkan',
            'scheduled_by' => $actor->id,
            'scheduled_at' => now(),
            'note' => $data['note'] ?? null,
        ];
    }
}
