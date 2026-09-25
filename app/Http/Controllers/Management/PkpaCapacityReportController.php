<?php

namespace App\Http\Controllers\Management;

use App\Exports\KpTableReportExport;
use App\Http\Controllers\Controller;
use App\Services\PkpaCapacityReportService;
use App\Support\SimplePdfReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PkpaCapacityReportController extends Controller
{
    public function preview(string $type, Request $request, PkpaCapacityReportService $service): View
    {
        $service->prepare($type, $request);

        return view('management.capacity-reports.preview', [
            'title' => $service->title($type),
            'type' => $type,
            'rows' => $service->rows($type, $request),
            'filters' => $service->filterSummary($type, $request),
            'printMode' => $request->boolean('print'),
        ]);
    }

    public function download(string $type, string $format, Request $request, PkpaCapacityReportService $service): Response|BinaryFileResponse
    {
        abort_unless(in_array($format, ['word', 'xlsx', 'pdf'], true), 404);
        $service->prepare($type, $request);

        $rows = $service->rows($type, $request);
        $title = $service->title($type);
        $filters = $service->filterSummary($type, $request);
        $filename = $service->filename($type);

        if ($format === 'xlsx') {
            return Excel::download(new KpTableReportExport($title, $filters, $rows), $filename.'.xlsx');
        }

        if ($format === 'pdf') {
            $headings = array_keys($rows->first() ?? ['No' => '', 'Data' => '']);

            return response(SimplePdfReport::table(
                $title,
                $filters,
                $headings,
                $rows->map(fn (array $row) => array_values($row))->all(),
            ), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        return response()->view('management.capacity-reports.word', [
            'title' => $title,
            'rows' => $rows,
            'filters' => $filters,
        ], 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.doc"',
        ]);
    }
}
