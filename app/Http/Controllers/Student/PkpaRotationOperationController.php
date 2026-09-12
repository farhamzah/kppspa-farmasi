<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PkpaAttendanceRecord;
use App\Models\PkpaLogbookAttachment;
use App\Models\PkpaLogbookEntry;
use App\Models\PkpaRotationRun;
use App\Services\PkpaAttendanceService;
use App\Services\PkpaLogbookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PkpaRotationOperationController extends Controller
{
    public function __construct(private readonly PkpaAttendanceService $attendance, private readonly PkpaLogbookService $logbooks)
    {
    }

    public function index(Request $request): View
    {
        return view('student.pkpa-operations.index', [
            'runs' => PkpaRotationRun::forStudent($request->user()->core_user_id)
                ->with(['practiceDomain', 'practiceSite', 'progressSnapshots' => fn ($query) => $query->latest('snapshot_date')->limit(1)])
                ->withCount(['attendanceRecords', 'logbookEntries'])
                ->latest()
                ->get(),
        ]);
    }

    public function show(Request $request, PkpaRotationRun $run): View
    {
        abort_unless(
            $run->loadMissing('enrollment', 'currentAssignment')->belongsToStudentCoreUser($request->user()->core_user_id),
            403
        );

        return view('student.pkpa-operations.show', [
            'run' => $run->load([
                'enrollment',
                'currentAssignment.supervisors',
                'practiceDomain',
                'practiceSite',
                'supervisorHistories',
                'progressSnapshots' => fn ($query) => $query->latest('snapshot_date')->limit(1),
                'attendanceRecords.correctionRequests',
                'logbookEntries.attachments',
                'logbookEntries.reviews',
            ]),
        ]);
    }

    public function saveAttendance(Request $request, PkpaRotationRun $run): RedirectResponse
    {
        $submissionAction = $request->validate([
            'submission_action' => ['nullable', 'in:draft,submit'],
        ])['submission_action'] ?? 'draft';

        $record = $this->attendance->save($run, $request->validate([
            'id' => ['nullable', 'integer'],
            'attendance_date' => ['required', 'date'],
            'attendance_type' => ['required', 'in:present,sick,permit,institution_closed'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'student_notes' => ['nullable', 'string', 'max:1000'],
        ]), $request->user());

        if ($submissionAction === 'submit') {
            $this->attendance->submit($record, $request->user());

            return back()->with('status', 'Presensi berhasil dikirim ke preseptor.');
        }

        return back()->with('status', 'Presensi disimpan sebagai draft.')->with('attendance_id', $record->id);
    }

    public function submitAttendance(Request $request, PkpaAttendanceRecord $record): RedirectResponse
    {
        $this->attendance->submit($record, $request->user());

        return back()->with('status', 'Presensi dikirim ke preseptor.');
    }

    public function deleteAttendance(Request $request, PkpaAttendanceRecord $record): RedirectResponse
    {
        $this->attendance->deleteDraft($record, $request->user());

        return back()->with('status', 'Presensi draft berhasil dihapus.');
    }

    public function requestCorrection(Request $request, PkpaAttendanceRecord $record): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'student_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $reason = $data['reason'];
        unset($data['reason']);
        $this->attendance->requestCorrection($record, $data, $reason, $request->user());

        return back()->with('status', 'Pengajuan koreksi presensi dikirim.');
    }

    public function saveLogbook(Request $request, PkpaRotationRun $run): RedirectResponse
    {
        $submissionAction = $request->validate([
            'submission_action' => ['nullable', 'in:draft,submit'],
        ])['submission_action'] ?? 'draft';

        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'entry_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'activity_summary' => ['required', 'string'],
            'learning_outcomes' => ['required', 'string'],
            'reflection' => ['required', 'string'],
            'problems_encountered' => ['nullable', 'string'],
            'follow_up_plan' => ['nullable', 'string'],
            'practice_minutes' => ['nullable', 'integer', 'min:0'],
            'evidence_links' => ['nullable', 'array', 'max:10'],
            'evidence_links.*.external_url' => ['nullable', 'string', 'max:4096'],
            'evidence_links.*.link_label' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = DB::transaction(function () use ($run, $data, $request, $submissionAction) {
            $entry = $this->logbooks->save($run, $data, $request->user());

            foreach ($data['evidence_links'] ?? [] as $link) {
                if (filled($link['external_url'] ?? null)) {
                    $this->logbooks->storeExternalLink($entry, $link, $request->user());
                }
            }

            if ($submissionAction === 'submit') {
                return $this->logbooks->submit($entry, $request->user());
            }

            return $entry;
        });

        if ($submissionAction === 'submit') {
            return back()->with('status', 'Logbook berhasil dikirim ke preseptor.');
        }

        return back()->with('status', 'Logbook disimpan sebagai draft.')->with('logbook_id', $entry->id);
    }

    public function submitLogbook(Request $request, PkpaLogbookEntry $entry): RedirectResponse
    {
        $this->logbooks->submit($entry, $request->user());

        return back()->with('status', 'Logbook dikirim ke preseptor.');
    }

    public function deleteLogbook(Request $request, PkpaLogbookEntry $entry): RedirectResponse
    {
        $this->logbooks->deleteDraft($entry->load('attachments'), $request->user());

        return back()->with('status', 'Logbook draft berhasil dihapus.');
    }

    public function uploadAttachmentLink(Request $request, PkpaLogbookEntry $entry): RedirectResponse
    {
        $data = $request->validate([
            'external_url' => ['required', 'string', 'max:4096'],
            'link_label' => ['nullable', 'string', 'max:255'],
        ]);

        $this->logbooks->storeExternalLink($entry, $data, $request->user());

        return back()->with('status', 'Tautan bukti logbook tersimpan.');
    }

    public function downloadAttachment(Request $request, PkpaLogbookAttachment $attachment)
    {
        return $this->logbooks->downloadResponse($attachment, $request->user());
    }
}
