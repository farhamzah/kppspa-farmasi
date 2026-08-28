<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreKpPlaceRequest;
use App\Http\Requests\Management\UpdateKpPlaceRequest;
use App\Models\KpPlace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpPlaceController extends Controller
{
    public function index(Request $request): View
    {
        $places = KpPlace::query()
            ->withCount('quotas')
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('city', 'like', '%'.$request->q.'%')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->type))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('management.places.index', [
            'places' => $places,
            'filters' => $request->only(['q', 'type', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('management.places.create', ['place' => new KpPlace]);
    }

    public function store(StoreKpPlaceRequest $request): RedirectResponse
    {
        $place = KpPlace::create($request->validated() + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('management.kp-places.show', $place)->with('status', 'Tempat PKPA berhasil dibuat.');
    }

    public function show(KpPlace $kpPlace): View
    {
        return view('management.places.show', [
            'place' => $kpPlace->load(['createdBy', 'updatedBy', 'quotas.period']),
        ]);
    }

    public function edit(KpPlace $kpPlace): View
    {
        return view('management.places.edit', ['place' => $kpPlace]);
    }

    public function update(UpdateKpPlaceRequest $request, KpPlace $kpPlace): RedirectResponse
    {
        $kpPlace->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('management.kp-places.show', $kpPlace)->with('status', 'Tempat PKPA berhasil diperbarui.');
    }

    public function destroy(KpPlace $kpPlace): RedirectResponse
    {
        if ($kpPlace->quotas()->exists()) {
            return back()->withErrors(['place' => 'Tempat PKPA sudah memiliki data kapasitas, silakan nonaktifkan jika tidak digunakan.']);
        }

        $kpPlace->delete();

        return redirect()->route('management.kp-places.index')->with('status', 'Tempat PKPA berhasil dihapus.');
    }
}
