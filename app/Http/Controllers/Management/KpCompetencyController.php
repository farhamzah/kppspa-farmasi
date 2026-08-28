<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\KpAssignment;
use App\Models\KpCompetency;
use App\Models\KpPeriod;
use App\Models\KpPlace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KpCompetencyController extends Controller
{
    public function index(Request $request): View
    {
        $periodId = $request->filled('period') ? (int) $request->period : null;
        $placeType = $request->string('place_type')->toString() ?: null;
        $periods = KpPeriod::latest()->get();
        $competencies = KpCompetency::query()
            ->with(['period', 'achievements'])
            ->when($periodId, fn ($query) => $query->where('kp_period_id', $periodId))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->when($placeType, fn ($items) => $items->filter(fn (KpCompetency $competency) => $competency->appliesToPlaceType($placeType))->values());

        $assignments = KpAssignment::query()
            ->with(['period', 'student.user', 'place', 'internalSupervisor.user', 'fieldSupervisor.user', 'competencyAchievements'])
            ->when($periodId, fn ($query) => $query->where('kp_period_id', $periodId))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('management.competencies.index', [
            'periods' => $periods,
            'competencies' => $competencies,
            'assignments' => $assignments,
            'filters' => $request->only(['period', 'place_type']),
            'placeTypes' => KpPlace::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        KpCompetency::create($data);

        return back()->with('status', 'Kompetensi PKPA berhasil ditambahkan.');
    }

    public function update(Request $request, KpCompetency $competency): RedirectResponse
    {
        $competency->update($this->validated($request));

        return back()->with('status', 'Kompetensi PKPA berhasil diperbarui.');
    }

    public function destroy(KpCompetency $competency): RedirectResponse
    {
        abort_if($competency->achievements()->exists(), 422, 'Kompetensi yang sudah dipakai tidak dapat dihapus.');

        $competency->delete();

        return back()->with('status', 'Kompetensi PKPA berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'kp_period_id' => ['nullable', 'integer', 'exists:kp_periods,id'],
            'place_type' => ['nullable', Rule::in(KpPlace::TYPES)],
            'place_types' => ['nullable', 'array'],
            'place_types.*' => ['string', Rule::in(KpPlace::TYPES)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);

        $types = collect($data['place_types'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($types === [] && filled($data['place_type'] ?? null)) {
            $types = [$data['place_type']];
        }

        $data['place_types'] = $types === [] ? null : $types;
        $data['place_type'] = count($types) === 1 ? $types[0] : null;

        return $data;
    }
}
