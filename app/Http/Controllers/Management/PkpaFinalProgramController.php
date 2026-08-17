<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\PkpaEnrollment;
use App\Models\PkpaFinalAssessmentScheme;
use App\Models\PkpaFinalGradeCalculation;
use App\Models\PkpaFinalGradeResult;
use App\Models\PkpaFinalGradeRelease;
use App\Models\PkpaGraduationDecision;
use App\Models\PkpaProgram;
use App\Models\PkpaRemedialCase;
use App\Services\PkpaFinalAssessmentSchemeService;
use App\Services\PkpaFinalGradeService;
use App\Services\PkpaRemedialService;
use App\Services\PkpaRequirementCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaFinalProgramController extends Controller
{
    public function __construct(
        private readonly PkpaFinalAssessmentSchemeService $schemes,
        private readonly PkpaRequirementCompletionService $completion,
        private readonly PkpaFinalGradeService $finalGrades,
        private readonly PkpaRemedialService $remedials
    ) {
    }

    public function index(): View
    {
        return view('management.pkpa-final-program.index', [
            'programs' => PkpaProgram::with(['domains.practiceDomain'])->latest()->get(),
            'schemes' => PkpaFinalAssessmentScheme::with('components')->latest()->limit(20)->get(),
            'enrollments' => PkpaEnrollment::with(['program', 'requirements.practiceDomain'])->latest()->paginate(15),
            'summary' => [
                'schemes' => PkpaFinalAssessmentScheme::count(),
                'calculations' => PkpaFinalGradeCalculation::count(),
                'finalized' => PkpaFinalGradeResult::whereIn('result_status', ['finalized', 'released'])->count(),
                'decisions' => PkpaGraduationDecision::count(),
                'released' => PkpaFinalGradeRelease::where('status', 'released')->count(),
                'remedial' => PkpaRemedialCase::whereIn('status', ['submitted', 'approved', 'in_progress'])->count(),
            ],
        ]);
    }

    public function storeScheme(Request $request, PkpaProgram $program): RedirectResponse
    {
        $this->schemes->create($program, $request->validate(['code' => ['required'], 'name' => ['required'], 'maximum_score' => ['nullable', 'numeric'], 'minimum_passing_score' => ['nullable', 'numeric'], 'rounding_mode' => ['nullable', 'in:half_up,half_even,floor,ceil']]), $request->user());
        return back()->with('status', 'Skema nilai akhir dibuat.');
    }

    public function storeComponent(Request $request, PkpaFinalAssessmentScheme $scheme): RedirectResponse
    {
        $data = $request->validate(['code' => ['required'], 'name' => ['required'], 'component_type' => ['required', 'in:wahana_grade,program_assessment,custom'], 'source_practice_domain_id' => ['nullable', 'integer'], 'weight_percentage' => ['required', 'numeric'], 'maximum_raw_score' => ['nullable', 'numeric']]);
        $data['status'] = 'active';
        $data['maximum_raw_score'] ??= 100;
        $data['is_required'] = $request->boolean('is_required', true);
        $this->schemes->saveComponent($scheme, $data, $request->user());
        return back()->with('status', 'Komponen skema akhir disimpan.');
    }

    public function activateScheme(Request $request, PkpaFinalAssessmentScheme $scheme): RedirectResponse
    {
        $this->schemes->activate($scheme, $request->user());
        return back()->with('status', 'Skema nilai akhir diaktifkan.');
    }

    public function evaluateRequirement(Request $request, $requirement): RedirectResponse
    {
        $model = \App\Models\PkpaEnrollmentRequirement::findOrFail($requirement);
        $completion = $this->completion->evaluate($model, $request->user());
        return back()->with('status', 'Status penyelesaian wahana: '.$completion->status);
    }

    public function completeRequirement(Request $request, $requirement): RedirectResponse
    {
        $model = \App\Models\PkpaEnrollmentRequirement::findOrFail($requirement);
        $this->completion->complete($model, $request->validate(['reason' => ['required', 'string']])['reason'], $request->user());
        return back()->with('status', 'Requirement wahana diselesaikan.');
    }

    public function calculate(Request $request, PkpaEnrollment $enrollment): RedirectResponse
    {
        $calculation = $this->finalGrades->calculate($enrollment, $request->user());
        return back()->with('status', 'Calculation final: '.$calculation->status);
    }

    public function finalize(Request $request, PkpaFinalGradeCalculation $calculation): RedirectResponse
    {
        $this->finalGrades->finalize($calculation, $request->user());
        return back()->with('status', 'Nilai akhir PKPA difinalisasi.');
    }

    public function decide(Request $request, PkpaEnrollment $enrollment, PkpaFinalGradeResult $result): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:passed,not_passed,pending_remedial'], 'reason' => ['required', 'string']]);
        $this->finalGrades->decide($enrollment, $result, $data['decision'], $data['reason'], $request->user());
        return back()->with('status', 'Keputusan kelulusan dicatat.');
    }

    public function release(Request $request, PkpaFinalGradeResult $result): RedirectResponse
    {
        $decision = PkpaGraduationDecision::where('pkpa_final_grade_result_id', $result->id)->latest()->first();
        $this->finalGrades->release($result, $decision, $request->user());
        return back()->with('status', 'Hasil akhir dirilis ke mahasiswa.');
    }

    public function remedial(Request $request, PkpaEnrollment $enrollment): RedirectResponse
    {
        $this->remedials->openCase($enrollment, $request->validate(['reason' => ['required', 'string'], 'case_type' => ['nullable', 'string']]), $request->user());
        return back()->with('status', 'Kasus remedial dibuka.');
    }

    public function export()
    {
        $results = PkpaFinalGradeResult::with('enrollment')->get();
        return response()->streamDownload(function () use ($results) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['Rekap Hasil Akhir Program PKPA - MY PKPA']);
            fputcsv($h, ['Core User', 'Final Score', 'Status', 'Released At']);
            foreach ($results as $result) {
                fputcsv($h, [$result->enrollment?->core_user_id, $result->final_score, $result->result_status, $result->released_at]);
            }
            fclose($h);
        }, 'rekap_hasil_akhir_pkpa_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
