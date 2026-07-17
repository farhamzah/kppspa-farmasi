<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PkpaPlacementPublication;
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
            ->forStudent($request->user()->core_user_id)
            ->whereHas('publication', fn ($query) => $query->whereIn('status', ['published', 'withdrawn'])->where('is_current', true))
            ->orderBy('start_date')
            ->get();
        $publication = $assignments->first()?->publication;
        if ($publication) {
            $this->acknowledgementService->record($publication, null, $request->user(), 'student', 'viewed', $request);
        }

        return view('student.pkpa-schedule.index', [
            'publication' => $publication,
            'assignments' => $assignments,
            'history' => PkpaPublishedAssignment::query()
                ->with('publication')
                ->forStudent($request->user()->core_user_id)
                ->get()
                ->pluck('publication')
                ->unique('id')
                ->values(),
        ]);
    }

    public function show(Request $request, PkpaPublishedAssignment $assignment): View
    {
        abort_unless($assignment->student_core_user_id === $request->user()->core_user_id, 403);
        $assignment->load('publication.program', 'supervisors');
        $this->acknowledgementService->record($assignment->publication, $assignment, $request->user(), 'student', 'viewed', $request);

        return view('student.pkpa-schedule.show', ['assignment' => $assignment]);
    }

    public function acknowledge(Request $request, PkpaPublishedAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->student_core_user_id === $request->user()->core_user_id, 403);
        $this->acknowledgementService->record($assignment->publication, $assignment, $request->user(), 'student', 'acknowledged', $request);

        return back()->with('status', 'Tanda membaca jadwal berhasil disimpan.');
    }
}
