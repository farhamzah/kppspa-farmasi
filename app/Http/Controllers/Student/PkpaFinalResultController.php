<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PkpaFinalGradeRelease;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaFinalResultController extends Controller
{
    public function index(Request $request): View
    {
        return view('student.pkpa-final-results.index', [
            'releases' => PkpaFinalGradeRelease::with('result')
                ->where('status', 'released')
                ->whereHas('result.enrollment', fn ($query) => $query->where('core_user_id', $request->user()->core_user_id))
                ->latest('released_at')
                ->get(),
        ]);
    }
}
