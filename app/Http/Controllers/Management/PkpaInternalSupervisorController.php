<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\Pkpa\StorePkpaInternalSupervisorRequest;
use App\Http\Requests\Management\Pkpa\StorePkpaSupervisorUnavailabilityRequest;
use App\Models\PkpaInternalSupervisorEligibility;
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
        if ($request->filled('program_id')) {
            $program = PkpaProgram::find($request->program_id);
            if ($program) {
                $this->internalService->bootstrapProgram($program, ['status' => 'active'], $request->user());
            }
        }

        $eligibilities = PkpaInternalSupervisorEligibility::query()
            ->with(['program', 'practiceDomain', 'unavailabilityPeriods'])
            ->when($request->filled('program_id'), fn ($q) => $q->where('pkpa_program_id', $request->program_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($sub) => $sub->where('name_snapshot', 'like', '%'.$request->q.'%')->orWhere('core_user_id', 'like', '%'.$request->q.'%')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $cards = $eligibilities->getCollection()
            ->groupBy(fn (PkpaInternalSupervisorEligibility $eligibility) => $eligibility->pkpa_program_id.'|'.$eligibility->core_user_id)
            ->map(function ($group) {
                /** @var PkpaInternalSupervisorEligibility $lead */
                $lead = $group->first();
                $allPeriods = $group->flatMap(fn (PkpaInternalSupervisorEligibility $eligibility) => $eligibility->unavailabilityPeriods)->unique('id')->sortBy('start_date')->values();

                return [
                    'lead' => $lead,
                    'domains' => $group->map(fn (PkpaInternalSupervisorEligibility $eligibility) => $eligibility->practiceDomain?->name)->filter()->unique()->values(),
                    'domain_count' => $group->count(),
                    'unavailability_periods' => $allPeriods,
                ];
            })
            ->values();

        return view('management.pkpa-internal-supervisors.index', [
            'eligibilities' => $eligibilities,
            'cards' => $cards,
            'programs' => PkpaProgram::orderByDesc('id')->get(),
            'filters' => $request->only(['q', 'program_id', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('management.pkpa-internal-supervisors.create', [
            'programs' => PkpaProgram::whereNotIn('status', ['completed', 'archived'])->orderByDesc('id')->get(),
        ]);
    }

    public function store(StorePkpaInternalSupervisorRequest $request): RedirectResponse
    {
        $program = PkpaProgram::findOrFail($request->validated('pkpa_program_id'));
        $summary = $this->internalService->bootstrapProgram($program, $request->validated(), $request->user(), true);

        return redirect()->route('management.pkpa-internal-supervisors.index', ['program_id' => $program->id])
            ->with('status', "Pembimbing Dalam otomatis disiapkan untuk semua wahana aktif program. {$summary['created']} baru, {$summary['updated']} diperbarui.");
    }

    public function sync(PkpaInternalSupervisorEligibility $eligibility, Request $request): RedirectResponse
    {
        foreach ($this->internalService->siblingEligibilities($eligibility) as $item) {
            $this->syncService->syncInternal($item, $request->user());
        }

        return back()->with('status', 'Pembimbing Dalam untuk seluruh wahana program berhasil disinkronkan.');
    }

    public function deactivate(PkpaInternalSupervisorEligibility $eligibility, Request $request): RedirectResponse
    {
        foreach ($this->internalService->siblingEligibilities($eligibility) as $item) {
            $this->internalService->deactivate($item, $request->user());
        }

        return back()->with('status', 'Pembimbing Dalam dinonaktifkan untuk seluruh wahana program.');
    }

    public function storeUnavailability(StorePkpaSupervisorUnavailabilityRequest $request, PkpaInternalSupervisorEligibility $eligibility): RedirectResponse
    {
        foreach ($this->internalService->siblingEligibilities($eligibility) as $item) {
            $this->availabilityService->createForInternal($item, $request->validated(), $request->user());
        }

        return back()->with('status', 'Periode tidak tersedia Pembimbing Dalam berhasil dibuat untuk seluruh wahana program.');
    }
}
