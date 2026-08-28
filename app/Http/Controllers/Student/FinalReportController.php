<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitFinalReportRequest;
use App\Http\Requests\Student\UploadFinalReportRequest;
use App\Models\KpAssignment;
use App\Models\KpFinalReportFile;
use App\Services\KpFinalReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinalReportController extends Controller
{
    public function show(KpFinalReportService $service): View
    {
        $assignment = $this->activeAssignment();
        $report = $assignment ? $service->createOrGetReport(request()->user(), $assignment)->load(['latestFile', 'files.uploadedBy', 'logs.user', 'internalReviewedBy', 'fieldReviewedBy']) : null;

        return view('student.final-reports.show', [
            'assignment' => $assignment?->load(['place', 'internalSupervisor.user', 'fieldSupervisor.user', 'reportGuidanceLogs.validatedBy']),
            'report' => $report,
            'examEligibility' => $assignment?->examEligibility(),
        ]);
    }

    public function upload(UploadFinalReportRequest $request, KpFinalReportService $service): RedirectResponse
    {
        $assignment = $this->requireActiveAssignment();
        $report = $service->createOrGetReport($request->user(), $assignment);
        $service->uploadFile($request->user(), $report, $request->file('report_file'), $request->note);

        return back()->with('status', 'File laporan akhir berhasil diupload.');
    }

    public function saveFinalLink(Request $request, KpFinalReportService $service): RedirectResponse
    {
        $data = $request->validate([
            'final_document_url' => ['required', 'url', 'max:2048'],
            'final_document_label' => ['nullable', 'string', 'max:255'],
        ]);

        $assignment = $this->requireActiveAssignment();
        $report = $service->createOrGetReport($request->user(), $assignment);
        $service->saveFinalDocumentLink($request->user(), $report, $data['final_document_url'], $data['final_document_label'] ?? null);

        return back()->with('status', 'Link laporan final berhasil disimpan.');
    }

    public function storeGuidance(Request $request, KpFinalReportService $service): RedirectResponse
    {
        $data = $request->validate([
            'guidance_date' => ['required', 'date'],
            'topic' => ['required', 'string', 'max:255'],
            'student_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->addGuidanceLog($request->user(), $this->requireActiveAssignment(), $data);

        return back()->with('status', 'Log bimbingan laporan berhasil dikirim untuk validasi pembimbing dalam.');
    }

    public function submit(SubmitFinalReportRequest $request, KpFinalReportService $service): RedirectResponse
    {
        $assignment = $this->requireActiveAssignment();
        $report = $service->createOrGetReport($request->user(), $assignment);
        $service->submit($request->user(), $report);

        return back()->with('status', 'Laporan akhir dikirim untuk review pembimbing dalam dan preseptor.');
    }

    public function download(KpFinalReportFile $file, KpFinalReportService $service): StreamedResponse
    {
        $service->ensureStudentCanDownload(request()->user(), $file);

        return Storage::disk($file->file_disk ?: 'local')->download($file->file_path, $file->original_filename);
    }

    private function activeAssignment(): ?KpAssignment
    {
        return request()->user()->student?->assignments()
            ->with(['place', 'internalSupervisor.user', 'fieldSupervisor.user'])
            ->whereIn('status', ['aktif', 'berjalan'])
            ->latest()
            ->first();
    }

    private function requireActiveAssignment(): KpAssignment
    {
        $assignment = $this->activeAssignment();

        if (! $assignment) {
            throw ValidationException::withMessages(['assignment' => 'Anda belum memiliki penempatan PKPA aktif.']);
        }

        return $assignment;
    }
}
