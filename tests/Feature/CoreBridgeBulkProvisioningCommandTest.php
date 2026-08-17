<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoreBridgeBulkProvisioningCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $coreDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->coreDatabasePath = tempnam(sys_get_temp_dir(), 'core-bulk-provision-');
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

    public function test_bulk_provision_defaults_to_dry_run_without_writes(): void
    {
        $this->coreUser(31, 'student-one@sikp.test', ['mahasiswa']);
        $this->coreUser(32, 'lecturer-one@sikp.test', ['dosen']);

        $this->artisan('kp:provision-core-bridge-users')
            ->expectsOutputToContain('dry-run only; no writes performed')
            ->expectsOutputToContain('Core users with active kppspa-farmasi access: 2')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'student-one@sikp.test']);
        $this->assertDatabaseMissing('users', ['email' => 'lecturer-one@sikp.test']);
    }

    public function test_bulk_provision_execute_requires_confirmation(): void
    {
        $this->coreUser(31, 'student-one@sikp.test', ['mahasiswa']);

        $this->artisan('kp:provision-core-bridge-users --execute')
            ->expectsOutputToContain('Execute refused: missing --confirm-execute.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'student-one@sikp.test']);
    }

    public function test_bulk_provision_execute_creates_bridge_users(): void
    {
        $this->coreUser(31, 'student-one@sikp.test', ['mahasiswa']);
        $this->coreUser(32, 'lecturer-one@sikp.test', ['dosen', 'penguji']);
        $this->coreUser(33, 'no-access@sikp.test', [], false);

        $this->artisan('kp:provision-core-bridge-users --execute --confirm-execute')
            ->expectsOutputToContain('Core users with active kppspa-farmasi access: 2')
            ->expectsOutputToContain('student-one@sikp.test: created')
            ->expectsOutputToContain('lecturer-one@sikp.test: created')
            ->assertSuccessful();

        $student = User::where('email', 'student-one@sikp.test')->firstOrFail();
        $lecturer = User::where('email', 'lecturer-one@sikp.test')->firstOrFail();

        $this->assertSame(31, (int) $student->core_user_id);
        $this->assertSame(['mahasiswa'], $student->roles()->pluck('name')->all());
        $this->assertSame(32, (int) $lecturer->core_user_id);
        $this->assertEqualsCanonicalizing(['pembimbing_dalam', 'penguji'], $lecturer->roles()->pluck('name')->all());
        $this->assertDatabaseMissing('users', ['email' => 'no-access@sikp.test']);
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

    private function coreUser(int $id, string $email, array $accessRoles, bool $withAppAccess = true): void
    {
        DB::connection('core')->table('users')->insert([
            'id' => $id,
            'name' => 'Core User '.$id,
            'email' => $email,
            'password' => Hash::make('core-secret'),
            'active' => true,
            'must_change_password' => false,
        ]);

        if (! $withAppAccess) {
            return;
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
