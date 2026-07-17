<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\Pkpa\StorePkpaProgramRequest;
use App\Http\Requests\Management\Pkpa\UpdatePkpaProgramDomainsRequest;
use App\Http\Requests\Management\Pkpa\UpdatePkpaProgramRequest;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramDomain;
use App\Services\PkpaProgramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaProgramController extends Controller
{
    public function __construct(private readonly PkpaProgramService $programService)
    {
    }

    public function index(Request $request): View
    {
        $programs = PkpaProgram::query()
            ->withCount(['domains as active_domains_count' => fn ($query) => $query->where('is_active', true)])
            ->search($request->input('q'))
            ->when($request->filled('academic_year'), fn ($query) => $query->where('academic_year', $request->academic_year))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', (bool) $request->boolean('active')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('management.pkpa-programs.index', [
            'programs' => $programs,
            'filters' => $request->only(['q', 'academic_year', 'status', 'active']),
        ]);
    }

    public function create(): View
    {
        return view('management.pkpa-programs.create', ['program' => new PkpaProgram]);
    }

    public function store(StorePkpaProgramRequest $request): RedirectResponse
    {
        $program = $this->programService->create($request->validated(), $request->user());

        return redirect()
            ->route('management.pkpa-programs.configure', $program)
            ->with('status', 'Program PKPA berhasil dibuat. Isi durasi setiap wahana sebelum aktivasi.');
    }

    public function show(PkpaProgram $pkpaProgram): View
    {
        $pkpaProgram->load(['domains.practiceDomain.options']);

        return view('management.pkpa-programs.show', [
            'program' => $pkpaProgram,
            'readiness' => $this->programService->readiness($pkpaProgram),
        ]);
    }

    public function edit(PkpaProgram $pkpaProgram): View
    {
        return view('management.pkpa-programs.edit', ['program' => $pkpaProgram]);
    }

    public function update(UpdatePkpaProgramRequest $request, PkpaProgram $pkpaProgram): RedirectResponse
    {
        $program = $this->programService->update($pkpaProgram, $request->validated(), $request->user());

        return redirect()->route('management.pkpa-programs.show', $program)->with('status', 'Program PKPA berhasil diperbarui.');
    }

    public function configure(PkpaProgram $pkpaProgram): View
    {
        $this->programService->ensureDefaultDomains($pkpaProgram, request()->user());
        $pkpaProgram->load(['domains.practiceDomain.options']);

        return view('management.pkpa-programs.configure', [
            'program' => $pkpaProgram,
            'durationUnits' => PkpaProgramDomain::DURATION_UNITS,
            'readiness' => $this->programService->readiness($pkpaProgram),
        ]);
    }

    public function updateConfiguration(UpdatePkpaProgramDomainsRequest $request, PkpaProgram $pkpaProgram): RedirectResponse
    {
        $this->programService->updateDomainConfiguration($pkpaProgram, $request->validated('domains'), $request->user());

        return redirect()->route('management.pkpa-programs.configure', $pkpaProgram)->with('status', 'Konfigurasi wahana dan durasi berhasil diperbarui.');
    }

    public function readiness(PkpaProgram $pkpaProgram): View
    {
        $pkpaProgram->load(['domains.practiceDomain.options']);

        return view('management.pkpa-programs.readiness', [
            'program' => $pkpaProgram,
            'readiness' => $this->programService->readiness($pkpaProgram),
        ]);
    }

    public function status(Request $request, PkpaProgram $pkpaProgram): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:'.implode(',', PkpaProgram::STATUSES)]]);
        $this->programService->changeStatus($pkpaProgram, $validated['status'], $request->user());

        return redirect()->route('management.pkpa-programs.show', $pkpaProgram)->with('status', 'Status program berhasil diperbarui.');
    }

    public function destroy(PkpaProgram $pkpaProgram): RedirectResponse
    {
        if ($pkpaProgram->domains()->exists()) {
            return back()->withErrors(['program' => 'Program memiliki konfigurasi wahana. Arsipkan/nonaktifkan program, jangan hapus permanen.']);
        }

        $pkpaProgram->delete();

        return redirect()->route('management.pkpa-programs.index')->with('status', 'Program PKPA berhasil dihapus.');
    }
}
