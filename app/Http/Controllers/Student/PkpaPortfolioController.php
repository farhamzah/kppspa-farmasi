<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PkpaPortfolioTemplate;
use App\Models\PkpaPortfolioExportVersion;
use App\Models\PkpaRotationPortfolio;
use App\Models\PkpaRotationRun;
use App\Services\PkpaPortfolioBuilderService;
use App\Support\PkpaApotekPortfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PkpaPortfolioController extends Controller
{
    public function __construct(private readonly PkpaPortfolioBuilderService $portfolios)
    {
    }

    public function index(Request $request)
    {
        $runs = PkpaRotationRun::with(['program', 'practiceDomain', 'practiceSite'])
            ->forStudent($request->user()->core_user_id)
            ->latest()
            ->get();
        $supportedRuns = $runs->filter(function (PkpaRotationRun $run) {
            return PkpaApotekPortfolio::isApotekCode($run->practiceDomain?->code)
                && PkpaPortfolioTemplate::query()
                    ->where('practice_domain_id', $run->practice_domain_id)
                    ->where('is_current', true)
                    ->where('status', 'active')
                    ->where(function ($query) use ($run) {
                        $query->whereNull('pkpa_program_id')->orWhere('pkpa_program_id', $run->pkpa_program_id);
                    })
                    ->exists();
        });
        $items = $supportedRuns->map(fn ($run) => $this->portfolios->ensureForRun($run, $request->user()));

        return view('student.pkpa-portfolios.index', [
            'portfolios' => $items,
            'supported_runs' => $supportedRuns->count(),
            'hidden_runs' => max(0, $runs->count() - $supportedRuns->count()),
        ]);
    }

    public function show(Request $request, PkpaRotationPortfolio $portfolio)
    {
        abort_unless($this->portfolios->canAccess($portfolio, $request->user()), 403);
        $portfolio = $this->portfolios->syncProgress($portfolio->fresh(['template.sections', 'sectionRecords', 'caseReports', 'weeklyReflections', 'selfAssessments', 'documentationItems', 'reviews', 'exportVersions']));

        return view('student.pkpa-portfolios.show', compact('portfolio'));
    }

    public function showIntegrity(Request $request, PkpaRotationPortfolio $portfolio)
    {
        abort_unless($this->portfolios->canAccess($portfolio, $request->user()), 403);
        $portfolio = $this->portfolios->syncProgress($portfolio->fresh(['rotationRun.practiceSite', 'rotationRun.enrollment']));
        $sheet = $this->portfolios->integritySheetData($portfolio);

        return view('student.pkpa-portfolios.integrity-sheet', [
            'portfolio' => $portfolio,
            'sheet' => $sheet,
            'interactive' => true,
        ]);
    }

    public function acknowledge(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $this->portfolios->acknowledgeIntegrity($portfolio, $request->user());

        return back()->with('status', 'Pakta integritas disetujui.');
    }

    public function declineIntegrity(Request $request, PkpaRotationPortfolio $portfolio)
    {
        abort_unless($this->portfolios->canAccess($portfolio, $request->user()), 403);

        return redirect()
            ->route('student.pkpa-portfolios.show', $portfolio)
            ->withErrors(['integrity' => 'Pakta integritas belum disetujui. Silakan baca kembali sebelum melanjutkan pengiriman portofolio.']);
    }

    public function verifyIntegrity(Request $request, PkpaRotationPortfolio $portfolio)
    {
        abort_unless($request->hasValidSignature() && $this->portfolios->matchesIntegrityToken($portfolio, $request->query('token')), 403);
        $sheet = $this->portfolios->integritySheetData($portfolio);

        return view('student.pkpa-portfolios.integrity-sheet', [
            'portfolio' => $portfolio,
            'sheet' => $sheet,
            'interactive' => false,
        ]);
    }

    public function storeSection(Request $request, PkpaRotationPortfolio $portfolio, string $sectionCode)
    {
        $definition = PkpaApotekPortfolio::sectionDefinition($sectionCode);
        abort_unless($definition, 404);

        $rules = [];
        foreach ($definition['fields'] ?? [] as $field) {
            $rules[$field['name']] = ['nullable', 'string'];
        }

        $data = $request->validate($rules);
        $this->portfolios->saveSectionRecord($portfolio, $sectionCode, $data, $request->user());

        return back()->with('status', ($definition['title'] ?? 'Bagian portofolio').' tersimpan.');
    }

    public function storeCase(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $data = $request->validate([
            'case_code' => ['required', 'string', 'max:255'],
            'case_date' => ['nullable', 'date'],
            'patient_initials' => ['nullable', 'string', 'max:16'],
            'gender' => ['nullable', 'string', 'max:32'],
            'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'complaint' => ['nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'history' => ['nullable', 'string'],
            'allergy' => ['nullable', 'string'],
            'medication_use' => ['nullable', 'string'],
            'drp' => ['nullable', 'string'],
            'intervention' => ['nullable', 'string'],
            'monitoring' => ['nullable', 'string'],
            'education' => ['nullable', 'string'],
            'conclusion' => ['nullable', 'string'],
            'references' => ['nullable', 'string'],
            'anonymization_confirmed' => ['accepted'],
        ]);
        $this->portfolios->saveCase($portfolio, $data, $request->user());

        return back()->with('status', 'Studi kasus tersimpan.');
    }

    public function storeReflection(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $data = $request->validate([
            'week_number' => ['required', 'integer', 'min:1'],
            'period_start_date' => ['nullable', 'date'],
            'period_end_date' => ['nullable', 'date'],
            'unit' => ['nullable', 'string', 'max:255'],
            'target' => ['nullable', 'string'],
            'achievement' => ['nullable', 'string'],
            'obstacle' => ['nullable', 'string'],
            'solution' => ['nullable', 'string'],
            'reflection' => ['nullable', 'string'],
            'next_plan' => ['nullable', 'string'],
        ]);
        $this->portfolios->saveReflection($portfolio, $data, $request->user());

        return back()->with('status', 'Refleksi tersimpan.');
    }

    public function storeSelfAssessment(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $data = $request->validate([
            'aspect' => ['required', 'string', 'max:255'],
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'evidence_experience' => ['nullable', 'string'],
            'strength' => ['nullable', 'string'],
            'weakness' => ['nullable', 'string'],
            'improvement_plan' => ['nullable', 'string'],
            'final_reflection' => ['nullable', 'string'],
        ]);
        $this->portfolios->saveSelfAssessment($portfolio, $data, $request->user());

        return back()->with('status', 'Penilaian Diri tersimpan.');
    }

    public function storeDocumentation(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:255'],
            'activity_date' => ['nullable', 'date'],
            'activity' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'competency_label' => ['nullable', 'string', 'max:255'],
            'anonymization_confirmed' => ['accepted'],
            'consent_confirmed' => ['accepted'],
            'file' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);
        unset($data['file']);
        $this->portfolios->saveDocumentation($portfolio, $data, $request->file('file'), $request->user());

        return back()->with('status', 'Dokumentasi tersimpan.');
    }

    public function submit(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $this->portfolios->submit($portfolio, $request->user());

        return back()->with('status', 'Portofolio dikirim ke Preseptor.');
    }

    public function submitToInternal(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $this->portfolios->submitToInternal($portfolio, $request->user());

        return back()->with('status', 'Portofolio dikirim ke Pembimbing Dalam.');
    }

    public function export(Request $request, PkpaRotationPortfolio $portfolio, string $format)
    {
        $version = $this->portfolios->export($portfolio, $format, $request->user());

        return redirect()->route('student.pkpa-portfolios.exports.download', $version);
    }

    public function download(Request $request, PkpaPortfolioExportVersion $version)
    {
        abort_unless($this->portfolios->canAccess($version->portfolio, $request->user()), 403);

        return Storage::disk($version->disk)->download($version->path, $version->original_filename, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
