<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreKpLogbookCommentRequest;
use App\Models\KpLogbook;
use App\Models\KpPeriod;
use App\Services\KpLogbookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogbookMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $logbooks = KpLogbook::query()
            ->with(['assignment.student.user', 'assignment.period', 'assignment.place', 'assignment.internalSupervisor.user', 'assignment.fieldSupervisor.user'])
            ->when($request->filled('period'), fn ($q) => $q->whereHas('assignment', fn ($assignment) => $assignment->where('kp_period_id', $request->period)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where('activity_title', 'like', "%{$keyword}%")
                    ->orWhereHas('assignment.student', fn ($student) => $student->where('nim', 'like', "%{$keyword}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")))
                    ->orWhereHas('assignment.place', fn ($place) => $place->where('name', 'like', "%{$keyword}%"));
            })
            ->latest('activity_date')
            ->paginate(12)
            ->withQueryString();

        return view('management.logbooks.index', [
            'logbooks' => $logbooks,
            'periods' => KpPeriod::latest()->get(),
            'filters' => $request->only(['period', 'status', 'q']),
            'stats' => [
                'total' => KpLogbook::count(),
                'draft' => KpLogbook::where('status', 'draft')->count(),
                'pending' => KpLogbook::where('status', 'menunggu_validasi')->count(),
                'approved' => KpLogbook::where('status', 'disetujui')->count(),
                'revision' => KpLogbook::where('status', 'revisi')->count(),
                'rejected' => KpLogbook::where('status', 'ditolak')->count(),
            ],
        ]);
    }

    public function show(KpLogbook $logbook): View
    {
        return view('management.logbooks.show', [
            'logbook' => $logbook->load(['assignment.student.user', 'assignment.period', 'assignment.place', 'assignment.internalSupervisor.user', 'assignment.fieldSupervisor.user', 'comments.user', 'logs.user']),
        ]);
    }

    public function comments(StoreKpLogbookCommentRequest $request, KpLogbook $logbook, KpLogbookService $service): RedirectResponse
    {
        $service->addComment($request->user(), $logbook, $request->comment, $request->visibility);

        return back()->with('status', 'Komentar monitoring berhasil ditambahkan.');
    }

    public function download(KpLogbook $logbook): StreamedResponse
    {
        abort_unless($logbook->hasEvidenceFile(), 404);

        return Storage::disk($logbook->evidence_disk ?: 'local')->download($logbook->evidence_path, $logbook->evidence_original_filename);
    }
}
