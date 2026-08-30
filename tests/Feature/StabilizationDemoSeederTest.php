<?php

namespace Tests\Feature;

use App\Models\KpFinalScore;
use App\Models\KpPeriod;
use App\Models\PkpaPlacementPublication;
use App\Models\Role;
use App\Models\User;
use App\Services\CoreProfileReadService;
use Database\Seeders\DemoEndToEndSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StabilizationDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_opens_with_no_cache_headers(): void
    {
        $response = $this->get('/login')
            ->assertOk()
            ->assertSee('MY PKPA')
            ->assertSee('toggle-password')
            ->assertSee('Tampilkan kata sandi');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    }

    public function test_koordinator_can_login_select_role_logout_and_login_again(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::create([
            'name' => 'Koordinator Test',
            'email' => 'koordinator-login@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->roles()->sync(Role::whereIn('name', ['koordinator_kp', 'pembimbing_dalam'])->pluck('id'));

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/pilih-role');

        $this->assertAuthenticatedAs($user);

        $this->post('/set-role/koordinator_kp')
            ->assertRedirect('/koordinator/dashboard');
        $this->assertSame('koordinator_kp', session('active_role'));

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/pilih-role');
        $this->assertAuthenticatedAs($user);
    }

    public function test_demo_end_to_end_seeder_is_idempotent_and_creates_demo_lifecycle(): void
    {
        $this->seed(DemoEndToEndSeeder::class);
        $this->seed(DemoEndToEndSeeder::class);

        $this->assertSame(1, User::where('email', 'mahasiswa@sikp.test')->count());
        $this->assertSame(1, User::where('email', 'mahasiswa2@sikp.test')->count());
        $this->assertSame(1, KpPeriod::where('name', 'KP Farmasi Demo 2026')->count());

        $finalScore = KpFinalScore::whereHas('assignment.student.user', fn ($query) => $query->where('email', 'mahasiswa@sikp.test'))->first();
        $this->assertNotNull($finalScore);
        $this->assertSame('published', $finalScore->status);
        $this->assertSame('A', $finalScore->final_grade);
    }

    public function test_demo_users_can_open_recaps_dashboards_and_score_pages(): void
    {
        $this->seed(DemoEndToEndSeeder::class);

        $admin = User::where('email', 'admin@sikp.test')->firstOrFail();
        $studentA = User::where('email', 'mahasiswa@sikp.test')->firstOrFail();
        $studentB = User::where('email', 'mahasiswa2@sikp.test')->firstOrFail();

        $this->actingAs($admin)->withSession(['active_role' => 'admin'])
            ->get('/management/recaps')
            ->assertOk()
            ->assertSee('Rekap, Pemantauan, dan Ekspor PKPA');

        $this->actingAs($studentA)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/dashboard')
            ->assertOk()
            ->assertSee('Dashboard Mahasiswa');

        $this->actingAs($studentA)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/nilai')
            ->assertOk()
            ->assertSee('Nilai Akhir PKPA')
            ->assertSee('A');

        $this->actingAs($studentB)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/nilai')
            ->assertOk()
            ->assertSee('Nilai sedang diproses');
    }

    public function test_pkpa_demo_publication_assignments_include_internal_and_field_supervisors(): void
    {
        $this->seed(\Database\Seeders\PkpaDemoEndToEndSeeder::class);

        $publication = PkpaPlacementPublication::with('assignments.supervisors')
            ->where('code', 'PUB-DEMO-2026')
            ->firstOrFail();

        $this->assertGreaterThanOrEqual(2, $publication->assignments->count());

        foreach ($publication->assignments as $assignment) {
            $types = $assignment->supervisors->pluck('supervisor_type')->all();
            $this->assertContains('internal', $types);
            $this->assertContains('field', $types);
        }
    }

    public function test_legacy_demo_mode_does_not_require_core_profile_connection(): void
    {
        config()->set('kp_master_data.read_mode', 'legacy');
        config()->set('core_farmasi.read_mode', 'legacy');
        config()->set('core_farmasi.enabled', false);
        config()->set('core_farmasi.base_url', null);
        config()->set('core_farmasi.profile_url', null);
        config()->set('core_farmasi.storage_public_path', null);
        config()->set('database.connections.core', [
            'driver' => 'mysql',
            'host' => '192.0.2.1',
            'port' => 3306,
            'database' => 'missing_core',
            'username' => 'missing',
            'password' => 'missing',
        ]);

        $this->seed(RoleSeeder::class);
        $student = User::create([
            'name' => 'Mahasiswa Demo',
            'email' => 'mahasiswa-demo@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
            'profile_completed' => true,
            'core_user_id' => 999,
        ]);
        $student->roles()->sync(Role::where('name', 'mahasiswa')->pluck('id'));
        $student->student()->create(['nim' => '240001']);

        $this->assertNull(app(CoreProfileReadService::class)->profilePhotoUrlFor($student));

        $this->actingAs($student)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/dashboard')
            ->assertOk()
            ->assertSee('Dashboard Mahasiswa');
    }

    public function test_friendly_error_pages_and_management_permission(): void
    {
        $this->seed(DemoEndToEndSeeder::class);
        $student = User::where('email', 'mahasiswa@sikp.test')->firstOrFail();

        $this->actingAs($student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/recaps')
            ->assertForbidden()
            ->assertSee('Akses Ditolak');

        $this->get('/route-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman Tidak Ditemukan');

        $this->assertStringContainsString('Sesi Kedaluwarsa', view('errors.419')->render());
        $this->assertStringContainsString('Terjadi Kesalahan', view('errors.500')->render());
    }
}
