<?php

namespace App\Http\Controllers\InternalSupervisor;

use App\Http\Controllers\Controller;
use App\Models\PkpaRotationCompetencyRecord;
use App\Models\PkpaRotationReport;
use App\Models\PkpaRotationRun;
use App\Models\PkpaSpecialTaskSubmission;
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
        return view('internal-supervisor.pkpa-academics.index', [
            'runs' => PkpaRotationRun::forSupervisor('internal', $request->user()->core_user_id)->with(['enrollment', 'practiceDomain', 'practiceSite', 'competencyRecords', 'specialTasks.submissions', 'rotationReport', 'guidanceSessions'])->get(),
        ]);
    }

    public function commentCompetency(Request $request, PkpaRotationCompetencyRecord $record): RedirectResponse
    {
        $this->competencies->internalComment($record, $request->validate(['comments' => ['required', 'string']])['comments'], $request->user());

        return back()->with('status', 'Monitoring kompetensi tersimpan.');
    }

    public function reviewTask(Request $request, PkpaSpecialTaskSubmission $submission): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:approved,revision_requested,rejected,marked_reviewed'], 'comments' => ['nullable', 'string']]);
        $this->tasks->review($submission, $data['action'], $data['comments'] ?? null, $request->user());

        return back()->with('status', 'Review tugas tersimpan.');
    }

    public function reviewReport(Request $request, PkpaRotationReport $report): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:approved,revision_requested,rejected'], 'comments' => ['nullable', 'string']]);
        $this->reports->internalReview($report, $data['action'], $data['comments'] ?? null, $request->user());

        return back()->with('status', 'Review laporan tersimpan.');
    }

    public function guidance(Request $request, PkpaRotationRun $run): RedirectResponse
    {
        $this->guidance->record($run, $request->validate([
            'topic' => ['required', 'string', 'max:255'],
            'guidance_type' => ['required', 'in:meeting,document_review,online_consultation,comment_only,other'],
            'guidance_date' => ['required', 'date'],
            'supervisor_notes' => ['nullable', 'string'],
            'follow_up_actions' => ['nullable', 'string'],
        ]) + ['supervisor_type' => 'internal'], $request->user());

        return back()->with('status', 'Bimbingan rotasi dicatat.');
    }
}
