<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\ReviewExamRequestRequest;
use App\Models\KpExamRequest;
use App\Models\KpPeriod;
use App\Services\KpExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExamRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = KpExamRequest::query()
            ->with(['assignment.student.user', 'assignment.period', 'assignment.place', 'assignment.internalSupervisor.user', 'exam'])
            ->when($request->filled('period'), fn ($q) => $q->whereHas('assignment', fn ($a) => $a->where('kp_period_id', $request->period)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('assignment.student', fn ($s) => $s->where('nim', 'like', "%{$request->q}%")->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$request->q}%"))))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('management.exam-requests.index', ['requests' => $requests, 'periods' => KpPeriod::latest()->get(), 'filters' => $request->only(['period', 'status', 'q'])]);
    }

    public function show(KpExamRequest $examRequest): View
    {
        return view('management.exam-requests.show', ['examRequest' => $examRequest->load(['assignment.student.user', 'assignment.period', 'assignment.place', 'assignment.internalSupervisor.user', 'assignment.finalReport.latestFile', 'exam.examiner.user', 'exam.examiners.user', 'logs.user'])]);
    }

    public function approve(ReviewExamRequestRequest $request, KpExamRequest $examRequest, KpExamService $service): RedirectResponse
    {
        $service->approveRequest($request->user(), $examRequest, $request->review_note);
        return back()->with('status', 'Pengajuan ujian berhasil disetujui.');
    }

    public function revision(ReviewExamRequestRequest $request, KpExamRequest $examRequest, KpExamService $service): RedirectResponse
    {
        if (! $request->filled('review_note')) {
            throw ValidationException::withMessages(['review_note' => 'Catatan revisi wajib diisi.']);
        }
        $service->requestRevision($request->user(), $examRequest, $request->review_note);
        return back()->with('status', 'Revisi pengajuan ujian berhasil diminta.');
    }

    public function reject(ReviewExamRequestRequest $request, KpExamRequest $examRequest, KpExamService $service): RedirectResponse
    {
        if (! $request->filled('review_note')) {
            throw ValidationException::withMessages(['review_note' => 'Catatan penolakan wajib diisi.']);
        }
        $service->rejectRequest($request->user(), $examRequest, $request->review_note);
        return back()->with('status', 'Pengajuan ujian berhasil ditolak.');
    }
}
