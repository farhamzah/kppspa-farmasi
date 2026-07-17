<?php

namespace App\Http\Controllers\FieldSupervisor;

use App\Http\Controllers\Controller;
use App\Models\PkpaPublishedAssignment;
use App\Services\PkpaScheduleAcknowledgementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaScheduleController extends Controller
{
    public function __construct(private readonly PkpaScheduleAcknowledgementService $acknowledgementService)
    {
    }

    public function index(Request $request): View
    {
        $assignments = PkpaPublishedAssignment::query()
            ->with(['publication.program', 'supervisors'])
            ->forSupervisor('field', $request->user()->core_user_id)
            ->whereHas('publication', fn ($query) => $query->whereIn('status', ['published', 'withdrawn'])->where('is_current', true))
            ->orderBy('start_date')
            ->get();
        if ($publication = $assignments->first()?->publication) {
            $this->acknowledgementService->record($publication, null, $request->user(), 'field_supervisor', 'viewed', $request);
        }

        return view('supervisors.pkpa-schedule.index', ['assignments' => $assignments, 'type' => 'field']);
    }

    public function show(Request $request, PkpaPublishedAssignment $assignment): View
    {
        $assignment->load('publication.program', 'supervisors');
        abort_unless($assignment->supervisors->contains(fn ($s) => $s->supervisor_type === 'field' && $s->core_user_id === $request->user()->core_user_id), 403);
        $this->acknowledgementService->record($assignment->publication, $assignment, $request->user(), 'field_supervisor', 'viewed', $request);

        return view('supervisors.pkpa-schedule.show', ['assignment' => $assignment, 'type' => 'field']);
    }

    public function acknowledge(Request $request, PkpaPublishedAssignment $assignment): RedirectResponse
    {
        $assignment->load('publication', 'supervisors');
        abort_unless($assignment->supervisors->contains(fn ($s) => $s->supervisor_type === 'field' && $s->core_user_id === $request->user()->core_user_id), 403);
        $this->acknowledgementService->record($assignment->publication, $assignment, $request->user(), 'field_supervisor', 'acknowledged', $request);

        return back()->with('status', 'Tanda membaca jadwal berhasil disimpan.');
    }
}
