<?php

namespace App\Http\Controllers\FieldSupervisor;

use App\Http\Controllers\Controller;
use App\Models\PkpaAttendanceCorrectionRequest;
use App\Models\PkpaAttendanceRecord;
use App\Models\PkpaLogbookAttachment;
use App\Models\PkpaLogbookEntry;
use App\Models\PkpaRotationRun;
use App\Services\PkpaAttendanceService;
use App\Services\PkpaLogbookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaRotationOperationController extends Controller
{
    public function __construct(private readonly PkpaAttendanceService $attendance, private readonly PkpaLogbookService $logbooks)
    {
    }

    public function index(Request $request): View
    {
        return view('field-supervisor.pkpa-operations.index', [
            'runs' => PkpaRotationRun::forSupervisor('field', $request->user()->core_user_id)
                ->with(['practiceDomain', 'practiceSite', 'enrollment', 'attendanceRecords', 'logbookEntries'])
                ->latest()
                ->get(),
        ]);
    }

    public function show(Request $request, PkpaRotationRun $run): View
    {
        abort_unless(PkpaRotationRun::query()
            ->whereKey($run->id)
            ->forSupervisor('field', $request->user()->core_user_id)
            ->exists(), 403);

        $run->load(['practiceDomain', 'practiceSite', 'enrollment', 'progressSnapshots' => fn ($query) => $query->latest('snapshot_date')->limit(1)]);
        $selectedLogbook = $request->integer('logbook')
            ? $run->logbookEntries()->with(['attachments', 'reviews'])->whereKey($request->integer('logbook'))->where('status', '!=', 'draft')->firstOrFail()
            : null;
        $selectedAttendance = $request->integer('attendance')
            ? $run->attendanceRecords()->with('correctionRequests')->whereKey($request->integer('attendance'))->where('submission_status', '!=', 'draft')->firstOrFail()
            : null;

        return view('field-supervisor.pkpa-operations.show', [
            'run' => $run,
            'selectedLogbook' => $selectedLogbook,
            'selectedAttendance' => $selectedAttendance,
            'attendances' => $run->attendanceRecords()->where('submission_status', '!=', 'draft')->latest('attendance_date')->paginate(15, ['*'], 'attendance_page')->withQueryString(),
            'logbooks' => $run->logbookEntries()->where('status', '!=', 'draft')->latest('entry_date')->paginate(15, ['*'], 'logbook_page')->withQueryString(),
            'pendingAttendanceCount' => $run->attendanceRecords()->where('submission_status', 'submitted')->count(),
            'pendingLogbookCount' => $run->logbookEntries()->where('status', 'submitted')->count(),
        ]);
    }

    public function reviewAttendance(Request $request, PkpaAttendanceRecord $record): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:approved,revision_requested,rejected'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $this->attendance->review($record, $data['action'], $data['notes'] ?? null, $request->user());

        return back()->with('status', 'Validasi presensi tersimpan.');
    }

    public function reviewCorrection(Request $request, PkpaAttendanceCorrectionRequest $correction): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:approved,rejected'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $this->attendance->reviewCorrection($correction, $data['action'], $data['notes'] ?? null, $request->user());

        return back()->with('status', 'Review koreksi presensi tersimpan.');
    }

    public function reviewLogbook(Request $request, PkpaLogbookEntry $entry): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:approved,revision_requested,rejected'], 'comments' => ['nullable', 'string', 'max:1500']]);
        $this->logbooks->fieldReview($entry, $data['action'], $data['comments'] ?? null, $request->user());

        return back()->with('status', 'Review logbook tersimpan.');
    }

    public function downloadAttachment(Request $request, PkpaLogbookAttachment $attachment)
    {
        return $this->logbooks->downloadResponse($attachment, $request->user());
    }
}
