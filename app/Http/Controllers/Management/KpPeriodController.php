<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreKpPeriodRequest;
use App\Http\Requests\Management\UpdateKpPeriodRequest;
use App\Models\KpPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpPeriodController extends Controller
{
    public function index(Request $request): View
    {
        $periods = KpPeriod::query()
            ->withCount('quotas')
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('academic_year', 'like', '%'.$request->q.'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('management.periods.index', [
            'periods' => $periods,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('management.periods.create', ['period' => new KpPeriod]);
    }

    public function store(StoreKpPeriodRequest $request): RedirectResponse
    {
        $period = KpPeriod::create($request->validated() + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('management.kp-periods.show', $period)->with('status', 'Periode PKPA berhasil dibuat.');
    }

    public function show(KpPeriod $kpPeriod): View
    {
        return view('management.periods.show', [
            'period' => $kpPeriod->load(['createdBy', 'updatedBy', 'quotas.place']),
        ]);
    }

    public function edit(KpPeriod $kpPeriod): View
    {
        return view('management.periods.edit', ['period' => $kpPeriod]);
    }

    public function update(UpdateKpPeriodRequest $request, KpPeriod $kpPeriod): RedirectResponse
    {
        $kpPeriod->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('management.kp-periods.show', $kpPeriod)->with('status', 'Periode PKPA berhasil diperbarui.');
    }

    public function destroy(KpPeriod $kpPeriod): RedirectResponse
    {
        if ($kpPeriod->quotas()->exists()) {
            return back()->withErrors(['period' => 'Periode PKPA sudah memiliki data kapasitas, hapus kapasitas terlebih dahulu jika benar-benar diperlukan.']);
        }

        $kpPeriod->delete();

        return redirect()->route('management.kp-periods.index')->with('status', 'Periode PKPA berhasil dihapus.');
    }
}
