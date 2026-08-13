<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\PkpaPortfolioExportVersion;
use App\Models\PkpaPortfolioTemplate;
use App\Models\PkpaRotationPortfolio;
use App\Models\PkpaRotationRun;
use App\Services\PkpaPortfolioBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PkpaPortfolioBuilderController extends Controller
{
    public function __construct(private readonly PkpaPortfolioBuilderService $portfolios)
    {
    }

    public function index()
    {
        return view('management.pkpa-portfolios.index', [
            'templates' => PkpaPortfolioTemplate::with(['practiceDomain', 'sections'])->orderBy('code')->get(),
            'portfolios' => PkpaRotationPortfolio::with(['program', 'practiceDomain', 'rotationRun.practiceSite'])->latest()->paginate(20),
        ]);
    }

    public function ensure(Request $request, PkpaRotationRun $run)
    {
        $portfolio = $this->portfolios->ensureForRun($run, $request->user());

        return redirect()->route('management.pkpa-portfolios.index')->with('status', 'Portofolio dibuat/diperbarui: #'.$portfolio->id);
    }

    public function reopen(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->portfolios->reopen($portfolio, $data['reason'], $request->user());

        return back()->with('status', 'Portofolio dibuka ulang.');
    }

    public function publish(Request $request, PkpaRotationPortfolio $portfolio)
    {
        $this->portfolios->publish($portfolio, $request->user());

        return back()->with('status', 'Portofolio diterbitkan dan dikunci.');
    }

    public function export(Request $request, PkpaRotationPortfolio $portfolio, string $format)
    {
        $version = $this->portfolios->export($portfolio, $format, $request->user());

        return redirect()->route('management.pkpa-portfolios.exports.download', $version);
    }

    public function download(Request $request, PkpaPortfolioExportVersion $version)
    {
        abort_unless($this->portfolios->canAccess($version->portfolio, $request->user()), 403);

        return Storage::disk($version->disk)->download($version->path, $version->original_filename, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
