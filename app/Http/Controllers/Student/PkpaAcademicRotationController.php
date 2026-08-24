<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PkpaRotationCompetencyRecord;
use App\Models\PkpaRotationGuidanceSession;
use App\Models\PkpaRotationReport;
use App\Models\PkpaRotationReportVersion;
use App\Models\PkpaRotationRun;
use App\Models\PkpaRotationSpecialTask;
use App\Services\PkpaRotationCompetencyService;
use App\Services\PkpaRotationGuidanceService;
use App\Services\PkpaRotationReportService;
use App\Services\PkpaSpecialTaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaAcademicRotationController extends Controller
{
    public function __construct(private readonly PkpaRotationCompetencyService $competencies, private readonly PkpaSpecialTaskService $tasks, private readonly PkpaRotationReportService $reports, private readonly PkpaRotationGuidanceService $guidance)
    {
    }

    public function index(Request $request): View
    {
        return view('student.pkpa-academics.index', [
            'runs' => PkpaRotationRun::forStudent($request->user()->core_user_id)->with(['practiceDomain', 'practiceSite', 'competencyRecords', 'specialTasks', 'rotationReport', 'academicReadinessReviews' => fn ($q) => $q->latest('reviewed_at')->limit(1)])->withCount(['guidanceSessions'])->get(),
        ]);
    }

    public function show(Request $request, PkpaRotationRun $run): View
    {
        abort_unless($run->student_core_user_id === $request->user()->core_user_id, 403);

        return view('student.pkpa-academics.show', ['run' => $run->load(['practiceDomain', 'practiceSite', 'progressSnapshots' => fn ($query) => $query->latest('snapshot_date')->limit(1), 'competencyRecords.evidences', 'competencyRecords.reviews', 'specialTasks.submissions.reviews', 'rotationReport.versions', 'guidanceSessions', 'academicReadinessReviews'])]);
    }

    public function markCompetency(Request $request, PkpaRotationCompetencyRecord $record): RedirectResponse
    {
        $this->competencies->markInProgress($record, $request->input('student_notes'), $request->user());

        return back()->with('status', 'Kompetensi ditandai sedang dikerjakan.');
    }

    public function evidence(Request $request, PkpaRotationCompetencyRecord $record): RedirectResponse
    {
        $this->competencies->addEvidence($record, $request->validate([
            'evidence_type' => ['required', 'in:file,text_note,external_reference,logbook_reference,attendance_reference'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'external_reference_url' => ['nullable', 'url'],
            'logbook_entry_id' => ['nullable', 'integer'],
            'attendance_record_id' => ['nullable', 'integer'],
            'file' => ['nullable', 'file', 'max:'.config('my_pkpa.academic_file_max_size_kb', 5120)],
        ]), $request->file('file'), $request->user());

        return back()->with('status', 'Bukti kompetensi disimpan.');
    }

    public function submitCompetency(Request $request, PkpaRotationCompetencyRecord $record): RedirectResponse
    {
        $this->competencies->submit($record, $request->user());

        return back()->with('status', 'Kompetensi dikirim untuk verifikasi.');
    }

    public function submitTask(Request $request, PkpaRotationSpecialTask $task): RedirectResponse
    {
        $this->tasks->saveSubmission($task, $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'submission_notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:'.config('my_pkpa.academic_file_max_size_kb', 5120)],
        ]) + ['submit' => true], $request->file('file'), $request->user());

        return back()->with('status', 'Submission tugas khusus dikirim.');
    }

    public function createReport(Request $request, PkpaRotationRun $run): RedirectResponse
    {
        $report = $this->reports->reportForRun($run, $request->user());

        return back()->with('status', 'Draft laporan rotasi siap.')->with('report_id', $report->id);
    }

    public function uploadReport(Request $request, PkpaRotationReport $report): RedirectResponse
    {
        $this->reports->uploadVersion($report, $request->file('file'), $request->validate([
            'file' => ['required', 'file', 'max:'.config('my_pkpa.academic_file_max_size_kb', 5120)],
            'change_summary' => ['nullable', 'string'],
            'submission_notes' => ['nullable', 'string'],
        ]), $request->user());

        return back()->with('status', 'Versi laporan diunggah.');
    }

    public function submitReport(Request $request, PkpaRotationReport $report): RedirectResponse
    {
        $this->reports->submit($report, $request->user());

        return back()->with('status', 'Laporan rotasi dikirim.');
    }

    public function downloadReport(Request $request, PkpaRotationReportVersion $version)
    {
        return $this->reports->downloadVersion($version, $request->user());
    }

    public function acknowledgeGuidance(Request $request, PkpaRotationGuidanceSession $session): RedirectResponse
    {
        $this->guidance->acknowledge($session, $request->user());

        return back()->with('status', 'Bimbingan ditandai sudah dibaca.');
    }
}
