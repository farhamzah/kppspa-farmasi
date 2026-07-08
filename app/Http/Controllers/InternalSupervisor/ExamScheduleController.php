<?php

namespace App\Http\Controllers\InternalSupervisor;

use App\Http\Controllers\Controller;
use App\Models\KpExam;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $lecturerId = $request->user()->lecturer?->id;
        $exams = KpExam::with(['assignment.student.user', 'assignment.place', 'examiner.user', 'examiners.user'])
            ->where('supervisor_id', $lecturerId)
            ->latest('exam_date')
            ->paginate(10);

        return view('internal-supervisor.exams.index', ['exams' => $exams]);
    }

    public function show(KpExam $exam): View
    {
        abort_unless(request()->user()->lecturer?->id === $exam->supervisor_id, 403);
        return view('internal-supervisor.exams.show', ['exam' => $exam->load(['assignment.student.user', 'assignment.place', 'assignment.finalReport.latestFile', 'examiner.user', 'examiners.user'])]);
    }
}
