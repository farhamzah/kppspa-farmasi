<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoreBridgeAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $coreDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->coreDatabasePath = tempnam(sys_get_temp_dir(), 'core-auth-');
        config()->set('database.connections.core', [
            'driver' => 'sqlite',
            'database' => $this->coreDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('core');
        $this->createCoreSchema();
    }

    protected function tearDown(): void
    {
        DB::purge('core');

        if (isset($this->coreDatabasePath) && file_exists($this->coreDatabasePath)) {
            unlink($this->coreDatabasePath);
        }

        parent::tearDown();
    }

    public function test_legacy_mode_keeps_existing_login_behavior(): void
    {
        config()->set('kp_auth.mode', 'legacy');
        $legacy = $this->legacyUser('legacy@sikp.test', 'legacy-pass', ['mahasiswa']);

        $response = $this->post('/login', [
            'email' => 'legacy@sikp.test',
            'password' => 'legacy-pass',
        ]);

        $response->assertRedirect('/mahasiswa/dashboard');
        $this->assertAuthenticatedAs($legacy);
        $this->assertSame('mahasiswa', session('active_role'));
    }

    public function test_core_bridge_logs_in_with_core_password_and_legacy_session(): void
    {
        config()->set('kp_auth.mode', 'core_bridge');
        $legacy = $this->legacyUser('admin@sikp.test', 'legacy-pass', ['admin'], ['core_user_id' => 10]);
        $legacyPassword = $legacy->password;
        $this->coreUser(10, 'admin@sikp.test', 'core-pass', true, ['admin-kp', 'admin-core'], ['admin-kp', 'admin-core']);

        $response = $this->post('/login', [
            'email' => 'admin@sikp.test',
            'password' => 'core-pass',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($legacy);
        $this->assertSame('admin', session('active_role'));
        $this->assertDatabaseHas('users', ['email' => 'admin@sikp.test', 'password' => $legacyPassword]);
    }

    public function test_core_bridge_syncs_core_roles_before_redirecting_to_role_selection(): void
    {
        config()->set('kp_auth.mode', 'core_bridge');
        $legacy = $this->legacyUser('multi@sikp.test', 'legacy-pass', ['koordinator_kp'], ['core_user_id' => 20]);
        $this->coreUser(20, 'multi@sikp.test', 'core-pass', true, ['admin-kp', 'penguji'], ['koordinator-kp']);

        $response = $this->post('/login', [
            'email' => 'multi@sikp.test',
            'password' => 'core-pass',
        ]);

        $response->assertRedirect('/pilih-role');
        $this->assertAuthenticatedAs($legacy);
        $this->assertEqualsCanonicalizing(
            ['admin', 'koordinator_kp', 'penguji'],
            $legacy->fresh()->roles()->pluck('name')->all(),
        );
        $this->assertNull(session('active_role'));
    }

    public function test_core_bridge_removes_stale_local_roles_on_login(): void
    {
        config()->set('kp_auth.mode', 'core_bridge');
        $legacy = $this->legacyUser('synced@sikp.test', 'legacy-pass', ['koordinator_kp', 'penguji'], ['core_user_id' => 21]);
        $this->coreUser(21, 'synced@sikp.test', 'core-pass', true, [], ['penguji']);

        $response = $this->post('/login', [
            'email' => 'synced@sikp.test',
            'password' => 'core-pass',
        ]);

        $response->assertRedirect('/penguji/dashboard');
        $this->assertAuthenticatedAs($legacy);
        $this->assertEqualsCanonicalizing(
            ['penguji'],
            $legacy->fresh()->roles()->pluck('name')->all(),
        );
        $this->assertSame('penguji', session('active_role'));
    }

    public function test_core_bridge_allows_kp_role_from_core_role_when_app_access_is_active(): void
    {
        config()->set('kp_auth.mode', 'core_bridge');
        $legacy = $this->legacyUser('lecturer-koordinator@sikp.test', 'legacy-pass', ['pembimbing_dalam'], ['core_user_id' => 22]);
        $this->coreUser(22, 'lecturer-koordinator@sikp.test', 'core-pass', true, ['koordinator-kp'], ['dosen']);

        $response = $this->post('/login', [
            'email' => 'lecturer-koordinator@sikp.test',
            'password' => 'core-pass',
        ]);

        $response->assertRedirect('/pilih-role');
        $this->assertAuthenticatedAs($legacy);
        $this->assertEqualsCanonicalizing(
            ['koordinator_kp', 'pembimbing_dalam'],
            $legacy->fresh()->roles()->pluck('name')->all(),
        );
    }

    public function test_core_bridge_rejects_user_without_kp_app_access(): void
    {
        config()->set('kp_auth.mode', 'core_bridge');
        $this->legacyUser('noaccess@sikp.test', 'legacy-pass', ['mahasiswa'], ['core_user_id' => 11]);
        $this->coreUser(11, 'noaccess@sikp.test', 'core-pass', true, ['mahasiswa'], []);

        $response = $this->post('/login', [
            'email' => 'noaccess@sikp.test',
            'password' => 'core-pass',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_core_bridge_rejects_inactive_core_user(): void
    {
        config()->set('kp_auth.mode', 'core_bridge');
        $this->legacyUser('inactive-core@sikp.test', 'legacy-pass', ['mahasiswa'], ['core_user_id' => 12]);
        $this->coreUser(12, 'inactive-core@sikp.test', 'core-pass', false, ['mahasiswa'], ['mahasiswa']);

        $response = $this->post('/login', [
            'email' => 'inactive-core@sikp.test',
            'password' => 'core-pass',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_core_bridge_rejects_core_user_that_must_change_password(): void
    {
        config()->set('kp_auth.mode', 'core_bridge');
        $this->legacyUser('must-change@sikp.test', 'legacy-pass', ['mahasiswa'], ['core_user_id' => 16]);
        $this->coreUser(16, 'must-change@sikp.test', 'core-pass', true, ['mahasiswa'], ['mahasiswa'], true);

        $response = $this->post('/login', [
            'email' => 'must-change@sikp.test',
            'password' => 'core-pass',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_core_bridge_auto_provisions_missing_legacy_mapping(): void
    {
        config()->set('kp_auth.mode', 'core_bridge');
        $this->coreUser(13, 'missing-legacy@sikp.test', 'core-pass', true, ['mahasiswa'], ['mahasiswa']);

        $response = $this->post('/login', [
            'email' => 'missing-legacy@sikp.test',
            'password' => 'core-pass',
        ]);

        $response->assertRedirect('/mahasiswa/dashboard');

        $legacy = User::where('email', 'missing-legacy@sikp.test')->firstOrFail();
        $this->assertAuthenticatedAs($legacy);
        $this->assertSame(13, (int) $legacy->core_user_id);
        $this->assertFalse(Hash::check('core-pass', $legacy->password));
        $this->assertEqualsCanonicalizing(['mahasiswa'], $legacy->roles()->pluck('name')->all());
    }

    public function test_core_bridge_with_legacy_fallback_allows_legacy_password_after_core_credential_failure(): void
    {
        config()->set('kp_auth.mode', 'core_bridge_with_legacy_fallback');
        $legacy = $this->legacyUser('fallback@sikp.test', 'legacy-pass', ['mahasiswa'], ['core_user_id' => 14]);
        $this->coreUser(14, 'fallback@sikp.test', 'core-pass', true, ['mahasiswa'], ['mahasiswa']);

        $response = $this->post('/login', [
            'email' => 'fallback@sikp.test',
            'password' => 'legacy-pass',
        ]);

        $response->assertRedirect('/mahasiswa/dashboard');
        $this->assertAuthenticatedAs($legacy);
    }

    public function test_auth_bridge_check_command_is_read_only(): void
    {
        config()->set('kp_auth.mode', 'core_bridge');
        $this->legacyUser('admin@sikp.test', 'legacy-pass', ['admin'], ['core_user_id' => 15]);
        $this->coreUser(15, 'admin@sikp.test', 'core-pass', true, ['admin-kp'], ['admin-kp']);
        $beforeCoreUsers = DB::connection('core')->table('users')->count();

        $this->artisan('kp:auth-bridge-check --email=admin@sikp.test')
            ->expectsOutputToContain('Auth bridge diagnostic passed.')
            ->assertSuccessful();

        $this->assertSame($beforeCoreUsers, DB::connection('core')->table('users')->count());
    }

    private function createCoreSchema(): void
    {
        Schema::connection('core')->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();
        });

        Schema::connection('core')->create('roles', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('label');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('core')->create('user_roles', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();
        });

        Schema::connection('core')->create('user_app_accesses', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('app_code');
            $table->string('role_slug')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function legacyUser(string $email, string $password, array $roles, array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Bridge User',
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
            'must_change_password' => false,
            'profile_completed' => true,
        ], $attributes));

        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user;
    }

    private function coreUser(int $id, string $email, string $password, bool $active, array $roles, array $accessRoles, bool $mustChangePassword = false): void
    {
        DB::connection('core')->table('users')->insert([
            'id' => $id,
            'name' => 'Core User',
            'email' => $email,
            'password' => Hash::make($password),
            'active' => $active,
            'must_change_password' => $mustChangePassword,
        ]);

        foreach ($roles as $index => $roleName) {
            $roleId = $id * 10 + $index;
            DB::connection('core')->table('roles')->insert([
                'id' => $roleId,
                'name' => $roleName,
                'label' => $roleName,
                'active' => true,
            ]);
            DB::connection('core')->table('user_roles')->insert([
                'user_id' => $id,
                'role_id' => $roleId,
            ]);
        }

        foreach ($accessRoles as $index => $roleSlug) {
            DB::connection('core')->table('user_app_accesses')->insert([
                'id' => $id * 10 + $index,
                'user_id' => $id,
                'app_code' => 'kppspa-farmasi',
                'role_slug' => $roleSlug,
                'is_active' => true,
            ]);
        }
    }
}
