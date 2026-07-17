<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PkpaGeneratedDocument;
use App\Models\PkpaGeneratedDocumentVersion;
use App\Services\PkpaDocumentFileService;
use App\Services\PkpaDocumentGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PkpaDocumentPortalController extends Controller
{
    public function __construct(
        private readonly PkpaDocumentGenerationService $generation,
        private readonly PkpaDocumentFileService $files,
    ) {
    }

    public function index(Request $request): View
    {
        return view('student.pkpa-documents.index', [
            'documents' => PkpaGeneratedDocument::with(['type', 'versions', 'recipients'])
                ->where('status', 'published')
                ->whereHas('recipients', fn ($query) => $query
                    ->where('status', 'active')
                    ->where('core_user_id', $request->user()->core_user_id))
                ->latest('published_at')
                ->get(),
        ]);
    }

    public function download(Request $request, PkpaGeneratedDocumentVersion $version): StreamedResponse
    {
        $document = $version->document()->with('recipients')->firstOrFail();
        abort_unless($this->generation->canAccess($document, $request->user()), 403);

        $document->distributionLogs()->create([
            'channel' => 'download',
            'status' => 'sent',
            'attempt_count' => 1,
            'downloaded_at' => now(),
            'distributed_by_core_user_id' => $request->user()->core_user_id,
        ]);

        return Storage::disk($version->disk)->download($version->path, $this->files->safeDownloadName($version->original_filename), [
            'Content-Type' => $version->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
