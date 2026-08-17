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

class AuthBridgeSmokeTestCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $coreDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->coreDatabasePath = tempnam(sys_get_temp_dir(), 'core-smoke-');
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

    public function test_auth_mode_command_reports_legacy_default(): void
    {
        config()->set('kp_auth.mode', 'legacy');

        $this->artisan('kp:auth-mode')
            ->expectsOutputToContain('Current mode: legacy')
            ->expectsOutputToContain('Allowed modes: legacy, core_http, core_bridge, core_bridge_with_legacy_fallback')
            ->assertSuccessful();
    }

    public function test_smoke_test_fails_safely_when_user_missing(): void
    {
        $this->artisan('kp:auth-bridge-smoke-test --mode=core_bridge --email=missing@sikp.test --no-write')
            ->expectsOutputToContain('Result: FAIL')
            ->assertFailed();
    }

    public function test_smoke_test_passes_without_password_and_does_not_write(): void
    {
        $this->seedBridgeUser();
        $beforeCoreUsers = DB::connection('core')->table('users')->count();
        $beforeLegacyUsers = DB::table('users')->count();

        $this->artisan('kp:auth-bridge-smoke-test --mode=core_bridge --email=admin@sikp.test --no-write')
            ->expectsOutputToContain('Password provided: no')
            ->expectsOutputToContain('Password value stored in report: no')
            ->expectsOutputToContain('Result: PASS')
            ->assertSuccessful();

        $this->assertSame($beforeCoreUsers, DB::connection('core')->table('users')->count());
        $this->assertSame($beforeLegacyUsers, DB::table('users')->count());
    }

    public function test_smoke_test_does_not_echo_or_store_password_in_json_report(): void
    {
        $this->seedBridgeUser();

        $this->artisan('kp:auth-bridge-smoke-test --mode=core_bridge --email=admin@sikp.test --password=secret-pass --no-write --report-json')
            ->doesntExpectOutputToContain('secret-pass')
            ->expectsOutputToContain('Result: PASS')
            ->assertSuccessful();

        $latest = collect(glob(storage_path('app/reports/kp-core-auth-bridge-smoke-test-*.json')))
            ->sort()
            ->last();

        $this->assertNotFalse($latest);
        $this->assertStringNotContainsString('secret-pass', file_get_contents($latest));
    }

    private function createCoreSchema(): void
    {
        Schema::connection('core')->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->boolean('active')->default(true);
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

    private function seedBridgeUser(): void
    {
        $legacy = User::create([
            'name' => 'Admin',
            'email' => 'admin@sikp.test',
            'password' => Hash::make('legacy-pass'),
            'status' => 'active',
            'core_user_id' => 1,
        ]);
        $legacy->roles()->sync(Role::where('name', 'admin')->pluck('id'));

        DB::connection('core')->table('users')->insert([
            'id' => 1,
            'name' => 'Admin',
            'email' => 'admin@sikp.test',
            'password' => Hash::make('secret-pass'),
            'active' => true,
        ]);
        DB::connection('core')->table('roles')->insert([
            'id' => 1,
            'name' => 'admin-kp',
            'label' => 'Admin KP',
            'active' => true,
        ]);
        DB::connection('core')->table('user_roles')->insert([
            'user_id' => 1,
            'role_id' => 1,
        ]);
        DB::connection('core')->table('user_app_accesses')->insert([
            'user_id' => 1,
            'app_code' => 'kp-farmasi',
            'role_slug' => 'admin-kp',
            'is_active' => true,
        ]);
    }
}
