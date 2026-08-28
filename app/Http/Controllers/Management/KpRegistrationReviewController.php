<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\ReviewKpDocumentRequest;
use App\Http\Requests\Management\ReviewKpRegistrationRequest;
use App\Models\KpDocument;
use App\Models\KpPeriod;
use App\Models\KpRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KpRegistrationReviewController extends Controller
{
    public function index(Request $request): View
    {
        $registrations = KpRegistration::query()
            ->with(['period', 'student.user', 'documents'])
            ->when($request->filled('period'), fn ($query) => $query->where('kp_period_id', $request->period))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('q'), function ($query) use ($request) {
                $keyword = $request->q;
                $query->whereHas('student', fn ($student) => $student
                    ->where('nim', 'like', "%{$keyword}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('management.registrations.index', [
            'registrations' => $registrations,
            'periods' => KpPeriod::latest()->get(),
            'filters' => $request->only(['period', 'status', 'q']),
        ]);
    }

    public function show(KpRegistration $registration): View
    {
        return view('management.registrations.show', [
            'registration' => $registration->load(['period.documentRequirements', 'student.user', 'documents.requirement', 'logs.user']),
            'backUrl' => $this->safeRegistrationBackUrl(request('return_url')),
        ]);
    }

    public function approveDocument(ReviewKpDocumentRequest $request, KpRegistration $registration, KpDocument $document): RedirectResponse
    {
        return $this->reviewDocument($request, $registration, $document, 'disetujui', 'document_approved');
    }

    public function revisionDocument(ReviewKpDocumentRequest $request, KpRegistration $registration, KpDocument $document): RedirectResponse
    {
        $request->validate(['review_note' => ['required', 'string', 'max:2000']]);

        return $this->reviewDocument($request, $registration, $document, 'revisi', 'document_revision');
    }

    public function rejectDocument(ReviewKpDocumentRequest $request, KpRegistration $registration, KpDocument $document): RedirectResponse
    {
        $request->validate(['review_note' => ['required', 'string', 'max:2000']]);

        return $this->reviewDocument($request, $registration, $document, 'ditolak', 'document_rejected');
    }

    public function verify(ReviewKpRegistrationRequest $request, KpRegistration $registration): RedirectResponse
    {
        $registration->load(['period.documentRequirements', 'documents']);
        if (! $registration->isWaitingVerification()) {
            return back()->withErrors(['registration' => 'Pendaftaran belum disubmit mahasiswa, jadi belum bisa diverifikasi.']);
        }

        if (! $registration->allRequiredDocumentsApproved()) {
            return back()->withErrors(['registration' => 'Pendaftaran belum bisa diverifikasi karena dokumen wajib belum semua disetujui.']);
        }

        $old = $registration->status;
        $registration->update([
            'status' => 'terverifikasi',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'verification_note' => $request->verification_note,
        ]);
        $this->log($registration, 'registration_verified', $old, 'terverifikasi', $request->verification_note, $request->user()->id);

        return back()->with('status', 'Pendaftaran PKPA berhasil diverifikasi.');
    }

    public function revision(ReviewKpRegistrationRequest $request, KpRegistration $registration): RedirectResponse
    {
        $request->validate(['verification_note' => ['required', 'string', 'max:2000']]);

        return $this->reviewRegistration($request, $registration, 'revisi', 'registration_revision');
    }

    public function reject(ReviewKpRegistrationRequest $request, KpRegistration $registration): RedirectResponse
    {
        $request->validate(['verification_note' => ['required', 'string', 'max:2000']]);

        return $this->reviewRegistration($request, $registration, 'ditolak', 'registration_rejected');
    }

    public function download(KpRegistration $registration, KpDocument $document): StreamedResponse
    {
        abort_unless($document->kp_registration_id === $registration->id && $document->file_path, 404);

        return Storage::disk($document->file_disk)->download($document->file_path, $document->original_filename);
    }

    public function preview(KpRegistration $registration, KpDocument $document): StreamedResponse
    {
        abort_unless($document->kp_registration_id === $registration->id && $document->file_path, 404);

        return Storage::disk($document->file_disk)->response(
            $document->file_path,
            $document->original_filename,
            array_filter(['Content-Type' => $document->file_mime]),
            'inline'
        );
    }

    private function reviewDocument(Request $request, KpRegistration $registration, KpDocument $document, string $status, string $action): RedirectResponse
    {
        abort_unless($document->kp_registration_id === $registration->id, 404);
        $old = $document->status;

        $document->update([
            'status' => $status,
            'review_note' => $request->review_note,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if (in_array($status, ['revisi', 'ditolak'], true)) {
            $registration->update(['status' => 'revisi']);
        }

        $this->log($registration, $action, $old, $status, $request->review_note ?: $document->requirement?->name, $request->user()->id);

        return back()->with('status', 'Status dokumen berhasil diperbarui.');
    }

    private function reviewRegistration(Request $request, KpRegistration $registration, string $status, string $action): RedirectResponse
    {
        $old = $registration->status;
        $registration->update([
            'status' => $status,
            'verification_note' => $request->verification_note,
        ]);
        $this->log($registration, $action, $old, $status, $request->verification_note, $request->user()->id);

        return back()->with('status', 'Status pendaftaran berhasil diperbarui.');
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

    private function safeRegistrationBackUrl(?string $returnUrl): string
    {
        $fallback = route('management.pkpa-registrations.index');

        if (! $returnUrl) {
            return $fallback;
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $returnHost = parse_url($returnUrl, PHP_URL_HOST);
        $returnPath = parse_url($returnUrl, PHP_URL_PATH);

        if ($returnHost && $appHost && $returnHost !== $appHost) {
            return $fallback;
        }

        if (! in_array($returnPath, ['/management/kp-registrations', '/management/pkpa-registrations'], true)) {
            return $fallback;
        }

        return $returnUrl;
    }
}
