<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreAssessmentComponentRequest;
use App\Http\Requests\Management\UpdateAssessmentComponentRequest;
use App\Models\KpAssessmentComponent;
use App\Models\KpPeriod;
use App\Models\KpScoreLog;
use App\Services\KpAssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssessmentComponentController extends Controller
{
    public function index(Request $request, KpAssessmentService $service): View
    {
        KpPeriod::query()->each(fn (KpPeriod $period) => $service->ensureDefaultComponents($period, $request->user()));

        $components = KpAssessmentComponent::with('period')
            ->when($request->filled('period'), fn ($q) => $q->where('kp_period_id', $request->period))
            ->when($request->filled('assessor_type'), fn ($q) => $q->where('assessor_type', $request->assessor_type))
            ->orderBy('kp_period_id')->orderBy('assessor_type')->orderBy('sort_order')
            ->paginate(12)->withQueryString();

        $weightTotals = KpAssessmentComponent::where('status', 'aktif')
            ->selectRaw('kp_period_id, assessor_type, sum(weight) as total_weight')
            ->groupBy('kp_period_id', 'assessor_type')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->kp_period_id.'-'.$row->assessor_type => $row->total_weight]);

        return view('management.assessment-components.index', [
            'components' => $components,
            'periods' => KpPeriod::latest()->get(),
            'filters' => $request->only(['period', 'assessor_type']),
            'weightTotals' => $weightTotals,
        ]);
    }

    public function create(): View
    {
        return view('management.assessment-components.form', ['component' => new KpAssessmentComponent(), 'periods' => KpPeriod::latest()->get()]);
    }

    public function store(StoreAssessmentComponentRequest $request): RedirectResponse
    {
        $component = KpAssessmentComponent::create($this->payload($request));
        KpScoreLog::create(['user_id' => $request->user()->id, 'action' => 'component_created', 'metadata' => ['component_id' => $component->id]]);

        return redirect()->route('management.assessment-components.index')->with('status', 'Komponen penilaian berhasil dibuat.');
    }

    public function edit(KpAssessmentComponent $component): View
    {
        return view('management.assessment-components.form', ['component' => $component, 'periods' => KpPeriod::latest()->get()]);
    }

    public function update(UpdateAssessmentComponentRequest $request, KpAssessmentComponent $component): RedirectResponse
    {
        $component->update($this->payload($request));
        KpScoreLog::create(['user_id' => $request->user()->id, 'action' => 'component_updated', 'metadata' => ['component_id' => $component->id]]);

        return redirect()->route('management.assessment-components.index')->with('status', 'Komponen penilaian berhasil diperbarui.');
    }

    public function destroy(KpAssessmentComponent $component): RedirectResponse
    {
        if ($component->scores()->exists()) {
            $component->update(['status' => 'nonaktif']);
            return back()->with('status', 'Komponen sudah dipakai, status diubah menjadi nonaktif.');
        }

        $component->delete();
        return back()->with('status', 'Komponen penilaian berhasil dihapus.');
    }

    private function payload(StoreAssessmentComponentRequest $request): array
    {
        $data = $request->validated();
        $data['is_required'] = $request->boolean('is_required');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['updated_by'] = $request->user()->id;

        if ($request->isMethod('post')) {
            $data['created_by'] = $request->user()->id;
        }

        return $data;
    }
}
