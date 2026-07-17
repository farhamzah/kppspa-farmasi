<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\PkpaProgram;
use App\Services\PkpaAnalyticsService;
use App\Services\PkpaDocumentFileService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PkpaAnalyticsController extends Controller
{
    public function __construct(
        private readonly PkpaAnalyticsService $analytics,
        private readonly PkpaDocumentFileService $files,
    ) {
    }

    public function index(Request $request): View
    {
        $programId = $request->integer('program_id') ?: null;

        return view('management.pkpa-analytics.index', [
            'programs' => PkpaProgram::latest()->get(),
            'selectedProgramId' => $programId,
            'analytics' => $this->analytics->dashboard($programId),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->analytics->rows($request->integer('program_id') ?: null);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Bagian', 'Metrik', 'Nilai']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $this->files->sanitizeSpreadsheetCell($row['section']),
                    $this->files->sanitizeSpreadsheetCell($row['metric']),
                    $row['value'],
                ]);
            }
            fclose($out);
        }, 'analytics_pkpa.csv', ['Content-Type' => 'text/csv']);
    }
}
