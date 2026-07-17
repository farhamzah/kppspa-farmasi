<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\Pkpa\StorePkpaPracticeSiteRequest;
use App\Http\Requests\Management\Pkpa\UpdatePkpaPracticeSiteRequest;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Services\PkpaPracticeSiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaPracticeSiteController extends Controller
{
    public function __construct(private readonly PkpaPracticeSiteService $siteService)
    {
    }

    public function index(Request $request): View
    {
        $sites = $this->siteService->query($request->only([
            'q',
            'practice_domain_id',
            'practice_domain_option_id',
            'city',
            'province',
            'status',
            'active',
            'cooperation',
        ]))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('management.pkpa-practice-sites.index', [
            'sites' => $sites,
            'domains' => $this->domains(),
            'filters' => $request->only(['q', 'practice_domain_id', 'practice_domain_option_id', 'city', 'province', 'status', 'active', 'cooperation']),
        ]);
    }

    public function create(): View
    {
        return view('management.pkpa-practice-sites.create', [
            'site' => new PkpaPracticeSite(['status' => 'draft', 'is_active' => true]),
            'domains' => $this->domains(),
        ]);
    }

    public function store(StorePkpaPracticeSiteRequest $request): RedirectResponse
    {
        $site = $this->siteService->create($request->validated() + ['is_active' => $request->boolean('is_active', true)], $request->user());

        return redirect()->route('management.pkpa-practice-sites.show', $site)->with('status', 'Tempat praktik berhasil dibuat.');
    }

    public function show(PkpaPracticeSite $pkpaPracticeSite): View
    {
        return view('management.pkpa-practice-sites.show', [
            'site' => $pkpaPracticeSite->load(['practiceDomain', 'practiceDomainOption']),
        ]);
    }

    public function edit(PkpaPracticeSite $pkpaPracticeSite): View
    {
        return view('management.pkpa-practice-sites.edit', [
            'site' => $pkpaPracticeSite->load(['practiceDomain', 'practiceDomainOption']),
            'domains' => $this->domains(),
        ]);
    }

    public function update(UpdatePkpaPracticeSiteRequest $request, PkpaPracticeSite $pkpaPracticeSite): RedirectResponse
    {
        $site = $this->siteService->update($pkpaPracticeSite, $request->validated() + ['is_active' => $request->boolean('is_active')], $request->user());

        return redirect()->route('management.pkpa-practice-sites.show', $site)->with('status', 'Tempat praktik berhasil diperbarui.');
    }

    public function destroy(PkpaPracticeSite $pkpaPracticeSite, Request $request): RedirectResponse
    {
        $this->siteService->delete($pkpaPracticeSite, $request->user());

        return redirect()->route('management.pkpa-practice-sites.index')->with('status', 'Tempat praktik berhasil dinonaktifkan dari daftar aktif.');
    }

    private function domains()
    {
        return PkpaPracticeDomain::query()->with('activeOptions')->orderBy('sort_order')->orderBy('name')->get();
    }
}
