<?php

namespace App\Http\Controllers\FieldSupervisor;

use App\Http\Controllers\Controller;
use App\Models\KpAssignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FieldStudentController extends Controller
{
    public function index(Request $request): View
    {
        $fieldSupervisorId = $request->user()->fieldSupervisor?->id ?: 0;
        $assignments = KpAssignment::with(['student.user', 'period', 'place', 'internalSupervisor.user'])
            ->where('field_supervisor_id', $fieldSupervisorId)
            ->paginate(10);

        return view('field-supervisor.assignments.index', compact('assignments'));
    }

    public function show(Request $request, KpAssignment $assignment): View
    {
        abort_unless($request->user()->fieldSupervisor?->id === $assignment->field_supervisor_id, 403);

        return view('field-supervisor.assignments.show', [
            'assignment' => $assignment->load(['student.user', 'period', 'place', 'internalSupervisor.user', 'registration', 'selection']),
        ]);
    }
}
