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
        $coreUserId = $request->user()->core_user_id;
        $assignments = PkpaPublishedAssignment::query()
            ->with(['publication.program', 'supervisors'])
            ->withCount([
                'acknowledgements as acknowledged_count' => fn ($query) => $query
                    ->where('core_user_id', $coreUserId)
                    ->where('acknowledgement_type', 'acknowledged'),
            ])
            ->forStudent($coreUserId)
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
            'summary' => [
                'total' => $assignments->count(),
                'acknowledged' => $assignments->where('acknowledged_count', '>', 0)->count(),
                'pending' => $assignments->where('acknowledged_count', 0)->count(),
            ],
            'history' => PkpaPublishedAssignment::query()
                ->with('publication')
                ->forStudent($coreUserId)
                ->get()
                ->pluck('publication')
                ->unique('id')
                ->sortByDesc(fn ($item) => sprintf('%08d-%08d', (int) $item->publication_number, (int) $item->revision_number))
                ->values(),
        ]);
    }

    public function show(Request $request, PkpaPublishedAssignment $assignment): View
    {
        abort_unless(PkpaPublishedAssignment::query()
            ->whereKey($assignment->id)
            ->forStudent($request->user()->core_user_id)
            ->exists(), 403);

        $assignment->load([
            'publication.program',
            'supervisors',
            'acknowledgements' => fn ($query) => $query
                ->where('core_user_id', $request->user()->core_user_id)
                ->where('acknowledgement_type', 'acknowledged')
                ->latest('acknowledged_at'),
        ])->loadCount([
            'acknowledgements as acknowledged_count' => fn ($query) => $query
                ->where('core_user_id', $request->user()->core_user_id)
                ->where('acknowledgement_type', 'acknowledged'),
        ]);
        $this->acknowledgementService->record($assignment->publication, $assignment, $request->user(), 'student', 'viewed', $request);

        return view('student.pkpa-schedule.show', ['assignment' => $assignment]);
    }

    public function acknowledge(Request $request, PkpaPublishedAssignment $assignment): RedirectResponse
    {
        abort_unless(PkpaPublishedAssignment::query()
            ->whereKey($assignment->id)
            ->forStudent($request->user()->core_user_id)
            ->exists(), 403);

        $assignment->load('publication', 'supervisors');
        $this->acknowledgementService->record($assignment->publication, $assignment, $request->user(), 'student', 'acknowledged', $request);

        return back()->with('status', 'Tanda membaca jadwal berhasil disimpan.');
    }
}
