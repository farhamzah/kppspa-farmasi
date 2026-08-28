<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitExamRequestRequest;
use App\Models\KpAssignment;
use App\Services\KpExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExamRequestController extends Controller
{
    public function index(): View
    {
        $assignment = $this->activeAssignment();

        return view('student.exams.index', [
            'assignment' => $assignment?->load(['place', 'internalSupervisor.user', 'finalReport', 'examRequest.exam.examiner.user', 'examRequest.exam.examiners.user', 'examRequest.exam.supervisor.user']),
            'examRequest' => $assignment?->examRequest,
            'exam' => $assignment?->exam,
            'examEligibility' => $assignment?->examEligibility(),
        ]);
    }

    public function submit(SubmitExamRequestRequest $request, KpExamService $service): RedirectResponse
    {
        $service->submitRequest($request->user(), $this->activeAssignmentOrFail(), $request->request_note);

        return back()->with('status', 'Pengajuan sidang berhasil dikirim.');
    }

    public function cancel(): RedirectResponse
    {
        $assignment = $this->activeAssignmentOrFail();
        $examRequest = $assignment->examRequest;
        abort_unless($examRequest && in_array($examRequest->status, ['draft', 'diajukan', 'revisi'], true), 403);
        app(KpExamService::class)->cancelRequest(request()->user(), $examRequest, 'Dibatalkan oleh mahasiswa.');

        return back()->with('status', 'Pengajuan sidang berhasil dibatalkan.');
    }

    private function activeAssignment(): ?KpAssignment
    {
        return request()->user()->student?->assignments()->whereIn('status', ['aktif', 'berjalan'])->latest()->first();
    }

    private function activeAssignmentOrFail(): KpAssignment
    {
        $assignment = $this->activeAssignment();
        abort_unless($assignment, 403, 'Anda belum memiliki penempatan PKPA aktif.');

        return $assignment;
    }
}
