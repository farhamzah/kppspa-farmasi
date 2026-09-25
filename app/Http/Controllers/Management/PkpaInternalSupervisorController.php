<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\Pkpa\StorePkpaInternalSupervisorRequest;
use App\Http\Requests\Management\Pkpa\StorePkpaSupervisorUnavailabilityRequest;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaProgram;
use App\Models\User;
use App\Services\PkpaInternalSupervisorService;
use App\Services\PkpaSupervisorAvailabilityService;
use App\Services\PkpaSupervisorCoreSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class PkpaInternalSupervisorController extends Controller
{
    public function __construct(
        private readonly PkpaInternalSupervisorService $internalService,
        private readonly PkpaSupervisorAvailabilityService $availabilityService,
        private readonly PkpaSupervisorCoreSyncService $syncService,
    ) {}

    public function index(Request $request): View
    {
        $programs = PkpaProgram::query()->orderByDesc('id')->get();
        $program = $request->filled('program_id')
            ? $programs->firstWhere('id', (int) $request->program_id)
            : ($programs->firstWhere('status', 'active') ?? $programs->first());

        if ($program) {
            $this->internalService->completeProgramDomains($program, $request->user());
        }

        $statusFilter = $request->input('status', 'active');

        $eligibilities = PkpaInternalSupervisorEligibility::query()
            ->with(['program', 'practiceDomain', 'unavailabilityPeriods'])
            ->when($program, fn ($q) => $q->where('pkpa_program_id', $program->id))
            ->when($statusFilter !== 'all', fn ($q) => $q->where('status', $statusFilter))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($sub) => $sub->where('name_snapshot', 'like', '%'.$request->q.'%')->orWhere('core_user_id', 'like', '%'.$request->q.'%')))
            ->orderBy('name_snapshot')
            ->get();

        $activeDomains = $program
            ? $program->domains()->with('practiceDomain')->where('is_active', true)->orderBy('sort_order')->get()
            : collect();
        $activeDomainIds = $activeDomains->pluck('practice_domain_id')->map(fn ($id) => (int) $id);

        $cards = $eligibilities
            ->groupBy(fn (PkpaInternalSupervisorEligibility $eligibility) => $eligibility->pkpa_program_id.'|'.$eligibility->core_user_id)
            ->map(function ($group) use ($activeDomainIds) {
                /** @var PkpaInternalSupervisorEligibility $lead */
                $lead = $group->firstWhere('status', 'active') ?? $group->first();
                $allPeriods = $group
                    ->flatMap(fn (PkpaInternalSupervisorEligibility $eligibility) => $eligibility->unavailabilityPeriods)
                    ->unique(fn ($period) => implode('|', [
                        $period->start_date?->toDateString(),
                        $period->end_date?->toDateString(),
                        $period->reason,
                        $period->status,
                    ]))
                    ->sortBy('start_date')
                    ->values();
                $coveredDomainIds = $group
                    ->where('status', 'active')
                    ->pluck('practice_domain_id')
                    ->map(fn ($id) => (int) $id)
                    ->intersect($activeDomainIds)
                    ->unique()
                    ->values();

                return [
                    'lead' => $lead,
                    'domains' => $group->map(fn (PkpaInternalSupervisorEligibility $eligibility) => $eligibility->practiceDomain?->name)->filter()->unique()->values(),
                    'domain_ids' => $coveredDomainIds,
                    'domain_count' => $coveredDomainIds->count(),
                    'domain_complete' => $activeDomainIds->isNotEmpty() && $coveredDomainIds->count() === $activeDomainIds->count(),
                    'unavailability_periods' => $allPeriods,
                ];
            })
            ->values();

        $localUsers = User::query()
            ->with('lecturer')
            ->whereIn('core_user_id', $cards->pluck('lead.core_user_id')->filter()->map(fn ($value) => (string) $value)->unique()->all())
            ->get()
            ->keyBy(fn (User $user) => (string) $user->core_user_id);

        $cards = $cards->map(function (array $card) use ($localUsers) {
            /** @var PkpaInternalSupervisorEligibility $lead */
            $lead = $card['lead'];
            $localUser = $localUsers->get((string) $lead->core_user_id);

            $card['display_name'] = $localUser
                ? user_display_name($localUser, 'pembimbing_dalam')
                : ($lead->name_snapshot ?: $lead->core_user_id);

            return $card;
        })->sortBy('display_name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $totalCards = $cards->count();
        $completeCards = $cards->where('domain_complete', true)->count();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $cards = new LengthAwarePaginator(
            $cards->forPage($page, 10)->values(),
            $totalCards,
            10,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('management.pkpa-internal-supervisors.index', [
            'cards' => $cards,
            'programs' => $programs,
            'selectedProgram' => $program,
            'activeDomains' => $activeDomains,
            'summary' => [
                'total' => $totalCards,
                'complete' => $completeCards,
                'incomplete' => $totalCards - $completeCards,
            ],
            'filters' => ['q' => $request->input('q'), 'status' => $statusFilter, 'program_id' => $program?->id],
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
