<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreKpLogbookRequest;
use App\Http\Requests\Student\SubmitKpLogbookRequest;
use App\Http\Requests\Student\UpdateKpLogbookRequest;
use App\Models\KpAssignment;
use App\Models\KpLogbook;
use App\Services\KpLogbookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogbookController extends Controller
{
    public function index(): View
    {
        $assignment = $this->activeAssignment();
        $logbooks = $assignment
            ? $assignment->logbooks()->latest('activity_date')->paginate(10)
            : null;

        return view('student.logbooks.index', [
            'assignment' => $assignment?->load(['place', 'internalSupervisor.user', 'fieldSupervisor.user']),
            'logbooks' => $logbooks,
            'stats' => $assignment ? $this->stats($assignment) : null,
        ]);
    }

    public function create(): View
    {
        return view('student.logbooks.create', ['assignment' => $this->requireActiveAssignment()]);
    }

    public function updateWorkPeriod(Request $request): RedirectResponse
    {
        $assignment = $this->requireActiveAssignment();

        $data = $request->validate([
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'workday_pattern' => ['required', 'in:senin_jumat,senin_sabtu'],
        ], [
            'started_at.required' => 'Tanggal mulai PKPA wajib diisi.',
            'ended_at.required' => 'Tanggal selesai PKPA wajib diisi.',
            'ended_at.after_or_equal' => 'Tanggal selesai PKPA tidak boleh sebelum tanggal mulai.',
            'workday_pattern.in' => 'Pola hari kerja tidak valid.',
        ]);

        $assignment->update($data);

        return back()->with('status', 'Periode kerja PKPA berhasil diperbarui.');
    }

    public function store(StoreKpLogbookRequest $request, KpLogbookService $service): RedirectResponse
    {
        $assignment = $this->requireActiveAssignment();
        $this->ensureDateIsUnique($assignment, $request->activity_date);

        $data = $request->validated();
        if ($request->hasFile('evidence')) {
            $data['evidence'] = $request->file('evidence');
        }

        $logbook = $service->createDraft($request->user(), $assignment, $data);
        if ($request->input('action') === 'submit') {
            $service->submit($request->user(), $logbook);
        }

        return redirect()->route('student.pkpa-journals.show', $logbook)->with('status', 'Logbook PKPA berhasil disimpan.');
    }

    public function show(KpLogbook $logbook, KpLogbookService $service): View
    {
        $service->ensureStudentOwnsLogbook(request()->user(), $logbook);

        return view('student.logbooks.show', [
            'logbook' => $logbook->load(['assignment.place', 'assignment.internalSupervisor.user', 'assignment.fieldSupervisor.user', 'comments.user', 'logs.user']),
        ]);
    }

    public function edit(KpLogbook $logbook, KpLogbookService $service): View
    {
        $service->ensureStudentOwnsLogbook(request()->user(), $logbook);
        if (! $logbook->canBeEditedByStudent()) {
            abort(403, 'Logbook ini tidak bisa diedit.');
        }

        return view('student.logbooks.edit', ['logbook' => $logbook->load('assignment.place')]);
    }

    public function update(UpdateKpLogbookRequest $request, KpLogbook $logbook, KpLogbookService $service): RedirectResponse
    {
        $service->ensureStudentOwnsLogbook($request->user(), $logbook);
        $this->ensureDateIsUnique($logbook->assignment, $request->activity_date, $logbook);

        $data = $request->validated();
        if ($request->hasFile('evidence')) {
            $data['evidence'] = $request->file('evidence');
        }

        $logbook = $service->updateDraft($request->user(), $logbook, $data);
        if ($request->input('action') === 'submit') {
            $service->submit($request->user(), $logbook);
        }

        return redirect()->route('student.pkpa-journals.show', $logbook)->with('status', 'Logbook PKPA berhasil diperbarui.');
    }

    public function submit(SubmitKpLogbookRequest $request, KpLogbook $logbook, KpLogbookService $service): RedirectResponse
    {
        $service->submit($request->user(), $logbook);

        return back()->with('status', 'Logbook dikirim untuk validasi preseptor.');
    }

    public function download(KpLogbook $logbook, KpLogbookService $service): StreamedResponse
    {
        $service->ensureStudentOwnsLogbook(request()->user(), $logbook);
        abort_unless($logbook->hasEvidenceFile(), 404);

        return Storage::disk($logbook->evidence_disk ?: 'local')->download($logbook->evidence_path, $logbook->evidence_original_filename);
    }

    public function destroy(KpLogbook $logbook, KpLogbookService $service): RedirectResponse
    {
        $service->ensureStudentOwnsLogbook(request()->user(), $logbook);
        if (! $logbook->canBeEditedByStudent()) {
            abort(403, 'Logbook ini tidak bisa dihapus.');
        }

        $service->deleteEvidence($logbook);
        $logbook->delete();

        return redirect()->route('student.pkpa-journals.index')->with('status', 'Draf logbook PKPA berhasil dihapus.');
    }

    private function activeAssignment(): ?KpAssignment
    {
        return request()->user()->student?->assignments()
            ->with(['place', 'internalSupervisor.user', 'fieldSupervisor.user'])
            ->whereIn('status', ['aktif', 'berjalan'])
            ->latest()
            ->first();
    }

    private function requireActiveAssignment(): KpAssignment
    {
        $assignment = $this->activeAssignment();

        if (! $assignment) {
            throw ValidationException::withMessages(['assignment' => 'Anda belum memiliki penempatan PKPA aktif.']);
        }

        return $assignment;
    }

    private function ensureDateIsUnique(KpAssignment $assignment, string $activityDate, ?KpLogbook $except = null): void
    {
        $exists = $assignment->logbooks()
            ->whereDate('activity_date', $activityDate)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['activity_date' => 'Logbook untuk tanggal ini sudah ada.']);
        }
    }

    private function stats(KpAssignment $assignment): array
    {
        return [
            'total' => $assignment->logbooks()->count(),
            'pending' => $assignment->logbooks()->where('status', 'menunggu_validasi')->count(),
            'approved' => $assignment->logbooks()->where('status', 'disetujui')->count(),
            'revision' => $assignment->logbooks()->where('status', 'revisi')->count(),
            'rejected' => $assignment->logbooks()->where('status', 'ditolak')->count(),
        ];
    }
}
