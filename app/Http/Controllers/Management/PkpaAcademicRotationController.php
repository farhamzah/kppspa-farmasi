<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\PkpaCompetencySet;
use App\Models\PkpaProgramDomain;
use App\Models\PkpaRotationReportTemplate;
use App\Models\PkpaRotationRun;
use App\Models\PkpaSpecialTaskTemplate;
use App\Services\PkpaAcademicReadinessService;
use App\Services\PkpaCompetencyManagementService;
use App\Services\PkpaRotationCompetencyService;
use App\Services\PkpaRotationReportService;
use App\Services\PkpaSpecialTaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaAcademicRotationController extends Controller
{
    public function __construct(
        private readonly PkpaCompetencyManagementService $competencies,
        private readonly PkpaRotationCompetencyService $rotationCompetencies,
        private readonly PkpaSpecialTaskService $tasks,
        private readonly PkpaRotationReportService $reports,
        private readonly PkpaAcademicReadinessService $readiness
    ) {
    }

    public function index(): View
    {
        return view('management.pkpa-academics.index', [
            'programDomains' => PkpaProgramDomain::with(['program', 'practiceDomain', 'activeCompetencySet', 'activeReportTemplate'])->get(),
            'runs' => PkpaRotationRun::with(['enrollment', 'practiceDomain', 'practiceSite', 'competencyRecords', 'specialTasks', 'rotationReport', 'academicReadinessReviews' => fn ($q) => $q->latest('reviewed_at')->limit(1)])->latest()->paginate(20),
            'summary' => [
                'runs' => PkpaRotationRun::count(),
                'without_competency' => PkpaRotationRun::doesntHave('competencyRecords')->count(),
                'ready' => PkpaRotationRun::whereHas('academicReadinessReviews', fn ($q) => $q->where('status', 'ready_for_assessment'))->count(),
                'blocked' => PkpaRotationRun::whereHas('academicReadinessReviews', fn ($q) => $q->where('status', 'assessment_blocked'))->count(),
            ],
        ]);
    }

    public function storeCompetencySet(Request $request, PkpaProgramDomain $programDomain): RedirectResponse
    {
        $set = $this->competencies->createSet($programDomain, $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
        ]), $request->user());

        return back()->with('status', 'Set kompetensi dibuat. Tambahkan item sebelum aktivasi.')->with('competency_set_id', $set->id);
    }

    public function storeCompetencyItem(Request $request, PkpaCompetencySet $set): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'achievement_criteria' => ['nullable', 'string'],
            'evidence_required' => ['nullable', 'boolean'],
            'minimum_evidence_count' => ['nullable', 'integer', 'min:0'],
            'is_required' => ['nullable', 'boolean'],
        ]);
        $data['evidence_required'] = $request->boolean('evidence_required');
        $data['is_required'] = $request->boolean('is_required', true);
        $this->competencies->saveItem($set, $data, $request->user());

        return back()->with('status', 'Item kompetensi disimpan.');
    }

    public function activateCompetencySet(Request $request, PkpaCompetencySet $set): RedirectResponse
    {
        $this->competencies->activate($set, $request->user());

        return back()->with('status', 'Set kompetensi diaktifkan.');
    }

    public function storeTaskTemplate(Request $request, PkpaProgramDomain $programDomain): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'submission_type' => ['required', 'in:document,presentation,case_report,project,reflection,mixed'],
            'due_offset_days' => ['nullable', 'integer'],
        ]);
        $data['status'] = 'active';
        $data['is_required'] = $request->boolean('is_required', true);
        $data['field_supervisor_review_required'] = $request->boolean('field_supervisor_review_required');
        $data['internal_supervisor_review_required'] = $request->boolean('internal_supervisor_review_required', true);
        $this->tasks->saveTemplate($programDomain, $data, $request->user());

        return back()->with('status', 'Template tugas khusus disimpan.');
    }

    public function storeReportTemplate(Request $request, PkpaProgramDomain $programDomain): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'maximum_file_size_kb' => ['nullable', 'integer', 'min:1'],
        ]);
        $data['status'] = 'active';
        $data['is_current'] = true;
        $data['field_supervisor_confirmation_required'] = $request->boolean('field_supervisor_confirmation_required');
        $data['internal_supervisor_approval_required'] = true;
        $this->reports->saveTemplate($programDomain, $data, $request->user());

        return back()->with('status', 'Template laporan rotasi disimpan.');
    }

    public function ensureChecklist(Request $request, PkpaRotationRun $run): RedirectResponse
    {
        $created = $this->rotationCompetencies->ensureChecklist($run, $request->user());

        return back()->with('status', "{$created} checklist kompetensi baru dibuat.");
    }

    public function assignTasks(Request $request, PkpaRotationRun $run): RedirectResponse
    {
        $created = $this->tasks->assignFromTemplates($run, $request->user());

        return back()->with('status', "{$created} tugas khusus baru ditugaskan.");
    }

    public function readiness(Request $request, PkpaRotationRun $run): RedirectResponse
    {
        $review = $this->readiness->review($run, $request->user());

        return back()->with('status', 'Readiness akademik: '.$review->status);
    }

    public function export()
    {
        $runs = PkpaRotationRun::with(['enrollment', 'practiceDomain', 'practiceSite', 'competencyRecords', 'specialTasks', 'rotationReport', 'guidanceSessions', 'academicReadinessReviews' => fn ($q) => $q->latest('reviewed_at')->limit(1)])->get();

        return response()->streamDownload(function () use ($runs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Mahasiswa', 'NPM', 'Core ID', 'Wahana', 'Tempat', 'Kompetensi Verified/Required', 'Tugas Approved/Required', 'Laporan', 'Bimbingan', 'Readiness']);
            foreach ($runs as $run) {
                $requiredCompetencies = $run->competencyRecords->where('is_required_snapshot', true);
                $requiredTasks = $run->specialTasks->where('is_required_snapshot', true);
                fputcsv($handle, [
                    $run->studentDisplayName(),
                    $run->enrollment?->student_number,
                    $run->student_core_user_id,
                    $run->practiceDomain?->name,
                    $run->practiceSite?->name,
                    $requiredCompetencies->where('status', 'verified')->count().'/'.$requiredCompetencies->count(),
                    $requiredTasks->where('status', 'approved')->count().'/'.$requiredTasks->count(),
                    $run->rotationReport?->status ?? '-',
                    $run->guidanceSessions->count(),
                    $run->academicReadinessReviews->first()?->status ?? '-',
                ]);
            }
            fclose($handle);
        }, 'monitoring_akademik_pkpa_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
