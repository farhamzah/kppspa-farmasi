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
use App\Support\PkpaApotekPortfolio;
use App\Support\PkpaHealthOfficePortfolio;
use App\Support\PkpaHospitalPortfolio;
use App\Support\PkpaIndustryPortfolio;
use App\Support\PkpaLokaPomPortfolio;
use App\Support\PkpaPbfPortfolio;
use App\Support\PkpaPortfolioTextFormatter;
use App\Support\PkpaPuskesmasPortfolio;
use App\Support\SimplePdfReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class PkpaPortfolioBuilderService
{
    private const EXPORT_GENERATOR_VERSIONS = [
        'docx' => 3,
        'pdf' => 1,
    ];

    public const PATIENT_IDENTIFIER_PATTERNS = [
        '/\b(no\.?\s*)?(rm|rekam\s*medis|medical\s*record)\b/i',
        '/\b(nik|ktp|kk|bpjs)\b/i',
        '/\b(08\d{8,12}|\+62\d{8,13})\b/',
        '/\b(jalan|jl\.|rt\s*\d+|rw\s*\d+|kelurahan|kecamatan)\b/i',
        '/\b(nama\s*pasien|pasien\s*bernama)\b/i',
    ];

    public function __construct(private readonly PkpaPortfolioTextFormatter $textFormatter) {}

    public function ensureForRun(PkpaRotationRun $run, ?User $actor = null): PkpaRotationPortfolio
    {
        $run->loadMissing([
            'program',
            'enrollment.activeGroupMembership.group',
            'practiceDomain',
            'practiceDomainOption',
            'practiceSite',
            'currentAssignment.supervisors',
            'supervisorHistories',
        ]);

        $preferredTemplateCode = match ($run->practiceDomainOption?->code) {
            'PUSKESMAS' => PkpaPuskesmasPortfolio::TEMPLATE_CODE,
            'DINKES' => PkpaHealthOfficePortfolio::TEMPLATE_CODE,
            'LOKAPOM' => PkpaLokaPomPortfolio::TEMPLATE_CODE,
            default => null,
        };
        $template = PkpaPortfolioTemplate::query()
            ->with('sections')
            ->where('practice_domain_id', $run->practice_domain_id)
            ->where('is_current', true)
            ->where('status', 'active')
            ->when($preferredTemplateCode, fn ($query) => $query->where('code', $preferredTemplateCode))
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
        $this->ensurePortfolioEditable($portfolio);
        $portfolio->update([
            'integrity_acknowledged_at' => now(),
            'integrity_acknowledged_by_core_user_id' => $actor->core_user_id,
            'status' => $portfolio->status === 'draft' ? 'in_progress' : $portfolio->status,
        ]);

        return $this->syncProgress($portfolio->fresh());
    }

    public function integritySheetData(PkpaRotationPortfolio $portfolio): array
    {
        $portfolio->loadMissing(['rotationRun.practiceSite', 'rotationRun.enrollment']);

        $studentPhone = User::query()
            ->with('student')
            ->where('core_user_id', data_get($portfolio->identity_snapshot, 'core_user_id'))
            ->first()?->student?->phone;
        $verificationUrl = $this->integrityVerificationUrl($portfolio);
        $signedAt = $portfolio->integrity_acknowledged_at;
        $city = $portfolio->rotationRun?->practiceSite?->city ?: 'Karawang';

        return [
            'title' => 'Pakta Integritas Mahasiswa PKPA',
            'student_name' => data_get($portfolio->identity_snapshot, 'student_name') ?: '-',
            'student_number' => data_get($portfolio->identity_snapshot, 'student_number') ?: '-',
            'student_email' => data_get($portfolio->identity_snapshot, 'student_email') ?: '-',
            'student_phone' => $studentPhone ?: '-',
            'practice_site' => data_get($portfolio->placement_snapshot, 'practice_site') ?: '-',
            'practice_domain' => data_get($portfolio->placement_snapshot, 'practice_domain') ?: '-',
            'practice_period' => trim(implode(' - ', array_filter([
                optional($portfolio->rotationRun?->scheduled_start_date)->format('d M Y'),
                optional($portfolio->rotationRun?->scheduled_end_date)->format('d M Y'),
            ]))) ?: '-',
            'statement_items' => $this->integrityStatementItems(),
            'city' => $city,
            'signed_date_label' => $signedAt ? $signedAt->translatedFormat('d F Y') : now()->translatedFormat('d F Y'),
            'signed_at_label' => $signedAt ? $signedAt->translatedFormat('d F Y H:i') : null,
            'acknowledged' => filled($signedAt),
            'status_label' => $signedAt ? 'Sudah disetujui secara elektronik' : 'Belum disetujui',
            'validation_code' => $this->integrityVerificationToken($portfolio),
            'verification_url' => $verificationUrl,
            'qr_markup' => $this->integrityQrMarkup($verificationUrl),
        ];
    }

    public function integrityVerificationUrl(PkpaRotationPortfolio $portfolio): string
    {
        return URL::signedRoute('student.pkpa-portfolios.integrity.verify', [
            'portfolio' => $portfolio,
            'token' => $this->integrityVerificationToken($portfolio),
        ]);
    }

    public function integrityVerificationToken(PkpaRotationPortfolio $portfolio): string
    {
        $seed = implode('|', [
            'pkpa-integrity',
            $portfolio->getKey(),
            data_get($portfolio->identity_snapshot, 'core_user_id'),
            data_get($portfolio->identity_snapshot, 'student_number'),
            optional($portfolio->integrity_acknowledged_at)->toIso8601String() ?: 'pending',
        ]);

        return 'INT-'.strtoupper(substr(hash('sha256', $seed), 0, 12));
    }

    public function matchesIntegrityToken(PkpaRotationPortfolio $portfolio, ?string $token): bool
    {
        return filled($token) && hash_equals($this->integrityVerificationToken($portfolio), (string) $token);
    }

    public function saveSectionRecord(PkpaRotationPortfolio $portfolio, string $sectionCode, array $payload, User $actor)
    {
        $this->ensureStudentOwns($portfolio, $actor);
        $this->ensurePortfolioEditable($portfolio);

        $record = $portfolio->sectionRecords()
            ->with('templateSection')
            ->where('section_code', $sectionCode)
            ->firstOrFail();

        if (! in_array($record->source_type, ['structured_form', 'attachment_list'], true)) {
            throw ValidationException::withMessages(['section' => 'Bagian portofolio ini tidak dapat diisi manual dari portal mahasiswa.']);
        }

        $cleanPayload = collect($this->textFormatter->normalize($payload))
            ->filter(fn ($value) => ! ($value === null || $value === ''))
            ->all();

        $fields = data_get($record->templateSection?->content_schema, 'fields', []);
        $completed = PkpaApotekPortfolio::isApotekCode($portfolio->practiceDomain?->code)
            ? PkpaApotekPortfolio::completed($cleanPayload, $sectionCode)
            : $this->genericSectionCompleted($cleanPayload, $fields);

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

    public function saveReportSection(PkpaRotationPortfolio $portfolio, string $sectionCode, array $data, User $actor)
    {
        $this->ensureStudentOwns($portfolio, $actor);
        $this->ensurePortfolioEditable($portfolio);
        if (! in_array($sectionCode, PkpaApotekPortfolio::reportSectionCodes(), true)) {
            throw ValidationException::withMessages(['section' => 'Bagian ini bukan laporan kegiatan PKPA.']);
        }

        $record = $portfolio->sectionRecords()->where('section_code', $sectionCode)->firstOrFail();
        $previousPayload = $record->manual_payload ?? [];
        $payload = [
            'purpose' => $this->textFormatter->normalize($data['purpose']),
            'result' => $this->textFormatter->normalize($data['result']),
        ];

        // Preserve entries created with the previous per-activity format for audit and export history.
        if (is_array($previousPayload['activity_entries'] ?? null)) {
            $payload['legacy_activity_entries'] = $previousPayload['activity_entries'];
        }

        $completed = PkpaApotekPortfolio::completed($payload, $sectionCode);
        $record->update([
            'manual_payload' => $payload,
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

    public function saveReportActivity(PkpaRotationPortfolio $portfolio, string $sectionCode, array $data, User $actor, ?string $entryId = null)
    {
        $this->ensureStudentOwns($portfolio, $actor);
        $this->ensurePortfolioEditable($portfolio);
        if (! in_array($sectionCode, PkpaApotekPortfolio::reportSectionCodes(), true)) {
            throw ValidationException::withMessages(['section' => 'Bagian ini bukan laporan kegiatan PKPA.']);
        }

        $record = $portfolio->sectionRecords()->where('section_code', $sectionCode)->firstOrFail();
        $previousPayload = $record->manual_payload ?? [];
        $entries = collect($previousPayload['activity_entries'] ?? $previousPayload['legacy_activity_entries'] ?? []);
        $entry = $this->textFormatter->normalize($data);
        if ($entryId) {
            $index = $entries->search(fn ($item) => ($item['id'] ?? null) === $entryId);
            if ($index === false) {
                throw ValidationException::withMessages(['activity' => 'Kegiatan yang akan diperbarui tidak ditemukan.']);
            }
            $entry['id'] = $entryId;
            $entries->put($index, $entry);
        } else {
            $entry['id'] = (string) str()->uuid();
            $entries->push($entry);
        }

        $payload = ['activity_entries' => PkpaApotekPortfolio::orderedActivityEntries($sectionCode, $entries->all())];
        if (filled($previousPayload['purpose'] ?? null) || filled($previousPayload['result'] ?? null)) {
            $payload['legacy_unified_report'] = [
                'purpose' => $previousPayload['purpose'] ?? '',
                'result' => $previousPayload['result'] ?? '',
            ];
        }

        $this->updateReportActivityRecord($portfolio, $record, $sectionCode, $payload, $actor);

        return $record->fresh();
    }

    public function deleteReportActivity(PkpaRotationPortfolio $portfolio, string $sectionCode, string $entryId, User $actor): void
    {
        $this->ensureStudentOwns($portfolio, $actor);
        $this->ensurePortfolioEditable($portfolio);
        $record = $portfolio->sectionRecords()->where('section_code', $sectionCode)->firstOrFail();
        $previousPayload = $record->manual_payload ?? [];
        $entries = collect($previousPayload['activity_entries'] ?? []);
        if (! $entries->contains(fn ($item) => ($item['id'] ?? null) === $entryId)) {
            throw ValidationException::withMessages(['activity' => 'Kegiatan yang akan dihapus tidak ditemukan.']);
        }

        $payload = ['activity_entries' => PkpaApotekPortfolio::orderedActivityEntries($sectionCode, $entries->reject(fn ($item) => ($item['id'] ?? null) === $entryId)->all())];
        if (isset($previousPayload['legacy_unified_report'])) {
            $payload['legacy_unified_report'] = $previousPayload['legacy_unified_report'];
        }
        $this->updateReportActivityRecord($portfolio, $record, $sectionCode, $payload, $actor);
    }

    private function updateReportActivityRecord(PkpaRotationPortfolio $portfolio, $record, string $sectionCode, array $payload, User $actor): void
    {
        $completed = PkpaApotekPortfolio::completed($payload, $sectionCode);
        $record->update([
            'manual_payload' => $payload,
            'status' => $completed ? 'completed' : 'pending',
            'completion_snapshot' => array_merge($record->completion_snapshot ?? [], [
                'updated_by' => $actor->core_user_id,
                'updated_at' => now()->toIso8601String(),
            ]),
            'completed_at' => $completed ? now() : null,
        ]);

        $this->syncProgress($portfolio->fresh());
    }

    public function saveCase(PkpaRotationPortfolio $portfolio, array $data, User $actor): PkpaPortfolioCaseReport
    {
        $this->ensureStudentOwns($portfolio, $actor);
        $this->ensurePortfolioEditable($portfolio);
        $data = $this->textFormatter->normalize($data);
        $data['drug_data'] = collect($data['drug_data'] ?? [])
            ->filter(fn (mixed $drug): bool => is_array($drug) && collect($drug)->contains(fn (mixed $value): bool => filled($value)))
            ->values()
            ->all();
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
        $this->ensurePortfolioEditable($portfolio);
        $data = $this->textFormatter->normalize($data);
        $record = $portfolio->weeklyReflections()->updateOrCreate([
            'week_number' => $data['week_number'],
        ], array_merge($data, ['status' => 'completed']));
        $this->syncProgress($portfolio->fresh());

        return $record;
    }

    public function saveSelfAssessment(PkpaRotationPortfolio $portfolio, array $data, User $actor): PkpaPortfolioSelfAssessment
    {
        $this->ensureStudentOwns($portfolio, $actor);
        $this->ensurePortfolioEditable($portfolio);
        $data = $this->textFormatter->normalize($data);
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
        $this->ensurePortfolioEditable($portfolio);
        $data = $this->textFormatter->normalize($data);
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
        $this->ensurePortfolioEditable($portfolio);
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
            $expectedStatus = 'submitted_to_field_supervisor';
        } elseif ($reviewerType === 'internal') {
            $this->ensureInternalSupervisorOwns($portfolio, $actor);
            $allowed = ['approve', 'revision_requested'];
            $expectedStatus = 'submitted_to_internal_supervisor';
        } else {
            throw ValidationException::withMessages(['reviewer_type' => 'Pemeriksa tidak valid.']);
        }
        if ($portfolio->status !== $expectedStatus) {
            throw ValidationException::withMessages([
                'status' => $reviewerType === 'field'
                    ? 'Portofolio belum dikirim ke Preseptor atau sudah selesai diperiksa.'
                    : 'Portofolio belum dikirim ke Pembimbing Dalam atau sudah selesai diperiksa.',
            ]);
        }
        if (! in_array($action, $allowed, true)) {
            throw ValidationException::withMessages(['action' => 'Aksi pemeriksaan tidak valid.']);
        }
        if ($action === 'revision_requested' && blank($comments)) {
            throw ValidationException::withMessages(['comments' => 'Catatan revisi wajib diisi.']);
        }
        if (in_array($action, ['verify', 'approve'], true)) {
            $progress = $this->completeness($portfolio->fresh());
            if (! $progress['ready_to_submit']) {
                throw ValidationException::withMessages([
                    'portfolio' => 'Portofolio belum lengkap dan belum dapat disetujui. Minta revisi kepada mahasiswa. '.implode(' ', $progress['blocking']),
                ]);
            }
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

        $portfolio->update([
            'submitted_at' => null,
            'submitted_by_core_user_id' => null,
            'field_verified_at' => null,
            'field_verified_by_core_user_id' => null,
            'internal_approved_at' => null,
            'internal_approved_by_core_user_id' => null,
        ]);

        return $this->syncProgress($portfolio->fresh());
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
        if ($publication) {
            $existing = $portfolio->exportVersions()
                ->where('pkpa_portfolio_publication_id', $publication->id)
                ->where('output_format', $format)
                ->latest('version_number')
                ->get()
                ->first(fn (PkpaPortfolioExportVersion $export) => $format === 'pdf'
                    || (int) data_get($export->metadata, 'generator_version') === self::EXPORT_GENERATOR_VERSIONS[$format]);

            if ($existing) {
                return $existing;
            }
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
            'metadata' => [
                'label' => $publication ? 'Unduhan versi terbit' : 'Draf unduhan internal',
                'generator_version' => self::EXPORT_GENERATOR_VERSIONS[$format],
            ],
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
        $checks = [];
        if (! $portfolio->integrity_acknowledged_at) {
            $blocking[] = 'Pakta integritas belum disetujui.';
        }
        $logbookTotal = $portfolio->rotationRun->logbookEntries->count();
        $logbookApproved = $portfolio->rotationRun->logbookEntries->where('status', 'internal_approved')->count();
        if ($logbookApproved === 0) {
            $blocking[] = $logbookTotal > 0
                ? "Logbook belum mendapat validasi akhir Pembimbing Dalam ({$logbookApproved} dari {$logbookTotal} disetujui)."
                : 'Belum ada logbook yang dikirim.';
        }
        $checks[] = [
            'key' => 'logbook',
            'title' => 'Logbook',
            'status' => $logbookApproved > 0 ? 'complete' : 'action_required',
            'summary' => $logbookTotal === 0
                ? 'Belum ada logbook yang dikirim.'
                : ($logbookApproved > 0
                    ? "{$logbookApproved} dari {$logbookTotal} logbook telah disetujui Pembimbing Dalam."
                    : "{$logbookTotal} logbook sudah tercatat, tetapi belum ada yang disetujui Pembimbing Dalam."),
            'owner' => $logbookTotal === 0 ? 'Mahasiswa' : 'Pembimbing Dalam',
            'action' => $logbookTotal === 0
                ? 'Buat dan kirim logbook kegiatan.'
                : ($logbookApproved > 0 ? 'Tidak ada tindakan.' : 'Pembimbing Dalam perlu membuka antrean logbook dan memberikan validasi akhir.'),
        ];
        if ($portfolio->rotationRun->attendanceRecords->isEmpty()) {
            $blocking[] = 'Presensi rotasi belum tersedia.';
        }
        if ($portfolio->rotationRun->competencyRecords->isEmpty()) {
            $blocking[] = 'Daftar kompetensi belum disiapkan oleh pengelola.';
        }
        $competencyTotal = $portfolio->rotationRun->competencyRecords->count();
        $competencyVerified = $portfolio->rotationRun->competencyRecords->where('status', 'verified')->count();
        $checks[] = [
            'key' => 'competency',
            'title' => 'Kompetensi',
            'status' => $competencyTotal > 0 ? 'complete' : 'setup_required',
            'summary' => $competencyTotal > 0
                ? "{$competencyVerified} dari {$competencyTotal} kompetensi telah terverifikasi."
                : 'Data kompetensi belum disiapkan untuk rotasi ini.',
            'owner' => $competencyTotal > 0 ? 'Tidak ada' : 'Koordinator PKPA',
            'action' => $competencyTotal > 0
                ? 'Tidak ada tindakan.'
                : 'Koordinator perlu menyiapkan daftar kompetensi. Mahasiswa tidak perlu mengulang isian portofolio.',
        ];
        if ($portfolio->caseReports->where('status', 'completed')->count() < 1) {
            $blocking[] = 'Minimal satu studi kasus wajib lengkap.';
        }
        $reflectionCompleted = $portfolio->weeklyReflections->where('status', 'completed')->count();
        $reflectionRequired = $this->requiredReflectionCount($portfolio->rotationRun);
        if ($reflectionCompleted < $reflectionRequired) {
            $blocking[] = "Refleksi mingguan belum lengkap ({$reflectionCompleted} dari {$reflectionRequired} selesai).";
        }
        $checks[] = [
            'key' => 'reflection',
            'title' => 'Refleksi mingguan',
            'status' => $reflectionCompleted >= $reflectionRequired ? 'complete' : 'action_required',
            'summary' => "{$reflectionCompleted} dari {$reflectionRequired} refleksi telah selesai.",
            'owner' => $reflectionCompleted >= $reflectionRequired ? 'Tidak ada' : 'Mahasiswa',
            'action' => $reflectionCompleted >= $reflectionRequired
                ? 'Tidak ada tindakan.'
                : 'Tambahkan refleksi untuk minggu yang belum terisi.',
        ];
        if ($portfolio->selfAssessments->where('status', 'completed')->isEmpty()) {
            $blocking[] = 'Penilaian Diri belum diisi.';
        }
        if ($portfolio->documentationItems->whereIn('status', ['submitted', 'verified'])->isEmpty()) {
            $blocking[] = 'Dokumentasi kegiatan belum tersedia.';
        }
        if (PkpaHospitalPortfolio::isHospitalCode($portfolio->practiceDomain?->code)
            && $portfolio->sectionRecords->whereIn('section_code', PkpaHospitalPortfolio::reportSectionCodes())->where('status', 'completed')->isEmpty()) {
            $blocking[] = 'Minimal satu laporan kegiatan Rumah Sakit wajib lengkap.';
        }
        if (PkpaIndustryPortfolio::isIndustryCode($portfolio->practiceDomain?->code)
            && $portfolio->sectionRecords->whereIn('section_code', PkpaIndustryPortfolio::reportSectionCodes())->where('status', 'completed')->isEmpty()) {
            $blocking[] = 'Minimal satu laporan kegiatan Industri Farmasi wajib lengkap.';
        }
        if (PkpaPbfPortfolio::isPbfCode($portfolio->practiceDomain?->code)
            && $portfolio->sectionRecords->whereIn('section_code', PkpaPbfPortfolio::reportSectionCodes())->where('status', 'completed')->isEmpty()) {
            $blocking[] = 'Minimal satu laporan kegiatan PBF wajib lengkap.';
        }
        if ($portfolio->template?->code === PkpaPuskesmasPortfolio::TEMPLATE_CODE
            && $portfolio->sectionRecords->whereIn('section_code', PkpaPuskesmasPortfolio::reportSectionCodes())->where('status', 'completed')->isEmpty()) {
            $blocking[] = 'Minimal satu laporan kegiatan Puskesmas wajib lengkap.';
        }
        if ($portfolio->template?->code === PkpaHealthOfficePortfolio::TEMPLATE_CODE
            && $portfolio->sectionRecords->whereIn('section_code', PkpaHealthOfficePortfolio::reportSectionCodes())->where('status', 'completed')->isEmpty()) {
            $blocking[] = 'Minimal satu laporan kegiatan Dinas Kesehatan wajib lengkap.';
        }
        if ($portfolio->template?->code === PkpaLokaPomPortfolio::TEMPLATE_CODE
            && $portfolio->sectionRecords->whereIn('section_code', PkpaLokaPomPortfolio::reportSectionCodes())->where('status', 'completed')->isEmpty()) {
            $blocking[] = 'Minimal satu laporan kegiatan Loka POM wajib lengkap.';
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
            'checks' => $checks,
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
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/></Relationships>');
        $zip->addFromString('word/styles.xml', $this->docxStylesXml());
        $zip->addFromString('word/settings.xml', $this->docxSettingsXml());
        $zip->addFromString('word/footer1.xml', $this->docxFooterXml());
        $body = $this->docxBody($portfolio);
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'.$body.'<w:sectPr><w:footerReference w:type="default" r:id="rId3"/><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="2268" w:right="1701" w:bottom="1701" w:left="2268" w:header="708" w:footer="708" w:gutter="0"/><w:docGrid w:linePitch="360"/></w:sectPr></w:body></w:document>');
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
                $physicalLines = preg_split('/\R/u', $this->textFormatter->normalize((string) $line)) ?: [''];
                foreach ($physicalLines as $lineIndex => $physicalLine) {
                    $rows[] = [
                        $index === 0 && $lineIndex === 0 ? $section['title'] : '',
                        trim($physicalLine) !== '' ? $physicalLine : ' ',
                    ];
                }
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
                'lines' => $this->integrityDocumentLines($portfolio),
            ],
        ];

        $isApotek = PkpaApotekPortfolio::isApotekCode($portfolio->practiceDomain?->code);
        $isPbf = PkpaPbfPortfolio::isPbfCode($portfolio->practiceDomain?->code);
        $isHospital = PkpaHospitalPortfolio::isHospitalCode($portfolio->practiceDomain?->code);
        $isIndustry = PkpaIndustryPortfolio::isIndustryCode($portfolio->practiceDomain?->code);
        $isPuskesmas = $portfolio->template?->code === PkpaPuskesmasPortfolio::TEMPLATE_CODE;
        $isHealthOffice = $portfolio->template?->code === PkpaHealthOfficePortfolio::TEMPLATE_CODE;
        $isLokaPom = $portfolio->template?->code === PkpaLokaPomPortfolio::TEMPLATE_CODE;

        if ($isApotek || $isPbf || $isHospital || $isIndustry || $isPuskesmas || $isHealthOffice || $isLokaPom) {
            $sections[] = [
                'title' => 'Lembar Pengesahan',
                'lines' => $this->approvalLines($portfolio),
            ];
            $sections[] = [
                'title' => $isPbf ? 'Visi dan Misi' : 'Visi, Misi, Tujuan, dan Sasaran',
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

        if ($isApotek) {
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
        } elseif ($isPbf) {
            $sections[] = [
                'title' => 'Profil Tempat PKPA PBF',
                'lines' => $this->sectionPayloadLines($portfolio, 'site_profile'),
            ];
            $sections[] = [
                'title' => 'Logbook Harian',
                'lines' => $this->logbookLines($portfolio),
            ];
            foreach (PkpaPbfPortfolio::reportSectionCodes() as $code) {
                $sections[] = [
                    'title' => $portfolio->sectionRecords->firstWhere('section_code', $code)?->templateSection?->title
                        ?? (PkpaPbfPortfolio::sectionDefinition($code)['title'] ?? str($code)->headline()->toString()),
                    'lines' => $this->sectionPayloadLines($portfolio, $code),
                ];
            }
        } elseif ($isHospital) {
            $sections[] = [
                'title' => 'Profil Tempat PKPA Rumah Sakit',
                'lines' => $this->sectionPayloadLines($portfolio, 'site_profile'),
            ];
            $sections[] = [
                'title' => 'Logbook Harian',
                'lines' => $this->logbookLines($portfolio),
            ];
            foreach (PkpaHospitalPortfolio::reportSectionCodes() as $code) {
                $sections[] = [
                    'title' => $portfolio->sectionRecords->firstWhere('section_code', $code)?->templateSection?->title
                        ?? (PkpaHospitalPortfolio::sectionDefinition($code)['title'] ?? str($code)->headline()->toString()),
                    'lines' => $this->sectionPayloadLines($portfolio, $code),
                ];
            }
        } elseif ($isIndustry) {
            $sections[] = [
                'title' => 'Profil Tempat PKPA Industri Farmasi',
                'lines' => $this->sectionPayloadLines($portfolio, 'site_profile'),
            ];
            $sections[] = [
                'title' => 'Logbook Harian',
                'lines' => $this->logbookLines($portfolio),
            ];
            foreach (PkpaIndustryPortfolio::reportSectionCodes() as $code) {
                $sections[] = [
                    'title' => $portfolio->sectionRecords->firstWhere('section_code', $code)?->templateSection?->title
                        ?? (PkpaIndustryPortfolio::sectionDefinition($code)['title'] ?? str($code)->headline()->toString()),
                    'lines' => $this->sectionPayloadLines($portfolio, $code),
                ];
            }
        } elseif ($isPuskesmas) {
            $sections[] = ['title' => 'Profil Tempat PKPA Puskesmas', 'lines' => $this->sectionPayloadLines($portfolio, 'site_profile')];
            $sections[] = ['title' => 'Logbook Harian', 'lines' => $this->logbookLines($portfolio)];
            foreach (PkpaPuskesmasPortfolio::reportSectionCodes() as $code) {
                $sections[] = [
                    'title' => PkpaPuskesmasPortfolio::sectionDefinition($code)['title'],
                    'lines' => $this->sectionPayloadLines($portfolio, $code),
                ];
            }
        } elseif ($isHealthOffice) {
            $sections[] = ['title' => 'Profil Tempat PKPA Dinas Kesehatan', 'lines' => $this->sectionPayloadLines($portfolio, 'site_profile')];
            $sections[] = ['title' => 'Logbook Harian', 'lines' => $this->logbookLines($portfolio)];
            foreach (PkpaHealthOfficePortfolio::reportSectionCodes() as $code) {
                $sections[] = ['title' => PkpaHealthOfficePortfolio::sectionDefinition($code)['title'], 'lines' => $this->sectionPayloadLines($portfolio, $code)];
            }
        } elseif ($isLokaPom) {
            $sections[] = ['title' => 'Profil Tempat PKPA Loka POM', 'lines' => $this->sectionPayloadLines($portfolio, 'site_profile')];
            $sections[] = ['title' => 'Logbook Harian', 'lines' => $this->logbookLines($portfolio)];
            foreach (PkpaLokaPomPortfolio::reportSectionCodes() as $code) {
                $sections[] = ['title' => PkpaLokaPomPortfolio::sectionDefinition($code)['title'], 'lines' => $this->sectionPayloadLines($portfolio, $code)];
            }
        } else {
            foreach ($portfolio->template->sections->whereIn('source_type', ['structured_form', 'attachment_list']) as $section) {
                $sections[] = [
                    'title' => $section->title,
                    'lines' => $this->sectionPayloadLines($portfolio, $section->code),
                ];
            }
        }

        $sections[] = [
            'title' => $isPbf ? 'Studi Kasus PBF' : 'Studi Kasus',
            'lines' => $this->caseReportLines($portfolio),
        ];
        $sections[] = [
            'title' => 'Refleksi Mingguan',
            'lines' => $this->reflectionLines($portfolio),
        ];
        $sections[] = [
            'title' => $isPbf ? 'Self Assessment PBF' : 'Self Assessment',
            'lines' => $this->selfAssessmentLines($portfolio),
        ];
        $sections[] = [
            'title' => $isPbf ? 'Dokumentasi Kegiatan PBF' : 'Dokumentasi Kegiatan',
            'lines' => $this->documentationLines($portfolio),
        ];

        if ($isApotek || $isPbf || $isHospital || $isIndustry || $isPuskesmas || $isHealthOffice || $isLokaPom) {
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

        if (PkpaPbfPortfolio::isPbfCode($portfolio->practiceDomain?->code)) {
            $titles = collect([
                'Ringkasan Dokumen', 'Identitas Mahasiswa', 'Pakta Integritas', 'Lembar Pengesahan',
                'Visi dan Misi', 'Tata Tertib PKPA', 'Daftar Isi', 'Profil Tempat PKPA PBF', 'Logbook Harian',
            ])->merge(
                collect(PkpaPbfPortfolio::reportSectionCodes())
                    ->map(fn ($code) => PkpaPbfPortfolio::sectionDefinition($code)['title'])
            )->merge([
                'Studi Kasus PBF', 'Refleksi Mingguan', 'Self Assessment PBF',
                'Dokumentasi Kegiatan PBF', 'Daftar Pustaka', 'Lampiran', 'Status Pemeriksaan',
            ])->values();

            return $titles->map(fn ($title, $index) => ($index + 1).'. '.$title)->all();
        }

        if (PkpaHospitalPortfolio::isHospitalCode($portfolio->practiceDomain?->code)) {
            $titles = collect([
                'Ringkasan Dokumen',
                'Identitas Mahasiswa',
                'Pakta Integritas',
                'Lembar Pengesahan',
                'Visi, Misi, Tujuan, dan Sasaran',
                'Tata Tertib PKPA',
                'Daftar Isi',
                'Profil Tempat PKPA Rumah Sakit',
                'Logbook Harian',
            ])->merge(
                collect(PkpaHospitalPortfolio::reportSectionCodes())
                    ->map(fn ($code) => PkpaHospitalPortfolio::sectionDefinition($code)['title'])
            )->merge([
                'Studi Kasus',
                'Refleksi Mingguan',
                'Self Assessment',
                'Dokumentasi Kegiatan',
                'Daftar Pustaka',
                'Lampiran',
                'Status Pemeriksaan',
            ])->values();

            return $titles->map(fn ($title, $index) => ($index + 1).'. '.$title)->all();
        }

        if (PkpaIndustryPortfolio::isIndustryCode($portfolio->practiceDomain?->code)) {
            $titles = collect([
                'Ringkasan Dokumen', 'Identitas Mahasiswa', 'Pakta Integritas', 'Lembar Pengesahan',
                'Visi, Misi, Tujuan, dan Sasaran', 'Tata Tertib PKPA', 'Daftar Isi',
                'Profil Tempat PKPA Industri Farmasi', 'Logbook Harian',
            ])->merge(
                collect(PkpaIndustryPortfolio::reportSectionCodes())
                    ->map(fn ($code) => PkpaIndustryPortfolio::sectionDefinition($code)['title'])
            )->merge([
                'Studi Kasus Industri Farmasi', 'Refleksi Mingguan', 'Self Assessment',
                'Dokumentasi Kegiatan', 'Daftar Pustaka', 'Lampiran', 'Status Pemeriksaan',
            ])->values();

            return $titles->map(fn ($title, $index) => ($index + 1).'. '.$title)->all();
        }

        if ($portfolio->template?->code === PkpaPuskesmasPortfolio::TEMPLATE_CODE) {
            $titles = collect([
                'Ringkasan Dokumen', 'Identitas Mahasiswa', 'Pakta Integritas', 'Lembar Pengesahan',
                'Visi, Misi, Tujuan, dan Sasaran', 'Tata Tertib PKPA', 'Daftar Isi',
                'Profil Tempat PKPA Puskesmas', 'Logbook Harian',
            ])->merge(collect(PkpaPuskesmasPortfolio::reportSectionCodes())->map(fn ($code) => PkpaPuskesmasPortfolio::sectionDefinition($code)['title']))
                ->merge(['Studi Kasus', 'Refleksi Mingguan', 'Self Assessment', 'Dokumentasi Kegiatan', 'Daftar Pustaka', 'Lampiran', 'Status Pemeriksaan'])->values();

            return $titles->map(fn ($title, $index) => ($index + 1).'. '.$title)->all();
        }

        if ($portfolio->template?->code === PkpaHealthOfficePortfolio::TEMPLATE_CODE) {
            $titles = collect(['Ringkasan Dokumen', 'Identitas Mahasiswa', 'Pakta Integritas', 'Lembar Pengesahan', 'Visi, Misi, Tujuan, dan Sasaran', 'Tata Tertib PKPA', 'Daftar Isi', 'Profil Tempat PKPA Dinas Kesehatan', 'Logbook Harian'])
                ->merge(collect(PkpaHealthOfficePortfolio::reportSectionCodes())->map(fn ($code) => PkpaHealthOfficePortfolio::sectionDefinition($code)['title']))
                ->merge(['Studi Kasus Dinas Kesehatan', 'Refleksi Mingguan', 'Self Assessment', 'Dokumentasi Kegiatan', 'Daftar Pustaka', 'Lampiran', 'Status Pemeriksaan'])->values();

            return $titles->map(fn ($title, $index) => ($index + 1).'. '.$title)->all();
        }

        if ($portfolio->template?->code === PkpaLokaPomPortfolio::TEMPLATE_CODE) {
            $titles = collect(['Ringkasan Dokumen', 'Identitas Mahasiswa', 'Pakta Integritas', 'Lembar Pengesahan', 'Visi, Misi, Tujuan, dan Sasaran', 'Tata Tertib PKPA', 'Daftar Isi', 'Profil Tempat PKPA Loka POM', 'Logbook Harian'])
                ->merge(collect(PkpaLokaPomPortfolio::reportSectionCodes())->map(fn ($code) => PkpaLokaPomPortfolio::sectionDefinition($code)['title']))
                ->merge(['Studi Kasus Loka POM', 'Refleksi Mingguan', 'Self Assessment', 'Dokumentasi Kegiatan', 'Daftar Pustaka', 'Lampiran', 'Status Pemeriksaan'])->values();

            return $titles->map(fn ($title, $index) => ($index + 1).'. '.$title)->all();
        }

        $titles = collect([
            'Ringkasan Dokumen',
            'Identitas Mahasiswa',
            'Pakta Integritas',
            'Daftar Isi',
        ])->merge(
            $portfolio->template->sections
                ->whereIn('source_type', ['structured_form', 'attachment_list'])
                ->pluck('title')
        )->merge([
            'Studi Kasus',
            'Refleksi Mingguan',
            'Self Assessment',
            'Dokumentasi Kegiatan',
            'Status Pemeriksaan',
        ])->values();

        return $titles->map(fn ($title, $index) => ($index + 1).'. '.$title)->all();
    }

    private function sectionPayloadLines(PkpaRotationPortfolio $portfolio, string $sectionCode): array
    {
        $record = $portfolio->sectionRecords->firstWhere('section_code', $sectionCode);
        if (! $record || blank($record->manual_payload)) {
            return ['Belum ada isi untuk bagian ini.'];
        }

        $apotekLines = PkpaApotekPortfolio::summaryLines($sectionCode, $record->manual_payload ?? []);
        if ($apotekLines !== []) {
            return $apotekLines;
        }

        $fields = data_get($record->templateSection?->content_schema, 'fields', []);

        return collect($fields)->map(function ($field) use ($record) {
            $value = data_get($record->manual_payload, $field['name']);
            if (is_array($value)) {
                $value = implode(', ', array_filter($value));
            }

            return filled($value) ? ($field['label'] ?? str($field['name'])->headline()).': '.$value : null;
        })->filter()->values()->all() ?: ['Belum ada isi untuk bagian ini.'];
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
            $detail = match (true) {
                PkpaPbfPortfolio::isPbfCode($portfolio->practiceDomain?->code) => $this->pbfCaseReportDetailLines($case),
                PkpaIndustryPortfolio::isIndustryCode($portfolio->practiceDomain?->code) => $this->industryCaseReportDetailLines($case),
                $portfolio->template?->code === PkpaHealthOfficePortfolio::TEMPLATE_CODE => $this->healthOfficeCaseReportDetailLines($case),
                $portfolio->template?->code === PkpaLokaPomPortfolio::TEMPLATE_CODE => $this->lokaPomCaseReportDetailLines($case),
                default => $this->caseReportDetailLines($case),
            };
            $lines = array_merge($lines, $detail, ['']);
        }

        return $this->trimTrailingBlankLines($lines);
    }

    private function industryCaseReportDetailLines(PkpaPortfolioCaseReport $case): array
    {
        return [
            'FORMAT STUDI KASUS INDUSTRI FARMASI',
            'Kasus Nomor: '.$case->case_code,
            'Tanggal: '.($case->case_date?->format('d M Y') ?: '-'),
            'Latar Belakang: '.($case->complaint ?: '-'),
            'Identifikasi Masalah: '.($case->diagnosis ?: '-'),
            'Analisis: '.($case->history ?: '-'),
            'Regulasi yang Digunakan: '.($case->drp ?: '-'),
            'Penyelesaian: '.($case->intervention ?: '-'),
            'Peran Apoteker: '.($case->monitoring ?: '-'),
            'Kesimpulan: '.($case->conclusion ?: '-'),
            'Daftar Pustaka: '.($case->references ?: '-'),
        ];
    }

    private function pbfCaseReportDetailLines(PkpaPortfolioCaseReport $case): array
    {
        return [
            'FORMAT STUDI KASUS PBF',
            'Judul/Nomor Kasus: '.$case->case_code,
            'Tanggal: '.($case->case_date?->format('d M Y') ?: '-'),
            'Latar Belakang: '.($case->complaint ?: '-'),
            'Identifikasi Masalah: '.($case->diagnosis ?: '-'),
            'Data dan Fakta Kasus: '.($case->history ?: '-'),
            'Analisis Akar Masalah dan Acuan CDOB/Regulasi: '.($case->drp ?: '-'),
            'Solusi atau Tindakan Perbaikan: '.($case->intervention ?: '-'),
            'Evaluasi Efektivitas dan Tindak Lanjut: '.($case->monitoring ?: '-'),
            'Kesimpulan: '.($case->conclusion ?: '-'),
            'Daftar Pustaka: '.($case->references ?: '-'),
        ];
    }

    private function healthOfficeCaseReportDetailLines(PkpaPortfolioCaseReport $case): array
    {
        return [
            'FORMAT STUDI KASUS DINAS KESEHATAN', 'Judul Kasus: '.$case->case_code,
            'Tanggal: '.($case->case_date?->format('d M Y') ?: '-'), 'Identitas Kasus: '.($case->medication_use ?: '-'),
            'Latar Belakang: '.($case->complaint ?: '-'), 'Identifikasi Masalah: '.($case->diagnosis ?: '-'),
            'Tujuan Analisis: '.($case->history ?: '-'), 'Data Kasus: '.($case->past_medical_history ?: '-'),
            'Analisis Masalah: '.($case->drp ?: '-'), 'Alternatif Solusi: '.($case->intervention ?: '-'),
            'Rekomendasi: '.($case->monitoring ?: '-'), 'Kesimpulan: '.($case->conclusion ?: '-'),
            'Daftar Pustaka: '.($case->references ?: '-'),
        ];
    }

    private function lokaPomCaseReportDetailLines(PkpaPortfolioCaseReport $case): array
    {
        return [
            'FORMAT STUDI KASUS LOKA POM', 'Judul Kasus: '.$case->case_code,
            'Tanggal: '.($case->case_date?->format('d M Y') ?: '-'), 'Latar Belakang: '.($case->complaint ?: '-'),
            'Identifikasi Masalah: '.($case->diagnosis ?: '-'), 'Analisis Berdasarkan Regulasi: '.($case->drp ?: '-'),
            'Penyelesaian dan Rekomendasi: '.($case->intervention ?: '-'), 'Kompetensi yang Dicapai: '.($case->monitoring ?: '-'),
            'Kesimpulan: '.($case->conclusion ?: '-'), 'Daftar Pustaka: '.($case->references ?: '-'),
        ];
    }

    private function caseReportDetailLines(PkpaPortfolioCaseReport $case): array
    {
        $lines = [
            'FORMAT STUDI KASUS',
            'Kasus Nomor: '.$case->case_code,
            'A. Identitas Pasien',
            'Tanggal: '.($case->case_date?->format('d M Y') ?: '-'),
            'Inisial Pasien: '.($case->patient_initials ?: '-'),
            'Jenis Kelamin: '.($case->gender ?: '-'),
            'Umur: '.($case->age ?? '-'),
            'Berat Badan: '.($case->weight_kg ?? '-').' kg',
            'Tinggi Badan: '.($case->height_cm ?? '-').' cm',
            'Keluhan Utama: '.($case->complaint ?: '-'),
            'Diagnosis: '.($case->diagnosis ?: '-'),
            'B. Riwayat Pasien',
            'Riwayat Penyakit Sekarang: '.($case->history ?: '-'),
            'Riwayat Penyakit Dahulu: '.($case->past_medical_history ?: '-'),
            'Riwayat Penyakit Keluarga: '.($case->family_history ?: '-'),
            'Riwayat Alergi: '.($case->allergy ?: '-'),
            'Riwayat Penggunaan Obat: '.($case->medication_use ?: '-'),
            'C. Data Obat',
        ];

        $drugRows = collect($case->drug_data ?? [])->filter(fn ($drug) => filled(implode('', $drug ?? [])));
        $lines[] = $drugRows->isEmpty() ? 'Belum ada data obat.' : 'Nama Obat | Dosis | Frekuensi | Rute | Indikasi';
        foreach ($drugRows as $drug) {
            $lines[] = implode(' | ', [
                $drug['name'] ?? '-', $drug['dose'] ?? '-', $drug['frequency'] ?? '-', $drug['route'] ?? '-', $drug['indication'] ?? '-',
            ]);
        }

        $soap = $case->soap ?? [];
        $lines = array_merge($lines, [
            'D. Analisis SOAP',
            'S (Subjective): '.($soap['subjective'] ?? '-'),
            'O (Objective): '.($soap['objective'] ?? '-'),
            'A (Assessment): '.($soap['assessment'] ?? '-'),
            'P (Plan): '.($soap['plan'] ?? '-'),
            'E. Drug Related Problems (DRP)',
        ]);

        $drpRows = collect($case->drp_items ?? [])->filter(fn ($item) => filled($item['status'] ?? null) || filled($item['note'] ?? null));
        $lines[] = $drpRows->isEmpty() ? 'Ringkasan DRP: '.($case->drp ?: '-') : 'Jenis DRP | Ada/Tidak | Keterangan';
        foreach ($drpRows as $item) {
            $lines[] = implode(' | ', [$item['type'] ?? '-', $item['status'] ?? '-', $item['note'] ?? '-']);
        }

        return array_merge($lines, [
            'Ringkasan DRP: '.($case->drp ?: '-'),
            'F. Intervensi Apoteker: '.($case->intervention ?: '-'),
            'G. Monitoring dan Evaluasi',
            'Parameter Klinis dan Follow-up: '.($case->monitoring ?: '-'),
            'Evaluasi: '.($case->evaluation ?: '-'),
            'H. Edukasi Pasien: '.($case->education ?: '-'),
            'I. Kesimpulan Kasus: '.($case->conclusion ?: '-'),
            'J. Referensi: '.($case->references ?: '-'),
        ]);
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
        $paragraphs[] = $this->docxParagraph('FAKULTAS FARMASI', 'cover-kicker');
        $paragraphs[] = $this->docxParagraph('UNIVERSITAS BUANA PERJUANGAN KARAWANG', 'cover-kicker');
        $paragraphs[] = $this->docxParagraph($this->documentTitle($portfolio), 'title');
        $paragraphs[] = $this->docxParagraph($this->documentSubtitle($portfolio), 'subtitle');
        $paragraphs[] = $this->docxParagraph('Program: '.(data_get($portfolio->identity_snapshot, 'program') ?: '-'), 'cover-meta');
        $paragraphs[] = $this->docxParagraph('Mahasiswa: '.(data_get($portfolio->identity_snapshot, 'student_name') ?: '-'), 'cover-meta');
        $paragraphs[] = $this->docxParagraph('NIM: '.(data_get($portfolio->identity_snapshot, 'student_number') ?: '-'), 'cover-meta');
        $paragraphs[] = $this->docxParagraph('Tempat PKPA: '.(data_get($portfolio->placement_snapshot, 'practice_site') ?: '-'), 'cover-meta');
        $paragraphs[] = $this->docxParagraph('Periode: '.$this->portfolioPeriod($portfolio), 'cover-meta');
        $paragraphs[] = $this->docxParagraph('Tahun Akademik '.(data_get($portfolio->identity_snapshot, 'academic_year') ?: '-'), 'cover-year');
        $paragraphs[] = $this->docxParagraph('', 'pagebreak');

        foreach ($this->exportSections($portfolio) as $sectionIndex => $section) {
            $startsNewPage = $sectionIndex > 0 && $this->sectionStartsNewPage($section['title']);
            $headingStyle = $startsNewPage
                ? 'heading-pagebreak'
                : 'heading';
            $paragraphs[] = $this->docxParagraph($section['title'], $headingStyle);

            if (in_array($section['title'], ['Ringkasan Dokumen', 'Identitas Mahasiswa'], true)) {
                $paragraphs[] = $this->docxKeyValueTable($section['lines']);
            } else {
                foreach ($section['lines'] as $line) {
                    foreach (preg_split('/\R/u', $this->textFormatter->normalize((string) $line)) ?: [''] as $physicalLine) {
                        $paragraphs[] = $this->docxParagraph($physicalLine, $this->detectDocxLineStyle($physicalLine));
                    }
                }
            }
            $paragraphs[] = $this->docxParagraph('', 'spacer');
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
            'Catatan: pengesahan pada dokumen ini menggunakan persetujuan elektronik dalam sistem MY PKPA.',
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
        $font = '<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman"/>';

        if ($style === 'meta' && preg_match('/^([^:]{1,55}):\s*(.*)$/u', trim($text), $matches) === 1) {
            $label = htmlspecialchars(trim($matches[1]).':', ENT_XML1, 'UTF-8');
            $value = htmlspecialchars(trim($matches[2]), ENT_XML1, 'UTF-8');

            return '<w:p><w:pPr><w:spacing w:after="70" w:line="300" w:lineRule="auto"/><w:widowControl/></w:pPr>'
                .'<w:r><w:rPr>'.$font.'<w:b/><w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.$label.' </w:t></w:r>'
                .'<w:r><w:rPr>'.$font.'<w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.$value.'</w:t></w:r></w:p>';
        }

        return match ($style) {
            'cover-kicker' => '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="30"/></w:pPr><w:r><w:rPr>'.$font.'<w:b/><w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'title' => '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="1200" w:after="260"/><w:keepNext/></w:pPr><w:r><w:rPr>'.$font.'<w:b/><w:sz w:val="36"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'subtitle' => '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="720"/><w:keepNext/></w:pPr><w:r><w:rPr>'.$font.'<w:b/><w:sz w:val="28"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'cover-meta' => '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="90"/></w:pPr><w:r><w:rPr>'.$font.'<w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'cover-year' => '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="900"/></w:pPr><w:r><w:rPr>'.$font.'<w:b/><w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'heading' => '<w:p><w:pPr><w:spacing w:before="180" w:after="140"/><w:keepNext/><w:keepLines/><w:outlineLvl w:val="0"/></w:pPr><w:r><w:rPr>'.$font.'<w:b/><w:sz w:val="28"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'heading-pagebreak' => '<w:p><w:pPr><w:pageBreakBefore/><w:spacing w:before="180" w:after="140"/><w:keepNext/><w:keepLines/><w:outlineLvl w:val="0"/></w:pPr><w:r><w:rPr>'.$font.'<w:b/><w:sz w:val="28"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'subheading' => '<w:p><w:pPr><w:spacing w:before="150" w:after="90"/><w:keepNext/><w:keepLines/><w:outlineLvl w:val="1"/></w:pPr><w:r><w:rPr>'.$font.'<w:b/><w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'toc' => '<w:p><w:pPr><w:ind w:left="360"/><w:spacing w:after="70" w:line="300" w:lineRule="auto"/></w:pPr><w:r><w:rPr>'.$font.'<w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
            'bullet' => '<w:p><w:pPr><w:ind w:left="540" w:hanging="260"/><w:spacing w:after="70" w:line="300" w:lineRule="auto"/><w:widowControl/></w:pPr><w:r><w:rPr>'.$font.'<w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.htmlspecialchars('• '.preg_replace('/^-\s*/u', '', trim($text)), ENT_XML1, 'UTF-8').'</w:t></w:r></w:p>',
            'spacer' => '<w:p><w:pPr><w:spacing w:after="140"/></w:pPr></w:p>',
            'pagebreak' => '<w:p><w:r><w:br w:type="page"/></w:r></w:p>',
            default => '<w:p><w:pPr><w:jc w:val="both"/><w:spacing w:line="360" w:lineRule="auto" w:after="100"/><w:widowControl/></w:pPr><w:r><w:rPr>'.$font.'<w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>',
        };
    }

    private function documentSubtitle(PkpaRotationPortfolio $portfolio): string
    {
        return strtoupper((string) (data_get($portfolio->placement_snapshot, 'practice_domain') ?: 'PKPA'));
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

        if (str_starts_with($trimmed, '- ')) {
            return 'bullet';
        }

        if (preg_match('/^(Entri \d+|Aspek \d+ -|Ringkasan Logbook PKPA|Panduan Penilaian Diri|Pihak Yang Mengetahui)$/', $trimmed) === 1) {
            return 'subheading';
        }

        if (preg_match('/^[^:]{1,55}:\s*/u', $trimmed) === 1) {
            return 'meta';
        }

        return 'body';
    }

    private function sectionStartsNewPage(string $title): bool
    {
        return in_array($title, [
            'Visi, Misi, Tujuan, dan Sasaran',
            'Daftar Isi',
            'Logbook Harian',
            'Studi Kasus',
            'Studi Kasus PBF',
        ], true);
    }

    private function portfolioPeriod(PkpaRotationPortfolio $portfolio): string
    {
        $start = data_get($portfolio->placement_snapshot, 'start_date');
        $end = data_get($portfolio->placement_snapshot, 'end_date');

        if (! $start && ! $end) {
            return '-';
        }

        return collect([$start, $end])
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->translatedFormat('d F Y'))
            ->join(' - ');
    }

    private function docxKeyValueTable(array $lines): string
    {
        $rows = collect($lines)->map(function ($line) {
            $parts = explode(':', (string) $line, 2);

            return [trim($parts[0]), trim($parts[1] ?? '-')];
        });
        $font = '<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman"/>';
        $borders = '<w:tblBorders><w:top w:val="single" w:sz="4" w:color="D9D9D9"/><w:left w:val="single" w:sz="4" w:color="D9D9D9"/><w:bottom w:val="single" w:sz="4" w:color="D9D9D9"/><w:right w:val="single" w:sz="4" w:color="D9D9D9"/><w:insideH w:val="single" w:sz="4" w:color="D9D9D9"/><w:insideV w:val="single" w:sz="4" w:color="D9D9D9"/></w:tblBorders>';

        return '<w:tbl><w:tblPr><w:tblW w:w="7937" w:type="dxa"/><w:tblLayout w:type="fixed"/>'.$borders.'<w:tblCellMar><w:top w:w="100" w:type="dxa"/><w:left w:w="120" w:type="dxa"/><w:bottom w:w="100" w:type="dxa"/><w:right w:w="120" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid><w:gridCol w:w="2300"/><w:gridCol w:w="5637"/></w:tblGrid>'
            .$rows->map(function (array $row) use ($font) {
                $label = htmlspecialchars($row[0], ENT_XML1, 'UTF-8');
                $value = htmlspecialchars($row[1] !== '' ? $row[1] : '-', ENT_XML1, 'UTF-8');

                return '<w:tr><w:trPr><w:cantSplit/></w:trPr>'
                    .'<w:tc><w:tcPr><w:tcW w:w="2300" w:type="dxa"/><w:shd w:val="clear" w:fill="F2F2F2"/><w:vAlign w:val="center"/></w:tcPr><w:p><w:pPr><w:spacing w:after="0"/></w:pPr><w:r><w:rPr>'.$font.'<w:b/><w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.$label.'</w:t></w:r></w:p></w:tc>'
                    .'<w:tc><w:tcPr><w:tcW w:w="5637" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr><w:p><w:pPr><w:spacing w:after="0" w:line="300" w:lineRule="auto"/></w:pPr><w:r><w:rPr>'.$font.'<w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.$value.'</w:t></w:r></w:p></w:tc></w:tr>';
            })->implode('')
            .'</w:tbl>';
    }

    private function docxStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="id-ID"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:line="360" w:lineRule="auto"/><w:widowControl/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style><w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:jc w:val="center"/></w:pPr><w:rPr><w:b/><w:sz w:val="36"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:b/><w:sz w:val="28"/></w:rPr></w:style></w:styles>';
    }

    private function docxSettingsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:zoom w:percent="100"/><w:defaultTabStop w:val="720"/><w:updateFields w:val="true"/><w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat></w:settings>';
    }

    private function docxFooterXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/><w:sz w:val="20"/></w:rPr><w:t xml:space="preserve">Halaman </w:t></w:r><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p></w:ftr>';
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

    private function genericSectionCompleted(array $payload, array $fields): bool
    {
        $required = collect($fields)
            ->filter(fn ($field) => ($field['required'] ?? true) && filled($field['name'] ?? null))
            ->pluck('name');

        if ($required->isEmpty()) {
            return $payload !== [];
        }

        return $required->every(fn ($name) => filled($payload[$name] ?? null));
    }

    private function ensureStudentOwns(PkpaRotationPortfolio $portfolio, User $actor): void
    {
        if (! $actor->hasRole('mahasiswa') || (string) $portfolio->rotationRun?->student_core_user_id !== (string) $actor->core_user_id) {
            throw ValidationException::withMessages(['authorization' => 'Portofolio hanya dapat diubah mahasiswa pemilik.']);
        }
    }

    private function ensurePortfolioEditable(PkpaRotationPortfolio $portfolio): void
    {
        if (! in_array($portfolio->status, ['draft', 'in_progress', 'field_revision_requested', 'internal_revision_requested'], true)) {
            throw ValidationException::withMessages(['portfolio' => 'Portofolio yang sudah dikirim tidak dapat diubah.']);
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

    private function integrityStatementItems(): array
    {
        return [
            'Merahasiakan segala sesuatu yang saya ketahui sehubungan dengan tugas di tempat PKPA yang dipercayakan kepada saya kecuali untuk kepentingan akademik.',
            'Menjalankan tugas PKPA di tempat PKPA dengan sebaik-baiknya dan berikhtiar dengan sungguh-sungguh agar tidak terpengaruh dengan pertimbangan keagamaan, kebangsaan, kesukuan, politik kepartaian, atau kedudukan sosial.',
            'Memelihara hubungan baik dan menghormati pembimbing saya, rekan sesama mahasiswa, apoteker, dan tenaga kesehatan lainnya.',
            'Apabila saya melanggar hal-hal yang telah saya nyatakan dalam pakta integritas ini, saya bersedia dikenakan sanksi moral, sanksi administratif, dan tuntutan hukum sesuai dengan ketentuan perundang-undangan yang berlaku.',
        ];
    }

    private function integrityDocumentLines(PkpaRotationPortfolio $portfolio): array
    {
        $sheet = $this->integritySheetData($portfolio);
        $lines = [
            'Yang bertanda tangan di bawah ini:',
            'Nama: '.$sheet['student_name'],
            'NIM: '.$sheet['student_number'],
            'No. HP: '.$sheet['student_phone'],
            'Email: '.$sheet['student_email'],
            'Tempat PKPA: '.$sheet['practice_site'],
            'Wahana PKPA: '.$sheet['practice_domain'],
            'Periode PKPA: '.$sheet['practice_period'],
            '',
            'Dengan ini menyatakan bahwa:',
        ];

        foreach ($sheet['statement_items'] as $index => $item) {
            $lines[] = ($index + 1).'. '.$item;
        }

        return array_merge($lines, [
            '',
            'Status persetujuan: '.$sheet['status_label'],
            'Tanggal persetujuan: '.($sheet['signed_at_label'] ?: 'Belum disetujui'),
            'Kode validasi: '.$sheet['validation_code'],
            'Tautan validasi: '.$sheet['verification_url'],
            '',
            $sheet['city'].', '.$sheet['signed_date_label'],
            'Yang Membuat Pernyataan',
            '',
            'Ruang tanda tangan basah: _________________________________',
            'Nama mahasiswa: '.$sheet['student_name'],
        ]);
    }

    private function integrityQrMarkup(string $payload): string
    {
        $src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data='.rawurlencode($payload);

        return '<img src="'.$src.'" alt="QR validasi pakta integritas" class="h-full w-full object-contain" loading="eager">';
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
