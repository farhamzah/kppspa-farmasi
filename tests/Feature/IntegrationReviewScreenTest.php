<?php

namespace Tests\Feature;

use App\Models\KpDocumentRequirement;
use App\Models\KpPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IntegrationReviewScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('admin-integration@test.local', ['admin']);
        $this->koordinator = $this->makeUser('koor-integration@test.local', ['koordinator_kp']);
        $this->mahasiswa = $this->makeUser('student-integration@test.local', ['mahasiswa']);
    }

    public function test_admin_and_koordinator_can_open_tu_review_but_student_cannot(): void
    {
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get(route('management.integration.tu-payload-preview'))
            ->assertOk()
            ->assertSee('Pratinjau Dokumen KP untuk TU')
            ->assertSee('KP_PLACEMENT_LETTER')
            ->assertSee('Permintaan keluar')
            ->assertDontSee('storage/app', false)
            ->assertDontSee('signed_url', false);

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get(route('management.integration.tu-payload-preview'))
            ->assertOk()
            ->assertSee('Pemeriksaan Integrasi TU');

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/integration/tu-payload-preview')
            ->assertForbidden();
    }

    public function test_admin_and_koordinator_can_open_safa_review_but_student_cannot(): void
    {
        $this->createPublicPeriod();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get(route('management.integration.safa-public-info-preview'))
            ->assertOk()
            ->assertSee('Pratinjau Informasi Publik KP untuk SAFA')
            ->assertSee('KP Farmasi Public')
            ->assertSee('Persyaratan Umum')
            ->assertDontSee('final_score', false)
            ->assertDontSee('student_documents', false)
            ->assertDontSee('individual_registration_status', false)
            ->assertDontSee('storage/app', false);

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get(route('management.integration.safa-public-info-preview'))
            ->assertOk()
            ->assertSee('Pemeriksaan Integrasi SAFA');

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/integration/safa-public-info-preview')
            ->assertForbidden();
    }

    public function test_json_previews_are_role_limited_sanitized_and_read_only(): void
    {
        $this->createPublicPeriod();

        $before = [
            'users' => DB::table('users')->count(),
            'periods' => DB::table('kp_periods')->count(),
            'requirements' => DB::table('kp_document_requirements')->count(),
        ];

        $tuJson = $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->getJson(route('management.integration.tu-payload-preview.json'))
            ->assertOk()
            ->assertJsonPath('dry_run', true)
            ->assertJsonPath('external_request_sent', false)
            ->content();

        $safaJson = $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->getJson(route('management.integration.safa-public-info-preview.json'))
            ->assertOk()
            ->assertJsonPath('dry_run', true)
            ->assertJsonPath('external_request_sent', false)
            ->assertJsonPath('private_data_excluded', true)
            ->content();

        foreach ([$tuJson, $safaJson] as $content) {
            $this->assertStringNotContainsString('storage/app', $content);
            $this->assertStringNotContainsString('signed_url', $content);
            $this->assertStringNotContainsString('password', strtolower($content));
            $this->assertStringNotContainsString('secret', strtolower($content));
            $this->assertStringNotContainsString('token', strtolower($content));
        }

        $this->assertStringNotContainsString('final_score', $safaJson);
        $this->assertStringNotContainsString('student_documents', $safaJson);
        $this->assertStringNotContainsString('individual_registration_status', $safaJson);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->getJson('/management/integration/tu-payload-preview.json')
            ->assertForbidden();

        $this->assertSame($before['users'], DB::table('users')->count());
        $this->assertSame($before['periods'], DB::table('kp_periods')->count());
        $this->assertSame($before['requirements'], DB::table('kp_document_requirements')->count());
    }

    private function makeUser(string $email, array $roles): User
    {
        $user = User::create([
            'name' => 'Integration Reviewer',
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user;
    }

    private function createPublicPeriod(): void
    {
        $period = KpPeriod::create([
            'name' => 'KP Farmasi Public',
            'academic_year' => '2025/2026',
            'semester' => 'genap',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'document_verification_start_at' => now()->subDay(),
            'document_verification_end_at' => now()->addDays(2),
            'selection_start_at' => now()->subDay(),
            'selection_end_at' => now()->addDays(3),
            'kp_start_date' => now()->addWeek()->toDateString(),
            'kp_end_date' => now()->addMonth()->toDateString(),
            'status' => 'dibuka',
            'description' => 'Pengumuman umum KP.',
        ]);

        KpDocumentRequirement::create([
            'kp_period_id' => $period->id,
            'name' => 'KRS',
            'description' => 'Dokumen KRS aktif.',
            'is_required' => true,
            'allowed_file_types' => 'pdf,jpg',
            'max_file_size_mb' => 5,
            'sort_order' => 1,
            'status' => 'aktif',
        ]);
    }
}
