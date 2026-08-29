<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PkpaPublishedAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function show(Request $request): View
    {
        $coreUserId = $request->user()->core_user_id;
        $today = Carbon::today();
        $assignments = PkpaPublishedAssignment::query()
            ->with(['publication.program', 'supervisors'])
            ->forStudent($coreUserId)
            ->whereHas('publication', fn ($query) => $query
                ->whereIn('status', ['published', 'withdrawn'])
                ->where('is_current', true))
            ->orderBy('start_date')
            ->get();

        $currentAssignment = $assignments->first(fn ($assignment) => $assignment->start_date && $assignment->end_date
            && $today->between($assignment->start_date, $assignment->end_date));

        if (! $currentAssignment) {
            $currentAssignment = $assignments->first(fn ($assignment) => $assignment->start_date && $assignment->start_date->isFuture())
                ?? $assignments->last();
        }

        return view('student.assignments.show', [
            'publication' => $assignments->first()?->publication,
            'assignments' => $assignments,
            'currentAssignment' => $currentAssignment,
        ]);
    }
}
