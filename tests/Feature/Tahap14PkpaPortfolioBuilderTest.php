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
use App\Models\PkpaPracticeDomainOption;
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
use App\Support\PkpaHospitalPortfolio;
use App\Support\PkpaIndustryPortfolio;
use App\Support\PkpaPuskesmasPortfolio;
use App\Support\PkpaHealthOfficePortfolio;
use App\Support\PkpaLokaPomPortfolio;
use App\Support\PkpaPortfolioTextFormatter;
use App\Services\PkpaEnrollmentRequirementService;
use App\Services\PkpaPortfolioBuilderService;
use App\Services\PkpaProgramService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\PkpaPortfolioTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

    public function test_templates_apotek_hospital_and_pbf_are_seeded_with_domain_specific_sections(): void
    {
        $this->assertDatabaseHas('pkpa_portfolio_templates', ['code' => 'PORT-APT-v1', 'status' => 'active']);
        $this->assertDatabaseHas('pkpa_portfolio_templates', ['code' => 'PORT-RS-v1', 'status' => 'active']);
        $this->assertDatabaseHas('pkpa_portfolio_templates', ['code' => 'PORT-PBF-v1', 'status' => 'active']);
        $this->assertDatabaseHas('pkpa_portfolio_templates', ['code' => 'PORT-IND-v1', 'status' => 'active']);
        $this->assertDatabaseHas('pkpa_portfolio_templates', ['code' => 'PORT-PKM-v1', 'status' => 'active']);
        $this->assertDatabaseHas('pkpa_portfolio_templates', ['code' => 'PORT-DINKES-v1', 'status' => 'active']);
        $this->assertDatabaseHas('pkpa_portfolio_templates', ['code' => 'PORT-LOKAPOM-v1', 'status' => 'active']);
        $apotek = PkpaPortfolioTemplate::where('code', 'PORT-APT-v1')->with('sections')->firstOrFail();
        $hospital = PkpaPortfolioTemplate::where('code', 'PORT-RS-v1')->with('sections')->firstOrFail();
        $pbf = PkpaPortfolioTemplate::where('code', 'PORT-PBF-v1')->with('sections')->firstOrFail();
        $industry = PkpaPortfolioTemplate::where('code', 'PORT-IND-v1')->with('sections')->firstOrFail();
        $this->assertStringContainsString('Profil Tempat PKPA', $apotek->sections->pluck('title')->implode(' '));
        $this->assertStringContainsString('Daftar Pustaka', $apotek->sections->pluck('title')->implode(' '));
        $this->assertStringContainsString('Logbook', $hospital->sections->pluck('title')->implode(' '));
        $this->assertStringContainsString('Profil Tempat PKPA Rumah Sakit', $hospital->sections->pluck('title')->implode(' '));
        $this->assertStringContainsString('Visite Apoteker', $hospital->sections->pluck('title')->implode(' '));
        $this->assertFalse($hospital->sections->firstWhere('code', 'pharmacy_warehouse')->is_required);
        $this->assertStringContainsString('Cold Chain Product', $pbf->sections->pluck('title')->implode(' '));
        $this->assertStringContainsString('Produk Rusak dan Kedaluwarsa', $pbf->sections->pluck('title')->implode(' '));
        $this->assertStringContainsString('Quality Assurance', $industry->sections->pluck('title')->implode(' '));
        $this->assertStringContainsString('Production Planning and Inventory Control', $industry->sections->pluck('title')->implode(' '));
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
        $service->saveCase($portfolio->fresh(), [
            'case_code' => 'CASE-1',
            'complaint' => 'Batuk',
            'history' => 'Batuk sejak tiga hari.',
            'past_medical_history' => 'Tidak ada riwayat penyakit dahulu.',
            'family_history' => 'Tidak ada riwayat penyakit keluarga.',
            'drug_data' => [['name' => 'Paracetamol', 'dose' => '500 mg', 'frequency' => '3 kali sehari', 'route' => 'Oral', 'indication' => 'Demam']],
            'soap' => ['subjective' => 'Batuk dan demam.', 'objective' => 'Suhu 37,8 C.', 'assessment' => 'ISPA ringan.', 'plan' => 'Terapi simptomatik dan edukasi.'],
            'drp_items' => [['type' => 'Interaksi obat', 'status' => 'tidak', 'note' => 'Tidak ditemukan.']],
            'intervention' => 'Edukasi penggunaan obat.',
            'monitoring' => 'Pantau suhu dan perbaikan gejala.',
            'evaluation' => 'Evaluasi setelah tiga hari.',
            'education' => 'Minum obat sesuai aturan pakai.',
            'conclusion' => 'Pasien dapat melanjutkan terapi simptomatik.',
            'references' => 'Pedoman pelayanan kefarmasian.',
            'anonymization_confirmed' => true,
        ], $this->student);
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
        $this->assertStringContainsString('Kode validasi:', $docxText);
        $this->assertStringContainsString('Entri 1', $docxText);
        $this->assertStringContainsString('Aspek 1 - Etika', $docxText);
        $this->assertStringContainsString('C. Data Obat', $docxText);
        $this->assertStringContainsString('Paracetamol | 500 mg', $docxText);
        $this->assertStringContainsString('D. Analisis SOAP', $docxText);
        $this->assertStringContainsString('Interaksi obat | tidak | Tidak ditemukan.', $docxText);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($pdf->path));
        $this->assertSame($docx->id, $service->export($portfolio->fresh(), 'docx', $this->koordinator)->id);
        $this->assertSame($publication->id, PkpaPortfolioExportVersion::find($docx->id)->pkpa_portfolio_publication_id);
    }

    public function test_hospital_portfolio_docx_pdf_exports_keep_hospital_labels(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $run = $this->fixtureRun('RS', '14RS');
        $portfolio = $service->ensureForRun($run, $this->admin);
        $service->saveSectionRecord($portfolio, 'pharmacy_warehouse', [
            'purpose' => 'Memahami pengelolaan perbekalan farmasi rumah sakit.',
            'theory' => 'Pengelolaan mengikuti standar pelayanan kefarmasian rumah sakit.',
            'activity' => 'Mengamati penerimaan, penyimpanan, dan distribusi obat.',
            'result' => 'Memahami alur stok dan penerapan FEFO di gudang farmasi.',
        ], $this->student);
        $docx = $service->export($portfolio->fresh(), 'docx', $this->koordinator);
        $pdf = $service->export($portfolio->fresh(), 'pdf', $this->koordinator);

        Storage::disk('local')->assertExists($docx->path);
        Storage::disk('local')->assertExists($pdf->path);
        $docxText = $this->docxDocumentXml(Storage::disk('local')->path($docx->path));
        $this->assertStringContainsString('Rumah Sakit', $docxText);
        $this->assertStringContainsString('Logbook Harian', $docxText);
        $this->assertStringContainsString('Laporan Kegiatan: Gudang Farmasi', $docxText);
        $this->assertStringContainsString('penerapan FEFO di gudang farmasi', $docxText);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($pdf->path));
    }

    public function test_hospital_portfolio_can_be_filled_and_reviewed_from_all_portals(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $run = $this->fixtureRun('RS', '14RSFORM');
        $portfolio = $service->ensureForRun($run, $this->admin);
        $profile = collect(PkpaHospitalPortfolio::sectionDefinition('site_profile')['fields'])
            ->mapWithKeys(fn ($field) => [$field['name'] => $field['label'].' Rumah Sakit Pendidikan Karawang.'])
            ->all();

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa')
            ->assertOk()
            ->assertSee('Rumah Sakit');

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/portofolio-pkpa/'.$portfolio->id.'/bagian/site_profile', $profile)
            ->assertRedirect();

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/portofolio-pkpa/'.$portfolio->id.'/bagian/ward_round', [
                'purpose' => 'Memahami kontribusi apoteker saat visite.',
                'theory' => 'Visite mendukung terapi obat yang efektif dan aman.',
                'activity' => 'Mengikuti visite bersama tim interprofesional.',
                'result' => 'Mampu mengidentifikasi masalah terkait obat saat visite.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pkpa_portfolio_section_records', [
            'pkpa_rotation_portfolio_id' => $portfolio->id,
            'section_code' => 'site_profile',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('pkpa_portfolio_section_records', [
            'pkpa_rotation_portfolio_id' => $portfolio->id,
            'section_code' => 'ward_round',
            'status' => 'completed',
        ]);
        $this->assertNotContains(
            'Minimal satu laporan kegiatan Rumah Sakit wajib lengkap.',
            $service->completeness($portfolio->fresh())['blocking']
        );

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa/'.$portfolio->id)
            ->assertOk()
            ->assertSee('Portofolio Rumah Sakit')
            ->assertSee('Profil Tempat PKPA Rumah Sakit')
            ->assertSee('Visite Apoteker')
            ->assertSee('minimal satu laporan kegiatan harus lengkap');

        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/review-portofolio/'.$portfolio->id)
            ->assertOk()
            ->assertSee('Bagian Portofolio Rumah Sakit')
            ->assertSee('Mengikuti visite bersama tim interprofesional.');

        $this->actingAs($this->internalSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/review-portofolio/'.$portfolio->id)
            ->assertOk()
            ->assertSee('Ringkasan Portofolio Rumah Sakit')
            ->assertSee('Mengikuti visite bersama tim interprofesional.');
    }

    public function test_industry_portfolio_uses_industry_units_and_operational_case_format(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $run = $this->fixtureRun('IND', '14IND');
        $portfolio = $service->ensureForRun($run, $this->admin);
        $profile = collect(PkpaIndustryPortfolio::sectionDefinition('site_profile')['fields'])
            ->mapWithKeys(fn ($field) => [$field['name'] => $field['label'].' Industri Farmasi Karawang.'])
            ->all();

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/portofolio-pkpa/'.$portfolio->id.'/bagian/site_profile', $profile)
            ->assertRedirect();
        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/portofolio-pkpa/'.$portfolio->id.'/bagian/quality_assurance', [
                'purpose' => 'Memahami penerapan sistem manajemen mutu.',
                'theory' => 'CPOB dan sistem mutu industri farmasi.',
                'activity' => 'Menelaah alur deviasi dan CAPA bersama unit QA.',
                'result' => 'Memahami hubungan investigasi, akar masalah, dan CAPA.',
            ])
            ->assertRedirect();
        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/portofolio-pkpa/'.$portfolio->id.'/studi-kasus', [
                'case_code' => 'DEV-01',
                'case_date' => '2026-07-03',
                'complaint' => 'Terjadi penyimpangan parameter proses.',
                'diagnosis' => 'Deviasi proses produksi.',
                'history' => 'Dilakukan penelusuran tahapan proses dan dokumentasi terkait.',
                'drp' => 'CPOB dan prosedur penanganan deviasi.',
                'intervention' => 'Investigasi akar masalah dan penyusunan CAPA.',
                'monitoring' => 'Apoteker memastikan investigasi dan efektivitas CAPA.',
                'conclusion' => 'CAPA ditetapkan dan dipantau efektivitasnya.',
                'references' => 'Pedoman CPOB yang berlaku.',
                'anonymization_confirmed' => '1',
            ])
            ->assertRedirect();

        $this->assertNotContains(
            'Minimal satu laporan kegiatan Industri Farmasi wajib lengkap.',
            $service->completeness($portfolio->fresh())['blocking']
        );
        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa/'.$portfolio->id)
            ->assertOk()
            ->assertSee('Portofolio Industri Farmasi')
            ->assertSee('Quality Assurance (QA)')
            ->assertSee('Studi Kasus Industri Farmasi')
            ->assertSee('Regulasi yang Digunakan')
            ->assertDontSee('Identitas Pasien');
        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/review-portofolio/'.$portfolio->id)
            ->assertOk()
            ->assertSee('Bagian Portofolio Industri Farmasi')
            ->assertSee('Menelaah alur deviasi dan CAPA bersama unit QA.');

        $docx = $service->export($portfolio->fresh(), 'docx', $this->koordinator);
        $docxText = $this->docxDocumentXml(Storage::disk('local')->path($docx->path));
        $this->assertStringContainsString('Profil Tempat PKPA Industri Farmasi', $docxText);
        $this->assertStringContainsString('FORMAT STUDI KASUS INDUSTRI FARMASI', $docxText);
        $this->assertStringContainsString('Investigasi akar masalah dan penyusunan CAPA.', $docxText);
        $this->assertStringNotContainsString('Identitas Pasien', $docxText);
    }

    public function test_puskesmas_option_selects_its_own_portfolio_template_and_sections(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $run = $this->fixtureRun('PEM', '14PKM', 'PUSKESMAS');
        $portfolio = $service->ensureForRun($run, $this->admin);
        $this->assertSame(PkpaPuskesmasPortfolio::TEMPLATE_CODE, $portfolio->template->code);

        $profile = collect(PkpaPuskesmasPortfolio::sectionDefinition('site_profile')['fields'])
            ->mapWithKeys(fn ($field) => [$field['name'] => $field['label'].' Puskesmas Karawang.'])->all();
        $service->saveSectionRecord($portfolio, 'site_profile', $profile, $this->student);
        $service->saveSectionRecord($portfolio->fresh(), 'pharmacy_management', [
            'purpose' => 'Memahami pengelolaan pelayanan kefarmasian Puskesmas.',
            'theory' => 'Standar pelayanan kefarmasian di Puskesmas.',
            'activity' => 'Mempelajari perencanaan, permintaan, penerimaan, dan pelaporan obat.',
            'result' => 'Memahami alur manajerial instalasi farmasi Puskesmas.',
        ], $this->student);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa/'.$portfolio->id)
            ->assertOk()->assertSee('Portofolio Puskesmas')->assertSee('Program Kesehatan Puskesmas')
            ->assertSee('Kegiatan Manajerial di Instalasi Farmasi Puskesmas')->assertSee('Identitas Pasien');
        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/review-portofolio/'.$portfolio->id)
            ->assertOk()->assertSee('Bagian Portofolio Puskesmas')
            ->assertSee('Mempelajari perencanaan, permintaan, penerimaan, dan pelaporan obat.');

        $docx = $service->export($portfolio->fresh(), 'docx', $this->koordinator);
        $text = $this->docxDocumentXml(Storage::disk('local')->path($docx->path));
        $this->assertStringContainsString('Profil Tempat PKPA Puskesmas', $text);
        $this->assertStringContainsString('Laporan Kegiatan: Kegiatan Manajerial di Instalasi Farmasi Puskesmas', $text);
    }

    public function test_health_office_option_uses_management_case_and_its_own_template(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $portfolio = $service->ensureForRun($this->fixtureRun('PEM', '14DK', 'DINKES'), $this->admin);
        $this->assertSame(PkpaHealthOfficePortfolio::TEMPLATE_CODE, $portfolio->template->code);
        $profile = collect(PkpaHealthOfficePortfolio::sectionDefinition('site_profile')['fields'])
            ->mapWithKeys(fn ($field) => [$field['name'] => $field['label'].' Kabupaten Karawang.'])->all();
        $service->saveSectionRecord($portfolio, 'site_profile', $profile, $this->student);
        $service->saveSectionRecord($portfolio->fresh(), 'medicine_planning', [
            'purpose' => 'Memahami perencanaan kebutuhan obat daerah.', 'theory' => 'Metode konsumsi dan morbiditas.',
            'activity' => 'Menganalisis data pemakaian dan kebutuhan obat program.',
            'result' => 'Memahami penyusunan kebutuhan berdasarkan data dan anggaran.',
        ], $this->student);
        $service->saveCase($portfolio->fresh(), [
            'case_code' => 'Analisis Stockout Obat Program', 'case_date' => '2026-07-04',
            'medication_use' => 'Gudang farmasi kabupaten, unit logistik.', 'complaint' => 'Terjadi kekosongan obat program.',
            'diagnosis' => 'Ketidaksesuaian perencanaan dengan pemakaian.', 'history' => 'Mengidentifikasi penyebab dan menyusun solusi.',
            'past_medical_history' => 'Data stok, pemakaian, distribusi, dan laporan logistik.',
            'drp' => 'Analisis dilakukan dengan pendekatan 5 Why dan indikator stok.',
            'intervention' => 'Perbaikan perencanaan dan jadwal pemantauan stok.',
            'monitoring' => 'Terapkan pemantauan stok minimum dan evaluasi bulanan.',
            'conclusion' => 'Rekomendasi diarahkan untuk mencegah stockout berulang.', 'references' => 'Pedoman pengelolaan obat pemerintah.',
            'anonymization_confirmed' => true,
        ], $this->student);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa/'.$portfolio->id)->assertOk()
            ->assertSee('Portofolio Dinas Kesehatan')->assertSee('Pengadaan Obat Pemerintah')
            ->assertSee('Studi Kasus Dinas Kesehatan')->assertSee('Data Kasus')->assertDontSee('Identitas Pasien');
        $this->actingAs($this->internalSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/review-portofolio/'.$portfolio->id)->assertOk()
            ->assertSee('Ringkasan Portofolio Dinas Kesehatan')->assertSee('Menganalisis data pemakaian dan kebutuhan obat program.');
        $docx = $service->export($portfolio->fresh(), 'docx', $this->koordinator);
        $text = $this->docxDocumentXml(Storage::disk('local')->path($docx->path));
        $this->assertStringContainsString('FORMAT STUDI KASUS DINAS KESEHATAN', $text);
        $this->assertStringContainsString('Analisis Stockout Obat Program', $text);
        $this->assertStringNotContainsString('Identitas Pasien', $text);
    }

    public function test_loka_pom_option_uses_surveillance_topics_and_regulatory_case(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $portfolio = $service->ensureForRun($this->fixtureRun('PEM', '14LP', 'LOKAPOM'), $this->admin);
        $this->assertSame(PkpaLokaPomPortfolio::TEMPLATE_CODE, $portfolio->template->code);
        $profile = collect(PkpaLokaPomPortfolio::sectionDefinition('site_profile')['fields'])
            ->mapWithKeys(fn ($field) => [$field['name'] => $field['label'].' Loka POM Karawang.'])->all();
        $service->saveSectionRecord($portfolio, 'site_profile', $profile, $this->student);
        $service->saveSectionRecord($portfolio->fresh(), 'marketplace_surveillance', [
            'purpose' => 'Memahami pengawasan produk pada perdagangan elektronik.',
            'theory' => 'Regulasi peredaran obat dan makanan melalui sistem elektronik.',
            'activity' => 'Mengamati penelusuran produk dan pemeriksaan informasi izin edar.',
            'result' => 'Memahami identifikasi temuan dan tindak lanjut pengawasan marketplace.',
        ], $this->student);
        $service->saveCase($portfolio->fresh(), [
            'case_code' => 'Produk Tanpa Izin Edar di Marketplace', 'case_date' => '2026-07-05',
            'complaint' => 'Ditemukan produk yang dipasarkan tanpa informasi izin edar yang sah.',
            'diagnosis' => 'Dugaan pelanggaran peredaran produk.', 'drp' => 'Analisis berdasarkan ketentuan izin edar dan penandaan.',
            'intervention' => 'Verifikasi temuan dan rekomendasi tindak lanjut pengawasan.',
            'monitoring' => 'Mampu menelaah informasi produk dan regulasi terkait.',
            'conclusion' => 'Temuan memerlukan tindak lanjut sesuai kewenangan pengawasan.',
            'references' => 'Peraturan BPOM terkait izin edar dan pengawasan daring.', 'anonymization_confirmed' => true,
        ], $this->student);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa/'.$portfolio->id)->assertOk()
            ->assertSee('Portofolio Loka POM')->assertSee('Pengawasan Produk di Marketplace')
            ->assertSee('Studi Kasus Loka POM')->assertSee('Analisis Berdasarkan Regulasi')->assertDontSee('Identitas Pasien');
        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/review-portofolio/'.$portfolio->id)->assertOk()
            ->assertSee('Bagian Portofolio Loka POM')->assertSee('Mengamati penelusuran produk dan pemeriksaan informasi izin edar.');
        $docx = $service->export($portfolio->fresh(), 'docx', $this->koordinator);
        $text = $this->docxDocumentXml(Storage::disk('local')->path($docx->path));
        $this->assertStringContainsString('FORMAT STUDI KASUS LOKA POM', $text);
        $this->assertStringContainsString('Produk Tanpa Izin Edar di Marketplace', $text);
        $this->assertStringNotContainsString('Identitas Pasien', $text);
    }

    public function test_student_can_store_apotek_section_record_from_portal(): void
    {
        $portfolio = app(PkpaPortfolioBuilderService::class)->ensureForRun($this->run, $this->admin);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/portofolio-pkpa/'.$portfolio->id.'/bagian/site_profile', [
                'overview' => 'Apotek melayani resep dan swamedikasi.',
                'history' => 'Apotek berdiri untuk menyediakan layanan kefarmasian bagi masyarakat.',
                'vision' => 'Menjadi apotek yang aman, bermutu, dan berorientasi pada pasien.',
                'mission' => 'Memberikan pelayanan kefarmasian yang profesional dan bertanggung jawab.',
                'facilities' => 'Area pelayanan, ruang penyimpanan, dan perangkat pendukung operasional.',
                'human_resources' => 'Apoteker penanggung jawab, tenaga teknis kefarmasian, dan kasir.',
                'operational_hours' => '08.00 - 21.00',
                'pharmacy_services' => 'Pelayanan resep, PIO, konseling.',
                'supply_management' => 'Perencanaan, pengadaan, penerimaan, penyimpanan, dan pengendalian persediaan.',
                'information_system' => 'Sistem komputerisasi untuk resep, stok, dan pelaporan operasional.',
                'prescription_service_types' => 'Resep umum, resep BPJS, resep racikan, dan resep nonracikan.',
                'available_medicines' => 'Obat bebas, obat bebas terbatas, obat keras, vitamin, dan alat kesehatan.',
                'site_analysis' => 'Tempat PKPA menyediakan pembelajaran alur pelayanan dan pengelolaan apotek yang lengkap.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pkpa_portfolio_section_records', [
            'pkpa_rotation_portfolio_id' => $portfolio->id,
            'section_code' => 'site_profile',
            'status' => 'completed',
        ]);
    }

    public function test_student_can_open_and_save_pbf_portfolio_sections_from_portal(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $run = $this->fixtureRun('PBF', '14PBF');
        $portfolio = $service->ensureForRun($run, $this->admin);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa')
            ->assertOk()
            ->assertSee('Pedagang Besar Farmasi');

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/portofolio-pkpa/'.$portfolio->id.'/bagian/procurement', [
                'purpose' => 'Memahami proses pengadaan sesuai CDOB.',
                'theory' => 'Pedoman CDOB dan SOP pengadaan.',
                'activity' => 'Mengamati perencanaan kebutuhan dan pemesanan.',
                'result' => 'Memahami alur pengadaan dan dokumentasinya.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pkpa_portfolio_section_records', [
            'pkpa_rotation_portfolio_id' => $portfolio->id,
            'section_code' => 'procurement',
            'status' => 'completed',
        ]);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa/'.$portfolio->id)
            ->assertOk()
            ->assertSee('Laporan Kegiatan PKPA PBF')
            ->assertSee('Laporan Kegiatan: Pengadaan');
    }

    public function test_student_can_save_repeated_manual_apotek_report_activities_in_creation_order(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $portfolio = $service->ensureForRun($this->run, $this->admin);
        $service->saveReportActivity($portfolio, 'supply_management', [
            'activity' => 'Pemeriksaan stok awal',
            'purpose' => 'Memahami pengadaan sediaan.',
            'description' => 'Mengamati pemesanan kepada pemasok.',
            'result' => 'Memahami alur pemesanan obat.',
        ], $this->student);
        $entry = $service->saveReportActivity($portfolio->fresh(), 'supply_management', [
            'activity' => 'Pemesanan obat',
            'purpose' => 'Memahami kebutuhan persediaan.',
            'description' => 'Menelaah stok dan kebutuhan obat.',
            'result' => 'Memahami dasar perencanaan persediaan.',
        ], $this->student);

        $this->assertSame(['Pemeriksaan stok awal', 'Pemesanan obat'], collect($entry->manual_payload['activity_entries'])->pluck('activity')->all());
        $this->assertSame('completed', $entry->status);
        $this->assertSame('Kegiatan 1: Pemeriksaan stok awal', PkpaApotekPortfolio::summaryLines('supply_management', $entry->manual_payload)[0]);

        $updated = $service->saveReportActivity($portfolio->fresh(), 'supply_management', [
            'activity' => 'Pemeriksaan stok dan FEFO',
            'purpose' => 'Memahami pengadaan sediaan dan pemasok.',
            'description' => 'Mengamati pemesanan dan penerimaan awal.',
            'result' => 'Memahami alur pemesanan obat ke pemasok.',
        ], $this->student, $entry->manual_payload['activity_entries'][0]['id']);
        $this->assertCount(2, $updated->manual_payload['activity_entries']);
        $this->assertSame(['Pemeriksaan stok dan FEFO', 'Pemesanan obat'], collect($updated->manual_payload['activity_entries'])->pluck('activity')->all());
        $this->assertSame('Memahami pengadaan sediaan dan pemasok.', $updated->manual_payload['activity_entries'][0]['purpose']);
    }

    public function test_portfolio_text_is_normalized_for_pasted_content_and_lists(): void
    {
        $formatter = app(PkpaPortfolioTextFormatter::class);

        $this->assertSame(
            "Tujuan kegiatan\n\n- Tahap pertama\n2. Tahap kedua",
            $formatter->normalize("  Tujuan   kegiatan \r\n\r\n\r\n•  Tahap   pertama\r\n(2) Tahap kedua\t")
        );

        $portfolio = app(PkpaPortfolioBuilderService::class)->ensureForRun($this->run, $this->admin);
        $record = app(PkpaPortfolioBuilderService::class)->saveReportActivity($portfolio, 'supply_management', [
            'activity' => '  Pemeriksaan   stok  ',
            'purpose' => "Memahami   persediaan.\n\n\n•  Menilai stok",
            'description' => "Mengamati\tproses.\n-   Mencatat hasil",
            'result' => 'Hasil dicatat  dengan baik .',
        ], $this->student);
        $entry = $record->manual_payload['activity_entries'][0];

        $this->assertSame('Pemeriksaan stok', $entry['activity']);
        $this->assertSame("Memahami persediaan.\n\n- Menilai stok", $entry['purpose']);
        $this->assertSame("Mengamati proses.\n- Mencatat hasil", $entry['description']);
        $this->assertSame('Hasil dicatat dengan baik.', $entry['result']);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa/'.$portfolio->id)
            ->assertOk()
            ->assertSee('data-portfolio-writing-assistant', false);
    }

    public function test_apotek_portfolio_detail_pages_render_new_structure_for_three_portals(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $portfolio = $service->ensureForRun($this->run, $this->admin);
        $this->fillApotekSections($service, $portfolio);
        $service->saveCase($portfolio->fresh(), ['case_code' => 'CASE-PREVIEW', 'complaint' => 'Batuk ringan', 'intervention' => 'Edukasi penggunaan obat', 'anonymization_confirmed' => true], $this->student);
        $service->saveReflection($portfolio->fresh(), ['week_number' => 1, 'achievement' => 'Target mingguan tercapai', 'next_plan' => 'Memperdalam konseling pasien'], $this->student);
        $service->saveSelfAssessment($portfolio->fresh(), ['aspect' => 'Komunikasi', 'score' => 4, 'evidence_experience' => 'Mendampingi konseling pasien'], $this->student);
        $service->saveDocumentation($portfolio->fresh(), ['activity' => 'Dokumentasi pelayanan resep', 'category' => 'Pelayanan Resep', 'description' => 'Bukti kegiatan layanan harian', 'anonymization_confirmed' => true, 'consent_confirmed' => true], null, $this->student);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa/'.$portfolio->id)
            ->assertOk()
            ->assertSee('Preview Hasil Isian')
            ->assertSee('CASE-PREVIEW')
            ->assertSee('Dokumentasi pelayanan resep')
            ->assertSee('Struktur Portofolio Apotek')
            ->assertSee('Profil Tempat PKPA')
            ->assertSee('Laporan Kegiatan PKPA')
            ->assertSee('Topik Laporan')
            ->assertSee('Referensi Tugas')
            ->assertSee('Tambah Kegiatan')
            ->assertSee('Simpan Kegiatan')
            ->assertSee('Buka Pakta Integritas');

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa/'.$portfolio->id.'?report=narcotics_psychotropics')
            ->assertOk()
            ->assertSee('Pengelolaan Narkotika dan Psikotropika')
            ->assertSee('Penyimpanan')
            ->assertSee('Pelaporan')
            ->assertSee('Dokumentasi')
            ->assertSee('Tambah Kegiatan')
            ->assertSee('Simpan Kegiatan')
            ->assertDontSee('Pilih kegiatan');

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

    public function test_supervisor_portfolio_queues_are_grouped_by_practice_domain(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $service->ensureForRun($this->run, $this->admin);
        $service->ensureForRun($this->fixtureRun('RS', '14RS'), $this->admin);

        $this->actingAs($this->internalSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/review-portofolio')
            ->assertOk()
            ->assertSee('Wahana PKPA')
            ->assertSee('Apotek')
            ->assertSee('Rumah Sakit');

        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/review-portofolio')
            ->assertOk()
            ->assertSee('Wahana PKPA')
            ->assertSee('Apotek')
            ->assertSee('Rumah Sakit');
    }

    public function test_student_can_open_integrity_sheet_and_public_verification_page(): void
    {
        $service = app(PkpaPortfolioBuilderService::class);
        $portfolio = $service->ensureForRun($this->run, $this->admin);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa/'.$portfolio->id.'/pakta-integritas')
            ->assertOk()
            ->assertSee('Pakta Integritas Mahasiswa PKPA')
            ->assertSee('Setuju Pakta Integritas')
            ->assertSee('Tidak Setuju')
            ->assertSee('QR Validasi');

        $service->acknowledgeIntegrity($portfolio->fresh(), $this->student);
        $signedUrl = URL::signedRoute('student.pkpa-portfolios.integrity.verify', [
            'portfolio' => $portfolio->id,
            'token' => $service->integrityVerificationToken($portfolio->fresh()),
        ]);

        $this->get($signedUrl)
            ->assertOk()
            ->assertSee('Sudah disetujui secara elektronik')
            ->assertSee('Scan untuk mengecek keaslian lembar persetujuan ini.');
    }

    private function fixtureRun(string $domainCode = 'APT', string $suffix = '14', ?string $domainOptionCode = null): PkpaRotationRun
    {
        $program = app(PkpaProgramService::class)->create(['code' => 'PKPA-'.$suffix, 'name' => 'Program Tahap '.$suffix, 'academic_year' => '2026/2027', 'cohort_name' => 'Demo', 'start_date' => '2026-07-01', 'end_date' => '2026-07-31'], $this->admin);
        $domain = PkpaPracticeDomain::where('code', $domainCode)->firstOrFail();
        $domainOption = $domainOptionCode
            ? PkpaPracticeDomainOption::where('practice_domain_id', $domain->id)->where('code', $domainOptionCode)->firstOrFail()
            : null;
        $programDomain = $program->domains()->where('practice_domain_id', $domain->id)->firstOrFail();
        $siteName = match ($domainCode) {
            'RS' => 'Rumah Sakit Tahap 14',
            'PBF' => 'PBF Tahap 14',
            'IND' => 'Industri Farmasi Tahap 14',
            'PEM' => match ($domainOptionCode) {
                'PUSKESMAS' => 'Puskesmas Tahap 14',
                'DINKES' => 'Dinas Kesehatan Tahap 14',
                'LOKAPOM' => 'Loka POM Tahap 14',
                default => 'Pemerintahan Tahap 14',
            },
            default => 'Apotek Tahap 14',
        };
        $site = PkpaPracticeSite::create(['practice_domain_id' => $domain->id, 'practice_domain_option_id' => $domainOption?->id, 'code' => $domainCode.'-'.$suffix, 'name' => $siteName, 'address' => 'Karawang', 'city' => 'Karawang', 'province' => 'Jawa Barat', 'cooperation_start_date' => '2026-01-01', 'cooperation_end_date' => '2026-12-31', 'status' => 'active', 'is_active' => true]);
        $programSite = PkpaProgramSite::create(['pkpa_program_id' => $program->id, 'practice_site_id' => $site->id, 'pkpa_program_domain_id' => $programDomain->id, 'practice_domain_id' => $domain->id, 'practice_domain_option_id' => $domainOption?->id, 'status' => 'active', 'is_active' => true]);
        $enrollment = PkpaEnrollment::create(['pkpa_program_id' => $program->id, 'core_user_id' => $this->student->core_user_id, 'student_number' => '2400'.$suffix, 'student_name_snapshot' => 'Mahasiswa Tahap 14', 'student_email_snapshot' => $this->student->email, 'status' => 'active', 'core_account_status_snapshot' => 'active']);
        app(PkpaEnrollmentRequirementService::class)->ensureRequirements($enrollment, $this->admin);
        $requirement = $enrollment->requirements()->where('practice_domain_id', $domain->id)->firstOrFail();
        $plan = PkpaPlacementPlan::create(['pkpa_program_id' => $program->id, 'code' => 'PLAN-'.$suffix, 'name' => 'Plan '.$suffix, 'version_number' => 1, 'status' => 'locked', 'is_current' => true, 'current_key' => 'PROGRAM:'.$program->id, 'validation_status' => 'valid']);
        $publication = PkpaPlacementPublication::create(['pkpa_program_id' => $program->id, 'pkpa_placement_plan_id' => $plan->id, 'publication_number' => 1, 'revision_number' => 0, 'code' => 'PUB-'.$suffix, 'title' => 'Publikasi '.$suffix, 'status' => 'published', 'is_current' => true, 'current_key' => 'PROGRAM:'.$program->id, 'published_at' => now()]);
        $assignment = PkpaPublishedAssignment::create(['pkpa_placement_publication_id' => $publication->id, 'pkpa_enrollment_id' => $enrollment->id, 'pkpa_enrollment_requirement_id' => $requirement->id, 'practice_domain_id' => $domain->id, 'practice_domain_option_id' => $domainOption?->id, 'practice_site_id' => $site->id, 'program_site_id' => $programSite->id, 'student_core_user_id' => $this->student->core_user_id, 'student_number_snapshot' => '2400'.$suffix, 'student_name_snapshot' => 'Mahasiswa Tahap 14', 'practice_domain_name_snapshot' => $domain->name, 'practice_site_name_snapshot' => $site->name, 'start_date' => '2026-07-01', 'end_date' => '2026-07-07', 'status' => 'scheduled']);
        $run = PkpaRotationRun::create(['pkpa_program_id' => $program->id, 'pkpa_enrollment_id' => $enrollment->id, 'pkpa_enrollment_requirement_id' => $requirement->id, 'current_placement_publication_id' => $publication->id, 'origin_published_assignment_id' => $assignment->id, 'current_published_assignment_id' => $assignment->id, 'practice_domain_id' => $domain->id, 'practice_domain_option_id' => $domainOption?->id, 'practice_site_id' => $site->id, 'student_core_user_id' => $this->student->core_user_id, 'scheduled_start_date' => '2026-07-01', 'scheduled_end_date' => '2026-07-07', 'status' => 'active', 'operational_status' => 'running', 'publication_sync_status' => 'synced', 'current_key' => 'REQ:'.$requirement->id]);
        foreach ([['field', $this->fieldSupervisor], ['internal', $this->internalSupervisor]] as [$type, $user]) {
            PkpaRotationSupervisorHistory::create(['pkpa_rotation_run_id' => $run->id, 'supervisor_type' => $type, 'core_user_id' => $user->core_user_id, 'name_snapshot' => $user->name, 'role_snapshot' => $type, 'effective_start_date' => '2026-07-01', 'status' => 'active', 'active_key' => $run->id.':'.$type]);
        }
        $run->logbookEntries()->create(['entry_date' => '2026-07-01', 'title' => 'Orientasi '.$siteName, 'activity_summary' => 'Mempelajari alur pelayanan', 'learning_outcomes' => 'Memahami pelayanan kefarmasian', 'reflection' => 'Perlu memperkuat komunikasi pasien', 'status' => 'internal_approved', 'entry_key' => $run->id.':2026-07-01']);
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
                $payload[$field['name']] = ($field['type'] ?? null) === 'multiselect'
                    ? [($field['options'] ?? [])[0] ?? 'Kegiatan demo']
                    : $field['label'].' demo';
            }

            $service->saveSectionRecord($portfolio->fresh(), $code, $payload, $this->student);
        }
    }
}
