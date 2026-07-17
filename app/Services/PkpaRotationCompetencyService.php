<?php

namespace App\Services;

use App\Models\PkpaAttendanceRecord;
use App\Models\PkpaLogbookEntry;
use App\Models\PkpaRotationCompetencyEvidence;
use App\Models\PkpaRotationCompetencyRecord;
use App\Models\PkpaRotationCompetencyReview;
use App\Models\PkpaRotationRun;
use App\Models\User;
use App\Services\Concerns\AuthorizesPkpaRotationActors;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaRotationCompetencyService
{
    use AuthorizesPkpaRotationActors;

    public function __construct(
        private readonly PkpaAuditService $audit,
        private readonly PkpaAcademicFileService $files,
        private readonly PkpaAcademicNotificationService $notifications
    )
    {
    }

    public function ensureChecklist(PkpaRotationRun $run, ?User $actor): int
    {
        if (! $this->isCoordinator($actor)) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat membuat checklist kompetensi.']);
        }
        $run->loadMissing('requirement.programDomain.activeCompetencySet.items');
        $set = $run->requirement?->programDomain?->activeCompetencySet;
        if (! $set) {
            throw ValidationException::withMessages(['competency_set' => 'Set kompetensi aktif belum tersedia untuk wahana ini.']);
        }

        $created = 0;
        DB::transaction(function () use ($run, $set, &$created) {
            foreach ($set->items()->where('is_active', true)->get() as $item) {
                $record = PkpaRotationCompetencyRecord::firstOrCreate(
                    ['pkpa_rotation_run_id' => $run->id, 'source_competency_item_id' => $item->id],
                    [
                        'source_competency_set_id' => $set->id,
                        'competency_code_snapshot' => $item->code,
                        'competency_title_snapshot' => $item->title,
                        'competency_description_snapshot' => $item->description,
                        'achievement_criteria_snapshot' => $item->achievement_criteria,
                        'is_required_snapshot' => $item->is_required,
                        'evidence_required_snapshot' => $item->evidence_required,
                        'minimum_evidence_count_snapshot' => $item->minimum_evidence_count,
                        'status' => 'pending',
                    ]
                );
                $created += $record->wasRecentlyCreated ? 1 : 0;
            }
        });
        $this->audit->record($actor, 'pkpa_competency_checklist_ensured', $run, null, ['created' => $created]);

        return $created;
    }

    public function markInProgress(PkpaRotationCompetencyRecord $record, ?string $notes, ?User $actor): PkpaRotationCompetencyRecord
    {
        $run = $record->rotationRun()->firstOrFail();
        $this->ensureStudentOwnsRun($run, $actor);
        if ($record->status === 'verified') {
            throw ValidationException::withMessages(['competency' => 'Kompetensi yang sudah verified tidak dapat diedit mahasiswa.']);
        }
        $record->update(['status' => 'in_progress', 'student_notes' => $notes, 'demonstrated_at' => now(), 'row_version' => $record->row_version + 1]);

        return $record->refresh();
    }

    public function addEvidence(PkpaRotationCompetencyRecord $record, array $data, ?UploadedFile $file, ?User $actor): PkpaRotationCompetencyEvidence
    {
        $run = $record->rotationRun()->firstOrFail();
        $this->ensureStudentOwnsRun($run, $actor);
        if ($record->status === 'verified') {
            throw ValidationException::withMessages(['competency' => 'Kompetensi verified tidak dapat ditambah bukti oleh mahasiswa.']);
        }
        $type = $data['evidence_type'] ?? ($file ? 'file' : 'text_note');
        $payload = [
            'evidence_type' => $type,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'uploaded_by_core_user_id' => $actor?->core_user_id,
            'status' => 'active',
        ];
        if ($type === 'logbook_reference') {
            $entry = PkpaLogbookEntry::whereKey($data['logbook_entry_id'] ?? null)->firstOrFail();
            if ((int) $entry->pkpa_rotation_run_id !== (int) $run->id) {
                throw ValidationException::withMessages(['logbook_entry_id' => 'Logbook referensi harus berasal dari rotasi yang sama.']);
            }
            $payload['logbook_entry_id'] = $entry->id;
        } elseif ($type === 'attendance_reference') {
            $attendance = PkpaAttendanceRecord::whereKey($data['attendance_record_id'] ?? null)->firstOrFail();
            if ((int) $attendance->pkpa_rotation_run_id !== (int) $run->id) {
                throw ValidationException::withMessages(['attendance_record_id' => 'Presensi referensi harus berasal dari rotasi yang sama.']);
            }
            $payload['attendance_record_id'] = $attendance->id;
        } elseif ($type === 'external_reference') {
            $url = $data['external_reference_url'] ?? '';
            if (! filter_var($url, FILTER_VALIDATE_URL) || preg_match('/^\s*javascript:/i', $url)) {
                throw ValidationException::withMessages(['external_reference_url' => 'URL bukti tidak valid.']);
            }
            $payload['external_reference_url'] = $url;
        } elseif ($file) {
            $payload = array_merge($payload, $this->files->store($file, 'pkpa-academic/competencies/'.$record->id));
        }

        $evidence = $record->evidences()->create($payload);
        $this->audit->record($actor, 'pkpa_competency_evidence_uploaded', $evidence);

        return $evidence;
    }

    public function submit(PkpaRotationCompetencyRecord $record, ?User $actor): PkpaRotationCompetencyRecord
    {
        $run = $record->rotationRun()->firstOrFail();
        $this->ensureStudentOwnsRun($run, $actor);
        $count = $record->evidences()->where('status', 'active')->count();
        if ($record->evidence_required_snapshot && $count < $record->minimum_evidence_count_snapshot) {
            throw ValidationException::withMessages(['evidence' => 'Bukti kompetensi belum memenuhi jumlah minimum.']);
        }
        if ($record->status === 'verified') {
            throw ValidationException::withMessages(['competency' => 'Kompetensi verified tidak dapat dikirim ulang.']);
        }
        $record->update(['status' => 'submitted', 'submitted_at' => now(), 'row_version' => $record->row_version + 1]);
        $this->audit->record($actor, 'pkpa_competency_submitted', $record);
        $this->notifications->notifyRun($run, 'pkpa_competency_submitted', $record, ['field_supervisor', 'internal_supervisor']);

        return $record->refresh();
    }

    public function fieldReview(PkpaRotationCompetencyRecord $record, string $action, ?string $comments, ?User $actor): PkpaRotationCompetencyRecord
    {
        $run = $record->rotationRun()->with('supervisorHistories')->firstOrFail();
        $this->ensureFieldSupervisor($run, $actor);
        if (! in_array($action, ['verified', 'revision_requested'], true)) {
            throw ValidationException::withMessages(['action' => 'Aksi review kompetensi tidak valid.']);
        }
        if ($action === 'revision_requested' && blank($comments)) {
            throw ValidationException::withMessages(['comments' => 'Catatan wajib untuk permintaan revisi.']);
        }
        if ($record->status !== 'submitted') {
            throw ValidationException::withMessages(['competency' => 'Hanya kompetensi submitted yang dapat diverifikasi.']);
        }
        $record->update([
            'status' => $action,
            'verified_at' => $action === 'verified' ? now() : null,
            'revision_requested_at' => $action === 'revision_requested' ? now() : null,
            'verified_by_core_user_id' => $action === 'verified' ? $actor?->core_user_id : null,
            'row_version' => $record->row_version + 1,
        ]);
        $this->review($record, 'field_supervisor', $action, $comments, $actor);
        $this->notifications->notifyRun($run, 'pkpa_competency_'.$action, $record, ['student', 'internal_supervisor']);

        return $record->refresh();
    }

    public function internalComment(PkpaRotationCompetencyRecord $record, string $comments, ?User $actor): void
    {
        $run = $record->rotationRun()->with('supervisorHistories')->firstOrFail();
        $this->ensureInternalSupervisor($run, $actor);
        $this->review($record, 'internal_supervisor', 'marked_reviewed', $comments, $actor);
    }

    public function markNotObserved(PkpaRotationCompetencyRecord $record, string $reason, ?User $actor): PkpaRotationCompetencyRecord
    {
        if (! $this->isCoordinator($actor) || blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Not observed wajib oleh Admin/Koordinator dengan alasan.']);
        }
        $record->update(['status' => 'not_observed', 'row_version' => $record->row_version + 1]);
        $this->review($record, 'coordinator', 'marked_not_observed', $reason, $actor);

        return $record->refresh();
    }

    private function review(PkpaRotationCompetencyRecord $record, string $type, string $action, ?string $comments, ?User $actor): void
    {
        PkpaRotationCompetencyReview::create([
            'pkpa_rotation_competency_record_id' => $record->id,
            'reviewer_type' => $type,
            'reviewer_core_user_id' => $actor?->core_user_id ?? 'system',
            'action' => $action,
            'comments' => $comments,
            'reviewed_at' => now(),
        ]);
        $this->audit->record($actor, 'pkpa_competency_reviewed', $record, null, ['action' => $action]);
    }
}
