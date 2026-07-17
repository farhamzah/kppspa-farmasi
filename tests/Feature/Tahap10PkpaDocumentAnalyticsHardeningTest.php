<?php

namespace Tests\Feature;

use App\Models\PkpaDocumentNumberingRule;
use App\Models\PkpaDocumentTemplate;
use App\Models\PkpaDocumentType;
use App\Models\PkpaGeneratedDocument;
use App\Models\PkpaGeneratedDocumentVersion;
use App\Models\PkpaProgram;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpaDocumentDistributionService;
use App\Services\PkpaDocumentFileService;
use App\Services\PkpaDocumentGenerationService;
use App\Services\PkpaDocumentPlaceholderService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class Tahap10PkpaDocumentAnalyticsHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $student;
    private User $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class]);
        $this->admin = $this->makeUser('admin10@test.local', ['admin'], 'CORE-ADMIN-10');
        $this->koordinator = $this->makeUser('koor10@test.local', ['koordinator_kp'], 'CORE-KOOR-10');
        $this->student = $this->makeUser('student10@test.local', ['mahasiswa'], 'CORE-STUDENT-10');
        $this->otherStudent = $this->makeUser('other10@test.local', ['mahasiswa'], 'CORE-OTHER-10');
    }

    public function test_document_schema_generation_numbering_publish_download_and_idor(): void
    {
        foreach (['pkpa_document_types', 'pkpa_document_templates', 'pkpa_document_numbering_rules', 'pkpa_generated_documents', 'pkpa_generated_document_versions', 'pkpa_document_recipients', 'pkpa_document_generation_jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} harus tersedia.");
        }
        $type = PkpaDocumentType::where('code', 'surat_penempatan_mahasiswa')->firstOrFail();
        $program = PkpaProgram::create([
            'code' => 'PKPA-10',
            'name' => 'Program Tahap 10',
            'academic_year' => '2026/2027',
            'cohort_name' => 'Angkatan 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_active' => true,
        ]);
        $template = PkpaDocumentTemplate::create([
            'pkpa_document_type_id' => $type->id,
            'pkpa_program_id' => $program->id,
            'code' => 'TPL-10',
            'name' => 'Template Tahap 10',
            'version_number' => 1,
            'status' => 'active',
            'is_current' => true,
            'current_key' => 'TYPE:'.$type->id.':PROGRAM:'.$program->id,
            'template_engine' => 'html',
            'template_content' => 'Dokumen Internal MY PSPA - {{ program.name }} - {{ student.name }} - {{ generated_document.number }}',
            'available_placeholders' => PkpaDocumentPlaceholderService::PLACEHOLDERS,
        ]);
        PkpaDocumentNumberingRule::create([
            'pkpa_document_type_id' => $type->id,
            'pkpa_program_id' => $program->id,
            'code' => 'NUM-10',
            'name' => 'Nomor Tahap 10',
            'pattern' => 'UAT/{sequence}/{type}/{year}',
            'sequence_scope' => 'document_type',
            'reset_policy' => 'never',
            'status' => 'active',
        ]);

        $document = app(PkpaDocumentGenerationService::class)->createDraft($type, [
            'pkpa_program_id' => $program->id,
            'template_id' => $template->id,
            'scope_type' => 'custom',
            'title' => 'Surat Penempatan Testing',
            'formats' => ['docx', 'pdf', 'xlsx', 'csv'],
            'context' => [
                'program' => ['name' => $program->name, 'code' => $program->code, 'academic_year' => $program->academic_year],
                'student' => ['name' => 'Mahasiswa Tahap 10', 'npm' => '260010', 'group' => 'Kelompok A'],
                'rows' => [['260010', '=FORMULA', 'Apotek', 'Tempat Test', '2026-07-01 - 2026-07-05']],
            ],
        ], $this->admin);
        app(PkpaDocumentDistributionService::class)->ensurePortalRecipient($document, [
            'recipient_type' => 'student',
            'core_user_id' => $this->student->core_user_id,
            'name_snapshot' => 'Mahasiswa Tahap 10',
        ]);
        $document = app(PkpaDocumentGenerationService::class)->generate($document, ['docx', 'pdf', 'xlsx', 'csv'], $this->admin);

        $this->assertSame(4, $document->versions()->count());
        foreach ($document->versions as $version) {
            Storage::disk('local')->assertExists($version->path);
            $this->assertNotEmpty($version->checksum);
        }
        $this->assertOfficeZip($document->versions->firstWhere('output_format', 'docx'));
        $this->assertOfficeZip($document->versions->firstWhere('output_format', 'xlsx'));
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($document->versions->firstWhere('output_format', 'pdf')->path));
        $this->assertStringContainsString("'=FORMULA", Storage::disk('local')->get($document->versions->firstWhere('output_format', 'csv')->path));

        $approved = app(PkpaDocumentGenerationService::class)->approve($document, $this->koordinator);
        $published = app(PkpaDocumentGenerationService::class)->publish($approved, $this->koordinator);
        $this->assertSame('published', $published->status);
        $this->assertSame('UAT/0001/surat_penempatan_mahasiswa/2026', $published->document_number);
        $this->assertDatabaseHas('pkpa_document_distribution_logs', ['channel' => 'email', 'status' => 'skipped']);

        $version = $published->versions()->where('output_format', 'docx')->firstOrFail();
        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get("/mahasiswa/dokumen-pkpa/{$version->id}/download")
            ->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->actingAs($this->otherStudent)->withSession(['active_role' => 'mahasiswa'])
            ->get("/mahasiswa/dokumen-pkpa/{$version->id}/download")
            ->assertForbidden();

        $this->expectException(ValidationException::class);
        app(PkpaDocumentGenerationService::class)->generate($published->fresh(), ['docx'], $this->admin);
    }

    public function test_placeholder_upload_health_analytics_routes_and_commands(): void
    {
        $this->assertDatabaseHas('pkpa_document_types', ['code' => 'transkrip_internal_pkpa', 'name' => 'Transkrip Internal PKPA']);
        $this->expectException(ValidationException::class);
        app(PkpaDocumentPlaceholderService::class)->validateContent('Halo {{ tidak.diizinkan }}');
    }

    public function test_security_health_analytics_and_file_hardening(): void
    {
        $fileService = app(PkpaDocumentFileService::class);
        try {
            $fileService->validateUpload(UploadedFile::fake()->create('dokumen.pdf.exe', 1, 'application/pdf'));
            $this->fail('Executable upload harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('executable', strtolower($exception->getMessage()));
        }

        $this->get('/health')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'app' => 'MY PSPA'])
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertDontSee(config('database.connections.'.config('database.default').'.database'));

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/admin/health')
            ->assertForbidden();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/admin/health')
            ->assertOk()
            ->assertJsonPath('app', 'MY PSPA');
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-analytics')
            ->assertOk()
            ->assertSee('Pelaporan dan Analytics');
        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/pkpa-documents')
            ->assertForbidden();
        $this->artisan('pkpa:queue-health --json')->assertExitCode(0);
        $this->artisan('pkpa:document-orphan-audit --json')->assertExitCode(0);
    }

    private function assertOfficeZip(PkpaGeneratedDocumentVersion $version): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pkpa-test-office-');
        file_put_contents($tmp, Storage::disk($version->disk)->get($version->path));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
        $zip->close();
        @unlink($tmp);
    }

    private function makeUser(string $email, array $roles, string $coreUserId): User
    {
        $user = User::factory()->create(['name' => str($email)->before('@')->headline(), 'email' => $email, 'password' => Hash::make('password'), 'status' => 'active', 'profile_completed' => true, 'core_user_id' => $coreUserId]);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user->load('roles');
    }
}
