<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaProgramDomain;
use App\Models\PkpaRotationRun;
use App\Services\PkpaRotationOperationRuleService;
use App\Services\PkpaRotationProgressService;
use App\Services\PkpaRotationPublicationSyncService;
use App\Services\PkpaRotationRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaRotationOperationController extends Controller
{
    public function __construct(
        private readonly PkpaRotationRunService $runs,
        private readonly PkpaRotationOperationRuleService $rules,
        private readonly PkpaRotationPublicationSyncService $sync,
        private readonly PkpaRotationProgressService $progress
    ) {
    }

    public function index(Request $request): View
    {
        $runQuery = PkpaRotationRun::query()
            ->with(['program', 'practiceDomain', 'practiceSite', 'requirement.programDomain.activeOperationRule', 'progressSnapshots' => fn ($query) => $query->latest('snapshot_date')->limit(1)])
            ->latest();

        return view('management.pkpa-operations.index', [
            'runs' => $runQuery->paginate(20),
            'publications' => PkpaPlacementPublication::with('program')->where('status', 'published')->where('is_current', true)->latest()->get(),
            'programDomains' => PkpaProgramDomain::with(['program', 'practiceDomain', 'activeOperationRule'])->get(),
            'summary' => [
                'total' => PkpaRotationRun::count(),
                'active' => PkpaRotationRun::where('status', 'active')->count(),
                'attention' => PkpaRotationRun::where('publication_sync_status', 'review_required')->count(),
                'complete' => PkpaRotationRun::where('status', 'operational_complete')->count(),
            ],
        ]);
    }

    public function createFromPublication(Request $request, PkpaPlacementPublication $publication): RedirectResponse
    {
        $stats = $this->runs->createFromPublication($publication, $request->user());

        return back()->with('status', "Runtime rotasi diproses: {$stats['created']} baru, {$stats['existing']} sudah ada.");
    }

    public function saveRule(Request $request, PkpaProgramDomain $programDomain): RedirectResponse
    {
        $data = $request->validate([
            'attendance_required' => ['nullable', 'boolean'],
            'logbook_required' => ['nullable', 'boolean'],
            'logbook_frequency' => ['required', 'in:daily,weekly,flexible'],
            'minimum_logbook_entries' => ['nullable', 'integer', 'min:0'],
            'minimum_approved_attendance_days' => ['nullable', 'integer', 'min:0'],
            'maximum_backdate_days' => ['nullable', 'integer', 'min:0'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['attendance_required'] = $request->boolean('attendance_required');
        $data['logbook_required'] = $request->boolean('logbook_required');
        $this->rules->save($programDomain, $data, $request->user());

        return back()->with('status', 'Aturan operasional wahana diperbarui.');
    }

    public function activate(Request $request, PkpaRotationRun $run): RedirectResponse
    {
        $this->runs->activate($run, $request->user());

        return back()->with('status', 'Rotasi PKPA diaktifkan.');
    }

    public function hold(Request $request, PkpaRotationRun $run): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->runs->hold($run, $data['reason'], $request->user());

        return back()->with('status', 'Rotasi ditahan sementara.');
    }

    public function resume(Request $request, PkpaRotationRun $run): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->runs->resume($run, $data['reason'], $request->user());

        return back()->with('status', 'Rotasi dilanjutkan.');
    }

    public function complete(Request $request, PkpaRotationRun $run): RedirectResponse
    {
        [$ready, $issues] = $this->progress->isReadyForOperationalCompletion($run);
        if (! $ready) {
            return back()->withErrors(['completion' => implode(' ', $issues) ?: 'Checklist operasional belum lengkap.']);
        }
        $this->runs->markAwaitingReview($run, $request->user());
        $this->runs->operationalComplete($run->refresh(), $request->input('reason', 'Checklist operasional lengkap.'), $request->user());

        return back()->with('status', 'Rotasi ditandai operational complete tanpa mengubah status akademik akhir.');
    }

    public function snapshot(PkpaRotationRun $run): RedirectResponse
    {
        $this->progress->snapshot($run, 'manual');

        return back()->with('status', 'Snapshot progress diperbarui.');
    }

    public function syncPublication(Request $request, PkpaPlacementPublication $publication): RedirectResponse
    {
        $stats = $this->sync->sync($publication, $request->user());

        return back()->with('status', "Sinkronisasi selesai: {$stats['applied']} diterapkan, {$stats['review_required']} perlu review, {$stats['ignored']} tanpa perubahan.");
    }

    public function export()
    {
        $runs = PkpaRotationRun::with(['program', 'practiceDomain', 'practiceSite', 'progressSnapshots' => fn ($query) => $query->latest('snapshot_date')->limit(1)])->get();
        $filename = 'monitoring_operasional_pkpa_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($runs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Program', 'Mahasiswa Core', 'Wahana', 'Tempat', 'Periode', 'Status Rotasi', 'Status Sinkron', 'Progress']);
            foreach ($runs as $run) {
                fputcsv($handle, [
                    $run->program?->code,
                    $run->student_core_user_id,
                    $run->practiceDomain?->name,
                    $run->practiceSite?->name,
                    $run->scheduled_start_date?->format('Y-m-d').' s.d. '.$run->scheduled_end_date?->format('Y-m-d'),
                    $run->status,
                    $run->publication_sync_status,
                    optional($run->progressSnapshots->first())->progress_percentage ?? 0,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
