<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\SaveScoreRequest;
use App\Models\KpAssessmentComponent;
use App\Models\KpExam;
use App\Services\KpAssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function index(): View
    {
        $lecturer = request()->user()->lecturer;
        $exams = KpExam::with(['assignment.student.user', 'assignment.period', 'assignment.place', 'assignment.scores'])
            ->forExaminer($lecturer?->id)
            ->latest('exam_date')->paginate(10);

        return view('examiner.assessments.index', ['exams' => $exams]);
    }

    public function show(KpExam $exam): View
    {
        abort_unless($exam->hasExaminer(request()->user()->lecturer?->id), 403);
        app(KpAssessmentService::class)->ensureDefaultComponents($exam->assignment->period, request()->user());
        $assignment = $exam->assignment->load(['student.user', 'period', 'place', 'scores.component', 'finalScore']);

        return view('examiner.assessments.show', [
            'exam' => $exam,
            'assignment' => $assignment,
            'components' => $assignment->period->assessmentComponents()->where('status', 'aktif')->where('assessor_type', 'penguji')->orderBy('sort_order')->get(),
            'assessorType' => 'penguji',
        ]);
    }

    public function save(SaveScoreRequest $request, KpExam $exam, KpAssessmentService $service): RedirectResponse
    {
        foreach ($request->validated('scores') as $row) {
            $service->saveScore($request->user(), $exam->assignment, KpAssessmentComponent::findOrFail($row['component_id']), (float) $row['score'], $row['note'] ?? null);
        }

        return back()->with('status', 'Nilai sidang berhasil disimpan.');
    }

    public function submit(KpExam $exam, KpAssessmentService $service): RedirectResponse
    {
        $service->submitScores(request()->user(), $exam->assignment, 'penguji');
        return back()->with('status', 'Nilai sidang berhasil disubmit.');
    }
}
