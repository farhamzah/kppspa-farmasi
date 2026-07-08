<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Support\KpScoreCalculator;
use Illuminate\View\View;

class ScoreController extends Controller
{
    public function show(KpScoreCalculator $calculator): View
    {
        $assignment = request()->user()->student?->assignments()
            ->with(['period.assessmentComponents', 'place', 'scores.component', 'logbooks', 'finalScore'])
            ->whereIn('status', ['aktif', 'berjalan', 'selesai'])
            ->latest()
            ->first();

        return view('student.scores.show', [
            'assignment' => $assignment,
            'finalScore' => $assignment?->finalScore,
            'breakdown' => $assignment ? $calculator->breakdown($assignment) : null,
        ]);
    }
}
