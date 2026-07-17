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
                ->with(['practiceDomain', 'practiceSite', 'attendanceRecords', 'logbookEntries'])
                ->latest()
                ->get(),
        ]);
    }

    public function show(Request $request, PkpaRotationRun $run): View
    {
        abort_unless($run->supervisorHistories()->where('supervisor_type', 'field')->where('core_user_id', $request->user()->core_user_id)->where('status', 'active')->exists(), 403);

        return view('field-supervisor.pkpa-operations.show', ['run' => $run->load(['practiceDomain', 'practiceSite', 'attendanceRecords.correctionRequests', 'logbookEntries.attachments', 'logbookEntries.reviews'])]);
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
