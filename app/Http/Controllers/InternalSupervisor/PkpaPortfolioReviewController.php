<?php

namespace App\Http\Controllers\InternalSupervisor;

use App\Http\Controllers\Controller;
use App\Models\PkpaRotationPortfolio;
use App\Models\PkpaRotationRun;
use App\Services\PkpaPortfolioBuilderService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaPortfolioReviewController extends Controller
{
    public function __construct(private readonly PkpaPortfolioBuilderService $portfolios) {}

    public function index(Request $request)
    {
        $runs = PkpaRotationRun::query()
            ->forSupervisor('internal', $request->user()->core_user_id)
            ->whereNull('cancelled_at')
            ->when($request->filled('domain'), fn ($q) => $q->where('practice_domain_id', $request->integer('domain')))
            ->with(['practiceDomain', 'practiceSite', 'enrollment', 'currentPortfolio.practiceDomain'])
            ->latest('scheduled_start_date')
            ->get();

        if ($request->filled('q')) {
            $search = mb_strtolower(trim($request->string('q')->toString()));
            $runs = $runs->filter(fn ($run) => str_contains(mb_strtolower($run->studentDisplayName().' '.$run->studentDisplaySecondary().' '.$run->practiceSite?->name), $search));
        }
        if ($request->query('status', config('my_pkpa.preceptor_document_validation_enabled') ? 'all' : 'ready') === 'ready') {
            $runs = $runs->filter(fn ($run) => in_array($run->currentPortfolio?->status, PkpaRotationPortfolio::internalReviewStatuses(), true));
        }

        $pagination = new LengthAwarePaginator(
            $runs->values()->forPage(max(1, $request->integer('page', 1)), 50)->values(),
            $runs->count(), 50, max(1, $request->integer('page', 1)),
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $runs = $pagination->getCollection();

        return view('internal-supervisor.pkpa-portfolios.index', compact('runs', 'pagination'));
    }

    public function show(Request $request, PkpaRotationPortfolio $portfolio)
    {
        abort_unless($this->portfolios->canAccess($portfolio, $request->user()), 403);

        $portfolio = $this->portfolios->syncProgress($portfolio->fresh());

        return view('internal-supervisor.pkpa-portfolios.show', ['portfolio' => $portfolio->load(['sectionRecords.templateSection', 'weeklyReflections', 'selfAssessments', 'reviews'])]);
    }

    public function approve(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $this->portfolios->review($portfolio, 'internal', 'approve', $request->string('comments')->toString(), $request->user());

        return back()->with('status', 'Portofolio disetujui Pembimbing Dalam.');
    }

    public function revision(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $data = $request->validate(['comments' => ['required', 'string', 'max:1000']]);
        $this->portfolios->review($portfolio, 'internal', 'revision_requested', $data['comments'], $request->user());

        return back()->with('status', 'Revisi akademik diminta.');
    }

    public function bulkApprove(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($request, $data) {
            $portfolios = PkpaRotationPortfolio::whereIn('id', $data['ids'])->orderBy('id')->lockForUpdate()->get();
            if ($portfolios->count() !== count($data['ids'])) {
                throw ValidationException::withMessages(['ids' => 'Sebagian portofolio tidak tersedia. Muat ulang antrean.']);
            }
            foreach ($portfolios as $portfolio) {
                $this->portfolios->review($portfolio, 'internal', 'approve', $data['comments'] ?? '', $request->user());
            }
        });

        return back()->with('status', count($data['ids']).' portofolio berhasil disetujui.');
    }
}
