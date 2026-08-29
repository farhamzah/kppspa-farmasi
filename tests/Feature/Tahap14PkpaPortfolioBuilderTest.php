<?php

namespace Tests\Feature;

use App\Models\PkpaAssessmentScheme;
use App\Models\PkpaAttendanceRecord;
use App\Models\PkpaEnrollment;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPortfolioExportVersion;
use App\Models\PkpaPortfolioTemplate;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgramSite;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaRotationAssessment;
use App\Models\PkpaRotationCompetencyRecord;
use App\Models\PkpaRotationGradeResult;
use App\Models\PkpaRotationPortfolio;
use App\Models\PkpaRotationRun;
use App\Models\PkpaRotationSupervisorHistory;
use App\Models\Role;
use App\Models\User;
use App\Support\PkpaApotekPortfolio;
use App\Services\PkpaEnrollmentRequirementService;
use App\Services\PkpaPortfolioBuilderService;
use App\Services\PkpaProgramService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\PkpaPortfolioTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class Tahap14PkpaPortfolioBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $student;
    private User $otherStudent;
    private User $fieldSupervisor;
    private User $internalSupervisor;
    private PkpaRotationRun $run;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class, PkpaPortfolioTemplateSeeder::class]);
        $this->admin = $this->makeUser('admin14@test.local', ['admin'], 'CORE-ADMIN-14');
        $this->koordinator = $this->makeUser('koor14@test.local', ['koordinator_kp'], 'CORE-KOOR-14');
        $this->student = $this->makeUser('student14@test.local', ['mahasiswa'], 'CORE-STUDENT-14');
        $this->otherStudent = $this->makeUser('other14@test.local', ['mahasiswa'], 'CORE-OTHER-14');
        $this->fieldSupervisor = $this->makeUser('pl14@test.local', ['pembimbing_lapangan'], 'CORE-PL-14');
        $this->internalSupervisor = $this->makeUser('pd14@test.local', ['pembimbing_dalam'], 'CORE-PD-14');
        $this->run = $this->fixtureRun();
    }

    public function test_templates_apotek_and_hospital_are_seeded_and_hospital_label_is_clean(): void
    {
        $this->assertDatabaseHas('pkpa_portfolio_templates', ['code' => 'PORT-APT-v1', 'status' => 'active']);
        $this->assertDatabaseHas('pkpa_portfolio_templates', ['code' => 'PORT-RS-v1', 'status' => 'active']);
        $apotek = PkpaPortfolioTemplate::where('code', 'PORT-APT-v1')->with('sections')->firstOrFail();
        $hospital = PkpaPortfolioTemplate::where('code', 'PORT-RS-v1')->with('sections')->firstOrFail();
        $this->assertStringContainsString('Profil Tempat PKPA', $apotek->sections->pluck('title')->implode(' '));
        $this->assertStringContainsString('Daftar Pustaka', $apotek->sections->pluck('title')->implode(' '));
        $this->assertStringNotContainsString('Apotek', $hospital->name.' '.$hospital->sections->pluck('title')->implode(' '));
        $this->assertStringContainsString('Logbook', $hospital->sections->pluck('title')->implode(' '));
        $this->assertStringContainsString('Penilaian Diri', $hospital->sections->pluck('title')->implode(' '));
    }

    public function test_portfolio_auto_create_idempotent_links_existing_data_and_blocks_incomplete_submit(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $portfolio = $service->ensureForRun($this->run, $this->admin);
        $again = $service->ensureForRun($this->run->fresh(), $this->admin);
        $this->assertSame($portfolio->id, $again->id);
        $this->assertSame('Mahasiswa Tahap 14', data_get($portfolio->identity_snapshot, 'student_name'));
        $logbookRecord = $portfolio->sectionRecords()->where('source_type', 'auto_logbook')->firstOrFail();
        $this->assertNotEmpty(data_get($logbookRecord->auto_source_refs, 'logbook_entry_ids'));
        $siteProfileRecord = $portfolio->sectionRecords()->where('section_code', 'site_profile')->firstOrFail();
        $this->assertSame('structured_form', $siteProfileRecord->source_type);
        $this->expectException(ValidationException::class);
        $service->submit($portfolio->fresh(), $this->student);
    }

    public function test_privacy_authorization_review_publication_and_exports_work(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $portfolio = $service->ensureForRun($this->run, $this->admin);
        $service->acknowledgeIntegrity($portfolio, $this->student);
        try {
            $service->saveCase($portfolio->fresh(), ['case_code' => 'BAD-1', 'complaint' => 'Nomor RM 12345', 'anonymization_confirmed' => true], $this->student);
            $this->fail('Direct patient identifier must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('identitas', strtolower($exception->getMessage()));
        }
        $this->fillApotekSections($service, $portfolio);
        $service->saveCase($portfolio->fresh(), ['case_code' => 'CASE-1', 'case_date' => '2026-07-02', 'patient_initials' => 'TN', 'gender' => 'L', 'age' => 40, 'complaint' => 'Batuk', 'diagnosis' => 'ISPA ringan', 'drp' => 'Tidak ada', 'intervention' => 'Edukasi penggunaan obat', 'anonymization_confirmed' => true], $this->student);
        $service->saveReflection($portfolio->fresh(), ['week_number' => 1, 'unit' => 'Pelayanan', 'target' => 'Memahami alur', 'achievement' => 'Tercapai'], $this->student);
        $service->saveSelfAssessment($portfolio->fresh(), ['aspect' => 'Komunikasi', 'score' => 4, 'evidence_experience' => 'Konseling pasien anonim'], $this->student);
        $documentation = $service->saveDocumentation($portfolio->fresh(), ['activity' => 'Konseling obat', 'anonymization_confirmed' => true, 'consent_confirmed' => true], null, $this->student);
        $this->assertSame('local', $documentation->disk);

        $this->assertFalse($service->canAccess($portfolio->fresh(), $this->otherStudent));
        $this->actingAs($this->otherStudent)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa/'.$portfolio->id)
            ->assertForbidden();
        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/review-portofolio/'.$portfolio->id)
            ->assertOk();
        $this->actingAs($this->internalSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/review-portofolio/'.$portfolio->id)
            ->assertOk();

        $submitted = $service->submit($portfolio->fresh(), $this->student);
        $this->assertSame('submitted_to_field_supervisor', $submitted->status);
        $service->review($submitted->fresh(), 'field', 'revision_requested', 'Perbaiki narasi kasus.', $this->fieldSupervisor);
        $this->assertSame('field_revision_requested', $submitted->fresh()->status);
        $service->review($submitted->fresh(), 'field', 'verify', 'Sudah sesuai.', $this->fieldSupervisor);
        $this->assertSame('field_verified', $submitted->fresh()->status);
        $service->submitToInternal($submitted->fresh(), $this->student);
        $service->review($submitted->fresh(), 'internal', 'approve', 'Layak final.', $this->internalSupervisor);
        $this->assertSame('approved', $submitted->fresh()->status);
        $this->expectException(ValidationException::class);
        $service->reopen($submitted->fresh(), '', $this->koordinator);
    }

    public function test_export_docx_pdf_and_published_export_are_versioned_without_overwrite(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $portfolio = $service->ensureForRun($this->run, $this->admin);
        $service->acknowledgeIntegrity($portfolio, $this->student);
        $this->fillApotekSections($service, $portfolio);
        $service->saveCase($portfolio->fresh(), ['case_code' => 'CASE-1', 'complaint' => 'Batuk', 'anonymization_confirmed' => true], $this->student);
        $service->saveReflection($portfolio->fresh(), ['week_number' => 1, 'achievement' => 'Tercapai'], $this->student);
        $service->saveSelfAssessment($portfolio->fresh(), ['aspect' => 'Etika', 'score' => 5], $this->student);
        $service->saveDocumentation($portfolio->fresh(), ['activity' => 'PIO', 'anonymization_confirmed' => true, 'consent_confirmed' => true], null, $this->student);
        $service->submit($portfolio->fresh(), $this->student);
        $service->review($portfolio->fresh(), 'field', 'verify', 'OK', $this->fieldSupervisor);
        $service->submitToInternal($portfolio->fresh(), $this->student);
        $service->review($portfolio->fresh(), 'internal', 'approve', 'OK', $this->internalSupervisor);
        $publication = $service->publish($portfolio->fresh(), $this->koordinator);
        $docx = $service->export($portfolio->fresh(), 'docx', $this->koordinator);
        $pdf = $service->export($portfolio->fresh(), 'pdf', $this->koordinator);
        Storage::disk('local')->assertExists($docx->path);
        Storage::disk('local')->assertExists($pdf->path);
        $this->assertDocx(Storage::disk('local')->path($docx->path));
        $docxText = $this->docxDocumentXml(Storage::disk('local')->path($docx->path));
        $this->assertStringContainsString('Portofolio PKPA Apotek', $docxText);
        $this->assertStringContainsString('Profil Tempat PKPA', $docxText);
        $this->assertStringContainsString('Laporan Kegiatan: Pelayanan Resep', $docxText);
        $this->assertStringContainsString('Self Assessment', $docxText);
        $this->assertStringContainsString('Pihak Yang Mengetahui', $docxText);
        $this->assertStringContainsString('Entri 1', $docxText);
        $this->assertStringContainsString('Aspek 1 - Etika', $docxText);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($pdf->path));
        $this->assertSame($docx->id, $service->export($portfolio->fresh(), 'docx', $this->koordinator)->id);
        $this->assertSame($publication->id, PkpaPortfolioExportVersion::find($docx->id)->pkpa_portfolio_publication_id);
    }

    public function test_hospital_portfolio_docx_pdf_exports_keep_hospital_labels(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $run = $this->fixtureRun('RS', '14RS');
        $portfolio = $service->ensureForRun($run, $this->admin);
        $docx = $service->export($portfolio->fresh(), 'docx', $this->koordinator);
        $pdf = $service->export($portfolio->fresh(), 'pdf', $this->koordinator);

        Storage::disk('local')->assertExists($docx->path);
        Storage::disk('local')->assertExists($pdf->path);
        $docxText = $this->docxDocumentXml(Storage::disk('local')->path($docx->path));
        $this->assertStringContainsString('Rumah Sakit', $docxText);
        $this->assertStringNotContainsString('Apotek', $docxText);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($pdf->path));
    }

    public function test_student_can_store_apotek_section_record_from_portal(): void
    {
        $portfolio = app(PkpaPortfolioBuilderService::class)->ensureForRun($this->run, $this->admin);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/portofolio-pkpa/'.$portfolio->id.'/bagian/site_profile', [
                'overview' => 'Apotek melayani resep dan swamedikasi.',
                'operational_hours' => '08.00 - 21.00',
                'pharmacy_services' => 'Pelayanan resep, PIO, konseling.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pkpa_portfolio_section_records', [
            'pkpa_rotation_portfolio_id' => $portfolio->id,
            'section_code' => 'site_profile',
            'status' => 'completed',
        ]);
    }

    public function test_apotek_portfolio_detail_pages_render_new_structure_for_three_portals(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $portfolio = $service->ensureForRun($this->run, $this->admin);
        $this->fillApotekSections($service, $portfolio);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa/'.$portfolio->id)
            ->assertOk()
            ->assertSee('Struktur Portofolio Apotek')
            ->assertSee('Profil Tempat PKPA')
            ->assertSee('Laporan Kegiatan PKPA Apotek');

        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/review-portofolio/'.$portfolio->id)
            ->assertOk()
            ->assertSee('Bagian Portofolio Apotek')
            ->assertSee('Laporan Kegiatan: Pelayanan Resep');

        $this->actingAs($this->internalSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/review-portofolio/'.$portfolio->id)
            ->assertOk()
            ->assertSee('Ringkasan Portofolio Apotek')
            ->assertSee('Daftar Pustaka');
    }

    private function fixtureRun(string $domainCode = 'APT', string $suffix = '14'): PkpaRotationRun
    {
        $program = app(PkpaProgramService::class)->create(['code' => 'PKPA-'.$suffix, 'name' => 'Program Tahap '.$suffix, 'academic_year' => '2026/2027', 'cohort_name' => 'Demo', 'start_date' => '2026-07-01', 'end_date' => '2026-07-31'], $this->admin);
        $domain = PkpaPracticeDomain::where('code', $domainCode)->firstOrFail();
        $programDomain = $program->domains()->where('practice_domain_id', $domain->id)->firstOrFail();
        $siteName = $domainCode === 'RS' ? 'Rumah Sakit Tahap 14' : 'Apotek Tahap 14';
        $site = PkpaPracticeSite::create(['practice_domain_id' => $domain->id, 'code' => $domainCode.'-'.$suffix, 'name' => $siteName, 'address' => 'Karawang', 'city' => 'Karawang', 'province' => 'Jawa Barat', 'cooperation_start_date' => '2026-01-01', 'cooperation_end_date' => '2026-12-31', 'status' => 'active', 'is_active' => true]);
        $programSite = PkpaProgramSite::create(['pkpa_program_id' => $program->id, 'practice_site_id' => $site->id, 'pkpa_program_domain_id' => $programDomain->id, 'practice_domain_id' => $domain->id, 'status' => 'active', 'is_active' => true]);
        $enrollment = PkpaEnrollment::create(['pkpa_program_id' => $program->id, 'core_user_id' => $this->student->core_user_id, 'student_number' => '2400'.$suffix, 'student_name_snapshot' => 'Mahasiswa Tahap 14', 'student_email_snapshot' => $this->student->email, 'status' => 'active', 'core_account_status_snapshot' => 'active']);
        app(PkpaEnrollmentRequirementService::class)->ensureRequirements($enrollment, $this->admin);
        $requirement = $enrollment->requirements()->where('practice_domain_id', $domain->id)->firstOrFail();
        $plan = PkpaPlacementPlan::create(['pkpa_program_id' => $program->id, 'code' => 'PLAN-'.$suffix, 'name' => 'Plan '.$suffix, 'version_number' => 1, 'status' => 'locked', 'is_current' => true, 'current_key' => 'PROGRAM:'.$program->id, 'validation_status' => 'valid']);
        $publication = PkpaPlacementPublication::create(['pkpa_program_id' => $program->id, 'pkpa_placement_plan_id' => $plan->id, 'publication_number' => 1, 'revision_number' => 0, 'code' => 'PUB-'.$suffix, 'title' => 'Publikasi '.$suffix, 'status' => 'published', 'is_current' => true, 'current_key' => 'PROGRAM:'.$program->id, 'published_at' => now()]);
        $assignment = PkpaPublishedAssignment::create(['pkpa_placement_publication_id' => $publication->id, 'pkpa_enrollment_id' => $enrollment->id, 'pkpa_enrollment_requirement_id' => $requirement->id, 'practice_domain_id' => $domain->id, 'practice_site_id' => $site->id, 'program_site_id' => $programSite->id, 'student_core_user_id' => $this->student->core_user_id, 'student_number_snapshot' => '2400'.$suffix, 'student_name_snapshot' => 'Mahasiswa Tahap 14', 'practice_domain_name_snapshot' => $domain->name, 'practice_site_name_snapshot' => $site->name, 'start_date' => '2026-07-01', 'end_date' => '2026-07-07', 'status' => 'scheduled']);
        $run = PkpaRotationRun::create(['pkpa_program_id' => $program->id, 'pkpa_enrollment_id' => $enrollment->id, 'pkpa_enrollment_requirement_id' => $requirement->id, 'current_placement_publication_id' => $publication->id, 'origin_published_assignment_id' => $assignment->id, 'current_published_assignment_id' => $assignment->id, 'practice_domain_id' => $domain->id, 'practice_site_id' => $site->id, 'student_core_user_id' => $this->student->core_user_id, 'scheduled_start_date' => '2026-07-01', 'scheduled_end_date' => '2026-07-07', 'status' => 'active', 'operational_status' => 'running', 'publication_sync_status' => 'synced', 'current_key' => 'REQ:'.$requirement->id]);
        foreach ([['field', $this->fieldSupervisor], ['internal', $this->internalSupervisor]] as [$type, $user]) {
            PkpaRotationSupervisorHistory::create(['pkpa_rotation_run_id' => $run->id, 'supervisor_type' => $type, 'core_user_id' => $user->core_user_id, 'name_snapshot' => $user->name, 'role_snapshot' => $type, 'effective_start_date' => '2026-07-01', 'status' => 'active', 'active_key' => $run->id.':'.$type]);
        }
        $run->logbookEntries()->create(['entry_date' => '2026-07-01', 'title' => 'Orientasi '.$siteName, 'activity_summary' => 'Mempelajari alur pelayanan', 'learning_outcomes' => 'Memahami pelayanan kefarmasian', 'reflection' => 'Perlu memperkuat komunikasi pasien', 'status' => 'submitted', 'entry_key' => $run->id.':2026-07-01']);
        PkpaAttendanceRecord::create(['pkpa_rotation_run_id' => $run->id, 'attendance_date' => '2026-07-01', 'attendance_type' => 'present', 'status' => 'approved', 'submission_status' => 'approved', 'source' => 'manual', 'active_key' => $run->id.':2026-07-01']);
        PkpaRotationCompetencyRecord::create(['pkpa_rotation_run_id' => $run->id, 'competency_code_snapshot' => 'K14', 'competency_title_snapshot' => 'Pelayanan kefarmasian', 'is_required_snapshot' => true, 'evidence_required_snapshot' => false, 'minimum_evidence_count_snapshot' => 0, 'status' => 'verified']);
        $scheme = PkpaAssessmentScheme::create(['pkpa_program_domain_id' => $programDomain->id, 'code' => 'SC-14', 'name' => 'Skema 14', 'version_number' => 1, 'maximum_score' => 100, 'rounding_precision' => 2, 'rounding_mode' => 'half_up', 'status' => 'active', 'is_current' => true]);
        $assessment = PkpaRotationAssessment::create(['pkpa_rotation_run_id' => $run->id, 'source_assessment_scheme_id' => $scheme->id, 'scheme_code_snapshot' => 'SC-14', 'scheme_name_snapshot' => 'Skema 14', 'scheme_version_snapshot' => 1, 'maximum_score_snapshot' => 100, 'rounding_precision_snapshot' => 2, 'rounding_mode_snapshot' => 'half_up', 'status' => 'finalized', 'completion_status' => 'complete']);
        PkpaRotationGradeResult::create(['pkpa_rotation_assessment_id' => $assessment->id, 'pkpa_rotation_run_id' => $run->id, 'pkpa_enrollment_id' => $enrollment->id, 'pkpa_enrollment_requirement_id' => $requirement->id, 'practice_domain_id' => $domain->id, 'assessment_scheme_id' => $scheme->id, 'raw_total_score' => 88, 'final_score' => 88, 'maximum_score' => 100, 'result_status' => 'finalized', 'calculation_snapshot' => [], 'component_snapshot' => []]);

        return $run->fresh();
    }

    private function makeUser(string $email, array $roles, string $coreUserId): User
    {
        $user = User::factory()->create(['name' => str($email)->before('@')->headline(), 'email' => $email, 'password' => Hash::make('password'), 'status' => 'active', 'profile_completed' => true, 'core_user_id' => $coreUserId]);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user->load('roles');
    }

    private function assertDocx(string $path): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
        $zip->close();
    }

    private function docxDocumentXml(string $path): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $contents = $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertIsString($contents);

        return html_entity_decode(strip_tags($contents));
    }

    private function fillApotekSections(PkpaPortfolioBuilderService $service, PkpaRotationPortfolio $portfolio): void
    {
        foreach (PkpaApotekPortfolio::editableSections() as $code => $definition) {
            if (! ($definition['is_required'] ?? false)) {
                continue;
            }

            $payload = [];
            foreach ($definition['fields'] ?? [] as $field) {
                $payload[$field['name']] = $field['label'].' demo';
            }

            $service->saveSectionRecord($portfolio->fresh(), $code, $payload, $this->student);
        }
    }
}
