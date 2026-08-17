<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\PkpaAssessmentComponent;
use App\Models\PkpaAssessmentRubric;
use App\Models\PkpaAssessmentRubricCriterion;
use App\Models\PkpaAssessmentScheme;
use App\Models\PkpaGradeRelease;
use App\Models\PkpaProgramDomain;
use App\Models\PkpaRotationAssessment;
use App\Models\PkpaRotationGradeResult;
use App\Models\PkpaRotationRun;
use App\Services\PkpaAssessmentSchemeService;
use App\Services\PkpaRotationAssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaAssessmentController extends Controller
{
    public function __construct(
        private readonly PkpaAssessmentSchemeService $schemes,
        private readonly PkpaRotationAssessmentService $assessments
    ) {
    }

    public function index(): View
    {
        return view('management.pkpa-assessments.index', [
            'programDomains' => PkpaProgramDomain::with(['program', 'practiceDomain', 'activeAssessmentScheme.components'])->get(),
            'schemes' => PkpaAssessmentScheme::with('programDomain.practiceDomain', 'components')->latest()->limit(20)->get(),
            'assessments' => PkpaRotationAssessment::with(['rotationRun.practiceDomain', 'rotationRun.practiceSite', 'componentScores', 'gradeResult'])->latest()->paginate(15),
            'runs' => PkpaRotationRun::with(['practiceDomain', 'practiceSite', 'rotationAssessment', 'academicReadinessReviews' => fn ($q) => $q->latest('reviewed_at')->limit(1)])->latest()->limit(30)->get(),
            'summary' => [
                'schemes' => PkpaAssessmentScheme::count(),
                'assessments' => PkpaRotationAssessment::count(),
                'complete' => PkpaRotationAssessment::where('completion_status', 'complete')->count(),
                'finalized' => PkpaRotationGradeResult::whereIn('result_status', ['finalized', 'released'])->count(),
                'released' => PkpaRotationGradeResult::where('result_status', 'released')->count(),
            ],
        ]);
    }

    public function storeScheme(Request $request, PkpaProgramDomain $programDomain): RedirectResponse
    {
        $this->schemes->createScheme($programDomain, $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'maximum_score' => ['nullable', 'numeric', 'min:1'],
            'minimum_passing_score' => ['nullable', 'numeric', 'min:0'],
            'rounding_precision' => ['nullable', 'integer', 'min:0', 'max:4'],
            'rounding_mode' => ['nullable', 'in:half_up,half_even,floor,ceil'],
        ]), $request->user());

        return back()->with('status', 'Skema penilaian dibuat.');
    }

    public function storeComponent(Request $request, PkpaAssessmentScheme $scheme): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'component_type' => ['required', 'string', 'max:80'],
            'assessor_type' => ['required', 'in:field_supervisor,internal_supervisor,coordinator,system,multiple'],
            'calculation_method' => ['required', 'in:rubric,direct_score,completion_percentage,binary_completion,derived'],
            'weight_percentage' => ['required', 'numeric', 'min:0'],
            'maximum_raw_score' => ['required', 'numeric', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['status'] = 'active';
        $data['is_required'] = $request->boolean('is_required', true);
        $this->schemes->saveComponent($scheme, $data, $request->user());

        return back()->with('status', 'Komponen penilaian disimpan.');
    }

    public function storeRubric(Request $request, PkpaAssessmentComponent $component): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'scoring_method' => ['required', 'in:weighted_criteria,sum_criteria,average_criteria,level_points'],
            'maximum_score' => ['required', 'numeric', 'min:1'],
        ]);
        $data['status'] = 'active';
        $this->schemes->saveRubric($component, $data, $request->user());

        return back()->with('status', 'Rubrik disimpan.');
    }

    public function storeCriterion(Request $request, PkpaAssessmentRubric $rubric): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'weight_percentage' => ['required', 'numeric', 'min:0'],
            'maximum_points' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['status'] = 'active';
        $data['is_required'] = $request->boolean('is_required', true);
        $this->schemes->saveCriterion($rubric, $data, $request->user());

        return back()->with('status', 'Kriteria rubrik disimpan.');
    }

    public function storeLevel(Request $request, PkpaAssessmentRubricCriterion $criterion): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'label' => ['required', 'string', 'max:255'],
            'points' => ['required', 'numeric', 'min:0'],
            'minimum_value' => ['nullable', 'numeric'],
            'maximum_value' => ['nullable', 'numeric'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['status'] = 'active';
        $this->schemes->saveLevel($criterion, $data, $request->user());

        return back()->with('status', 'Level rubrik disimpan.');
    }

    public function activateScheme(Request $request, PkpaAssessmentScheme $scheme): RedirectResponse
    {
        $this->schemes->activate($scheme, $request->user());

        return back()->with('status', 'Skema penilaian diaktifkan.');
    }

    public function createAssessment(Request $request, PkpaRotationRun $run): RedirectResponse
    {
        $this->assessments->createFromRun($run, $request->user());

        return back()->with('status', 'Assessment wahana dibuat.');
    }

    public function moderate(Request $request, PkpaRotationAssessment $assessment): RedirectResponse
    {
        $this->assessments->moderate($assessment, $request->validate([
            'reason' => ['required', 'string'],
            'final_total_score' => ['nullable', 'numeric', 'min:0'],
            'moderation_type' => ['nullable', 'string', 'max:60'],
            'review_notes' => ['nullable', 'string'],
        ]), $request->user());

        return back()->with('status', 'Moderasi nilai dicatat.');
    }

    public function finalize(Request $request, PkpaRotationAssessment $assessment): RedirectResponse
    {
        $this->assessments->finalize($assessment, $request->user());

        return back()->with('status', 'Nilai wahana difinalisasi.');
    }

    public function release(Request $request, PkpaRotationGradeResult $result): RedirectResponse
    {
        $this->assessments->release($result, $request->user());

        return back()->with('status', 'Nilai wahana dirilis ke mahasiswa.');
    }

    public function withdraw(Request $request, PkpaGradeRelease $release): RedirectResponse
    {
        $this->assessments->withdrawRelease($release, $request->validate(['reason' => ['required', 'string']])['reason'], $request->user());

        return back()->with('status', 'Release nilai ditarik.');
    }

    public function export()
    {
        $assessments = PkpaRotationAssessment::with(['rotationRun.practiceDomain', 'rotationRun.practiceSite', 'componentScores', 'gradeResult'])->get();

        return response()->streamDownload(function () use ($assessments) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Rekap Penilaian Per Wahana PKPA - MY PKPA']);
            fputcsv($handle, ['Mahasiswa Core', 'Wahana', 'Tempat', 'Status Assessment', 'Completion', 'Final Score', 'Release']);
            foreach ($assessments as $assessment) {
                fputcsv($handle, [
                    $assessment->rotationRun?->student_core_user_id,
                    $assessment->rotationRun?->practiceDomain?->name,
                    $assessment->rotationRun?->practiceSite?->name,
                    $assessment->status,
                    $assessment->completion_status,
                    $assessment->gradeResult?->final_score ?? '-',
                    $assessment->grade_release_status,
                ]);
            }
            fclose($handle);
        }, 'rekap_penilaian_wahana_pkpa_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
