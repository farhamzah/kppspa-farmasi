<?php

namespace App\Http\Controllers;

use App\Models\PkpaPortfolioSignedDocument;
use App\Models\PkpaRotationPortfolio;
use App\Services\PkpaPortfolioBuilderService;
use App\Services\PkpaPortfolioSignedDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PkpaPortfolioSignedDocumentController extends Controller
{
    public function store(Request $request, PkpaRotationPortfolio $portfolio, PkpaPortfolioSignedDocumentService $documents)
    {
        $data = $request->validate([
            'signed_file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:20480'],
            'signatures_confirmed' => ['required', 'accepted'],
        ]);
        $document = $documents->store($portfolio, $data['signed_file'], $request->user());

        return back()->with('status', 'PDF bertanda tangan tersimpan sebagai '.$document->download_filename);
    }

    public function show(Request $request, PkpaPortfolioSignedDocument $document, PkpaPortfolioBuilderService $portfolios)
    {
        abort_unless($document->portfolio && $portfolios->canAccess($document->portfolio, $request->user()), 403);
        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);
        $headers = ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];

        return $request->boolean('download')
            ? $disk->download($document->path, $document->download_filename, $headers)
            : $disk->response($document->path, $document->download_filename, $headers, 'inline');
    }
}
