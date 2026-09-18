<?php

namespace App\Http\Controllers\FieldSupervisor;

use App\Http\Controllers\Controller;
use App\Models\PkpaRotationAssessmentAssessor;
use App\Models\PkpaRotationComponentScore;
use App\Services\PkpaApotekAssessmentService;
use App\Services\PkpaRotationAssessmentService;
use App\Support\PkpaApotekPortfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaAssessmentController extends Controller
{
    public function __construct(
        private readonly PkpaRotationAssessmentService $assessments,
        private readonly PkpaApotekAssessmentService $apotekAssessments
    ) {
    }

    public function index(Request $request): View
    {
        $assignments = PkpaRotationAssessmentAssessor::with(['assessment.rotationRun.enrollment', 'assessment.rotationRun.requirement', 'assessment.rotationRun.currentAssignment.availabilityPeriod', 'assessment.rotationRun.practiceDomain', 'assessment.rotationRun.practiceSite', 'component', 'scores.component'])
            ->where('assessor_type', 'field_supervisor')
            ->where('core_user_id', $request->user()->core_user_id)
            ->latest()
            ->paginate(20);
        $attendanceSummaries = $assignments->getCollection()
            ->filter(fn ($assignment) => PkpaApotekPortfolio::isApotekCode($assignment->assessment?->rotationRun?->practiceDomain?->code))
            ->flatMap(function ($assignment) {
                return $assignment->scores->mapWithKeys(function ($score) use ($assignment) {
                    $score->setRelation('assessment', $assignment->assessment);

                    return [$score->id => $this->apotekAssessments->attendanceSummary($score)];
                });
            })
            ->all();

        return view('field-supervisor.pkpa-assessments.index', [
            'assignments' => $assignments,
            'attendanceSummaries' => $attendanceSummaries,
        ]);
    }

    public function save(Request $request, PkpaRotationComponentScore $score): RedirectResponse
    {
        if ($this->isApotekScore($score)) {
            $this->apotekAssessments->save($score, $request->validate($this->apotekRules()), $request->user());

            return back()->with('status', 'Draf penilaian Apotek disimpan.');
        }

        $this->assessments->saveDirectScore($score, $request->validate([
            'raw_score' => ['required', 'numeric', 'min:0'],
            'comments' => ['nullable', 'string'],
        ])['raw_score'], $request->input('comments'), $request->user());

        return back()->with('status', 'Draft nilai disimpan.');
    }

    public function submit(Request $request, PkpaRotationComponentScore $score): RedirectResponse
    {
        if ($this->isApotekScore($score)) {
            $this->apotekAssessments->save($score, $request->validate($this->apotekRules()), $request->user());
            $this->apotekAssessments->submit($score->fresh(), $request->user());

            return back()->with('status', 'Penilaian Apotek dikirim dan dikunci.');
        }

        $this->assessments->submitScore($score, $request->user());

        return back()->with('status', 'Nilai dikirim dan dikunci.');
    }

    private function isApotekScore(PkpaRotationComponentScore $score): bool
    {
        $score->loadMissing('assessment.rotationRun.practiceDomain');

        return PkpaApotekPortfolio::isApotekCode($score->assessment?->rotationRun?->practiceDomain?->code);
    }

    private function apotekRules(): array
    {
        return [
            'criteria' => ['nullable', 'array'],
            'criteria.*' => ['nullable', 'integer', 'between:1,5'],
            'overall_comments' => ['nullable', 'string'],
            'strengths' => ['nullable', 'string'],
            'improvements' => ['nullable', 'string'],
            'development_suggestions' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'array'],
            'recommendations.competency_met' => ['nullable', 'in:yes,no'],
            'recommendations.portfolio_accepted' => ['nullable', 'in:yes,no'],
            'recommendations.final_exam_recommended' => ['nullable', 'in:yes,no'],
        ];
    }
}
