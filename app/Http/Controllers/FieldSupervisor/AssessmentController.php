<?php

namespace App\Http\Controllers\FieldSupervisor;

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
        $fieldSupervisorId = request()->user()->fieldSupervisor?->id ?: 0;
        $assignments = KpAssignment::with(['student.user', 'period', 'place', 'scores'])
            ->where('field_supervisor_id', $fieldSupervisorId)
            ->latest()->paginate(10);

        return view('field-supervisor.assessments.index', ['assignments' => $assignments]);
    }

    public function show(KpAssignment $assignment, KpAssessmentService $service): View
    {
        abort_unless(request()->user()->fieldSupervisor?->id === $assignment->field_supervisor_id, 403);
        $service->ensureDefaultComponents($assignment->period, request()->user());

        return view('field-supervisor.assessments.show', [
            'assignment' => $assignment->load(['student.user', 'period', 'place', 'scores.component', 'finalScore']),
            'components' => $assignment->period->assessmentComponents()->where('status', 'aktif')->where('assessor_type', 'pembimbing_lapangan')->orderBy('sort_order')->get(),
            'assessorType' => 'pembimbing_lapangan',
        ]);
    }

    public function save(SaveScoreRequest $request, KpAssignment $assignment, KpAssessmentService $service): RedirectResponse
    {
        foreach ($request->validated('scores') as $row) {
            $service->saveScore($request->user(), $assignment, KpAssessmentComponent::findOrFail($row['component_id']), (float) $row['score'], $row['note'] ?? null);
        }

        return back()->with('status', 'Nilai lapangan berhasil disimpan.');
    }

    public function submit(KpAssignment $assignment, KpAssessmentService $service): RedirectResponse
    {
        $service->submitScores(request()->user(), $assignment, 'pembimbing_lapangan');
        return back()->with('status', 'Nilai lapangan berhasil disubmit.');
    }
}
