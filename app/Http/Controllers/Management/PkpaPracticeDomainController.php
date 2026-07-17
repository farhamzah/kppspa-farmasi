<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\Pkpa\StorePkpaPracticeDomainOptionRequest;
use App\Http\Requests\Management\Pkpa\StorePkpaPracticeDomainRequest;
use App\Http\Requests\Management\Pkpa\UpdatePkpaPracticeDomainOptionRequest;
use App\Http\Requests\Management\Pkpa\UpdatePkpaPracticeDomainRequest;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeDomainOption;
use App\Services\PkpaPracticeDomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaPracticeDomainController extends Controller
{
    public function __construct(private readonly PkpaPracticeDomainService $domainService)
    {
    }

    public function index(Request $request): View
    {
        $domains = PkpaPracticeDomain::query()
            ->withCount(['options', 'practiceSites'])
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($sub) => $sub
                ->where('code', 'like', '%'.$request->q.'%')
                ->orWhere('name', 'like', '%'.$request->q.'%')))
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', (bool) $request->boolean('active')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('management.pkpa-practice-domains.index', [
            'domains' => $domains,
            'filters' => $request->only(['q', 'active']),
        ]);
    }

    public function create(): View
    {
        return view('management.pkpa-practice-domains.create', ['domain' => new PkpaPracticeDomain]);
    }

    public function store(StorePkpaPracticeDomainRequest $request): RedirectResponse
    {
        $domain = $this->domainService->createDomain($request->validated() + ['is_active' => $request->boolean('is_active', true)], $request->user());

        return redirect()->route('management.pkpa-practice-domains.show', $domain)->with('status', 'Wahana PKPA berhasil dibuat.');
    }

    public function show(PkpaPracticeDomain $pkpaPracticeDomain): View
    {
        $pkpaPracticeDomain->load(['options', 'practiceSites']);

        return view('management.pkpa-practice-domains.show', ['domain' => $pkpaPracticeDomain]);
    }

    public function edit(PkpaPracticeDomain $pkpaPracticeDomain): View
    {
        return view('management.pkpa-practice-domains.edit', ['domain' => $pkpaPracticeDomain]);
    }

    public function update(UpdatePkpaPracticeDomainRequest $request, PkpaPracticeDomain $pkpaPracticeDomain): RedirectResponse
    {
        $domain = $this->domainService->updateDomain($pkpaPracticeDomain, $request->validated() + ['is_active' => $request->boolean('is_active')], $request->user());

        return redirect()->route('management.pkpa-practice-domains.show', $domain)->with('status', 'Wahana PKPA berhasil diperbarui.');
    }

    public function destroy(PkpaPracticeDomain $pkpaPracticeDomain, Request $request): RedirectResponse
    {
        $this->domainService->deleteDomain($pkpaPracticeDomain, $request->user());

        return redirect()->route('management.pkpa-practice-domains.index')->with('status', 'Wahana PKPA berhasil dihapus.');
    }

    public function storeOption(StorePkpaPracticeDomainOptionRequest $request, PkpaPracticeDomain $pkpaPracticeDomain): RedirectResponse
    {
        $this->domainService->createOption($pkpaPracticeDomain, $request->validated() + ['is_active' => $request->boolean('is_active', true)], $request->user());

        return redirect()->route('management.pkpa-practice-domains.show', $pkpaPracticeDomain)->with('status', 'Pilihan wahana berhasil ditambahkan.');
    }

    public function updateOption(UpdatePkpaPracticeDomainOptionRequest $request, PkpaPracticeDomain $pkpaPracticeDomain, PkpaPracticeDomainOption $option): RedirectResponse
    {
        abort_unless($option->practice_domain_id === $pkpaPracticeDomain->id, 404);
        $this->domainService->updateOption($option, $request->validated() + ['is_active' => $request->boolean('is_active')], $request->user());

        return redirect()->route('management.pkpa-practice-domains.show', $pkpaPracticeDomain)->with('status', 'Pilihan wahana berhasil diperbarui.');
    }

    public function deleteOption(PkpaPracticeDomain $pkpaPracticeDomain, PkpaPracticeDomainOption $option, Request $request): RedirectResponse
    {
        abort_unless($option->practice_domain_id === $pkpaPracticeDomain->id, 404);
        $this->domainService->deleteOption($option, $request->user());

        return redirect()->route('management.pkpa-practice-domains.show', $pkpaPracticeDomain)->with('status', 'Pilihan wahana berhasil dihapus.');
    }
}
