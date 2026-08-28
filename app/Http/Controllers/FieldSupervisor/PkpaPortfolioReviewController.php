<?php

namespace App\Http\Controllers\FieldSupervisor;

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
                ->where('supervisor_type', 'field')
                ->where('core_user_id', $request->user()->core_user_id)
                ->where('status', 'active'))
            ->latest()
            ->paginate(20);

        return view('field-supervisor.pkpa-portfolios.index', ['portfolios' => $items]);
    }

    public function show(Request $request, PkpaRotationPortfolio $portfolio)
    {
        abort_unless($this->portfolios->canAccess($portfolio, $request->user()), 403);

        return view('field-supervisor.pkpa-portfolios.show', ['portfolio' => $portfolio->load(['caseReports', 'documentationItems', 'reviews'])]);
    }

    public function verify(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $this->portfolios->review($portfolio, 'field', 'verify', $request->string('comments')->toString(), $request->user());

        return back()->with('status', 'Portofolio diverifikasi Preseptor.');
    }

    public function revision(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $data = $request->validate(['comments' => ['required', 'string', 'max:1000']]);
        $this->portfolios->review($portfolio, 'field', 'revision_requested', $data['comments'], $request->user());

        return back()->with('status', 'Revisi diminta.');
    }
}
