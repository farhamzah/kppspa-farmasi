<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\PkpaProgram;
use App\Services\PkpaAuditService;
use App\Services\PkpaPlacementReadinessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaPlacementReadinessController extends Controller
{
    public function __construct(
        private readonly PkpaPlacementReadinessService $readinessService,
        private readonly PkpaAuditService $audit,
    ) {
    }

    public function index(Request $request): View
    {
        $program = PkpaProgram::query()
            ->when($request->filled('program_id'), fn ($q) => $q->whereKey($request->program_id))
            ->orderByDesc('id')
            ->first();
        $readiness = $program ? $this->readinessService->check($program) : null;
        if ($program) {
            $this->audit->record($request->user(), 'placement_readiness_checked', $program, null, ['status' => $readiness['status']]);
        }

        return view('management.pkpa-placement-readiness.index', [
            'programs' => PkpaProgram::orderByDesc('id')->get(),
            'program' => $program,
            'readiness' => $readiness,
        ]);
    }
}
