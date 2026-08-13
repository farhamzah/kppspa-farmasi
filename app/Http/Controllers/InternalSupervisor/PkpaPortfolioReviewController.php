<?php

namespace App\Http\Controllers\InternalSupervisor;

use App\Http\Controllers\Controller;
use App\Models\PkpaRotationPortfolio;
use App\Services\PkpaPortfolioBuilderService;
use Illuminate\Http\Request;

class PkpaPortfolioReviewController extends Controller
{
    public function __construct(private readonly PkpaPortfolioBuilderService $portfolios)
    {
    }

    public function index(Request $request)
    {
        $items = PkpaRotationPortfolio::with(['rotationRun.practiceSite', 'enrollment', 'practiceDomain'])
            ->whereHas('rotationRun.supervisorHistories', fn ($query) => $query
                ->where('supervisor_type', 'internal')
                ->where('core_user_id', $request->user()->core_user_id)
                ->where('status', 'active'))
            ->latest()
            ->paginate(20);

        return view('internal-supervisor.pkpa-portfolios.index', ['portfolios' => $items]);
    }

    public function show(Request $request, PkpaRotationPortfolio $portfolio)
    {
        abort_unless($this->portfolios->canAccess($portfolio, $request->user()), 403);

        return view('internal-supervisor.pkpa-portfolios.show', ['portfolio' => $portfolio->load(['weeklyReflections', 'selfAssessments', 'reviews'])]);
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
}
