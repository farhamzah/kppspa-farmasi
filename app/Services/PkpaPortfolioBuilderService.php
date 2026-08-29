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
use App\Support\PkpaApotekPortfolio;
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
        $template = $this->syncApotekTemplateSections($template, $run);

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

    public function saveSectionRecord(PkpaRotationPortfolio $portfolio, string $sectionCode, array $payload, User $actor)
    {
        $this->ensureStudentOwns($portfolio, $actor);

        $record = $portfolio->sectionRecords()
            ->where('section_code', $sectionCode)
            ->firstOrFail();

        if (! in_array($record->source_type, ['structured_form', 'attachment_list'], true)) {
            throw ValidationException::withMessages(['section' => 'Bagian portofolio ini tidak dapat diisi manual dari portal mahasiswa.']);
        }

        $cleanPayload = collect($payload)
            ->map(function ($value) {
                if (is_string($value)) {
                    return trim($value);
                }

                return $value;
            })
            ->filter(fn ($value) => ! ($value === null || $value === ''))
            ->all();

        $completed = PkpaApotekPortfolio::completed($cleanPayload, $sectionCode);

        $record->update([
            'manual_payload' => $cleanPayload,
            'status' => $completed ? 'completed' : 'pending',
            'completion_snapshot' => array_merge($record->completion_snapshot ?? [], [
                'updated_by' => $actor->core_user_id,
                'updated_at' => now()->toIso8601String(),
            ]),
            'completed_at' => $completed ? now() : null,
        ]);

        $this->syncProgress($portfolio->fresh());

        return $record->fresh();
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
        $portfolio->loadMissing(['template.sections', 'sectionRecords.templateSection', 'caseReports', 'weeklyReflections', 'selfAssessments', 'documentationItems', 'rotationRun.logbookEntries', 'rotationRun.attendanceRecords', 'rotationRun.competencyRecords', 'rotationRun.specialTasks', 'rotationRun.rotationReport', 'rotationRun.gradeResults', 'reviews']);
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

        $pendingSections = $this->pendingManualSections($portfolio);
        if ($pendingSections !== []) {
            $blocking[] = 'Bagian portofolio yang belum lengkap: '.implode(', ', $pendingSections).'.';
        }

        $completedManualSections = $portfolio->sectionRecords
            ->filter(fn ($record) => in_array($record->source_type, ['structured_form', 'attachment_list'], true) && $record->status === 'completed')
            ->count();

        return [
            'ready_to_submit' => $blocking === [],
            'blocking' => $blocking,
            'counts' => [
                'sections' => $completedManualSections,
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
            'internal_supervisor' => $internal?->display_name,
            'internal_supervisor_core_user_id' => $internal?->core_user_id,
            'field_supervisor' => $field?->display_name,
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
            'sections' => $portfolio->sectionRecords()
                ->whereNotNull('manual_payload')
                ->get()
                ->mapWithKeys(fn ($record) => [$record->section_code => $record->manual_payload])
                ->all(),
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
        $body = $this->docxBody($portfolio);
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/></w:sectPr></w:body></w:document>');
        $zip->close();
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function pdf(PkpaRotationPortfolio $portfolio): string
    {
        return SimplePdfReport::table($this->documentTitle($portfolio), [
            'Mahasiswa' => data_get($portfolio->identity_snapshot, 'student_name'),
            'NIM' => data_get($portfolio->identity_snapshot, 'student_number'),
            'Wahana' => data_get($portfolio->placement_snapshot, 'practice_domain'),
            'Status' => $portfolio->statusLabel(),
        ], ['Bagian', 'Ringkasan'], $this->exportRows($portfolio));
    }

    private function exportLines(PkpaRotationPortfolio $portfolio): array
    {
        $lines = [$this->documentTitle($portfolio)];
        foreach ($this->exportSections($portfolio) as $section) {
            $lines[] = $section['title'];
            foreach ($section['lines'] as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function exportRows(PkpaRotationPortfolio $portfolio): array
    {
        $rows = [];

        foreach ($this->exportSections($portfolio) as $section) {
            if ($section['lines'] === []) {
                $rows[] = [$section['title'], '-'];
                continue;
            }

            foreach ($section['lines'] as $index => $line) {
                $rows[] = [$index === 0 ? $section['title'] : '', trim($line) !== '' ? $line : ' '];
            }
        }

        return $rows;
    }

    private function exportSections(PkpaRotationPortfolio $portfolio): array
    {
        $portfolio->loadMissing([
            'template.sections',
            'sectionRecords.templateSection',
            'caseReports',
            'weeklyReflections',
            'selfAssessments',
            'documentationItems',
            'rotationRun.logbookEntries',
            'reviews',
        ]);

        $sections = [
            [
                'title' => 'Ringkasan Dokumen',
                'lines' => [
                    'Label: Dokumen internal MY PKPA'.($portfolio->status === 'published' ? ' - Diterbitkan' : ' - Draf internal'),
                    'Program: '.data_get($portfolio->identity_snapshot, 'program').' / '.data_get($portfolio->identity_snapshot, 'academic_year'),
                    'Mahasiswa: '.data_get($portfolio->identity_snapshot, 'student_name'),
                    'NIM: '.data_get($portfolio->identity_snapshot, 'student_number'),
                    'Wahana: '.data_get($portfolio->placement_snapshot, 'practice_domain'),
                    'Tempat PKPA: '.data_get($portfolio->placement_snapshot, 'practice_site'),
                    'Preseptor: '.data_get($portfolio->placement_snapshot, 'field_supervisor'),
                    'Pembimbing Dalam: '.data_get($portfolio->placement_snapshot, 'internal_supervisor'),
                ],
            ],
            [
                'title' => 'Identitas Mahasiswa',
                'lines' => [
                    'Nama: '.data_get($portfolio->identity_snapshot, 'student_name'),
                    'NIM: '.data_get($portfolio->identity_snapshot, 'student_number'),
                    'Email: '.data_get($portfolio->identity_snapshot, 'student_email'),
                    'Kelompok: '.(data_get($portfolio->identity_snapshot, 'group') ?: '-'),
                ],
            ],
            [
                'title' => 'Pakta Integritas',
                'lines' => [
                    $portfolio->integrity_pact_text,
                    'Status persetujuan: '.($portfolio->integrity_acknowledged_at ? 'Disetujui elektronik' : 'Belum disetujui'),
                ],
            ],
        ];

        if (PkpaApotekPortfolio::isApotekCode($portfolio->practiceDomain?->code)) {
            $sections[] = [
                'title' => 'Lembar Pengesahan',
                'lines' => $this->approvalLines($portfolio),
            ];
            $sections[] = [
                'title' => 'Visi, Misi, Tujuan, dan Sasaran',
                'lines' => $this->staticSectionLines($portfolio, 'vision_mission'),
            ];
            $sections[] = [
                'title' => 'Tata Tertib PKPA',
                'lines' => $this->staticSectionLines($portfolio, 'rules'),
            ];
        }

        $sections[] = [
            'title' => 'Daftar Isi',
            'lines' => $this->exportTableOfContents($portfolio),
        ];

        if (PkpaApotekPortfolio::isApotekCode($portfolio->practiceDomain?->code)) {
            $sections[] = [
                'title' => 'Profil Tempat PKPA',
                'lines' => $this->sectionPayloadLines($portfolio, 'site_profile'),
            ];
            $sections[] = [
                'title' => 'Logbook Harian',
                'lines' => $this->logbookLines($portfolio),
            ];
            foreach (PkpaApotekPortfolio::reportSectionCodes() as $code) {
                $sections[] = [
                    'title' => $portfolio->sectionRecords->firstWhere('section_code', $code)?->templateSection?->title
                        ?? (PkpaApotekPortfolio::sectionDefinition($code)['title'] ?? str($code)->headline()->toString()),
                    'lines' => $this->sectionPayloadLines($portfolio, $code),
                ];
            }
        }

        $sections[] = [
            'title' => 'Studi Kasus',
            'lines' => $this->caseReportLines($portfolio),
        ];
        $sections[] = [
            'title' => 'Refleksi Mingguan',
            'lines' => $this->reflectionLines($portfolio),
        ];
        $sections[] = [
            'title' => 'Self Assessment',
            'lines' => $this->selfAssessmentLines($portfolio),
        ];
        $sections[] = [
            'title' => 'Dokumentasi Kegiatan',
            'lines' => $this->documentationLines($portfolio),
        ];

        if (PkpaApotekPortfolio::isApotekCode($portfolio->practiceDomain?->code)) {
            $sections[] = [
                'title' => 'Daftar Pustaka',
                'lines' => $this->sectionPayloadLines($portfolio, 'bibliography'),
            ];
            $sections[] = [
                'title' => 'Lampiran',
                'lines' => $this->sectionPayloadLines($portfolio, 'attachments'),
            ];
        }

        $sections[] = [
            'title' => 'Status Pemeriksaan',
            'lines' => $this->reviewLines($portfolio),
        ];

        return $sections;
    }

    private function documentTitle(PkpaRotationPortfolio $portfolio): string
    {
        $domain = data_get($portfolio->placement_snapshot, 'practice_domain') ?: 'PKPA';

        return 'Portofolio PKPA '.$domain;
    }

    private function exportTableOfContents(PkpaRotationPortfolio $portfolio): array
    {
        if (PkpaApotekPortfolio::isApotekCode($portfolio->practiceDomain?->code)) {
            return [
                '1. Ringkasan Dokumen',
                '2. Identitas Mahasiswa',
                '3. Pakta Integritas',
                '4. Lembar Pengesahan',
                '5. Visi, Misi, Tujuan, dan Sasaran',
                '6. Tata Tertib PKPA',
                '7. Daftar Isi',
                '8. Profil Tempat PKPA',
                '9. Logbook Harian',
                '10. Laporan Kegiatan PKPA Apotek',
                '11. Studi Kasus',
                '12. Refleksi Mingguan',
                '13. Self Assessment',
                '14. Dokumentasi Kegiatan',
                '15. Daftar Pustaka',
                '16. Lampiran',
                '17. Status Pemeriksaan',
            ];
        }

        return [
            '1. Ringkasan Dokumen',
            '2. Daftar Isi',
            '3. Identitas Mahasiswa',
            '4. Pakta Integritas',
            '5. Studi Kasus',
            '6. Refleksi Mingguan',
            '7. Self Assessment',
            '8. Dokumentasi Kegiatan',
            '9. Status Pemeriksaan',
        ];
    }

    private function sectionPayloadLines(PkpaRotationPortfolio $portfolio, string $sectionCode): array
    {
        $record = $portfolio->sectionRecords->firstWhere('section_code', $sectionCode);
        if (! $record || blank($record->manual_payload)) {
            return ['Belum ada isi untuk bagian ini.'];
        }

        return PkpaApotekPortfolio::summaryLines($sectionCode, $record->manual_payload ?? []);
    }

    private function logbookLines(PkpaRotationPortfolio $portfolio): array
    {
        if ($portfolio->rotationRun->logbookEntries->isEmpty()) {
            return ['Logbook rotasi belum tersedia.'];
        }

        $lines = [
            'Ringkasan Logbook PKPA',
            'Nama Mahasiswa: '.data_get($portfolio->identity_snapshot, 'student_name'),
            'NIM: '.data_get($portfolio->identity_snapshot, 'student_number'),
            'Universitas: Universitas Buana Perjuangan Karawang',
            'Wahana PKPA: '.data_get($portfolio->placement_snapshot, 'practice_domain'),
            'Periode PKPA: '.trim(implode(' - ', array_filter([
                $portfolio->rotationRun->scheduled_start_date?->format('d M Y'),
                $portfolio->rotationRun->scheduled_end_date?->format('d M Y'),
            ]))),
            'Preseptor: '.(data_get($portfolio->placement_snapshot, 'field_supervisor') ?: '-'),
            'Dosen Pembimbing: '.(data_get($portfolio->placement_snapshot, 'internal_supervisor') ?: '-'),
            'Petunjuk: Logbook diisi setiap hari selama pelaksanaan PKPA dan divalidasi oleh Preseptor serta Pembimbing Dalam.',
            '',
        ];

        foreach ($portfolio->rotationRun->logbookEntries->take(10)->values() as $index => $entry) {
            $lines[] = 'Entri '.($index + 1);
            foreach (array_filter([
                'Tanggal: '.(optional($entry->entry_date)->format('d M Y') ?: '-'),
                'Topik/Kegiatan: '.($entry->title ?: '-'),
                'Uraian Aktivitas: '.($entry->activity_summary ?: '-'),
                'Kompetensi/Output Belajar: '.($entry->learning_outcomes ?: '-'),
                'Refleksi Harian: '.($entry->reflection ?: '-'),
            ]) as $line) {
                $lines[] = $line;
            }
            $lines[] = '';
        }

        return $this->trimTrailingBlankLines($lines);
    }

    private function caseReportLines(PkpaRotationPortfolio $portfolio): array
    {
        if ($portfolio->caseReports->isEmpty()) {
            return ['Belum ada studi kasus.'];
        }

        $lines = [];
        foreach ($portfolio->caseReports as $case) {
            $lines[] = 'Kasus '.$case->case_code.' - '.($case->case_date?->format('d M Y') ?: 'Tanggal belum diisi');
            foreach (array_filter([
                'Inisial pasien: '.($case->patient_initials ?: '-'),
                'Jenis kelamin: '.($case->gender ?: '-'),
                'Umur: '.($case->age ?: '-'),
                'Keluhan utama: '.($case->complaint ?: '-'),
                'Diagnosis: '.($case->diagnosis ?: '-'),
                'Riwayat pasien: '.($case->history ?: '-'),
                'Alergi: '.($case->allergy ?: '-'),
                'Penggunaan obat: '.($case->medication_use ?: '-'),
                'DRP: '.($case->drp ?: '-'),
                'Intervensi: '.($case->intervention ?: '-'),
                'Monitoring: '.($case->monitoring ?: '-'),
                'Edukasi: '.($case->education ?: '-'),
                'Kesimpulan: '.($case->conclusion ?: '-'),
                'Referensi: '.($case->references ?: '-'),
            ]) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function reflectionLines(PkpaRotationPortfolio $portfolio): array
    {
        if ($portfolio->weeklyReflections->isEmpty()) {
            return ['Belum ada refleksi mingguan.'];
        }

        $lines = [];
        foreach ($portfolio->weeklyReflections->sortBy('week_number') as $reflection) {
            $lines[] = 'Minggu '.$reflection->week_number;
            foreach (array_filter([
                'Periode: '.trim(implode(' - ', array_filter([
                    optional($reflection->period_start_date)->format('d M Y'),
                    optional($reflection->period_end_date)->format('d M Y'),
                ]))) ?: 'Periode belum diisi',
                'Unit/Kegiatan: '.($reflection->unit ?: '-'),
                'Target: '.($reflection->target ?: '-'),
                'Pencapaian: '.($reflection->achievement ?: '-'),
                'Hambatan: '.($reflection->obstacle ?: '-'),
                'Solusi: '.($reflection->solution ?: '-'),
                'Rencana Minggu Berikutnya: '.($reflection->next_plan ?: '-'),
            ]) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function selfAssessmentLines(PkpaRotationPortfolio $portfolio): array
    {
        $lines = [
            'Panduan Penilaian Diri',
            'Skala penilaian: 5 = Sangat Baik, 4 = Baik, 3 = Cukup, 2 = Kurang, 1 = Sangat Kurang.',
            '',
        ];

        if ($portfolio->selfAssessments->isEmpty()) {
            $lines[] = 'Belum ada self assessment.';

            return $lines;
        }

        foreach ($portfolio->selfAssessments->values() as $index => $assessment) {
            $lines[] = 'Aspek '.($index + 1).' - '.($assessment->aspect ?: 'Belum diisi');
            $lines[] = 'Skor: '.($assessment->score ? $assessment->score.'/5' : '-');
            foreach (array_filter([
                'Bukti/Pengalaman: '.($assessment->evidence_experience ?: '-'),
                'Kelebihan: '.($assessment->strength ?: '-'),
                'Kekurangan: '.($assessment->weakness ?: '-'),
                'Upaya Perbaikan: '.($assessment->improvement_plan ?: '-'),
                'Refleksi Akhir: '.($assessment->final_reflection ?: '-'),
            ]) as $line) {
                $lines[] = $line;
            }
            $lines[] = '';
        }

        return $this->trimTrailingBlankLines($lines);
    }

    private function documentationLines(PkpaRotationPortfolio $portfolio): array
    {
        if ($portfolio->documentationItems->isEmpty()) {
            return ['Belum ada dokumentasi kegiatan.'];
        }

        return $portfolio->documentationItems->map(function ($item) {
            return trim(implode(' | ', array_filter([
                $item->category ?: 'Dokumentasi',
                $item->activity,
                optional($item->activity_date)->format('d M Y'),
                $item->description,
            ])));
        })->values()->all();
    }

    private function reviewLines(PkpaRotationPortfolio $portfolio): array
    {
        $lines = ['Status portofolio: '.$portfolio->statusLabel()];

        foreach ($portfolio->reviews->sortBy('created_at') as $review) {
            $lines[] = trim(implode(' | ', array_filter([
                strtoupper($review->reviewer_type),
                strtoupper($review->action),
                $review->comments,
                optional($review->reviewed_at)->format('d M Y H:i'),
            ])));
        }

        if (count($lines) === 1) {
            $lines[] = 'Belum ada catatan pemeriksaan.';
        }

        return $lines;
    }

    private function docxBody(PkpaRotationPortfolio $portfolio): string
    {
        $paragraphs = [];
        $paragraphs[] = $this->docxParagraph($this->documentTitle($portfolio), 'title');
        $paragraphs[] = $this->docxParagraph($this->documentSubtitle($portfolio), 'subtitle');
        $paragraphs[] = $this->docxParagraph('Program: '.(data_get($portfolio->identity_snapshot, 'program') ?: '-'), 'cover-meta');
        $paragraphs[] = $this->docxParagraph('Mahasiswa: '.(data_get($portfolio->identity_snapshot, 'student_name') ?: '-'), 'cover-meta');
        $paragraphs[] = $this->docxParagraph('Tempat PKPA: '.(data_get($portfolio->placement_snapshot, 'practice_site') ?: '-'), 'cover-meta');
        $paragraphs[] = $this->docxParagraph('', 'pagebreak');

        foreach ($this->exportSections($portfolio) as $section) {
            $paragraphs[] = $this->docxParagraph($section['title'], 'heading');
            foreach ($section['lines'] as $line) {
                $paragraphs[] = $this->docxParagraph($line, $this->detectDocxLineStyle($line));
            }
            $paragraphs[] = $this->docxParagraph('', $this->shouldPageBreakAfterSection($section['title']) ? 'pagebreak' : 'spacer');
        }

        return implode('', $paragraphs);
    }

    private function approvalLines(PkpaRotationPortfolio $portfolio): array
    {
        return [
            'Portofolio PKPA ini disiapkan untuk proses pengesahan akademik.',
            'Program: '.data_get($portfolio->identity_snapshot, 'program'),
            'Wahana: '.(data_get($portfolio->placement_snapshot, 'practice_domain') ?: '-'),
            'Tempat PKPA: '.(data_get($portfolio->placement_snapshot, 'practice_site') ?: '-'),
            '',
            'Disusun di Karawang, '.now()->translatedFormat('d F Y'),
            '',
            'Pihak Yang Mengetahui',
            'Mahasiswa: '.data_get($portfolio->identity_snapshot, 'student_name'),
            'Paraf Mahasiswa: ____________________',
            'Preseptor: '.(data_get($portfolio->placement_snapshot, 'field_supervisor') ?: '-'),
            'Paraf Preseptor: ____________________',
            'Status Preseptor: '.($portfolio->field_verified_at ? 'Terverifikasi pada '.$portfolio->field_verified_at->format('d M Y H:i') : 'Belum verifikasi'),
            'Pembimbing Dalam: '.(data_get($portfolio->placement_snapshot, 'internal_supervisor') ?: '-'),
            'Paraf Pembimbing Dalam: ____________________',
            'Status Pembimbing Dalam: '.($portfolio->internal_approved_at ? 'Disetujui pada '.$portfolio->internal_approved_at->format('d M Y H:i') : 'Belum menyetujui'),
            '',
            'Catatan: pada portal lokal ini pengesahan masih berbentuk persetujuan elektronik internal.',
        ];
    }

    private function staticSectionLines(PkpaRotationPortfolio $portfolio, string $sectionCode): array
    {
        $section = $portfolio->template->sections->firstWhere('code', $sectionCode);
        $content = trim((string) $section?->static_content);

        return $content !== '' ? [$content] : ['Konten bagian ini mengikuti panduan resmi PKPA 2026.'];
    }

    private function docxParagraph(string $text, string $style = 'body'): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1, 'UTF-8');

        return match ($style) {
            'title' => '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="220"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="32"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'subtitle' => '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="180"/></w:pPr><w:r><w:rPr><w:i/><w:sz w:val="22"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'cover-meta' => '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="70"/></w:pPr><w:r><w:rPr><w:sz w:val="22"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'heading' => '<w:p><w:pPr><w:spacing w:before="220" w:after="120"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="26"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'subheading' => '<w:p><w:pPr><w:spacing w:before="120" w:after="80"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'meta' => '<w:p><w:pPr><w:spacing w:after="50"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="22"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'toc' => '<w:p><w:pPr><w:ind w:left="280"/><w:spacing w:after="40"/></w:pPr><w:r><w:rPr><w:sz w:val="22"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'spacer' => '<w:p><w:pPr><w:spacing w:after="120"/></w:pPr></w:p>',
            'pagebreak' => '<w:p><w:r><w:br w:type="page"/></w:r></w:p>',
            default => '<w:p><w:pPr><w:spacing w:after="70"/></w:pPr><w:r><w:rPr><w:sz w:val="22"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
        };
    }

    private function documentSubtitle(PkpaRotationPortfolio $portfolio): string
    {
        return 'Dokumen internal MY PKPA. Struktur isi mengikuti template portofolio PKPA aktif dan panduan program 2026 untuk '
            .(data_get($portfolio->placement_snapshot, 'practice_domain') ?: 'wahana PKPA').'.';
    }

    private function detectDocxLineStyle(string $line): string
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return 'spacer';
        }

        if (preg_match('/^\d+\.\s/', $trimmed) === 1) {
            return 'toc';
        }

        if (preg_match('/^(Entri \d+|Aspek \d+ -|Ringkasan Logbook PKPA|Panduan Penilaian Diri|Pihak Yang Mengetahui)$/', $trimmed) === 1) {
            return 'subheading';
        }

        if (str_contains($trimmed, ':')) {
            return 'meta';
        }

        return 'body';
    }

    private function shouldPageBreakAfterSection(string $title): bool
    {
        return in_array($title, [
            'Lembar Pengesahan',
            'Daftar Isi',
            'Logbook Harian',
            'Self Assessment',
        ], true);
    }

    private function trimTrailingBlankLines(array $lines): array
    {
        while ($lines !== [] && trim((string) end($lines)) === '') {
            array_pop($lines);
        }

        return array_values($lines);
    }

    private function pendingManualSections(PkpaRotationPortfolio $portfolio): array
    {
        if (! PkpaApotekPortfolio::isApotekCode($portfolio->practiceDomain?->code)) {
            return [];
        }

        return $portfolio->sectionRecords
            ->filter(function ($record) {
                return in_array($record->source_type, ['structured_form'], true)
                    && (bool) optional($record->templateSection)->is_required
                    && $record->status !== 'completed';
            })
            ->map(fn ($record) => $record->templateSection?->title ?? $record->section_code)
            ->values()
            ->all();
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

    private function syncApotekTemplateSections(PkpaPortfolioTemplate $template, PkpaRotationRun $run): PkpaPortfolioTemplate
    {
        if (! PkpaApotekPortfolio::isApotekCode($run->practiceDomain?->code) || $template->code !== 'PORT-APT-v1') {
            return $template;
        }

        foreach (PkpaApotekPortfolio::templateSections() as $index => $sectionConfig) {
            [$sectionCode, $title, $sourceType, $reviewerType] = array_slice($sectionConfig, 0, 4);
            $isRequired = $sectionConfig[4] ?? false;
            $staticContent = $sectionConfig[5] ?? null;
            $definition = PkpaApotekPortfolio::sectionDefinition($sectionCode);

            $template->sections()->updateOrCreate(['code' => $sectionCode], [
                'title' => $title,
                'source_type' => $sourceType,
                'reviewer_type' => $reviewerType,
                'is_required' => $isRequired,
                'minimum_items' => in_array($sourceType, ['repeatable_case', 'weekly_reflection', 'self_assessment', 'evidence_gallery'], true) ? 1 : 0,
                'sort_order' => ($index + 1) * 10,
                'requirement_rules' => [
                    'no_duplicate_existing_data' => str_starts_with($sourceType, 'auto_'),
                    'private_files' => in_array($sourceType, ['evidence_gallery', 'attachment_list'], true),
                ],
                'content_schema' => array_filter([
                    'fields' => $definition['fields'] ?? null,
                    'activity_hint' => $definition['activity_hint'] ?? null,
                ]),
                'static_content' => $sourceType === 'static_content' ? $staticContent : null,
            ]);
        }

        return $template->fresh('sections');
    }
}
