<?php

namespace App\Http\Controllers\InternalSupervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\SaveScoreRequest;
use App\Models\KpAssignment;
use App\Models\KpAssessmentComponent;
use App\Services\KpAssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function index(): View
    {
        $lecturer = request()->user()->lecturer;
        $assignments = KpAssignment::with(['student.user', 'period', 'place', 'scores'])
            ->where('internal_supervisor_id', $lecturer?->id)
            ->latest()->paginate(10);

        return view('internal-supervisor.assessments.index', ['assignments' => $assignments]);
    }

    public function show(KpAssignment $assignment, KpAssessmentService $service): View
    {
        abort_unless(request()->user()->lecturer?->id === $assignment->internal_supervisor_id, 403);
        $service->ensureDefaultComponents($assignment->period, request()->user());

        return view('internal-supervisor.assessments.show', $this->payload($assignment, 'pembimbing_dalam'));
    }

    public function save(SaveScoreRequest $request, KpAssignment $assignment, KpAssessmentService $service): RedirectResponse
    {
        foreach ($request->validated('scores') as $row) {
            $service->saveScore($request->user(), $assignment, KpAssessmentComponent::findOrFail($row['component_id']), (float) $row['score'], $row['note'] ?? null);
        }

        return back()->with('status', 'Nilai pembimbing berhasil disimpan.');
    }

    public function submit(KpAssignment $assignment, KpAssessmentService $service): RedirectResponse
    {
        $service->submitScores(request()->user(), $assignment, 'pembimbing_dalam');
        return back()->with('status', 'Nilai pembimbing berhasil disubmit.');
    }

    private function payload(KpAssignment $assignment, string $type): array
    {
        return [
            'assignment' => $assignment->load(['student.user', 'period', 'place', 'scores.component', 'finalScore']),
            'components' => $assignment->period->assessmentComponents()->where('status', 'aktif')->where('assessor_type', $type)->orderBy('sort_order')->get(),
            'assessorType' => $type,
        ];
    }
}
