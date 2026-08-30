<?php

namespace App\Services;

use App\Models\PkpaAttendanceCorrectionRequest;
use App\Models\PkpaAttendanceRecord;
use App\Models\PkpaRotationRun;
use App\Models\User;
use App\Services\Concerns\AuthorizesPkpaRotationActors;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaAttendanceService
{
    use AuthorizesPkpaRotationActors;

    public function __construct(private readonly PkpaAuditService $audit, private readonly PkpaRotationProgressService $progress)
    {
    }

    public function save(PkpaRotationRun $run, array $data, ?User $actor): PkpaAttendanceRecord
    {
        $this->ensureStudentOwnsRun($run, $actor);
        $this->validateAttendance($run, $data);

        return DB::transaction(function () use ($run, $data, $actor) {
            $record = isset($data['id'])
                ? PkpaAttendanceRecord::whereKey($data['id'])->lockForUpdate()->first()
                : null;

            if ($record && (int) $record->pkpa_rotation_run_id !== (int) $run->id) {
                throw ValidationException::withMessages(['authorization' => 'Presensi tidak sesuai dengan rotasi yang sedang dibuka.']);
            }

            $dateKey = 'RUN:'.$run->id.':'.$data['attendance_date'];

            if (! $record) {
                $record = PkpaAttendanceRecord::where('pkpa_rotation_run_id', $run->id)
                    ->whereDate('attendance_date', $data['attendance_date'])
                    ->where('active_key', $dateKey)
                    ->lockForUpdate()
                    ->first();
            }

            if ($record && ! in_array($record->submission_status, ['draft', 'revision_requested'], true)) {
                throw ValidationException::withMessages(['attendance' => 'Presensi yang sudah dikirim tidak dapat diubah langsung. Ajukan koreksi.']);
            }

            $existingForDate = PkpaAttendanceRecord::where('pkpa_rotation_run_id', $run->id)
                ->whereDate('attendance_date', $data['attendance_date'])
                ->where('active_key', $dateKey)
                ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                ->lockForUpdate()
                ->first();

            if ($existingForDate) {
                throw ValidationException::withMessages(['attendance_date' => 'Presensi untuk tanggal ini sudah ada. Silakan edit entri yang sudah tersimpan.']);
            }

            $payload = [
                'attendance_date' => $data['attendance_date'],
                'attendance_type' => $data['attendance_type'] ?? 'present',
                'check_in_time' => $data['check_in_time'] ?? null,
                'check_out_time' => $data['check_out_time'] ?? null,
                'calculated_minutes' => $this->minutes($data['check_in_time'] ?? null, $data['check_out_time'] ?? null),
                'student_notes' => $data['student_notes'] ?? null,
                'source' => 'student_manual',
                'updated_by_core_user_id' => $actor?->core_user_id,
                'row_version' => ($record?->row_version ?? 0) + 1,
                'active_key' => $dateKey,
            ];

            $record = $record
                ? tap($record)->update($payload)
                : PkpaAttendanceRecord::create(array_merge($payload, [
                    'pkpa_rotation_run_id' => $run->id,
                    'submission_status' => 'draft',
                    'created_by_core_user_id' => $actor?->core_user_id,
                ]));

            $this->audit->record($actor, 'pkpa_attendance_saved', $record, null, $record->only(['attendance_date', 'submission_status']));

            return $record->refresh();
        });
    }

    public function submit(PkpaAttendanceRecord $record, ?User $actor): PkpaAttendanceRecord
    {
        $run = $record->rotationRun()->firstOrFail();
        $this->ensureStudentOwnsRun($run, $actor);
        if (! in_array($record->submission_status, ['draft', 'revision_requested'], true)) {
            throw ValidationException::withMessages(['attendance' => 'Presensi tidak dapat dikirim pada status saat ini.']);
        }

        $record->update([
            'submission_status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by_core_user_id' => $actor?->core_user_id,
            'row_version' => $record->row_version + 1,
        ]);
        $this->audit->record($actor, 'pkpa_attendance_submitted', $record);
        $this->progress->snapshot($run, 'attendance_submit');

        return $record->refresh();
    }

    public function review(PkpaAttendanceRecord $record, string $action, ?string $notes, ?User $actor): PkpaAttendanceRecord
    {
        $run = $record->rotationRun()->with('supervisorHistories')->firstOrFail();
        $this->ensureFieldSupervisor($run, $actor);
        if (! in_array($action, ['approved', 'revision_requested', 'rejected'], true)) {
            throw ValidationException::withMessages(['action' => 'Aksi validasi presensi tidak valid.']);
        }
        if (in_array($action, ['revision_requested', 'rejected'], true) && blank($notes)) {
            throw ValidationException::withMessages(['notes' => 'Catatan wajib diisi untuk revisi atau penolakan.']);
        }
        if ($record->submission_status !== 'submitted') {
            throw ValidationException::withMessages(['attendance' => 'Hanya presensi terkirim yang dapat divalidasi.']);
        }

        $record->update([
            'submission_status' => $action,
            'field_supervisor_notes' => $notes,
            'reviewed_at' => now(),
            'approved_at' => $action === 'approved' ? now() : null,
            'rejected_at' => $action === 'rejected' ? now() : null,
            'reviewed_by_core_user_id' => $actor?->core_user_id,
            'row_version' => $record->row_version + 1,
        ]);
        $this->audit->record($actor, 'pkpa_attendance_reviewed', $record, null, ['action' => $action]);
        $this->progress->snapshot($run, 'attendance_review');

        return $record->refresh();
    }

    public function requestCorrection(PkpaAttendanceRecord $record, array $proposed, string $reason, ?User $actor): PkpaAttendanceCorrectionRequest
    {
        $run = $record->rotationRun()->firstOrFail();
        $this->ensureStudentOwnsRun($run, $actor);
        if (! in_array($record->submission_status, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['attendance' => 'Koreksi hanya untuk presensi yang sudah selesai divalidasi.']);
        }
        $this->validateAttendance($run, array_merge($record->only(['attendance_date']), $proposed));

        return PkpaAttendanceCorrectionRequest::create([
            'pkpa_attendance_record_id' => $record->id,
            'request_type' => 'student_correction',
            'status' => 'submitted',
            'reason' => $reason,
            'before_snapshot' => $record->only(['attendance_date', 'attendance_type', 'check_in_time', 'check_out_time', 'calculated_minutes', 'student_notes']),
            'proposed_snapshot' => $proposed,
            'requested_by_core_user_id' => $actor?->core_user_id,
            'requested_at' => now(),
        ]);
    }

    public function deleteDraft(PkpaAttendanceRecord $record, ?User $actor): void
    {
        $run = $record->rotationRun()->firstOrFail();
        $this->ensureStudentOwnsRun($run, $actor);

        if (! in_array($record->submission_status, ['draft', 'revision_requested'], true)) {
            throw ValidationException::withMessages(['attendance' => 'Presensi yang sudah dikirim tidak dapat dihapus langsung.']);
        }

        $record->delete();
        $this->audit->record($actor, 'pkpa_attendance_deleted', $record, null, ['attendance_date' => $record->attendance_date?->toDateString()]);
    }

    public function reviewCorrection(PkpaAttendanceCorrectionRequest $request, string $action, ?string $notes, ?User $actor): PkpaAttendanceCorrectionRequest
    {
        $record = $request->attendanceRecord()->with('rotationRun.supervisorHistories')->firstOrFail();
        $this->ensureFieldSupervisor($record->rotationRun, $actor);
        if (! in_array($action, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['action' => 'Aksi koreksi tidak valid.']);
        }

        $request->update([
            'status' => $action,
            'reviewed_by_core_user_id' => $actor?->core_user_id,
            'approved_by_core_user_id' => $action === 'approved' ? $actor?->core_user_id : null,
            'rejected_by_core_user_id' => $action === 'rejected' ? $actor?->core_user_id : null,
            'reviewed_at' => now(),
            'approved_at' => $action === 'approved' ? now() : null,
            'rejected_at' => $action === 'rejected' ? now() : null,
            'rejection_reason' => $action === 'rejected' ? $notes : null,
        ]);
        if ($action === 'approved') {
            $this->applyCorrection($request->refresh(), $actor);
        }

        return $request->refresh();
    }

    private function applyCorrection(PkpaAttendanceCorrectionRequest $request, ?User $actor): void
    {
        if ($request->applied_at || $request->status !== 'approved') {
            return;
        }
        $record = $request->attendanceRecord()->firstOrFail();
        $proposed = $request->proposed_snapshot ?? [];
        $record->update(array_merge($proposed, [
            'calculated_minutes' => $this->minutes($proposed['check_in_time'] ?? $record->check_in_time, $proposed['check_out_time'] ?? $record->check_out_time),
            'updated_by_core_user_id' => $actor?->core_user_id,
            'row_version' => $record->row_version + 1,
        ]));
        $request->update(['applied_at' => now()]);
        $this->audit->record($actor, 'pkpa_attendance_correction_applied', $record);
    }

    private function validateAttendance(PkpaRotationRun $run, array $data): void
    {
        $date = Carbon::parse($data['attendance_date'] ?? null);
        if ($date->isFuture()) {
            throw ValidationException::withMessages(['attendance_date' => 'Tanggal presensi tidak boleh melebihi hari ini.']);
        }
        if ($date->lt($run->scheduled_start_date) || $date->gt($run->scheduled_end_date)) {
            throw ValidationException::withMessages(['attendance_date' => 'Tanggal presensi harus berada dalam periode rotasi.']);
        }
        if (($data['attendance_type'] ?? 'present') === 'present' && isset($data['check_in_time'], $data['check_out_time']) && $data['check_out_time'] <= $data['check_in_time']) {
            throw ValidationException::withMessages(['check_out_time' => 'Jam pulang harus setelah jam masuk.']);
        }
    }

    private function minutes(?string $start, ?string $end): ?int
    {
        if (! $start || ! $end || $end <= $start) {
            return null;
        }

        return Carbon::parse($start)->diffInMinutes(Carbon::parse($end));
    }
}
