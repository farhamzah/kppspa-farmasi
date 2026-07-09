<?php

namespace App\Http\Controllers\FieldSupervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\ReviewKpLogbookRequest;
use App\Models\KpAssignment;
use App\Models\KpLogbook;
use App\Services\KpLogbookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogbookValidationController extends Controller
{
    public function index(Request $request): View
    {
        $fieldSupervisorId = $request->user()->fieldSupervisor?->id ?: 0;
        $assignmentsQuery = KpAssignment::query()
            ->with(['student.user', 'period', 'place'])
            ->withCount([
                'logbooks',
                'logbooks as pending_logbooks_count' => fn ($q) => $q->where('status', 'menunggu_validasi'),
                'logbooks as approved_logbooks_count' => fn ($q) => $q->where('status', 'disetujui'),
                'logbooks as revision_logbooks_count' => fn ($q) => $q->whereIn('status', ['revisi', 'ditolak']),
            ])
            ->withMax('logbooks', 'activity_date')
            ->withMax('logbooks', 'submitted_at')
            ->where('field_supervisor_id', $fieldSupervisorId)
            ->when($request->filled('status'), fn ($q) => $q->whereHas('logbooks', fn ($logbook) => $logbook->where('status', $request->status)))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->whereHas('student', fn ($student) => $student->where('nim', 'like', "%{$keyword}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")));
            })
            ->orderByDesc('pending_logbooks_count')
            ->orderByDesc('logbooks_max_activity_date');

        $assignments = $assignmentsQuery
            ->paginate(10)
            ->withQueryString();

        $selectedAssignment = null;
        $selectedLogbooks = collect();

        if ($request->filled('assignment')) {
            $selectedAssignment = KpAssignment::query()
                ->with(['student.user', 'period', 'place', 'internalSupervisor.user'])
                ->where('field_supervisor_id', $fieldSupervisorId)
                ->whereKey($request->integer('assignment'))
                ->first();

            $selectedLogbooks = $selectedAssignment
                ? $selectedAssignment->logbooks()
                    ->with(['comments.user', 'validatedBy'])
                    ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                    ->latest('activity_date')
                    ->get()
                : collect();
        }

        return view('field-supervisor.logbooks.index', [
            'assignments' => $assignments,
            'selectedAssignment' => $selectedAssignment,
            'selectedLogbooks' => $selectedLogbooks,
            'filters' => $request->only(['status', 'q', 'assignment']),
        ]);
    }

    public function show(KpLogbook $logbook, KpLogbookService $service): View
    {
        $service->ensureFieldSupervisorCanReview(request()->user(), $logbook);

        return view('field-supervisor.logbooks.show', [
            'logbook' => $logbook->load(['assignment.student.user', 'assignment.period', 'assignment.place', 'comments.user', 'logs.user']),
        ]);
    }

    public function approve(ReviewKpLogbookRequest $request, KpLogbook $logbook, KpLogbookService $service): RedirectResponse
    {
        $service->approve($request->user(), $logbook, $request->validation_note);

        return $this->redirectToAssignmentLogbooks($logbook, 'Logbook berhasil disetujui.');
    }

    public function revision(ReviewKpLogbookRequest $request, KpLogbook $logbook, KpLogbookService $service): RedirectResponse
    {
        if (! $request->filled('validation_note')) {
            throw ValidationException::withMessages(['validation_note' => 'Catatan revisi wajib diisi.']);
        }

        $service->requestRevision($request->user(), $logbook, $request->validation_note);

        return $this->redirectToAssignmentLogbooks($logbook, 'Revisi logbook berhasil diminta.');
    }

    public function reject(ReviewKpLogbookRequest $request, KpLogbook $logbook, KpLogbookService $service): RedirectResponse
    {
        if (! $request->filled('validation_note')) {
            throw ValidationException::withMessages(['validation_note' => 'Catatan penolakan wajib diisi.']);
        }

        $service->reject($request->user(), $logbook, $request->validation_note);

        return $this->redirectToAssignmentLogbooks($logbook, 'Logbook berhasil ditolak.');
    }

    public function download(KpLogbook $logbook, KpLogbookService $service): StreamedResponse
    {
        $service->ensureFieldSupervisorCanReview(request()->user(), $logbook);
        abort_unless($logbook->hasEvidenceFile(), 404);

        return Storage::disk($logbook->evidence_disk ?: 'local')->download($logbook->evidence_path, $logbook->evidence_original_filename);
    }

    private function redirectToAssignmentLogbooks(KpLogbook $logbook, string $message): RedirectResponse
    {
        return redirect()
            ->route('field-supervisor.logbooks.index', ['assignment' => $logbook->kp_assignment_id])
            ->with('status', $message);
    }
}
