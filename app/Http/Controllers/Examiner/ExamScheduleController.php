<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\KpExam;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $lecturerId = $request->user()->lecturer?->id;
        $exams = KpExam::with(['assignment.student.user', 'assignment.place', 'supervisor.user', 'examiners.user'])
            ->forExaminer($lecturerId)
            ->latest('exam_date')
            ->paginate(10);

        return view('examiner.exams.index', ['exams' => $exams]);
    }

    public function show(KpExam $exam): View
    {
        abort_unless($exam->hasExaminer(request()->user()->lecturer?->id), 403);
        return view('examiner.exams.show', ['exam' => $exam->load(['assignment.student.user', 'assignment.place', 'assignment.finalReport.latestFile', 'supervisor.user', 'examiners.user'])]);
    }
}
