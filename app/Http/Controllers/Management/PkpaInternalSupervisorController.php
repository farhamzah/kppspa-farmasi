<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\Pkpa\StorePkpaInternalSupervisorRequest;
use App\Http\Requests\Management\Pkpa\StorePkpaSupervisorUnavailabilityRequest;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaProgram;
use App\Services\PkpaInternalSupervisorService;
use App\Services\PkpaSupervisorAvailabilityService;
use App\Services\PkpaSupervisorCoreSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaInternalSupervisorController extends Controller
{
    public function __construct(
        private readonly PkpaInternalSupervisorService $internalService,
        private readonly PkpaSupervisorAvailabilityService $availabilityService,
        private readonly PkpaSupervisorCoreSyncService $syncService,
    ) {
    }

    public function index(Request $request): View
    {
        $eligibilities = PkpaInternalSupervisorEligibility::query()
            ->with(['program', 'practiceDomain', 'unavailabilityPeriods'])
            ->when($request->filled('program_id'), fn ($q) => $q->where('pkpa_program_id', $request->program_id))
            ->when($request->filled('practice_domain_id'), fn ($q) => $q->where('practice_domain_id', $request->practice_domain_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($sub) => $sub->where('name_snapshot', 'like', '%'.$request->q.'%')->orWhere('core_user_id', 'like', '%'.$request->q.'%')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('management.pkpa-internal-supervisors.index', [
            'eligibilities' => $eligibilities,
            'programs' => PkpaProgram::orderByDesc('id')->get(),
            'domains' => PkpaPracticeDomain::orderBy('sort_order')->get(),
            'filters' => $request->only(['q', 'program_id', 'practice_domain_id', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('management.pkpa-internal-supervisors.create', [
            'programs' => PkpaProgram::whereNotIn('status', ['completed', 'archived'])->orderByDesc('id')->get(),
            'domains' => PkpaPracticeDomain::active()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(StorePkpaInternalSupervisorRequest $request): RedirectResponse
    {
        $program = PkpaProgram::findOrFail($request->validated('pkpa_program_id'));
        $domain = PkpaPracticeDomain::findOrFail($request->validated('practice_domain_id'));
        $eligibility = $this->internalService->create($program, $domain, $request->validated(), $request->user());

        return redirect()->route('management.pkpa-internal-supervisors.index')->with('status', 'Eligibility Pembimbing Dalam berhasil dibuat untuk '.$eligibility->name_snapshot.'.');
    }

    public function sync(PkpaInternalSupervisorEligibility $eligibility, Request $request): RedirectResponse
    {
        $this->syncService->syncInternal($eligibility, $request->user());

        return back()->with('status', 'Pembimbing Dalam berhasil disinkronkan.');
    }

    public function deactivate(PkpaInternalSupervisorEligibility $eligibility, Request $request): RedirectResponse
    {
        $this->internalService->deactivate($eligibility, $request->user());

        return back()->with('status', 'Eligibility Pembimbing Dalam berhasil dinonaktifkan.');
    }

    public function storeUnavailability(StorePkpaSupervisorUnavailabilityRequest $request, PkpaInternalSupervisorEligibility $eligibility): RedirectResponse
    {
        $this->availabilityService->createForInternal($eligibility, $request->validated(), $request->user());

        return back()->with('status', 'Periode tidak tersedia Pembimbing Dalam berhasil dibuat.');
    }
}
