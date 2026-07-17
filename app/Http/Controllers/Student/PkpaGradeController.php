<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PkpaGradeRelease;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaGradeController extends Controller
{
    public function index(Request $request): View
    {
        return view('student.pkpa-grades.index', [
            'releases' => PkpaGradeRelease::with(['gradeResult.rotationRun.practiceDomain', 'gradeResult.rotationRun.practiceSite'])
                ->where('status', 'released')
                ->whereHas('gradeResult.rotationRun', fn ($query) => $query->where('student_core_user_id', $request->user()->core_user_id))
                ->latest('released_at')
                ->get(),
        ]);
    }
}
