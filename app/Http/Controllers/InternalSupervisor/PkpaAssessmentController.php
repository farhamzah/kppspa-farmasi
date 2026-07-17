<?php

namespace App\Http\Controllers\InternalSupervisor;

use App\Http\Controllers\Controller;
use App\Models\PkpaRotationAssessmentAssessor;
use App\Models\PkpaRotationComponentScore;
use App\Services\PkpaRotationAssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaAssessmentController extends Controller
{
    public function __construct(private readonly PkpaRotationAssessmentService $assessments)
    {
    }

    public function index(Request $request): View
    {
        return view('internal-supervisor.pkpa-assessments.index', [
            'assignments' => PkpaRotationAssessmentAssessor::with(['assessment.rotationRun.practiceDomain', 'assessment.rotationRun.practiceSite', 'component', 'scores'])
                ->where('assessor_type', 'internal_supervisor')
                ->where('core_user_id', $request->user()->core_user_id)
                ->latest()
                ->paginate(20),
        ]);
    }

    public function save(Request $request, PkpaRotationComponentScore $score): RedirectResponse
    {
        $this->assessments->saveDirectScore($score, $request->validate([
            'raw_score' => ['required', 'numeric', 'min:0'],
            'comments' => ['nullable', 'string'],
        ])['raw_score'], $request->input('comments'), $request->user());

        return back()->with('status', 'Draft nilai disimpan.');
    }

    public function submit(Request $request, PkpaRotationComponentScore $score): RedirectResponse
    {
        $this->assessments->submitScore($score, $request->user());

        return back()->with('status', 'Nilai dikirim dan dikunci.');
    }
}
