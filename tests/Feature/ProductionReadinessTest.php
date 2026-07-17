<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_env_example_contains_safe_production_relevant_placeholders(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('APP_NAME="MY PSPA"', $envExample);
        $this->assertStringContainsString('APP_ENV=local', $envExample);
        $this->assertStringContainsString('APP_DEBUG=true', $envExample);
        $this->assertStringContainsString('APP_URL=http://127.0.0.1:8000', $envExample);
        $this->assertStringContainsString('SESSION_DRIVER=file', $envExample);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=false', $envExample);
        $this->assertStringContainsString('SESSION_SAME_SITE=lax', $envExample);
        $this->assertStringContainsString('FILESYSTEM_DISK=local', $envExample);
        $this->assertStringContainsString('QUEUE_CONNECTION=sync', $envExample);
        $this->assertStringContainsString('CACHE_STORE=file', $envExample);
        $this->assertStringContainsString('MAIL_MAILER=log', $envExample);
    }

    public function test_production_env_example_is_safe_and_matches_readiness_requirements(): void
    {
        $envExample = file_get_contents(base_path('.env.production.example'));

        $this->assertStringContainsString('APP_ENV=production', $envExample);
        $this->assertStringContainsString('APP_DEBUG=false', $envExample);
        $this->assertStringContainsString('APP_NAME="MY PSPA"', $envExample);
        $this->assertStringContainsString('APP_URL=https://my-pspa.example.ac.id', $envExample);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $envExample);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $envExample);
        $this->assertStringContainsString('CORE_FARMASI_VERIFY_SSL=true', $envExample);
        $this->assertStringContainsString('KP_CORE_VERIFY_SSL=true', $envExample);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $envExample);
        $this->assertStringContainsString('CACHE_STORE=database', $envExample);
        $this->assertStringContainsString('MAIL_MAILER=smtp', $envExample);

        foreach ([
            'DB_PASSWORD=secret',
            'CORE_DB_PASSWORD=secret',
            'KP_CORE_CLIENT_SECRET=secret',
            'AWS_SECRET_ACCESS_KEY=secret',
            'password123',
            'token=',
            'access_token=',
            'signed=',
        ] as $unsafeValue) {
            $this->assertStringNotContainsString($unsafeValue, strtolower($envExample));
        }
    }

    public function test_vps_env_example_is_safe_and_uses_core_preferred_master_data(): void
    {
        $envExample = file_get_contents(base_path('.env.vps.example'));

        $this->assertStringContainsString('APP_ENV=production', $envExample);
        $this->assertStringContainsString('APP_DEBUG=false', $envExample);
        $this->assertStringContainsString('APP_NAME="MY PSPA"', $envExample);
        $this->assertStringContainsString('APP_URL=https://my-pspa.example.ac.id', $envExample);
        $this->assertStringContainsString('KP_AUTH_MODE=core_http', $envExample);
        $this->assertStringContainsString('KP_MASTER_DATA_READ_MODE=core_preferred', $envExample);
        $this->assertStringContainsString('CORE_FARMASI_ENABLED=true', $envExample);
        $this->assertStringContainsString('KP_CORE_HTTP_ENABLED=true', $envExample);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $envExample);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $envExample);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $envExample);
        $this->assertStringContainsString('CACHE_STORE=database', $envExample);

        foreach ([
            'DB_PASSWORD=secret',
            'CORE_DB_PASSWORD=secret',
            'KP_CORE_CLIENT_SECRET=secret',
            'AWS_SECRET_ACCESS_KEY=secret',
            'password123',
            'access_token=',
            'signed=',
        ] as $unsafeValue) {
            $this->assertStringNotContainsString($unsafeValue, strtolower($envExample));
        }
    }

    public function test_error_pages_and_login_page_render_for_release_readiness(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('MY PSPA')
            ->assertSee('Masuk ke MY PSPA');

        $this->assertStringContainsString('Akses Ditolak', view('errors.403')->render());
        $this->assertStringContainsString('Halaman Tidak Ditemukan', view('errors.404')->render());
        $this->assertStringContainsString('Sesi Kedaluwarsa', view('errors.419')->render());
        $this->assertStringContainsString('Terjadi Kesalahan', view('errors.500')->render());
    }

    public function test_management_recaps_and_exports_remain_protected_from_student_role(): void
    {
        $this->seed(RoleSeeder::class);

        $student = User::create([
            'name' => 'Mahasiswa Readiness',
            'email' => 'readiness-student@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $student->roles()->sync(Role::where('name', 'mahasiswa')->pluck('id'));

        $this->actingAs($student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/recaps')
            ->assertForbidden();

        $this->actingAs($student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/exports/students')
            ->assertForbidden();
    }
}
