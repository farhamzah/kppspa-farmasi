<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreKpRegistrationRequest;
use App\Models\KpDocument;
use App\Models\KpDocumentRequirement;
use App\Models\KpPeriod;
use App\Models\KpRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KpRegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user()->student;

        return view('student.registrations.index', [
            'student' => $student,
            'registrations' => $student?->kpRegistrations()->with(['period', 'documents.requirement'])->latest()->get() ?? collect(),
            'openPeriods' => KpPeriod::query()
                ->where('status', 'dibuka')
                ->whereNotNull('registration_start_at')
                ->whereNotNull('registration_end_at')
                ->where('registration_start_at', '<=', now())
                ->where('registration_end_at', '>=', now())
                ->with('documentRequirements')
                ->latest()
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('student.registrations.create', [
            'periods' => KpPeriod::query()
                ->where('status', 'dibuka')
                ->where('registration_start_at', '<=', now())
                ->where('registration_end_at', '>=', now())
                ->latest()
                ->get(),
            'student' => $request->user()->student,
        ]);
    }

    public function store(StoreKpRegistrationRequest $request): RedirectResponse
    {
        $student = $request->user()->student;

        if (KpRegistration::where('kp_period_id', $request->kp_period_id)->where('student_id', $student->id)->exists()) {
            return back()->withErrors(['kp_period_id' => 'Anda sudah memiliki pendaftaran pada periode ini.']);
        }

        $registration = KpRegistration::create([
            'kp_period_id' => $request->kp_period_id,
            'student_id' => $student->id,
            'status' => 'draft',
            'notes' => $request->notes,
        ]);

        $this->syncDocuments($registration);
        $this->log($registration, 'created', null, 'draft', 'Pendaftaran PKPA dibuat.', $request->user()->id);

        return redirect()->route('student.pkpa-registrations.show', $registration)->with('status', 'Pendaftaran PKPA berhasil dibuat.');
    }

    public function show(Request $request, KpRegistration $registration): View
    {
        $this->authorizeStudent($request, $registration);
        $this->syncDocuments($registration);

        return view('student.registrations.show', [
            'registration' => $registration->fresh(['period.documentRequirements', 'student.user', 'documents.requirement', 'logs.user']),
        ]);
    }

    public function submit(Request $request, KpRegistration $registration): RedirectResponse
    {
        $this->authorizeStudent($request, $registration);
        $registration->load(['period.documentRequirements', 'documents']);

        if (! in_array($registration->status, ['draft', 'revisi'], true)) {
            return back()->withErrors(['registration' => 'Pendaftaran ini sudah disubmit atau sudah selesai direview.']);
        }

        if (! $registration->requiredDocumentsCompleted()) {
            return back()->withErrors(['registration' => 'Lengkapi semua dokumen wajib sebelum submit pendaftaran.']);
        }

        $old = $registration->status;
        $registration->update([
            'status' => 'menunggu_verifikasi',
            'submitted_at' => now(),
            'registration_number' => $registration->registration_number ?: $this->makeRegistrationNumber($registration),
        ]);
        $this->log($registration, 'submitted', $old, 'menunggu_verifikasi', 'Pendaftaran disubmit untuk verifikasi.', $request->user()->id);

        return back()->with('status', 'Pendaftaran PKPA berhasil dikirim untuk verifikasi.');
    }

    public function cancel(Request $request, KpRegistration $registration): RedirectResponse
    {
        $this->authorizeStudent($request, $registration);
        $old = $registration->status;
        $registration->update(['status' => 'dibatalkan']);
        $this->log($registration, 'registration_cancelled', $old, 'dibatalkan', 'Pendaftaran dibatalkan mahasiswa.', $request->user()->id);

        return redirect()->route('student.pkpa-registrations.index')->with('status', 'Pendaftaran PKPA dibatalkan.');
    }

    public function download(Request $request, KpRegistration $registration, KpDocument $document): StreamedResponse
    {
        $this->authorizeStudent($request, $registration);
        abort_unless($document->kp_registration_id === $registration->id && $document->file_path, 404);

        return Storage::disk($document->file_disk)->download($document->file_path, $document->original_filename);
    }

    private function syncDocuments(KpRegistration $registration): void
    {
        $requirements = $registration->period->documentRequirements()->where('status', 'aktif')->get();

        foreach ($requirements as $requirement) {
            KpDocument::firstOrCreate([
                'kp_registration_id' => $registration->id,
                'kp_document_requirement_id' => $requirement->id,
            ]);
        }
    }

    private function authorizeStudent(Request $request, KpRegistration $registration): void
    {
        abort_unless($request->user()->student?->id === $registration->student_id, 403);
    }

    private function makeRegistrationNumber(KpRegistration $registration): string
    {
        return 'KP-'.now()->year.'-'.str_pad((string) $registration->id, 4, '0', STR_PAD_LEFT);
    }

    private function log(KpRegistration $registration, string $action, ?string $oldStatus, ?string $newStatus, ?string $note, ?int $userId): void
    {
        $registration->logs()->create([
            'user_id' => $userId,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
        ]);
    }
}
