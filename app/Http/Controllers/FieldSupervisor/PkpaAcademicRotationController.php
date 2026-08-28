<?php

namespace App\Http\Controllers\FieldSupervisor;

use App\Http\Controllers\Controller;
use App\Models\PkpaRotationCompetencyRecord;
use App\Models\PkpaRotationReport;
use App\Models\PkpaRotationRun;
use App\Models\PkpaSpecialTaskSubmission;
use App\Services\PkpaRotationCompetencyService;
use App\Services\PkpaRotationReportService;
use App\Services\PkpaSpecialTaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaAcademicRotationController extends Controller
{
    public function __construct(private readonly PkpaRotationCompetencyService $competencies, private readonly PkpaSpecialTaskService $tasks, private readonly PkpaRotationReportService $reports)
    {
    }

    public function index(Request $request): View
    {
        return view('field-supervisor.pkpa-academics.index', [
            'runs' => PkpaRotationRun::forSupervisor('field', $request->user()->core_user_id)->with(['enrollment', 'practiceDomain', 'practiceSite', 'competencyRecords', 'specialTasks.submissions', 'rotationReport'])->get(),
        ]);
    }

    public function reviewCompetency(Request $request, PkpaRotationCompetencyRecord $record): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:verified,revision_requested'], 'comments' => ['nullable', 'string']]);
        $this->competencies->fieldReview($record, $data['action'], $data['comments'] ?? null, $request->user());

        return back()->with('status', 'Review kompetensi tersimpan.');
    }

    public function reviewTask(Request $request, PkpaSpecialTaskSubmission $submission): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:approved,revision_requested,rejected,marked_reviewed'], 'comments' => ['nullable', 'string']]);
        $this->tasks->review($submission, $data['action'], $data['comments'] ?? null, $request->user());

        return back()->with('status', 'Review tugas tersimpan.');
    }

    public function confirmReport(Request $request, PkpaRotationReport $report): RedirectResponse
    {
        $this->reports->fieldConfirm($report, $request->input('comments'), $request->user());

        return back()->with('status', 'Laporan dikonfirmasi Preseptor.');
    }
}
