<?php

namespace App\Services;

use App\Models\PkpaLogbookAttachment;
use App\Models\PkpaLogbookEntry;
use App\Models\PkpaLogbookReview;
use App\Models\PkpaRotationRun;
use App\Models\User;
use App\Services\Concerns\AuthorizesPkpaRotationActors;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PkpaLogbookService
{
    use AuthorizesPkpaRotationActors;

    public function __construct(private readonly PkpaAuditService $audit, private readonly PkpaRotationProgressService $progress)
    {
    }

    public function save(PkpaRotationRun $run, array $data, ?User $actor): PkpaLogbookEntry
    {
        $this->ensureStudentOwnsRun($run, $actor);
        $this->validateEntry($run, $data);
        $keyDate = $data['entry_date'] ?? $data['period_start_date'];
        $entry = isset($data['id']) ? PkpaLogbookEntry::whereKey($data['id'])->first() : null;
        if ($entry && (int) $entry->pkpa_rotation_run_id !== (int) $run->id) {
            throw ValidationException::withMessages(['authorization' => 'Logbook tidak sesuai dengan rotasi yang sedang dibuka.']);
        }
        if ($entry && ! in_array($entry->status, ['draft', 'revision_requested'], true)) {
            throw ValidationException::withMessages(['logbook' => 'Logbook yang sudah dikirim tidak dapat diubah langsung.']);
        }

        $payload = [
            'entry_date' => $data['entry_date'] ?? null,
            'period_start_date' => $data['period_start_date'] ?? ($data['entry_date'] ?? null),
            'period_end_date' => $data['period_end_date'] ?? ($data['entry_date'] ?? null),
            'title' => trim($data['title']),
            'activity_summary' => trim($data['activity_summary']),
            'learning_outcomes' => trim($data['learning_outcomes']),
            'reflection' => trim($data['reflection']),
            'problems_encountered' => $data['problems_encountered'] ?? null,
            'follow_up_plan' => $data['follow_up_plan'] ?? null,
            'practice_minutes' => $data['practice_minutes'] ?? null,
            'updated_by_core_user_id' => $actor?->core_user_id,
            'row_version' => ($entry?->row_version ?? 0) + 1,
        ];

        $entry = $entry
            ? tap($entry)->update($payload)
            : PkpaLogbookEntry::create(array_merge($payload, [
                'pkpa_rotation_run_id' => $run->id,
                'status' => 'draft',
                'created_by_core_user_id' => $actor?->core_user_id,
                'entry_key' => 'RUN:'.$run->id.':'.$keyDate,
            ]));

        $this->audit->record($actor, 'pkpa_logbook_saved', $entry);

        return $entry->refresh();
    }

    public function submit(PkpaLogbookEntry $entry, ?User $actor): PkpaLogbookEntry
    {
        $run = $entry->rotationRun()->firstOrFail();
        $this->ensureStudentOwnsRun($run, $actor);
        if (! in_array($entry->status, ['draft', 'revision_requested'], true)) {
            throw ValidationException::withMessages(['logbook' => 'Logbook tidak dapat dikirim pada status saat ini.']);
        }
        $entry->update(['status' => 'submitted', 'submitted_at' => now(), 'submitted_by_core_user_id' => $actor?->core_user_id, 'row_version' => $entry->row_version + 1]);
        $this->audit->record($actor, 'pkpa_logbook_submitted', $entry);
        $this->progress->snapshot($run, 'logbook_submit');

        return $entry->refresh();
    }

    public function fieldReview(PkpaLogbookEntry $entry, string $action, ?string $comments, ?User $actor): PkpaLogbookEntry
    {
        $run = $entry->rotationRun()->with('supervisorHistories')->firstOrFail();
        $this->ensureFieldSupervisor($run, $actor);
        if (! in_array($action, ['approved', 'revision_requested', 'rejected'], true)) {
            throw ValidationException::withMessages(['action' => 'Aksi review logbook tidak valid.']);
        }
        if (in_array($action, ['revision_requested', 'rejected'], true) && blank($comments)) {
            throw ValidationException::withMessages(['comments' => 'Catatan wajib diisi untuk revisi atau penolakan.']);
        }
        if ($entry->status !== 'submitted') {
            throw ValidationException::withMessages(['logbook' => 'Hanya logbook terkirim yang dapat divalidasi.']);
        }

        $entry->update(['status' => $action, 'field_reviewed_at' => now(), 'locked_at' => $action === 'approved' ? now() : null, 'row_version' => $entry->row_version + 1]);
        $this->review($entry, 'field', $action, $comments, $actor);
        $this->progress->snapshot($run, 'logbook_field_review');

        return $entry->refresh();
    }

    public function internalReview(PkpaLogbookEntry $entry, string $comments, ?User $actor): PkpaLogbookEntry
    {
        $run = $entry->rotationRun()->with('supervisorHistories')->firstOrFail();
        $this->ensureInternalSupervisor($run, $actor);
        if (! in_array($entry->status, ['approved', 'reviewed_by_internal'], true)) {
            throw ValidationException::withMessages(['logbook' => 'Pembimbing dalam hanya memantau logbook yang sudah tervalidasi lapangan.']);
        }
        $entry->update(['status' => 'reviewed_by_internal', 'internal_reviewed_at' => now(), 'row_version' => $entry->row_version + 1]);
        $this->review($entry, 'internal', 'reviewed', $comments, $actor);
        $this->progress->snapshot($run, 'logbook_internal_review');

        return $entry->refresh();
    }

    public function storeAttachment(PkpaLogbookEntry $entry, UploadedFile $file, ?User $actor): PkpaLogbookAttachment
    {
        $run = $entry->rotationRun()->firstOrFail();
        $this->ensureStudentOwnsRun($run, $actor);
        if (! in_array($entry->status, ['draft', 'revision_requested'], true)) {
            throw ValidationException::withMessages(['attachment' => 'Lampiran hanya dapat ditambahkan sebelum logbook dikirim.']);
        }
        if ($file->getSize() > ((int) config('my_pspa.logbook_attachment_max_kb', 5120) * 1024)) {
            throw ValidationException::withMessages(['attachment' => 'Ukuran lampiran melebihi batas.']);
        }
        $allowed = config('my_pspa.logbook_attachment_allowed_mimes', []);
        if (! in_array($file->getMimeType(), $allowed, true)) {
            throw ValidationException::withMessages(['attachment' => 'Tipe lampiran tidak diizinkan.']);
        }
        $disk = config('my_pspa.logbook_attachment_disk', 'local');
        $storedName = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('pkpa-logbooks/'.$entry->id, $storedName, $disk);

        return PkpaLogbookAttachment::create([
            'pkpa_logbook_entry_id' => $entry->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedName,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by_core_user_id' => $actor?->core_user_id,
        ]);
    }

    public function downloadResponse(PkpaLogbookAttachment $attachment, ?User $actor)
    {
        $this->ensureCanAccessAttachment($attachment, $actor);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_filename);
    }

    private function review(PkpaLogbookEntry $entry, string $type, string $action, ?string $comments, ?User $actor): void
    {
        PkpaLogbookReview::create([
            'pkpa_logbook_entry_id' => $entry->id,
            'reviewer_type' => $type,
            'reviewer_core_user_id' => $actor?->core_user_id ?? 'system',
            'action' => $action,
            'comments' => $comments,
            'reviewed_at' => now(),
        ]);
        $this->audit->record($actor, 'pkpa_logbook_reviewed', $entry, null, ['type' => $type, 'action' => $action]);
    }

    private function validateEntry(PkpaRotationRun $run, array $data): void
    {
        foreach (['title', 'activity_summary', 'learning_outcomes', 'reflection'] as $field) {
            if (blank($data[$field] ?? null)) {
                throw ValidationException::withMessages([$field => 'Kolom ini wajib diisi.']);
            }
        }
        $date = Carbon::parse($data['entry_date'] ?? $data['period_start_date'] ?? null);
        if ($date->isFuture()) {
            throw ValidationException::withMessages(['entry_date' => 'Tanggal logbook tidak boleh melebihi hari ini.']);
        }
        if ($date->lt($run->scheduled_start_date) || $date->gt($run->scheduled_end_date)) {
            throw ValidationException::withMessages(['entry_date' => 'Tanggal logbook harus berada dalam periode rotasi.']);
        }
    }
}
