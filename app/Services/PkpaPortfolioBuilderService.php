<?php

namespace App\Services;

use App\Models\PkpaPortfolioCaseReport;
use App\Models\PkpaPortfolioDocumentationItem;
use App\Models\PkpaPortfolioExportVersion;
use App\Models\PkpaPortfolioPublication;
use App\Models\PkpaPortfolioReview;
use App\Models\PkpaPortfolioSelfAssessment;
use App\Models\PkpaPortfolioTemplate;
use App\Models\PkpaRotationPortfolio;
use App\Models\PkpaRotationRun;
use App\Models\User;
use App\Support\SimplePdfReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class PkpaPortfolioBuilderService
{
    public const PATIENT_IDENTIFIER_PATTERNS = [
        '/\b(no\.?\s*)?(rm|rekam\s*medis|medical\s*record)\b/i',
        '/\b(nik|ktp|kk|bpjs)\b/i',
        '/\b(08\d{8,12}|\+62\d{8,13})\b/',
        '/\b(jalan|jl\.|rt\s*\d+|rw\s*\d+|kelurahan|kecamatan)\b/i',
        '/\b(nama\s*pasien|pasien\s*bernama)\b/i',
    ];

    public function ensureForRun(PkpaRotationRun $run, ?User $actor = null): PkpaRotationPortfolio
    {
        $run->loadMissing([
            'program',
            'enrollment.activeGroupMembership.group',
            'practiceDomain',
            'practiceSite',
            'currentAssignment.supervisors',
            'supervisorHistories',
        ]);

        $template = PkpaPortfolioTemplate::query()
            ->with('sections')
            ->where('practice_domain_id', $run->practice_domain_id)
            ->where('is_current', true)
            ->where('status', 'active')
            ->where(function ($query) use ($run) {
                $query->whereNull('pkpa_program_id')->orWhere('pkpa_program_id', $run->pkpa_program_id);
            })
            ->orderByRaw('pkpa_program_id is null')
            ->firstOrFail();

        return DB::transaction(function () use ($run, $template, $actor) {
            $portfolio = PkpaRotationPortfolio::firstOrCreate([
                'pkpa_rotation_run_id' => $run->id,
                'is_current' => true,
            ], [
                'pkpa_portfolio_template_id' => $template->id,
                'pkpa_enrollment_id' => $run->pkpa_enrollment_id,
                'pkpa_program_id' => $run->pkpa_program_id,
                'practice_domain_id' => $run->practice_domain_id,
                'portfolio_number' => 1,
                'status' => 'draft',
                'current_key' => 'RUN:'.$run->id,
                'identity_snapshot' => $this->identitySnapshot($run),
                'placement_snapshot' => $this->placementSnapshot($run),
                'integrity_pact_version' => data_get($template->integrity_pact, 'version', 'v1'),
                'integrity_pact_text' => data_get($template->integrity_pact, 'text', $this->defaultIntegrityText()),
            ]);

            if ($portfolio->wasRecentlyCreated || $portfolio->pkpa_portfolio_template_id !== $template->id) {
                $portfolio->update([
                    'pkpa_portfolio_template_id' => $template->id,
                    'identity_snapshot' => $this->identitySnapshot($run),
                    'placement_snapshot' => $this->placementSnapshot($run),
                ]);
            }

            foreach ($template->sections as $section) {
                $portfolio->sectionRecords()->firstOrCreate([
                    'section_code' => $section->code,
                ], [
                    'pkpa_portfolio_template_section_id' => $section->id,
                    'source_type' => $section->source_type,
                    'status' => $this->autoSectionStatus($section->source_type, $run),
                    'auto_source_refs' => $this->sourceRefs($section->source_type, $run),
                    'completion_snapshot' => ['created_by' => $actor?->core_user_id, 'source_type' => $section->source_type],
                    'completed_at' => str_starts_with($section->source_type, 'auto_') ? now() : null,
                ]);
            }

            $this->syncProgress($portfolio->fresh(['template.sections', 'sectionRecords', 'caseReports', 'weeklyReflections', 'selfAssessments', 'documentationItems']));

            return $portfolio->fresh(['template.sections', 'sectionRecords']);
        });
    }

    public function acknowledgeIntegrity(PkpaRotationPortfolio $portfolio, User $actor): PkpaRotationPortfolio
    {
        $this->ensureStudentOwns($portfolio, $actor);
        $portfolio->update([
            'integrity_acknowledged_at' => now(),
            'integrity_acknowledged_by_core_user_id' => $actor->core_user_id,
            'status' => $portfolio->status === 'draft' ? 'in_progress' : $portfolio->status,
        ]);

        return $this->syncProgress($portfolio->fresh());
    }

    public function saveCase(PkpaRotationPortfolio $portfolio, array $data, User $actor): PkpaPortfolioCaseReport
    {
        $this->ensureStudentOwns($portfolio, $actor);
        $warnings = $this->patientPrivacyWarnings($data);
        if ($warnings !== []) {
            throw ValidationException::withMessages(['privacy' => implode(' ', $warnings)]);
        }
        if (empty($data['anonymization_confirmed'])) {
            throw ValidationException::withMessages(['anonymization_confirmed' => 'Konfirmasi anonimisasi wajib dicentang.']);
        }

        $case = PkpaPortfolioCaseReport::updateOrCreate([
            'pkpa_rotation_portfolio_id' => $portfolio->id,
            'case_code' => $data['case_code'],
        ], array_merge($data, [
            'privacy_warnings' => [],
            'status' => 'completed',
            'created_by_core_user_id' => $actor->core_user_id,
        ]));

        $this->syncProgress($portfolio->fresh());

        return $case;
    }

    public function saveReflection(PkpaRotationPortfolio $portfolio, array $data, User $actor)
    {
        $this->ensureStudentOwns($portfolio, $actor);
        $record = $portfolio->weeklyReflections()->updateOrCreate([
            'week_number' => $data['week_number'],
        ], array_merge($data, ['status' => 'completed']));
        $this->syncProgress($portfolio->fresh());

        return $record;
    }

    public function saveSelfAssessment(PkpaRotationPortfolio $portfolio, array $data, User $actor): PkpaPortfolioSelfAssessment
    {
        $this->ensureStudentOwns($portfolio, $actor);
        if (($data['score'] ?? 0) < 1 || ($data['score'] ?? 0) > 5) {
            throw ValidationException::withMessages(['score' => 'Skor penilaian diri wajib 1 sampai 5.']);
        }
        $record = $portfolio->selfAssessments()->create(array_merge($data, ['status' => 'completed']));
        $this->syncProgress($portfolio->fresh());

        return $record;
    }

    public function saveDocumentation(PkpaRotationPortfolio $portfolio, array $data, ?UploadedFile $file, User $actor): PkpaPortfolioDocumentationItem
    {
        $this->ensureStudentOwns($portfolio, $actor);
        if (empty($data['anonymization_confirmed']) || empty($data['consent_confirmed'])) {
            throw ValidationException::withMessages(['documentation' => 'Konfirmasi izin dan anonimisasi dokumentasi wajib.']);
        }

        $fileData = ['disk' => 'local'];
        if ($file) {
            $path = $file->store('pkpa-portfolios/'.$portfolio->id.'/documentation', 'local');
            $fileData = [
                'disk' => 'local',
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ];
        }

        $record = $portfolio->documentationItems()->create(array_merge($data, $fileData, ['status' => 'submitted']));
        $this->syncProgress($portfolio->fresh());

        return $record;
    }

    public function submit(PkpaRotationPortfolio $portfolio, User $actor): PkpaRotationPortfolio
    {
        $this->ensureStudentOwns($portfolio, $actor);
        $progress = $this->completeness($portfolio->fresh());
        if (! $progress['ready_to_submit']) {
            throw ValidationException::withMessages(['portfolio' => implode(' ', $progress['blocking'])]);
        }
        $portfolio->update([
            'status' => 'submitted_to_field_supervisor',
            'submitted_at' => now(),
            'submitted_by_core_user_id' => $actor->core_user_id,
            'progress_snapshot' => $progress,
        ]);

        return $portfolio->fresh();
    }

    public function review(PkpaRotationPortfolio $portfolio, string $reviewerType, string $action, string $comments, User $actor): PkpaPortfolioReview
    {
        if ($reviewerType === 'field') {
            $this->ensureFieldSupervisorOwns($portfolio, $actor);
            $allowed = ['verify', 'revision_requested'];
        } elseif ($reviewerType === 'internal') {
            $this->ensureInternalSupervisorOwns($portfolio, $actor);
            $allowed = ['approve', 'revision_requested'];
        } else {
            throw ValidationException::withMessages(['reviewer_type' => 'Pemeriksa tidak valid.']);
        }
        if (! in_array($action, $allowed, true)) {
            throw ValidationException::withMessages(['action' => 'Aksi pemeriksaan tidak valid.']);
        }
        if ($action === 'revision_requested' && blank($comments)) {
            throw ValidationException::withMessages(['comments' => 'Catatan revisi wajib diisi.']);
        }

        $review = PkpaPortfolioReview::create([
            'pkpa_rotation_portfolio_id' => $portfolio->id,
            'reviewer_type' => $reviewerType,
            'reviewer_core_user_id' => $actor->core_user_id,
            'action' => $action,
            'comments' => $comments,
            'privacy_findings' => $reviewerType === 'field' ? $this->portfolioPrivacyFindings($portfolio) : [],
            'reviewed_at' => now(),
        ]);

        $updates = [];
        if ($reviewerType === 'field' && $action === 'verify') {
            if ($review->privacy_findings !== []) {
                throw ValidationException::withMessages(['privacy' => 'Masih ada temuan privasi pasien.']);
            }
            $updates = ['status' => 'field_verified', 'field_verified_at' => now(), 'field_verified_by_core_user_id' => $actor->core_user_id];
        } elseif ($reviewerType === 'field') {
            $updates = ['status' => 'field_revision_requested'];
        } elseif ($reviewerType === 'internal' && $action === 'approve') {
            $updates = ['status' => 'approved', 'internal_approved_at' => now(), 'internal_approved_by_core_user_id' => $actor->core_user_id];
        } else {
            $updates = ['status' => 'internal_revision_requested'];
        }

        $portfolio->update($updates);
        $this->syncProgress($portfolio->fresh());

        return $review;
    }

    public function submitToInternal(PkpaRotationPortfolio $portfolio, User $actor): PkpaRotationPortfolio
    {
        $this->ensureStudentOwns($portfolio, $actor);
        if ($portfolio->status !== 'field_verified') {
            throw ValidationException::withMessages(['status' => 'Portofolio harus diverifikasi Preseptor terlebih dahulu.']);
        }
        $portfolio->update(['status' => 'submitted_to_internal_supervisor']);

        return $portfolio->fresh();
    }

    public function reopen(PkpaRotationPortfolio $portfolio, string $reason, User $actor): PkpaRotationPortfolio
    {
        if (! $actor->hasAnyRole(['admin', 'koordinator_kp']) || blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Pembukaan ulang wajib dilakukan Admin/Koordinator dengan alasan.']);
        }
        PkpaPortfolioReview::create([
            'pkpa_rotation_portfolio_id' => $portfolio->id,
            'reviewer_type' => 'coordinator',
            'reviewer_core_user_id' => $actor->core_user_id,
            'action' => 'reopen',
            'comments' => $reason,
            'reviewed_at' => now(),
        ]);
        $portfolio->update(['status' => 'in_progress', 'locked_at' => null, 'locked_by_core_user_id' => null]);

        return $portfolio->fresh();
    }

    public function publish(PkpaRotationPortfolio $portfolio, User $actor): PkpaPortfolioPublication
    {
        if (! $actor->hasAnyRole(['admin', 'koordinator_kp']) || $portfolio->status !== 'approved') {
            throw ValidationException::withMessages(['portfolio' => 'Penerbitan hanya dapat dilakukan Admin/Koordinator setelah portofolio disetujui.']);
        }

        return DB::transaction(function () use ($portfolio, $actor) {
            $portfolio->update([
                'status' => 'published',
                'locked_at' => now(),
                'locked_by_core_user_id' => $actor->core_user_id,
                'published_at' => now(),
                'published_by_core_user_id' => $actor->core_user_id,
            ]);

            return PkpaPortfolioPublication::create([
                'pkpa_rotation_portfolio_id' => $portfolio->id,
                'publication_number' => ((int) $portfolio->publications()->max('publication_number')) + 1,
                'status' => 'published',
                'publication_snapshot' => $this->publicationSnapshot($portfolio->fresh()),
                'published_at' => now(),
                'published_by_core_user_id' => $actor->core_user_id,
            ]);
        });
    }

    public function export(PkpaRotationPortfolio $portfolio, string $format, User $actor): PkpaPortfolioExportVersion
    {
        if (! in_array($format, ['docx', 'pdf'], true)) {
            throw ValidationException::withMessages(['format' => 'Format unduhan wajib DOCX atau PDF.']);
        }
        if (! $this->canAccess($portfolio, $actor)) {
            throw ValidationException::withMessages(['authorization' => 'Tidak berwenang mengakses portofolio.']);
        }

        $publication = $portfolio->publications()->latest('publication_number')->first();
        if ($publication && $existing = $portfolio->exportVersions()->where('pkpa_portfolio_publication_id', $publication->id)->where('output_format', $format)->first()) {
            return $existing;
        }

        $version = ((int) $portfolio->exportVersions()->max('version_number')) + 1;
        $bytes = $format === 'docx' ? $this->docx($portfolio) : $this->pdf($portfolio);
        $path = 'pkpa-portfolios/'.$portfolio->id.'/exports/v'.$version.'-'.str()->uuid().'.'.$format;
        Storage::disk('local')->put($path, $bytes);

        return PkpaPortfolioExportVersion::create([
            'pkpa_rotation_portfolio_id' => $portfolio->id,
            'pkpa_portfolio_publication_id' => $publication?->id,
            'version_number' => $version,
            'output_format' => $format,
            'status' => $publication ? 'published_snapshot' : 'generated',
            'disk' => 'local',
            'path' => $path,
            'original_filename' => 'portofolio-pkpa-'.$portfolio->id.'.'.$format,
            'stored_filename' => basename($path),
            'mime_type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => strlen($bytes),
            'checksum' => hash('sha256', $bytes),
            'metadata' => ['label' => $publication ? 'Unduhan versi terbit' : 'Draf unduhan internal'],
            'generated_at' => now(),
            'generated_by_core_user_id' => $actor->core_user_id,
        ]);
    }

    public function canAccess(PkpaRotationPortfolio $portfolio, User $user): bool
    {
        if ($user->hasAnyRole(['admin', 'koordinator_kp'])) {
            return true;
        }
        if ($user->hasRole('mahasiswa') && (string) $portfolio->rotationRun?->student_core_user_id === (string) $user->core_user_id) {
            return true;
        }
        if ($user->hasRole('pembimbing_lapangan') && $this->isSupervisor($portfolio, 'field', $user)) {
            return true;
        }
        if ($user->hasRole('pembimbing_dalam') && $this->isSupervisor($portfolio, 'internal', $user)) {
            return true;
        }

        return false;
    }

    public function completeness(PkpaRotationPortfolio $portfolio): array
    {
        $portfolio->loadMissing(['template.sections', 'sectionRecords', 'caseReports', 'weeklyReflections', 'selfAssessments', 'documentationItems', 'rotationRun.logbookEntries', 'rotationRun.attendanceRecords', 'rotationRun.competencyRecords', 'rotationRun.specialTasks', 'rotationRun.rotationReport', 'rotationRun.gradeResults', 'reviews']);
        $blocking = [];
        if (! $portfolio->integrity_acknowledged_at) {
            $blocking[] = 'Pakta integritas belum disetujui.';
        }
        if ($portfolio->rotationRun->logbookEntries->whereIn('status', ['submitted', 'field_approved', 'internal_reviewed', 'approved'])->isEmpty()) {
            $blocking[] = 'Logbook rotasi belum tersedia.';
        }
        if ($portfolio->rotationRun->attendanceRecords->isEmpty()) {
            $blocking[] = 'Presensi rotasi belum tersedia.';
        }
        if ($portfolio->rotationRun->competencyRecords->isEmpty()) {
            $blocking[] = 'Kompetensi wajib belum tersedia.';
        }
        if ($portfolio->caseReports->where('status', 'completed')->count() < 1) {
            $blocking[] = 'Minimal satu studi kasus wajib lengkap.';
        }
        if ($portfolio->weeklyReflections->where('status', 'completed')->count() < $this->requiredReflectionCount($portfolio->rotationRun)) {
            $blocking[] = 'Refleksi mingguan belum memenuhi durasi rotasi.';
        }
        if ($portfolio->selfAssessments->where('status', 'completed')->isEmpty()) {
            $blocking[] = 'Penilaian Diri belum diisi.';
        }
        if ($portfolio->documentationItems->whereIn('status', ['submitted', 'verified'])->isEmpty()) {
            $blocking[] = 'Dokumentasi kegiatan belum tersedia.';
        }
        if ($portfolio->reviews->where('action', 'revision_requested')->where('created_at', '>', $portfolio->updated_at)->isNotEmpty()) {
            $blocking[] = 'Masih ada revisi terbuka.';
        }

        return [
            'ready_to_submit' => $blocking === [],
            'blocking' => $blocking,
            'counts' => [
                'sections' => $portfolio->sectionRecords->count(),
                'cases' => $portfolio->caseReports->where('status', 'completed')->count(),
                'reflections' => $portfolio->weeklyReflections->where('status', 'completed')->count(),
                'documentation' => $portfolio->documentationItems->whereIn('status', ['submitted', 'verified'])->count(),
            ],
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function syncProgress(PkpaRotationPortfolio $portfolio): PkpaRotationPortfolio
    {
        $portfolio->update(['progress_snapshot' => $this->completeness($portfolio)]);

        return $portfolio->fresh();
    }

    private function identitySnapshot(PkpaRotationRun $run): array
    {
        return [
            'student_name' => $run->enrollment?->student_name_snapshot,
            'student_number' => $run->enrollment?->student_number,
            'student_email' => $run->enrollment?->student_email_snapshot,
            'core_user_id' => $run->student_core_user_id,
            'program' => $run->program?->name,
            'academic_year' => $run->program?->academic_year,
            'group' => $run->enrollment?->activeGroupMembership?->group?->name,
        ];
    }

    private function placementSnapshot(PkpaRotationRun $run): array
    {
        $internal = $run->activeSupervisor('internal') ?? $run->supervisorHistories->firstWhere('supervisor_type', 'internal');
        $field = $run->activeSupervisor('field') ?? $run->supervisorHistories->firstWhere('supervisor_type', 'field');

        return [
            'practice_domain' => $run->practiceDomain?->name,
            'practice_site' => $run->practiceSite?->name,
            'address' => $run->practiceSite?->address,
            'start_date' => $run->scheduled_start_date?->toDateString(),
            'end_date' => $run->scheduled_end_date?->toDateString(),
            'internal_supervisor' => $internal?->name_snapshot,
            'internal_supervisor_core_user_id' => $internal?->core_user_id,
            'field_supervisor' => $field?->name_snapshot,
            'field_supervisor_core_user_id' => $field?->core_user_id,
        ];
    }

    private function autoSectionStatus(string $sourceType, PkpaRotationRun $run): string
    {
        return match ($sourceType) {
            'auto_identity', 'auto_placement' => 'completed',
            'auto_attendance' => $run->attendanceRecords()->exists() ? 'completed' : 'pending',
            'auto_logbook' => $run->logbookEntries()->exists() ? 'completed' : 'pending',
            'auto_competency' => $run->competencyRecords()->exists() ? 'completed' : 'pending',
            'auto_special_task' => $run->specialTasks()->exists() ? 'completed' : 'optional',
            'auto_rotation_report' => $run->rotationReport()->exists() ? 'completed' : 'optional',
            'auto_assessment' => $run->gradeResults()->exists() ? 'completed' : 'pending',
            default => 'pending',
        };
    }

    private function sourceRefs(string $sourceType, PkpaRotationRun $run): array
    {
        return match ($sourceType) {
            'auto_identity' => ['pkpa_enrollment_id' => $run->pkpa_enrollment_id],
            'auto_placement' => ['pkpa_rotation_run_id' => $run->id, 'published_assignment_id' => $run->current_published_assignment_id],
            'auto_attendance' => ['attendance_record_ids' => $run->attendanceRecords()->pluck('id')->all()],
            'auto_logbook' => ['logbook_entry_ids' => $run->logbookEntries()->pluck('id')->all()],
            'auto_competency' => ['competency_record_ids' => $run->competencyRecords()->pluck('id')->all()],
            'auto_special_task' => ['special_task_ids' => $run->specialTasks()->pluck('id')->all()],
            'auto_rotation_report' => ['rotation_report_id' => $run->rotationReport?->id],
            'auto_assessment' => ['grade_result_ids' => $run->gradeResults()->pluck('id')->all()],
            default => [],
        };
    }

    private function patientPrivacyWarnings(array $data): array
    {
        $warnings = [];
        $text = collect($data)->flatten()->filter(fn ($value) => is_scalar($value))->implode(' ');
        foreach (self::PATIENT_IDENTIFIER_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                $warnings[] = 'Input terindikasi memuat identitas langsung pasien.';
                break;
            }
        }
        if (! empty($data['patient_name']) || ! empty($data['medical_record_number']) || ! empty($data['patient_address']) || ! empty($data['patient_phone'])) {
            $warnings[] = 'Nama pasien, nomor rekam medis, alamat, dan kontak pasien dilarang.';
        }

        return array_values(array_unique($warnings));
    }

    private function portfolioPrivacyFindings(PkpaRotationPortfolio $portfolio): array
    {
        return $portfolio->caseReports->flatMap(fn ($case) => $this->patientPrivacyWarnings($case->toArray()))->unique()->values()->all();
    }

    private function requiredReflectionCount(PkpaRotationRun $run): int
    {
        if (! $run->scheduled_start_date || ! $run->scheduled_end_date) {
            return 1;
        }

        return max(1, (int) ceil($run->scheduled_start_date->diffInDays($run->scheduled_end_date) / 7));
    }

    private function publicationSnapshot(PkpaRotationPortfolio $portfolio): array
    {
        return [
            'identity' => $portfolio->identity_snapshot,
            'placement' => $portfolio->placement_snapshot,
            'progress' => $portfolio->progress_snapshot,
            'template' => $portfolio->template?->only(['code', 'name', 'version_number']),
            'published_at' => now()->toIso8601String(),
        ];
    }

    private function docx(PkpaRotationPortfolio $portfolio): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pkpa-portfolio-docx-');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $body = collect($this->exportLines($portfolio))->map(fn ($line) => '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($line, ENT_XML1, 'UTF-8').'</w:t></w:r></w:p>')->implode('');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/></w:sectPr></w:body></w:document>');
        $zip->close();
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function pdf(PkpaRotationPortfolio $portfolio): string
    {
        return SimplePdfReport::table('Portofolio Digital PKPA', [
            'Mahasiswa' => data_get($portfolio->identity_snapshot, 'student_name'),
            'NIM' => data_get($portfolio->identity_snapshot, 'student_number'),
            'Wahana' => data_get($portfolio->placement_snapshot, 'practice_domain'),
            'Status' => $portfolio->statusLabel(),
        ], ['Bagian', 'Ringkasan'], collect($this->exportLines($portfolio))->map(fn ($line, $i) => [$i + 1, $line])->all());
    }

    private function exportLines(PkpaRotationPortfolio $portfolio): array
    {
        $portfolio->loadMissing(['template.sections', 'caseReports', 'weeklyReflections', 'selfAssessments', 'documentationItems']);
        return array_merge([
            'PORTOFOLIO DIGITAL PKPA',
            'Label: Dokumen internal MY PKPA'.($portfolio->status === 'published' ? ' - Diterbitkan' : ' - Draf internal'),
            'Mahasiswa: '.data_get($portfolio->identity_snapshot, 'student_name').' ('.data_get($portfolio->identity_snapshot, 'student_number').')',
            'Program: '.data_get($portfolio->identity_snapshot, 'program').' / '.data_get($portfolio->identity_snapshot, 'academic_year'),
            'Wahana: '.data_get($portfolio->placement_snapshot, 'practice_domain'),
            'Tempat: '.data_get($portfolio->placement_snapshot, 'practice_site'),
            'Preseptor: '.data_get($portfolio->placement_snapshot, 'field_supervisor'),
            'Pembimbing Dalam: '.data_get($portfolio->placement_snapshot, 'internal_supervisor'),
            'Daftar Isi',
        ], $portfolio->template->sections->pluck('title')->all(), [
            'Studi kasus: '.$portfolio->caseReports->count(),
            'Refleksi mingguan: '.$portfolio->weeklyReflections->count(),
            'Penilaian Diri: '.$portfolio->selfAssessments->count(),
            'Dokumentasi kegiatan: '.$portfolio->documentationItems->count(),
            'Pakta integritas: '.($portfolio->integrity_acknowledged_at ? 'Disetujui elektronik' : 'Belum disetujui'),
            'Catatan: persetujuan elektronik bukan tanda tangan digital tersertifikasi.',
        ]);
    }

    private function ensureStudentOwns(PkpaRotationPortfolio $portfolio, User $actor): void
    {
        if (! $actor->hasRole('mahasiswa') || (string) $portfolio->rotationRun?->student_core_user_id !== (string) $actor->core_user_id) {
            throw ValidationException::withMessages(['authorization' => 'Portofolio hanya dapat diubah mahasiswa pemilik.']);
        }
    }

    private function ensureFieldSupervisorOwns(PkpaRotationPortfolio $portfolio, User $actor): void
    {
        if (! $actor->hasRole('pembimbing_lapangan') || ! $this->isSupervisor($portfolio, 'field', $actor)) {
            throw ValidationException::withMessages(['authorization' => 'Portofolio hanya dapat diperiksa Preseptor terkait.']);
        }
    }

    private function ensureInternalSupervisorOwns(PkpaRotationPortfolio $portfolio, User $actor): void
    {
        if (! $actor->hasRole('pembimbing_dalam') || ! $this->isSupervisor($portfolio, 'internal', $actor)) {
            throw ValidationException::withMessages(['authorization' => 'Portofolio hanya dapat diperiksa Pembimbing Dalam terkait.']);
        }
    }

    private function isSupervisor(PkpaRotationPortfolio $portfolio, string $type, User $actor): bool
    {
        return $portfolio->rotationRun?->supervisorHistories()
            ->where('supervisor_type', $type)
            ->where('core_user_id', $actor->core_user_id)
            ->where('status', 'active')
            ->exists() || $portfolio->rotationRun?->currentAssignment?->supervisors()
            ->where('supervisor_type', $type)
            ->where('core_user_id', $actor->core_user_id)
            ->where('status', 'assigned')
            ->exists();
    }

    private function defaultIntegrityText(): string
    {
        return 'Saya menyatakan seluruh isi portofolio PKPA ini benar, tidak memuat identitas langsung pasien, dan disusun untuk keperluan akademik internal MY PKPA. Persetujuan elektronik ini bukan tanda tangan digital tersertifikasi.';
    }
}
