<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreKpPlaceQuotaRequest;
use App\Http\Requests\Management\UpdateKpPlaceQuotaRequest;
use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpPlaceQuota;
use App\Services\LegacyKpCatalogSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpPlaceQuotaController extends Controller
{
    public function __construct(
        private readonly LegacyKpCatalogSyncService $catalogSync,
    ) {
    }

    public function index(Request $request): View
    {
        $this->catalogSync->sync($request->user());

        $quotas = KpPlaceQuota::query()
            ->with(['period', 'place'])
            ->when($request->filled('period'), fn ($query) => $query->where('kp_period_id', $request->period))
            ->when($request->filled('type'), fn ($query) => $query->whereHas('place', fn ($place) => $place->where('type', $request->type)))
            ->when($request->filled('status'), fn ($query) => $query->where('is_open', $request->status === 'open'))
            ->when($request->filled('q'), fn ($query) => $query->whereHas('place', fn ($place) => $place->where('name', 'like', '%'.$request->q.'%')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('management.quotas.index', [
            'quotas' => $quotas,
            'periods' => KpPeriod::latest()->get(),
            'filters' => $request->only(['period', 'type', 'status', 'q']),
        ]);
    }

    public function create(): View
    {
        $this->catalogSync->sync(request()->user());

        return view('management.quotas.create', [
            'quota' => new KpPlaceQuota(['is_open' => true]),
            'periods' => KpPeriod::latest()->get(),
            'places' => KpPlace::where('status', 'aktif')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreKpPlaceQuotaRequest $request): RedirectResponse
    {
        $quota = KpPlaceQuota::create($request->validated() + [
            'is_open' => $request->boolean('is_open'),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->log($quota, 'created', null, $quota->quota, null, $quota->is_open, $request->input('notes'), $request->user()->id);

        return redirect()->route('management.kp-place-quotas.show', $quota)->with('status', 'Kapasitas tempat PKPA berhasil dibuat.');
    }

    public function show(KpPlaceQuota $kpPlaceQuota): View
    {
        return view('management.quotas.show', [
            'quota' => $kpPlaceQuota->load(['period', 'place', 'logs.user']),
        ]);
    }

    public function edit(KpPlaceQuota $kpPlaceQuota): View
    {
        $this->catalogSync->sync(request()->user());

        return view('management.quotas.edit', [
            'quota' => $kpPlaceQuota,
            'periods' => KpPeriod::latest()->get(),
            'places' => KpPlace::query()
                ->where(fn ($query) => $query->where('status', 'aktif')->orWhere('id', $kpPlaceQuota->kp_place_id))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(UpdateKpPlaceQuotaRequest $request, KpPlaceQuota $kpPlaceQuota): RedirectResponse
    {
        $oldQuota = $kpPlaceQuota->quota;
        $oldIsOpen = $kpPlaceQuota->is_open;
        $newQuota = (int) $request->input('quota');
        $newIsOpen = $request->boolean('is_open');

        $kpPlaceQuota->update($request->validated() + [
            'is_open' => $newIsOpen,
            'updated_by' => $request->user()->id,
        ]);

        $this->log($kpPlaceQuota, $this->actionFor($oldQuota, $newQuota, $oldIsOpen, $newIsOpen), $oldQuota, $newQuota, $oldIsOpen, $newIsOpen, $request->input('notes'), $request->user()->id);

        return redirect()->route('management.kp-place-quotas.show', $kpPlaceQuota)->with('status', 'Kapasitas tempat PKPA berhasil diperbarui.');
    }

    public function destroy(Request $request, KpPlaceQuota $kpPlaceQuota): RedirectResponse
    {
        $this->log($kpPlaceQuota, 'deleted', $kpPlaceQuota->quota, null, $kpPlaceQuota->is_open, null, 'Kapasitas dihapus.', $request->user()->id);
        $kpPlaceQuota->delete();

        return redirect()->route('management.kp-place-quotas.index')->with('status', 'Kapasitas tempat PKPA berhasil dihapus.');
    }

    public function toggleOpen(Request $request, KpPlaceQuota $quota): RedirectResponse
    {
        $oldIsOpen = $quota->is_open;
        $quota->update([
            'is_open' => ! $oldIsOpen,
            'updated_by' => $request->user()->id,
        ]);

        $this->log($quota, $quota->is_open ? 'opened' : 'closed', $quota->quota, $quota->quota, $oldIsOpen, $quota->is_open, $request->input('note'), $request->user()->id);

        return back()->with('status', 'Status kapasitas berhasil diperbarui.');
    }

    private function log(KpPlaceQuota $quota, string $action, ?int $oldQuota, ?int $newQuota, ?bool $oldIsOpen, ?bool $newIsOpen, ?string $note, ?int $userId): void
    {
        $quota->logs()->create([
            'user_id' => $userId,
            'old_quota' => $oldQuota,
            'new_quota' => $newQuota,
            'old_is_open' => $oldIsOpen,
            'new_is_open' => $newIsOpen,
            'action' => $action,
            'note' => $note,
        ]);
    }

    private function actionFor(int $oldQuota, int $newQuota, bool $oldIsOpen, bool $newIsOpen): string
    {
        if ($oldIsOpen !== $newIsOpen) {
            return $newIsOpen ? 'opened' : 'closed';
        }

        if ($newQuota > $oldQuota) {
            return 'quota_increased';
        }

        if ($newQuota < $oldQuota) {
            return 'quota_decreased';
        }

        return 'updated';
    }
}
