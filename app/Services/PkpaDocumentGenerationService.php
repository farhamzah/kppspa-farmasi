<?php

namespace App\Services;

use App\Models\PkpaDocumentGenerationJob;
use App\Models\PkpaDocumentTemplate;
use App\Models\PkpaDocumentType;
use App\Models\PkpaFinalGradeRelease;
use App\Models\PkpaGeneratedDocument;
use App\Models\PkpaGeneratedDocumentVersion;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPublishedAssignment;
use App\Models\User;
use App\Support\SimplePdfReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class PkpaDocumentGenerationService
{
    public function __construct(
        private readonly PkpaDocumentPlaceholderService $placeholder,
        private readonly PkpaDocumentNumberingService $numbering,
        private readonly PkpaDocumentDistributionService $distribution,
        private readonly PkpaDocumentFileService $files,
    ) {
    }

    public function createDraft(PkpaDocumentType $type, array $data, User $actor): PkpaGeneratedDocument
    {
        $template = $this->templateFor($type, $data['pkpa_program_id'] ?? null, $data['template_id'] ?? null);
        $scopeType = $data['scope_type'] ?? $type->scope_type;
        $scopeId = $data['scope_id'] ?? null;
        $formats = $this->supportedFormats($type, $data['formats'] ?? null);
        $generationKey = $data['generation_key'] ?? $this->generationKey($type, $template, $scopeType, $scopeId, $formats);

        return DB::transaction(function () use ($type, $template, $data, $actor, $scopeType, $scopeId, $formats, $generationKey) {
            $document = PkpaGeneratedDocument::firstOrCreate([
                'generation_key' => $generationKey,
            ], [
                'pkpa_document_type_id' => $type->id,
                'pkpa_document_template_id' => $template?->id,
                'pkpa_program_id' => $data['pkpa_program_id'] ?? null,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'title' => $data['title'] ?? $type->name,
                'status' => 'draft',
                'approval_status' => $type->requires_approval ? 'pending' : 'not_required',
                'generation_context' => $this->context($type, $scopeType, $scopeId, $data),
                'created_by_core_user_id' => $actor->core_user_id,
                'updated_by_core_user_id' => $actor->core_user_id,
            ]);

            PkpaDocumentGenerationJob::firstOrCreate([
                'generation_key' => $generationKey,
            ], [
                'pkpa_generated_document_id' => $document->id,
                'status' => 'queued',
                'requested_formats' => $formats,
                'request_snapshot' => [
                    'type' => $type->code,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                ],
                'queued_at' => now(),
                'created_by_core_user_id' => $actor->core_user_id,
            ]);

            return $document->fresh(['type', 'template']);
        });
    }

    public function generate(PkpaGeneratedDocument $document, array $formats, User $actor): PkpaGeneratedDocument
    {
        return DB::transaction(function () use ($document, $formats, $actor) {
            $document = PkpaGeneratedDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if (in_array($document->status, ['published', 'cancelled', 'superseded', 'archived'], true)) {
                throw ValidationException::withMessages(['document' => 'Dokumen yang sudah diterbitkan atau dibatalkan tidak dapat ditimpa.']);
            }

            $document->loadMissing(['type', 'template', 'program']);
            $versionNumber = ((int) $document->versions()->max('version_number')) + 1;
            $context = $document->generation_context ?? [];
            $rendered = $this->placeholder->render($document->template?->template_content, $context);
            $versions = [];

            foreach ($formats as $format) {
                $bytes = $this->renderFormat($format, $document, $context, $rendered);
                $this->files->validateBytes($bytes, $format);
                $path = 'pkpa-documents/'.$document->id.'/v'.$versionNumber.'/'.str()->uuid().'.'.$format;
                Storage::disk('local')->put($path, $bytes);
                $stored = basename($path);

                $versions[] = PkpaGeneratedDocumentVersion::create([
                    'pkpa_generated_document_id' => $document->id,
                    'version_number' => $versionNumber,
                    'output_format' => $format,
                    'original_filename' => $this->files->safeDownloadName(str($document->title)->slug('_').'_'.$versionNumber.'.'.$format),
                    'stored_filename' => $stored,
                    'disk' => 'local',
                    'path' => $path,
                    'mime_type' => $this->mime($format),
                    'file_size' => strlen($bytes),
                    'checksum' => hash('sha256', $bytes),
                    'generation_status' => 'generated',
                    'generation_log_summary' => 'Generated by MY PKPA document service.',
                    'generated_by_core_user_id' => $actor->core_user_id,
                    'generated_at' => now(),
                ]);
            }

            $document->update([
                'status' => 'generated',
                'current_version_id' => $versions[0]?->id,
                'updated_by_core_user_id' => $actor->core_user_id,
            ]);

            PkpaDocumentGenerationJob::where('generation_key', $document->generation_key)->update([
                'pkpa_generated_document_id' => $document->id,
                'status' => 'finished',
                'finished_at' => now(),
            ]);

            return $document->fresh(['versions', 'recipients']);
        });
    }

    public function approve(PkpaGeneratedDocument $document, User $actor): PkpaGeneratedDocument
    {
        if (! in_array($document->status, ['generated', 'under_review'], true)) {
            throw ValidationException::withMessages(['document' => 'Dokumen harus generated atau under review sebelum approval.']);
        }

        $document->update([
            'status' => 'approved',
            'approval_status' => 'approved',
            'approved_by_core_user_id' => $actor->core_user_id,
            'approved_at' => now(),
        ]);

        return $document->fresh();
    }

    public function publish(PkpaGeneratedDocument $document, User $actor): PkpaGeneratedDocument
    {
        return DB::transaction(function () use ($document, $actor) {
            $document = PkpaGeneratedDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $document->loadMissing(['type', 'recipients', 'program']);
            if (! in_array($document->status, ['approved', 'generated'], true)) {
                throw ValidationException::withMessages(['document' => 'Dokumen belum siap dipublish.']);
            }
            if ($document->type->requires_approval && $document->approval_status !== 'approved') {
                throw ValidationException::withMessages(['document' => 'Dokumen memerlukan approval sebelum publish.']);
            }
            if ($document->type->requires_number) {
                $this->numbering->allocate($document);
                $document->refresh();
            }

            $document->update([
                'status' => 'published',
                'published_by_core_user_id' => $actor->core_user_id,
                'published_at' => now(),
                'updated_by_core_user_id' => $actor->core_user_id,
            ]);
            $this->distribution->markPublished($document->fresh('recipients'), $actor);

            return $document->fresh(['recipients', 'distributionLogs']);
        });
    }

    public function cancel(PkpaGeneratedDocument $document, string $reason, User $actor): PkpaGeneratedDocument
    {
        $document->update([
            'status' => 'cancelled',
            'cancelled_by_core_user_id' => $actor->core_user_id,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $document->fresh();
    }

    public function canAccess(PkpaGeneratedDocument $document, User $user): bool
    {
        if ($user->hasAnyRole(['admin', 'koordinator_kp'])) {
            return true;
        }
        if ($document->status !== 'published') {
            return false;
        }

        return $document->recipients()
            ->where('status', 'active')
            ->where('core_user_id', $user->core_user_id)
            ->exists();
    }

    private function templateFor(PkpaDocumentType $type, ?int $programId, ?int $templateId): ?PkpaDocumentTemplate
    {
        if ($templateId) {
            return PkpaDocumentTemplate::whereKey($templateId)->where('pkpa_document_type_id', $type->id)->firstOrFail();
        }

        return $type->templates()
            ->where('status', 'active')
            ->where(function ($query) use ($programId) {
                $query->where('pkpa_program_id', $programId)->orWhereNull('pkpa_program_id');
            })
            ->orderByRaw('pkpa_program_id is null')
            ->first();
    }

    private function context(PkpaDocumentType $type, string $scopeType, ?int $scopeId, array $data): array
    {
        $context = [
            'generated_document' => ['number' => '-', 'date' => now()->toDateString()],
            'program' => ['name' => '-', 'code' => '-', 'academic_year' => '-'],
            'publication' => ['number' => '-'],
            'student' => ['name' => '-', 'npm' => '-', 'group' => '-'],
            'rotation' => ['domain' => '-', 'option' => '-', 'site' => '-', 'start_date' => '-', 'end_date' => '-'],
            'internal_supervisor' => ['name' => '-'],
            'field_supervisor' => ['name' => '-'],
            'final_grade' => ['score' => '-'],
            'graduation' => ['decision' => '-'],
            'label' => 'Dokumen Internal MY PKPA',
            'rows' => [],
        ];

        if ($scopeType === 'publication' && $scopeId) {
            $publication = PkpaPlacementPublication::with(['program', 'assignments.supervisors'])->findOrFail($scopeId);
            $first = $publication->assignments->first();
            $context['program'] = [
                'name' => $publication->program?->name,
                'code' => $publication->program?->code,
                'academic_year' => $publication->program?->academic_year,
            ];
            $context['publication'] = ['number' => $publication->publication_number];
            if ($first) {
                $context['student'] = ['name' => $first->student_name_snapshot, 'npm' => $first->student_number_snapshot, 'group' => $first->student_group_snapshot ?: '-'];
                $context['rotation'] = ['domain' => $first->practice_domain_name_snapshot, 'option' => $first->practice_domain_option_name_snapshot ?: '-', 'site' => $first->practice_site_name_snapshot, 'start_date' => $first->start_date?->toDateString(), 'end_date' => $first->end_date?->toDateString()];
                $context['internal_supervisor'] = ['name' => $first->supervisors->firstWhere('supervisor_type', 'internal')?->display_name ?: '-'];
                $context['field_supervisor'] = ['name' => $first->supervisors->firstWhere('supervisor_type', 'field')?->display_name ?: '-'];
            }
            $context['rows'] = $publication->assignments->map(fn (PkpaPublishedAssignment $assignment) => [
                $assignment->student_number_snapshot,
                $assignment->student_name_snapshot,
                $assignment->practice_domain_name_snapshot,
                $assignment->practice_site_name_snapshot,
                $assignment->start_date?->toDateString().' - '.$assignment->end_date?->toDateString(),
            ])->values()->all();
        }

        if ($scopeType === 'assignment' && $scopeId) {
            $assignment = PkpaPublishedAssignment::with(['publication.program', 'supervisors'])->findOrFail($scopeId);
            $context['program'] = [
                'name' => $assignment->publication?->program?->name,
                'code' => $assignment->publication?->program?->code,
                'academic_year' => $assignment->publication?->program?->academic_year,
            ];
            $context['publication'] = ['number' => $assignment->publication?->publication_number];
            $context['student'] = ['name' => $assignment->student_name_snapshot, 'npm' => $assignment->student_number_snapshot, 'group' => $assignment->student_group_snapshot ?: '-'];
            $context['rotation'] = ['domain' => $assignment->practice_domain_name_snapshot, 'option' => $assignment->practice_domain_option_name_snapshot ?: '-', 'site' => $assignment->practice_site_name_snapshot, 'start_date' => $assignment->start_date?->toDateString(), 'end_date' => $assignment->end_date?->toDateString()];
            $context['internal_supervisor'] = ['name' => $assignment->supervisors->firstWhere('supervisor_type', 'internal')?->display_name ?: '-'];
            $context['field_supervisor'] = ['name' => $assignment->supervisors->firstWhere('supervisor_type', 'field')?->display_name ?: '-'];
            $context['rows'] = [[$assignment->student_number_snapshot, $assignment->student_name_snapshot, $assignment->practice_domain_name_snapshot, $assignment->practice_site_name_snapshot, $assignment->start_date?->toDateString().' - '.$assignment->end_date?->toDateString()]];
        }

        if ($scopeType === 'final_release' && $scopeId) {
            $release = PkpaFinalGradeRelease::with(['enrollment.program', 'decision', 'result'])->findOrFail($scopeId);
            $snapshot = $release->student_visible_snapshot ?? [];
            $context['label'] = $type->code === 'transkrip_internal_pkpa' ? 'Transkrip Internal PKPA - MY PKPA' : 'Rekap Hasil Akademik PKPA';
            $context['program'] = [
                'name' => $release->enrollment?->program?->name,
                'code' => $release->enrollment?->program?->code,
                'academic_year' => $release->enrollment?->program?->academic_year,
            ];
            $context['student'] = [
                'name' => $release->enrollment?->student_name_snapshot,
                'npm' => $release->enrollment?->student_number,
                'group' => data_get($snapshot, 'student.group', '-'),
            ];
            $context['final_grade'] = ['score' => data_get($snapshot, 'final_score', $release->result?->final_score)];
            $context['graduation'] = ['decision' => $release->decision?->decision ?? data_get($snapshot, 'decision', '-')];
            $context['rows'] = collect(data_get($release->result?->calculation_snapshot, 'component_results', []))->map(fn ($row) => [
                data_get($row, 'name', '-'),
                data_get($row, 'raw_score', '-'),
                data_get($row, 'weighted_score', '-'),
            ])->values()->all();
        }

        return array_replace_recursive($context, $data['context'] ?? []);
    }

    private function supportedFormats(PkpaDocumentType $type, ?array $requested): array
    {
        $supported = $type->output_formats ?: ['docx'];
        $formats = $requested ? array_values(array_intersect($requested, $supported)) : [$supported[0]];
        if ($formats === []) {
            throw ValidationException::withMessages(['formats' => 'Format output belum didukung untuk jenis dokumen ini.']);
        }

        return $formats;
    }

    private function generationKey(PkpaDocumentType $type, ?PkpaDocumentTemplate $template, string $scopeType, ?int $scopeId, array $formats): string
    {
        return hash('sha256', implode('|', [$type->id, $template?->id ?: 'no-template', $scopeType, $scopeId ?: 'none', implode(',', $formats)]));
    }

    private function renderFormat(string $format, PkpaGeneratedDocument $document, array $context, string $rendered): string
    {
        return match ($format) {
            'docx' => $this->docx($document, $rendered),
            'pdf' => $this->pdf($document, $context),
            'xlsx' => $this->xlsx($document, $context),
            'csv' => $this->csv($context),
            default => throw ValidationException::withMessages(['formats' => 'Format tidak didukung.']),
        };
    }

    private function docx(PkpaGeneratedDocument $document, string $rendered): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pkpa-docx-');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $paragraphs = collect(preg_split('/\R+/', strip_tags($rendered)))->map(fn ($line) => '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($line ?: ' ', ENT_XML1, 'UTF-8').'</w:t></w:r></w:p>')->implode('');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$paragraphs.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/></w:sectPr></w:body></w:document>');
        $zip->close();
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function pdf(PkpaGeneratedDocument $document, array $context): string
    {
        $rows = $context['rows'] ?: [['-', 'Belum ada data', '-', '-', '-']];

        return SimplePdfReport::table($document->title, [
            'Jenis' => $document->type?->name,
            'Status' => 'Dokumen Internal MY PKPA',
            'Tanggal' => now()->format('Y-m-d'),
        ], ['Kolom 1', 'Kolom 2', 'Kolom 3', 'Kolom 4', 'Kolom 5'], $rows);
    }

    private function xlsx(PkpaGeneratedDocument $document, array $context): string
    {
        $rows = array_merge([
            [$document->title],
            ['Dokumen Internal MY PKPA'],
            [],
            ['Kolom 1', 'Kolom 2', 'Kolom 3', 'Kolom 4', 'Kolom 5'],
        ], $context['rows'] ?: [['-', 'Belum ada data', '-', '-', '-']]);
        $tmp = tempnam(sys_get_temp_dir(), 'pkpa-xlsx-');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Dokumen" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $sheetRows = collect($rows)->values()->map(function (array $row, int $index) {
            $cells = collect($row)->values()->map(function ($value, int $cellIndex) use ($index) {
                $column = chr(65 + $cellIndex).($index + 1);
                $text = htmlspecialchars($this->files->sanitizeSpreadsheetCell($value), ENT_XML1, 'UTF-8');
                return '<c r="'.$column.'" t="inlineStr"><is><t>'.$text.'</t></is></c>';
            })->implode('');
            return '<row r="'.($index + 1).'">'.$cells.'</row>';
        })->implode('');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function csv(array $context): string
    {
        $rows = $context['rows'] ?: [['-', 'Belum ada data', '-', '-', '-']];
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Kolom 1', 'Kolom 2', 'Kolom 3', 'Kolom 4', 'Kolom 5']);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($value) => $this->files->sanitizeSpreadsheetCell($value), $row));
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function mime(string $format): string
    {
        return match ($format) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }
}
