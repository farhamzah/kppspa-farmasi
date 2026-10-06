<?php

namespace App\Services;

use App\Models\PkpaPortfolioSignedDocument;
use App\Models\PkpaRotationPortfolio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PkpaPortfolioSignedDocumentService
{
    public function contentChecksum(PkpaRotationPortfolio $portfolio): string
    {
        $portfolio = $portfolio->fresh();
        $content = $portfolio->only(['identity_snapshot', 'placement_snapshot', 'integrity_acknowledged_at']);
        foreach (['sectionRecords', 'caseReports', 'weeklyReflections', 'selfAssessments', 'documentationItems'] as $relation) {
            $query = $portfolio->{$relation}();
            if ($relation === 'sectionRecords') {
                $query->whereIn('source_type', ['structured_form', 'attachment_list']);
            }
            $content[$relation] = $query->orderBy('id')->get()
                ->map(fn ($record) => collect($record->attributesToArray())->except(['created_at', 'updated_at'])->all())->all();
        }
        foreach (['logbookEntries', 'attendanceRecords'] as $relation) {
            $content[$relation] = $portfolio->rotationRun->{$relation}()->orderBy('id')->get()
                ->map(fn ($record) => collect($record->attributesToArray())->except(['created_at', 'updated_at'])->all())->all();
        }

        return hash('sha256', json_encode($content, JSON_THROW_ON_ERROR));
    }

    public function currentDocument(PkpaRotationPortfolio $portfolio): ?PkpaPortfolioSignedDocument
    {
        $document = $portfolio->signedDocuments()->latest('version_number')->first();

        return $document && Storage::disk($document->disk)->exists($document->path)
            && hash_equals($document->content_checksum, $this->contentChecksum($portfolio)) ? $document : null;
    }

    public function filename(PkpaRotationPortfolio $portfolio, int $version): string
    {
        $parts = [
            $portfolio->practiceDomain?->name ?: 'Wahana',
            $portfolio->enrollment?->student_number ?: data_get($portfolio->identity_snapshot, 'student_number', 'Tanpa_NPM'),
            data_get($portfolio->identity_snapshot, 'student_name', 'Mahasiswa'),
        ];
        $parts = array_map(fn ($part) => Str::limit(Str::slug((string) $part, '_'), 50, '') ?: 'data', $parts);

        return 'Portofolio_PKPA_'.implode('_', $parts).'_Bertanda_Tangan_v'.str_pad((string) $version, 2, '0', STR_PAD_LEFT).'.pdf';
    }

    public function store(PkpaRotationPortfolio $portfolio, UploadedFile $file, User $actor): PkpaPortfolioSignedDocument
    {
        Validator::make(['signed_file' => $file], [
            'signed_file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:20480'],
        ])->validate();
        $path = null;
        try {
            return DB::transaction(function () use ($portfolio, $file, $actor, &$path) {
                $portfolio = PkpaRotationPortfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
                $owner = $portfolio->enrollment?->core_user_id ?? $portfolio->rotationRun?->student_core_user_id;
                if (! $actor->hasRole('mahasiswa') || (string) $owner !== (string) $actor->core_user_id) {
                    throw ValidationException::withMessages(['authorization' => 'Unggahan hanya dapat dilakukan mahasiswa pemilik.']);
                }
                if ($portfolio->locked_at || ! in_array($portfolio->status, ['draft', 'in_progress', 'field_revision_requested', 'internal_revision_requested', 'submitted_to_field_supervisor', 'field_verified', 'submitted_to_internal_supervisor'], true)) {
                    throw ValidationException::withMessages(['signed_file' => 'Dokumen final sudah dikunci. Hubungi koordinator bila perlu revisi.']);
                }
                $version = (int) $portfolio->signedDocuments()->max('version_number') + 1;
                $path = $file->storeAs('pkpa-portfolios/'.$portfolio->id.'/signed', Str::uuid().'.pdf', 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['signed_file' => 'Unggahan gagal disimpan. Coba kembali.']);
                }
                $document = $portfolio->signedDocuments()->create([
                    'version_number' => $version, 'disk' => 'local', 'path' => $path,
                    'download_filename' => $this->filename($portfolio, $version),
                    'file_size' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath()),
                    'content_checksum' => $this->contentChecksum($portfolio),
                    'uploaded_by_core_user_id' => $actor->core_user_id, 'signatures_confirmed_at' => now(),
                ]);
                app(PkpaAuditService::class)->record($actor, 'portfolio_signed_document_uploaded', $document, null, ['version_number' => $version, 'checksum' => $document->checksum]);

                return $document;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }
}
