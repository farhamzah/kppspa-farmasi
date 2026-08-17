<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\PkpaDocumentNumberingRule;
use App\Models\PkpaDocumentSignatoryConfig;
use App\Models\PkpaDocumentTemplate;
use App\Models\PkpaDocumentType;
use App\Models\PkpaFinalGradeRelease;
use App\Models\PkpaGeneratedDocument;
use App\Models\PkpaGeneratedDocumentVersion;
use App\Models\PkpaProgram;
use App\Models\PkpaPublishedAssignment;
use App\Services\PkpaDocumentDistributionService;
use App\Services\PkpaDocumentFileService;
use App\Services\PkpaDocumentGenerationService;
use App\Services\PkpaDocumentPlaceholderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PkpaDocumentController extends Controller
{
    public function __construct(
        private readonly PkpaDocumentPlaceholderService $placeholder,
        private readonly PkpaDocumentGenerationService $generation,
        private readonly PkpaDocumentDistributionService $distribution,
        private readonly PkpaDocumentFileService $files,
    ) {
    }

    public function index(Request $request): View
    {
        return view('management.pkpa-documents.index', [
            'types' => PkpaDocumentType::orderBy('sort_order')->get(),
            'programs' => PkpaProgram::latest()->get(),
            'templates' => PkpaDocumentTemplate::with(['type', 'program'])->latest()->limit(20)->get(),
            'numberingRules' => PkpaDocumentNumberingRule::with(['type', 'program'])->latest()->limit(20)->get(),
            'signatories' => PkpaDocumentSignatoryConfig::with(['type', 'program'])->latest()->limit(20)->get(),
            'documents' => PkpaGeneratedDocument::with(['type', 'program', 'versions', 'recipients'])->latest()->paginate(15),
            'assignments' => PkpaPublishedAssignment::with('publication.program')->latest()->limit(20)->get(),
            'finalReleases' => PkpaFinalGradeRelease::with('enrollment.program')->latest()->limit(20)->get(),
            'placeholders' => PkpaDocumentPlaceholderService::PLACEHOLDERS,
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:pkpa_document_types,code'],
            'name' => ['required', 'string', 'max:255'],
            'scope_type' => ['required', 'in:program,student,rotation,site,supervisor,grade,graduation,custom'],
            'output_formats' => ['required', 'array', 'min:1'],
            'output_formats.*' => ['in:docx,pdf,xlsx,csv'],
            'requires_number' => ['nullable', 'boolean'],
            'requires_signatory' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
        ]);

        PkpaDocumentType::create($data + [
            'description' => 'Jenis dokumen internal MY PKPA.',
            'is_system' => false,
            'is_active' => true,
            'created_by_core_user_id' => $request->user()->core_user_id,
            'updated_by_core_user_id' => $request->user()->core_user_id,
        ]);

        return back()->with('status', 'Jenis dokumen berhasil dibuat.');
    }

    public function storeTemplate(Request $request, PkpaDocumentType $type): RedirectResponse
    {
        $data = $request->validate([
            'pkpa_program_id' => ['nullable', 'exists:pkpa_programs,id'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'template_engine' => ['required', 'in:docx_template,html,blade,spreadsheet,csv'],
            'template_content' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
        ]);
        $found = $this->placeholder->validateContent($data['template_content'] ?? null);
        $nextVersion = ((int) PkpaDocumentTemplate::where('pkpa_document_type_id', $type->id)
            ->where('pkpa_program_id', $data['pkpa_program_id'] ?? null)
            ->where('code', $data['code'])
            ->max('version_number')) + 1;

        PkpaDocumentTemplate::create($data + [
            'pkpa_document_type_id' => $type->id,
            'version_number' => $nextVersion,
            'status' => 'draft',
            'is_current' => false,
            'available_placeholders' => $found ?: PkpaDocumentPlaceholderService::PLACEHOLDERS,
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'created_by_core_user_id' => $request->user()->core_user_id,
            'updated_by_core_user_id' => $request->user()->core_user_id,
        ]);

        return back()->with('status', 'Template draft berhasil dibuat.');
    }

    public function activateTemplate(Request $request, PkpaDocumentTemplate $template): RedirectResponse
    {
        if (! $request->user()->hasRole('koordinator_kp')) {
            abort(403);
        }
        DB::transaction(function () use ($template, $request) {
            PkpaDocumentTemplate::where('pkpa_document_type_id', $template->pkpa_document_type_id)
                ->where('pkpa_program_id', $template->pkpa_program_id)
                ->where('is_current', true)
                ->update(['is_current' => false, 'current_key' => null, 'status' => 'superseded']);

            $template->update([
                'status' => 'active',
                'is_current' => true,
                'current_key' => 'TYPE:'.$template->pkpa_document_type_id.':PROGRAM:'.($template->pkpa_program_id ?: 'GLOBAL'),
                'activated_by_core_user_id' => $request->user()->core_user_id,
                'activated_at' => now(),
            ]);
        });

        return back()->with('status', 'Template aktif berhasil ditetapkan.');
    }

    public function storeNumbering(Request $request, PkpaDocumentType $type): RedirectResponse
    {
        $data = $request->validate([
            'pkpa_program_id' => ['nullable', 'exists:pkpa_programs,id'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'pattern' => ['required', 'string', 'max:255'],
            'sequence_scope' => ['required', 'in:global,yearly,program,document_type,custom'],
            'reset_policy' => ['required', 'in:never,yearly,monthly,per_program'],
        ]);

        PkpaDocumentNumberingRule::updateOrCreate([
            'pkpa_document_type_id' => $type->id,
            'pkpa_program_id' => $data['pkpa_program_id'] ?? null,
            'code' => $data['code'],
        ], $data + [
            'pkpa_document_type_id' => $type->id,
            'status' => 'active',
            'created_by_core_user_id' => $request->user()->core_user_id,
            'updated_by_core_user_id' => $request->user()->core_user_id,
        ]);

        return back()->with('status', 'Aturan nomor dokumen disimpan.');
    }

    public function storeSignatory(Request $request, PkpaDocumentType $type): RedirectResponse
    {
        $data = $request->validate([
            'pkpa_program_id' => ['nullable', 'exists:pkpa_programs,id'],
            'signatory_role' => ['required', 'string', 'max:80'],
            'core_user_id' => ['nullable', 'string', 'max:80'],
            'name_snapshot' => ['required', 'string', 'max:255'],
            'title_snapshot' => ['nullable', 'string', 'max:255'],
            'employee_number_snapshot' => ['nullable', 'string', 'max:80'],
            'signature_mode' => ['required', 'in:wet_signature,name_only,manual_external,digital_placeholder'],
            'effective_start_date' => ['required', 'date'],
            'effective_end_date' => ['required', 'date', 'after_or_equal:effective_start_date'],
        ]);

        PkpaDocumentSignatoryConfig::create($data + [
            'pkpa_document_type_id' => $type->id,
            'status' => 'active',
            'created_by_core_user_id' => $request->user()->core_user_id,
            'updated_by_core_user_id' => $request->user()->core_user_id,
        ]);

        return back()->with('status', 'Konfigurasi penandatangan disimpan.');
    }

    public function generateDraft(Request $request, PkpaDocumentType $type): RedirectResponse
    {
        $data = $request->validate([
            'pkpa_program_id' => ['nullable', 'exists:pkpa_programs,id'],
            'template_id' => ['nullable', 'exists:pkpa_document_templates,id'],
            'scope_type' => ['required', 'in:program,publication,assignment,final_release,custom'],
            'scope_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'formats' => ['required', 'array', 'min:1'],
            'formats.*' => ['in:docx,pdf,xlsx,csv'],
        ]);
        $document = $this->generation->createDraft($type, $data, $request->user());
        $this->attachRecipients($document);
        $document = $this->generation->generate($document, $data['formats'], $request->user());

        return redirect()->route('management.pkpa-documents.index')->with('status', 'Draft dokumen dibuat dan file versi awal digenerate.');
    }

    public function approve(Request $request, PkpaGeneratedDocument $document): RedirectResponse
    {
        if (! $request->user()->hasRole('koordinator_kp')) {
            abort(403);
        }
        $this->generation->approve($document, $request->user());

        return back()->with('status', 'Dokumen disetujui.');
    }

    public function publish(Request $request, PkpaGeneratedDocument $document): RedirectResponse
    {
        if (! $request->user()->hasRole('koordinator_kp')) {
            abort(403);
        }
        $this->generation->publish($document, $request->user());

        return back()->with('status', 'Dokumen dipublish dan distribusi portal dicatat.');
    }

    public function cancel(Request $request, PkpaGeneratedDocument $document): RedirectResponse
    {
        $data = $request->validate(['cancellation_reason' => ['required', 'string']]);
        $this->generation->cancel($document, $data['cancellation_reason'], $request->user());

        return back()->with('status', 'Dokumen dibatalkan tanpa menghapus histori.');
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

    public function exportCsv(Request $request): StreamedResponse
    {
        $rows = PkpaGeneratedDocument::with('type')->latest()->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Jenis', 'Judul', 'Nomor', 'Status', 'Tanggal']);
            foreach ($rows as $document) {
                fputcsv($out, [
                    $this->files->sanitizeSpreadsheetCell($document->type?->name),
                    $this->files->sanitizeSpreadsheetCell($document->title),
                    $this->files->sanitizeSpreadsheetCell($document->document_number),
                    $document->status,
                    optional($document->document_date)->toDateString(),
                ]);
            }
            fclose($out);
        }, 'rekap_dokumen_pkpa.csv', ['Content-Type' => 'text/csv']);
    }

    private function attachRecipients(PkpaGeneratedDocument $document): void
    {
        $document->loadMissing('type');
        if ($document->scope_type === 'assignment' && $document->scope_id) {
            $assignment = PkpaPublishedAssignment::with('supervisors')->find($document->scope_id);
            if (! $assignment) {
                return;
            }
            $this->distribution->ensurePortalRecipient($document, [
                'recipient_type' => 'student',
                'core_user_id' => $assignment->student_core_user_id,
                'name_snapshot' => $assignment->student_name_snapshot,
            ]);
            foreach ($assignment->supervisors as $supervisor) {
                $this->distribution->ensurePortalRecipient($document, [
                    'recipient_type' => $supervisor->supervisor_type === 'internal' ? 'internal_supervisor' : 'field_supervisor',
                    'core_user_id' => $supervisor->core_user_id,
                    'name_snapshot' => $supervisor->name_snapshot,
                    'email_snapshot' => $supervisor->email_snapshot,
                ]);
            }
        }

        if ($document->scope_type === 'final_release' && $document->scope_id) {
            $release = PkpaFinalGradeRelease::with('enrollment')->find($document->scope_id);
            if ($release?->enrollment) {
                $this->distribution->ensurePortalRecipient($document, [
                    'recipient_type' => 'student',
                    'core_user_id' => $release->enrollment->core_user_id,
                    'name_snapshot' => $release->enrollment->student_name_snapshot,
                    'email_snapshot' => $release->enrollment->student_email_snapshot,
                ]);
            }
        }
    }
}
