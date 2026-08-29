<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\Pkpa\StorePkpaFieldSupervisorRequest;
use App\Http\Requests\Management\Pkpa\StorePkpaProgramSiteRequest;
use App\Http\Requests\Management\Pkpa\StorePkpaSiteAvailabilityRequest;
use App\Http\Requests\Management\Pkpa\StorePkpaSupervisorUnavailabilityRequest;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramSite;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\PkpaSiteAvailabilityPeriod;
use App\Models\PkpaSupervisorUnavailabilityPeriod;
use App\Services\PkpaFieldSupervisorService;
use App\Services\PkpaProgramSiteService;
use App\Services\PkpaSiteAvailabilityService;
use App\Services\PkpaSupervisorAvailabilityService;
use App\Services\PkpaSupervisorCoreSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaProgramSiteController extends Controller
{
    public function __construct(
        private readonly PkpaProgramSiteService $programSiteService,
        private readonly PkpaSiteAvailabilityService $availabilityService,
        private readonly PkpaFieldSupervisorService $fieldSupervisorService,
        private readonly PkpaSupervisorAvailabilityService $supervisorAvailabilityService,
        private readonly PkpaSupervisorCoreSyncService $syncService,
    ) {
    }

    public function index(Request $request): View
    {
        return $this->renderIndex($request);
    }

    public function preceptorsIndex(Request $request): View
    {
        $supervisors = PkpaSiteFieldSupervisor::query()
            ->with([
                'practiceSite.programSites.program',
                'practiceSite.programSites.practiceDomain',
                'practiceSite.programSites.practiceDomainOption',
                'user.lecturer',
            ])
            ->whereHas('practiceSite.programSites')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = trim((string) $request->input('q'));

                $query->where(function ($sub) use ($search) {
                    $sub
                        ->where('name_snapshot', 'like', '%'.$search.'%')
                        ->orWhere('email_snapshot', 'like', '%'.$search.'%')
                        ->orWhere('core_user_id', 'like', '%'.$search.'%')
                        ->orWhere('position_title', 'like', '%'.$search.'%')
                        ->orWhereHas('practiceSite', fn ($site) => $site
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('code', 'like', '%'.$search.'%')
                            ->orWhere('city', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('program_id'), fn ($query) => $query->whereHas('practiceSite.programSites', fn ($sites) => $sites->where('pkpa_program_id', $request->integer('program_id'))))
            ->when($request->filled('practice_domain_id'), fn ($query) => $query->whereHas('practiceSite.programSites', fn ($sites) => $sites->where('practice_domain_id', $request->integer('practice_domain_id'))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('management.pkpa-preceptors.index', [
            'supervisors' => $supervisors,
            'programs' => PkpaProgram::orderByDesc('id')->get(),
            'domains' => PkpaPracticeDomain::orderBy('sort_order')->get(),
            'filters' => $request->only(['q', 'program_id', 'practice_domain_id', 'status']),
        ]);
    }

    public function show(PkpaProgramSite $pkpaProgramSite): View
    {
        return $this->renderShow($pkpaProgramSite);
    }

    public function showPreceptors(PkpaProgramSite $pkpaProgramSite): View
    {
        return $this->renderShow($pkpaProgramSite, 'preceptors');
    }

    private function renderIndex(Request $request, string $mode = 'sites'): View
    {
        $query = PkpaProgramSite::query()
            ->with(['program', 'practiceSite', 'practiceDomain', 'practiceDomainOption'])
            ->withCount(['availabilityPeriods'])
            ->search($request->input('q'))
            ->when($request->filled('program_id'), fn ($q) => $q->where('pkpa_program_id', $request->program_id))
            ->when($request->filled('practice_domain_id'), fn ($q) => $q->where('practice_domain_id', $request->practice_domain_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status));

        return view('management.pkpa-program-sites.index', [
            'programSites' => $query->latest()->paginate(12)->withQueryString(),
            'programs' => PkpaProgram::orderByDesc('id')->get(),
            'domains' => PkpaPracticeDomain::orderBy('sort_order')->get(),
            'filters' => $request->only(['q', 'program_id', 'practice_domain_id', 'status']),
            'pageMode' => $mode,
            'pageTitle' => 'Tempat Tersedia',
        ]);
    }

    public function create(): View
    {
        return view('management.pkpa-program-sites.create', [
            'programs' => PkpaProgram::whereNotIn('status', ['completed', 'archived'])->orderByDesc('id')->get(),
            'sites' => PkpaPracticeSite::with(['practiceDomain', 'practiceDomainOption'])->where('is_active', true)->where('status', 'active')->orderBy('name')->get(),
            'programSite' => new PkpaProgramSite(['status' => 'active', 'is_active' => true]),
        ]);
    }

    public function store(StorePkpaProgramSiteRequest $request): RedirectResponse
    {
        $program = PkpaProgram::findOrFail($request->validated('pkpa_program_id'));
        $site = PkpaPracticeSite::findOrFail($request->validated('practice_site_id'));
        $programSite = $this->programSiteService->create($program, $site, $request->validated() + ['is_active' => $request->boolean('is_active', true)], $request->user());

        return redirect()->route('management.pkpa-program-sites.show', $programSite)->with('status', 'Tempat berhasil ditambahkan ke Program PKPA.');
    }

    private function renderShow(PkpaProgramSite $pkpaProgramSite, string $mode = 'sites'): View
    {
        $pkpaProgramSite->load(['program', 'practiceSite.fieldSupervisors.unavailabilityPeriods', 'practiceDomain', 'practiceDomainOption', 'availabilityPeriods']);

        return view('management.pkpa-program-sites.show', [
            'programSite' => $pkpaProgramSite,
            'pageMode' => $mode,
            'pageTitle' => $mode === 'preceptors' ? 'Kelola Preseptor' : 'Kelola Tempat Tersedia',
        ]);
    }

    public function deactivate(PkpaProgramSite $pkpaProgramSite, Request $request): RedirectResponse
    {
        $this->programSiteService->deactivate($pkpaProgramSite, $request->user());

        return back()->with('status', 'Tempat program berhasil dinonaktifkan tanpa menghapus histori.');
    }

    public function storeAvailability(StorePkpaSiteAvailabilityRequest $request, PkpaProgramSite $pkpaProgramSite): RedirectResponse
    {
        $this->availabilityService->create($pkpaProgramSite, $request->validated(), $request->user());

        return back()->with('status', 'Periode availability berhasil dibuat.');
    }

    public function updateAvailability(StorePkpaSiteAvailabilityRequest $request, PkpaProgramSite $pkpaProgramSite, PkpaSiteAvailabilityPeriod $period): RedirectResponse
    {
        abort_unless((int) $period->pkpa_program_site_id === (int) $pkpaProgramSite->id, 404);
        $this->availabilityService->update($period, $request->validated(), $request->user());

        return back()->with('status', 'Periode availability berhasil diperbarui.');
    }

    public function cancelAvailability(PkpaProgramSite $pkpaProgramSite, PkpaSiteAvailabilityPeriod $period, Request $request): RedirectResponse
    {
        abort_unless((int) $period->pkpa_program_site_id === (int) $pkpaProgramSite->id, 404);
        $this->availabilityService->cancel($period, $request->user());

        return back()->with('status', 'Periode availability berhasil dibatalkan.');
    }

    public function storeFieldSupervisor(StorePkpaFieldSupervisorRequest $request, PkpaProgramSite $pkpaProgramSite): RedirectResponse
    {
        $this->fieldSupervisorService->create($pkpaProgramSite->practiceSite, $request->validated() + ['is_primary_contact' => $request->boolean('is_primary_contact')], $request->user());

        return back()->with('status', 'Preseptor berhasil ditambahkan dari Core.');
    }

    public function syncFieldSupervisor(PkpaProgramSite $pkpaProgramSite, PkpaSiteFieldSupervisor $supervisor, Request $request): RedirectResponse
    {
        abort_unless((int) $supervisor->practice_site_id === (int) $pkpaProgramSite->practice_site_id, 404);
        $this->syncService->syncField($supervisor, $request->user());

        return back()->with('status', 'Preseptor berhasil disinkronkan.');
    }

    public function storeFieldUnavailability(StorePkpaSupervisorUnavailabilityRequest $request, PkpaProgramSite $pkpaProgramSite, PkpaSiteFieldSupervisor $supervisor): RedirectResponse
    {
        abort_unless((int) $supervisor->practice_site_id === (int) $pkpaProgramSite->practice_site_id, 404);
        $this->supervisorAvailabilityService->createForField($supervisor, $request->validated(), $request->user());

        return back()->with('status', 'Periode tidak tersedia preseptor berhasil dibuat.');
    }

    public function cancelUnavailability(PkpaSupervisorUnavailabilityPeriod $period, Request $request): RedirectResponse
    {
        $this->supervisorAvailabilityService->cancel($period, $request->user());

        return back()->with('status', 'Periode tidak tersedia berhasil dibatalkan.');
    }
}
