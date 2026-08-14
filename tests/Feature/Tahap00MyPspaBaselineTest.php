<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\CoreBridgeAuthService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class Tahap00MyPspaBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        config()->set('kp_auth.mode', 'core_http');
        config()->set('core_farmasi.enabled', true);
        config()->set('core_farmasi.base_url', 'https://core.test');
        config()->set('core_farmasi.app_code', 'kppspa-farmasi');
        config()->set('core_farmasi.client_id', 'client-id');
        config()->set('core_farmasi.client_secret', 'client-secret');
        config()->set('my_pspa.local_account_management_enabled', false);
        config()->set('my_pspa.student_place_selection_enabled', false);
    }

    public function test_landing_page_is_public_and_branded_as_my_pspa(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('MY PSPA')
            ->assertSee('Sistem Informasi Program Studi Profesi Apoteker')
            ->assertSee('Apotek')
            ->assertSee('Pemerintahan');
    }

    public function test_core_http_login_creates_local_projection_without_using_local_password(): void
    {
        $legacy = User::create([
            'name' => 'Local User',
            'email' => 'mahasiswa@example.test',
            'password' => Hash::make('local-pass'),
            'status' => 'active',
            'core_user_id' => 10,
        ]);
        $legacy->roles()->sync(Role::where('name', 'admin')->pluck('id'));
        $oldPassword = $legacy->password;

        Http::fake([
            'https://core.test/api/v1/auth/login' => Http::response([
                'token' => 'core-token',
                'user' => [
                    'id' => 10,
                    'name' => 'Mahasiswa Core',
                    'email' => 'mahasiswa@example.test',
                    'active' => true,
                    'roles' => [['name' => 'mahasiswa']],
                ],
            ]),
            'https://core.test/api/v1/internal/apps/kppspa-farmasi/users/10/access' => Http::response([
                'has_access' => true,
                'app_code' => 'kppspa-farmasi',
                'user_id' => 10,
                'roles' => [['slug' => 'mahasiswa', 'name' => 'Mahasiswa']],
            ]),
        ]);

        $response = $this->post('/login', [
            'email' => 'mahasiswa@example.test',
            'password' => 'core-pass',
        ]);

        $response->assertRedirect('/mahasiswa/dashboard');
        $this->assertAuthenticated();
        $this->assertSame('mahasiswa', session('active_role'));
        $this->assertDatabaseHas('users', [
            'core_user_id' => 10,
            'name' => 'Mahasiswa Core',
            'email' => 'mahasiswa@example.test',
            'core_sync_status' => 'synced',
        ]);
        $this->assertSame($oldPassword, $legacy->fresh()->password);
        $this->assertEqualsCanonicalizing(['mahasiswa'], $legacy->fresh()->roles()->pluck('name')->all());
    }

    public function test_core_http_login_links_existing_local_user_by_email_without_duplicate_user(): void
    {
        $legacy = User::create([
            'name' => 'Farhamzah Local',
            'email' => 'farhamzah@ubpkarawang.ac.id',
            'password' => Hash::make('local-pass'),
            'status' => 'active',
            'core_user_id' => null,
        ]);
        $legacy->roles()->sync(Role::where('name', 'mahasiswa')->pluck('id'));
        $oldPassword = $legacy->password;

        Http::fake([
            'https://core.test/api/v1/auth/login' => Http::response([
                'token' => 'core-token',
                'user' => [
                    'id' => 337,
                    'name' => 'apt. Farhamzah, S.Si., M.T.I',
                    'email' => 'farhamzah@ubpkarawang.ac.id',
                    'active' => true,
                    'roles' => [['name' => 'admin-kp']],
                ],
            ]),
            'https://core.test/api/v1/internal/apps/kppspa-farmasi/users/337/access' => Http::response([
                'has_access' => true,
                'app_code' => 'kppspa-farmasi',
                'user_id' => 337,
                'roles' => [['slug' => 'admin-kp', 'name' => 'Admin KP']],
            ]),
        ]);

        $response = $this->post('/login', [
            'email' => 'farhamzah@ubpkarawang.ac.id',
            'password' => 'core-pass',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticated();
        $this->assertSame('admin', session('active_role'));
        $this->assertSame(1, User::where('email', 'farhamzah@ubpkarawang.ac.id')->count());

        $linked = $legacy->fresh();
        $this->assertSame(337, (int) $linked->core_user_id);
        $this->assertSame('apt. Farhamzah, S.Si., M.T.I', $linked->name);
        $this->assertSame($oldPassword, $linked->password);
        $this->assertEqualsCanonicalizing(['admin'], $linked->roles()->pluck('name')->all());
    }

    public function test_local_password_is_not_used_when_core_http_rejects_login(): void
    {
        User::create([
            'name' => 'Local User',
            'email' => 'local@example.test',
            'password' => Hash::make('local-pass'),
            'status' => 'active',
            'core_user_id' => 11,
        ])->roles()->sync(Role::where('name', 'mahasiswa')->pluck('id'));

        Http::fake([
            'https://core.test/api/v1/auth/login' => Http::response(['message' => 'Invalid credentials'], 401),
        ]);

        $this->post('/login', [
            'email' => 'local@example.test',
            'password' => 'local-pass',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_core_http_login_does_not_require_direct_core_database_connection(): void
    {
        config()->set('database.connections.core', [
            'driver' => 'sqlite',
            'database' => database_path('missing-core-direct-db.sqlite'),
            'prefix' => '',
        ]);
        DB::purge('core');

        Http::fake([
            'https://core.test/api/v1/auth/login' => Http::response([
                'token' => 'core-token',
                'user' => [
                    'id' => 40,
                    'name' => 'HTTP Only Admin',
                    'email' => 'http-only@example.test',
                    'active' => true,
                ],
            ]),
            'https://core.test/api/v1/internal/apps/kppspa-farmasi/users/40/access' => Http::response([
                'has_access' => true,
                'app_code' => 'kppspa-farmasi',
                'user_id' => 40,
                'roles' => [['slug' => 'admin-kp', 'name' => 'Admin KP']],
            ]),
        ]);

        $this->post('/login', [
            'email' => 'http-only@example.test',
            'password' => 'core-pass',
        ])->assertRedirect('/admin/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'core_user_id' => 40,
            'email' => 'http-only@example.test',
            'core_sync_status' => 'synced',
        ]);
    }

    public function test_core_http_login_skips_unknown_roles_when_supported_role_remains(): void
    {
        Http::fake([
            'https://core.test/api/v1/auth/login' => Http::response([
                'token' => 'core-token',
                'user' => [
                    'id' => 41,
                    'name' => 'Mixed Role User',
                    'email' => 'mixed-role@example.test',
                    'active' => true,
                ],
            ]),
            'https://core.test/api/v1/internal/apps/kppspa-farmasi/users/41/access' => Http::response([
                'has_access' => true,
                'app_code' => 'kppspa-farmasi',
                'user_id' => 41,
                'roles' => [
                    ['slug' => 'viewer', 'name' => 'Viewer'],
                    ['slug' => 'pembimbing-dalam', 'name' => 'Pembimbing Dalam'],
                ],
            ]),
        ]);

        $this->post('/login', [
            'email' => 'mixed-role@example.test',
            'password' => 'core-pass',
        ])->assertRedirect('/pembimbing-dalam/dashboard');

        $this->assertAuthenticated();
        $this->assertSame('pembimbing_dalam', session('active_role'));
    }

    public function test_core_http_login_denies_cleanly_when_only_unknown_roles_are_returned(): void
    {
        Http::fake([
            'https://core.test/api/v1/auth/login' => Http::response([
                'token' => 'core-token',
                'user' => [
                    'id' => 42,
                    'name' => 'Unsupported Role User',
                    'email' => 'unsupported-role@example.test',
                    'active' => true,
                ],
            ]),
            'https://core.test/api/v1/internal/apps/kppspa-farmasi/users/42/access' => Http::response([
                'has_access' => true,
                'app_code' => 'kppspa-farmasi',
                'user_id' => 42,
                'roles' => [['slug' => 'viewer', 'name' => 'Viewer']],
            ]),
        ]);

        $this->post('/login', [
            'email' => 'unsupported-role@example.test',
            'password' => 'core-pass',
        ])->assertRedirect()
            ->assertSessionHasErrors(['email' => 'Akun Core Anda belum memiliki akses aplikasi MY PSPA / KPPSPA.']);

        $this->assertGuest();
    }

    public function test_inactive_core_account_and_unavailable_core_are_rejected_safely(): void
    {
        Http::fake([
            'https://core.test/api/v1/auth/login' => Http::response([
                'token' => 'core-token',
                'user' => ['id' => 12, 'name' => 'Inactive', 'email' => 'inactive@example.test', 'active' => false],
            ]),
        ]);

        $this->post('/login', [
            'email' => 'inactive@example.test',
            'password' => 'core-pass',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        Http::fake([
            'https://core.test/api/v1/auth/login' => Http::response(null, 503),
        ]);

        $this->post('/login', [
            'email' => 'down@example.test',
            'password' => 'core-pass',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_core_http_login_exception_returns_safe_error_instead_of_500(): void
    {
        $this->mock(CoreBridgeAuthService::class, function ($mock): void {
            $mock->shouldReceive('attempt')
                ->once()
                ->andThrow(new RuntimeException('Simulated Core bridge failure'));
        });

        $this->post('/login', [
            'email' => 'farhamzah@ubpkarawang.ac.id',
            'password' => 'core-pass',
        ])->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_single_role_and_multi_role_core_users_follow_expected_dashboard_flow(): void
    {
        Http::fake([
            'https://core.test/api/v1/auth/login' => Http::response([
                'token' => 'core-token',
                'user' => ['id' => 20, 'name' => 'Dosen Core', 'email' => 'dosen@example.test', 'active' => true],
            ]),
            'https://core.test/api/v1/internal/apps/kppspa-farmasi/users/20/access' => Http::response([
                'has_access' => true,
                'roles' => [
                    ['slug' => 'pembimbing-dalam', 'name' => 'Pembimbing Dalam'],
                    ['slug' => 'penguji', 'name' => 'Penguji'],
                ],
            ]),
        ]);

        $this->post('/login', [
            'email' => 'dosen@example.test',
            'password' => 'core-pass',
        ])->assertRedirect('/pilih-role');

        $this->assertAuthenticated();
        $this->assertNull(session('active_role'));
    }

    public function test_role_not_granted_by_core_cannot_be_selected_or_used(): void
    {
        $user = User::create([
            'name' => 'Mahasiswa',
            'email' => 'student@example.test',
            'password' => Hash::make('projection-only'),
            'status' => 'active',
            'core_user_id' => 30,
        ]);
        $user->roles()->sync(Role::where('name', 'mahasiswa')->pluck('id'));

        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/set-role/'.$adminRole->name)
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_role' => 'admin'])
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_logout_clears_my_pspa_session(): void
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.test',
            'password' => Hash::make('projection-only'),
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertNull(session('active_role'));
    }

    public function test_registration_reset_local_account_management_and_war_routes_are_disabled(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('projection-only'),
            'status' => 'active',
        ]);
        $admin->roles()->sync(Role::where('name', 'admin')->pluck('id'));

        $student = User::create([
            'name' => 'Student',
            'email' => 'student2@example.test',
            'password' => Hash::make('projection-only'),
            'status' => 'active',
        ]);
        $student->roles()->sync(Role::where('name', 'mahasiswa')->pluck('id'));

        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();

        $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->get('/admin/users')
            ->assertForbidden();

        $this->actingAs($student)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/pemilihan-tempat')
            ->assertForbidden();
    }
}
