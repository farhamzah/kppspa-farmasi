<?php

namespace App\Http\Controllers\Management;

use App\Exports\KpRecapExport;
use App\Http\Controllers\Controller;
use App\Services\KpRecapService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __invoke(string $type, Request $request, KpRecapService $service): BinaryFileResponse
    {
        abort_unless(in_array($type, ['students', 'placements', 'logbooks', 'exams', 'scores'], true), 404);

        $filenames = [
            'students' => 'rekap_mahasiswa_pkpa.xlsx',
            'placements' => 'rekap_penempatan_pkpa.xlsx',
            'logbooks' => 'rekap_logbook_pkpa.xlsx',
            'exams' => 'rekap_ujian_pkpa.xlsx',
            'scores' => 'rekap_nilai_pkpa.xlsx',
        ];

        return Excel::download(new KpRecapExport($service->rows($type, $request)), $filenames[$type]);
    }
}
