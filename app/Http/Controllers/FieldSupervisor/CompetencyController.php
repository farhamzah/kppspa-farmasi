<?php

namespace App\Http\Controllers\FieldSupervisor;

use App\Http\Controllers\Controller;
use App\Models\KpAssignment;
use App\Models\KpCompetency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompetencyController extends Controller
{
    public function index(Request $request): View
    {
        $fieldSupervisorId = $request->user()->fieldSupervisor?->id ?: 0;

        $assignments = KpAssignment::query()
            ->with(['period', 'student.user', 'place', 'competencyAchievements'])
            ->where('field_supervisor_id', $fieldSupervisorId)
            ->latest()
            ->paginate(10);

        return view('field-supervisor.competencies.index', ['assignments' => $assignments]);
    }

    public function show(Request $request, KpAssignment $assignment): View
    {
        abort_unless($assignment->field_supervisor_id === $request->user()->fieldSupervisor?->id, 403);

        return view('field-supervisor.competencies.show', $this->payload($assignment));
    }

    public function update(Request $request, KpAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->field_supervisor_id === $request->user()->fieldSupervisor?->id, 403);

        $data = $request->validate([
            'competencies' => ['array'],
            'competencies.*' => ['integer', 'exists:kp_competencies,id'],
            'notes' => ['array'],
            'notes.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $competencies = $this->competenciesFor($assignment);
        $checkedIds = collect($data['competencies'] ?? [])->map(fn ($id) => (int) $id);

        foreach ($competencies as $competency) {
            $achievement = $assignment->competencyAchievements()->where('kp_competency_id', $competency->id)->first();

            if ($checkedIds->contains($competency->id)) {
                $assignment->competencyAchievements()->updateOrCreate(
                    ['kp_competency_id' => $competency->id],
                    [
                        'checked_by' => $request->user()->id,
                        'achieved_at' => $achievement?->achieved_at ?? now(),
                        'note' => $data['notes'][$competency->id] ?? null,
                    ]
                );
            } elseif ($achievement) {
                $achievement->delete();
            }
        }

        return back()->with('status', 'Capaian kompetensi mahasiswa berhasil diperbarui.');
    }

    private function payload(KpAssignment $assignment): array
    {
        $assignment->load(['period', 'student.user', 'place', 'internalSupervisor.user', 'competencyAchievements.checkedBy']);

        return [
            'assignment' => $assignment,
            'competencies' => $this->competenciesFor($assignment),
            'readonly' => false,
        ];
    }

    private function competenciesFor(KpAssignment $assignment)
    {
        $assignment->loadMissing('place');

        return KpCompetency::query()
            ->where('status', 'aktif')
            ->where(fn ($query) => $query->whereNull('kp_period_id')->orWhere('kp_period_id', $assignment->kp_period_id))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->filter(fn (KpCompetency $competency) => $competency->appliesToPlaceType($assignment->place?->type))
            ->values();
    }
}
