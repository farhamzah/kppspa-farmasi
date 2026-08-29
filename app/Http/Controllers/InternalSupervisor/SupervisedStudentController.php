<?php

namespace App\Http\Controllers\InternalSupervisor;

use App\Http\Controllers\Controller;
use App\Models\PkpaPublishedAssignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupervisedStudentController extends Controller
{
    public function index(Request $request): View
    {
        $assignments = PkpaPublishedAssignment::query()
            ->with(['publication.program', 'supervisors'])
            ->forSupervisor('internal', $request->user()->core_user_id)
            ->whereHas('publication', fn ($query) => $query->whereIn('status', ['published', 'withdrawn'])->where('is_current', true))
            ->orderBy('start_date')
            ->paginate(10);

        return view('internal-supervisor.assignments.index', compact('assignments'));
    }

    public function show(Request $request, PkpaPublishedAssignment $assignment): View
    {
        abort_unless(PkpaPublishedAssignment::query()
            ->whereKey($assignment->id)
            ->forSupervisor('internal', $request->user()->core_user_id)
            ->exists(), 403);

        return view('internal-supervisor.assignments.show', [
            'assignment' => $assignment->load(['publication.program', 'supervisors']),
        ]);
    }
}
