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

class CoreBridgeProvisioningCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $coreDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->coreDatabasePath = tempnam(sys_get_temp_dir(), 'core-provision-');
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

    public function test_dry_run_does_not_create_legacy_user(): void
    {
        $this->coreUser(21, 'farhamzah@ubpkarawang.ac.id', ['dosen', 'pembimbing-dalam']);

        $this->artisan('kp:provision-core-bridge-user --email=farhamzah@ubpkarawang.ac.id')
            ->expectsOutputToContain('dry-run only; no writes performed')
            ->expectsOutputToContain('Action: create')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'farhamzah@ubpkarawang.ac.id']);
    }

    public function test_execute_requires_confirmation(): void
    {
        $this->coreUser(21, 'farhamzah@ubpkarawang.ac.id', ['dosen']);

        $this->artisan('kp:provision-core-bridge-user --email=farhamzah@ubpkarawang.ac.id --execute')
            ->expectsOutputToContain('Execute refused: missing --confirm-execute.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'farhamzah@ubpkarawang.ac.id']);
    }

    public function test_execute_creates_legacy_bridge_user_without_copying_core_password(): void
    {
        $this->coreUser(21, 'farhamzah@ubpkarawang.ac.id', ['dosen', 'pembimbing-dalam', 'penguji']);

        $this->artisan('kp:provision-core-bridge-user --email=farhamzah@ubpkarawang.ac.id --execute --confirm-execute')
            ->expectsOutputToContain('Action: created')
            ->assertSuccessful();

        $user = User::where('email', 'farhamzah@ubpkarawang.ac.id')->firstOrFail();

        $this->assertSame(21, (int) $user->core_user_id);
        $this->assertSame('active', $user->status);
        $this->assertFalse($user->must_change_password);
        $this->assertTrue($user->profile_completed);
        $this->assertFalse(Hash::check('core-secret', $user->password));
        $this->assertEqualsCanonicalizing(
            ['pembimbing_dalam', 'penguji'],
            $user->roles()->pluck('name')->all(),
        );
    }

    public function test_execute_creates_legacy_lecturer_profile_when_core_lecturer_exists(): void
    {
        $this->coreUser(21, 'farhamzah@ubpkarawang.ac.id', ['dosen', 'pembimbing-dalam']);
        $this->coreLecturer(8, 21, '0430037804', 'farhamzah@ubpkarawang.ac.id');

        $this->artisan('kp:provision-core-bridge-user --email=farhamzah@ubpkarawang.ac.id --execute --confirm-execute')
            ->expectsOutputToContain('Legacy KP lecturer profile: found/synced')
            ->assertSuccessful();

        $user = User::where('email', 'farhamzah@ubpkarawang.ac.id')->firstOrFail();

        $this->assertDatabaseHas('lecturers', [
            'user_id' => $user->id,
            'nidn_nip' => '0430037804',
            'core_lecturer_id' => 8,
            'core_sync_status' => 'synced',
        ]);
    }

    public function test_execute_creates_legacy_student_profile_when_core_student_exists(): void
    {
        $this->coreUser(22, 'student@sikp.test', ['mahasiswa']);
        $this->coreStudent(9, 22, '221063120009', 'student@sikp.test');

        $this->artisan('kp:provision-core-bridge-user --email=student@sikp.test --execute --confirm-execute')
            ->expectsOutputToContain('Action: created')
            ->assertSuccessful();

        $user = User::where('email', 'student@sikp.test')->firstOrFail();

        $this->assertDatabaseHas('students', [
            'user_id' => $user->id,
            'nim' => '221063120009',
            'core_student_id' => 9,
            'core_sync_status' => 'synced',
        ]);
    }

    public function test_execute_creates_legacy_field_supervisor_profile_when_core_external_person_exists(): void
    {
        $this->coreUser(23, 'field.supervisor@example.test', ['pembimbing-lapangan']);
        $this->coreExternalPerson(5, 23, 'field.supervisor@example.test');

        $this->artisan('kp:provision-core-bridge-user --email=field.supervisor@example.test --execute --confirm-execute')
            ->expectsOutputToContain('Legacy KP field supervisor profile: found/synced')
            ->assertSuccessful();

        $user = User::where('email', 'field.supervisor@example.test')->firstOrFail();

        $this->assertDatabaseHas('field_supervisors', [
            'user_id' => $user->id,
            'core_user_id' => 23,
            'institution_name' => 'Apotek Sehat Core',
            'position' => 'Preseptor KP',
            'phone' => '081300000001',
            'status' => 'active',
            'core_sync_status' => 'synced',
        ]);
        $this->assertTrue($user->hasRole('pembimbing_lapangan'));
    }


    public function test_admin_core_is_not_translated_to_kp_role(): void
    {
        $this->coreUser(21, 'farhamzah@ubpkarawang.ac.id', ['admin-core']);

        $this->artisan('kp:provision-core-bridge-user --email=farhamzah@ubpkarawang.ac.id')
            ->expectsOutputToContain('belum punya role yang bisa diterjemahkan ke role KP')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'farhamzah@ubpkarawang.ac.id']);
    }

    public function test_core_user_that_must_change_password_is_blocked_from_provisioning(): void
    {
        $this->coreUser(21, 'farhamzah@ubpkarawang.ac.id', ['dosen'], true);

        $this->artisan('kp:provision-core-bridge-user --email=farhamzah@ubpkarawang.ac.id')
            ->expectsOutputToContain('must_change_password: yes')
            ->expectsOutputToContain('harus mengganti password')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'farhamzah@ubpkarawang.ac.id']);
    }

    public function test_existing_legacy_user_is_linked_and_roles_are_synchronized(): void
    {
        $legacy = User::create([
            'name' => 'Farhamzah Lama',
            'email' => 'farhamzah@ubpkarawang.ac.id',
            'password' => Hash::make('legacy-pass'),
            'status' => 'inactive',
            'must_change_password' => true,
            'profile_completed' => false,
        ]);
        $legacy->roles()->sync(Role::whereIn('name', ['koordinator_kp'])->pluck('id'));
        $legacyPassword = $legacy->password;
        $this->coreUser(21, 'farhamzah@ubpkarawang.ac.id', ['dosen', 'penguji']);

        $this->artisan('kp:provision-core-bridge-user --email=farhamzah@ubpkarawang.ac.id --execute --confirm-execute')
            ->expectsOutputToContain('Action: synced')
            ->assertSuccessful();

        $legacy->refresh();

        $this->assertSame(21, (int) $legacy->core_user_id);
        $this->assertSame('active', $legacy->status);
        $this->assertSame($legacyPassword, $legacy->password);
        $this->assertEqualsCanonicalizing(
            ['pembimbing_dalam', 'penguji'],
            $legacy->roles()->pluck('name')->all(),
        );
    }

    public function test_execute_uses_core_roles_and_app_access_roles_then_removes_stale_local_roles(): void
    {
        $legacy = User::create([
            'name' => 'Farhamzah Lama',
            'email' => 'farhamzah@ubpkarawang.ac.id',
            'password' => Hash::make('legacy-pass'),
            'status' => 'active',
            'must_change_password' => false,
            'profile_completed' => true,
            'core_user_id' => 21,
        ]);
        $legacy->roles()->sync(Role::whereIn('name', ['koordinator_kp', 'pembimbing_lapangan'])->pluck('id'));
        $this->coreUser(21, 'farhamzah@ubpkarawang.ac.id', ['koordinator-kp']);
        $this->coreRole(21, 'admin-kp');
        $this->coreRole(21, 'penguji');

        $this->artisan('kp:provision-core-bridge-user --email=farhamzah@ubpkarawang.ac.id --execute --confirm-execute')
            ->expectsOutputToContain('mapped KP roles: koordinator_kp, admin, penguji')
            ->assertSuccessful();

        $legacy->refresh();

        $this->assertEqualsCanonicalizing(
            ['admin', 'koordinator_kp', 'penguji'],
            $legacy->roles()->pluck('name')->all(),
        );
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

        Schema::connection('core')->create('study_programs', function ($table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::connection('core')->create('students', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('student_number');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('study_program_id')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('class_name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('core')->create('lecturers', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('lecturer_number');
            $table->string('nidn')->nullable();
            $table->string('nip')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('study_program_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('core')->create('external_people', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('external_number')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('institution_name')->nullable();
            $table->string('institution_type')->nullable();
            $table->string('position_title')->nullable();
            $table->string('profession')->nullable();
            $table->string('identity_number')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function coreUser(int $id, string $email, array $accessRoles, bool $mustChangePassword = false): void
    {
        DB::connection('core')->table('users')->insert([
            'id' => $id,
            'name' => 'Farhamzah',
            'email' => $email,
            'password' => Hash::make('core-secret'),
            'active' => true,
            'must_change_password' => $mustChangePassword,
        ]);

        foreach ($accessRoles as $index => $roleSlug) {
            DB::connection('core')->table('user_app_accesses')->insert([
                'id' => $id * 10 + $index,
                'user_id' => $id,
                'app_code' => 'kp-farmasi',
                'role_slug' => $roleSlug,
                'is_active' => true,
            ]);
        }
    }

    private function coreRole(int $userId, string $roleName): void
    {
        $roleId = abs(crc32($userId.'-'.$roleName));
        DB::connection('core')->table('roles')->insertOrIgnore([
            'id' => $roleId,
            'name' => $roleName,
            'label' => $roleName,
            'active' => true,
        ]);
        DB::connection('core')->table('user_roles')->insertOrIgnore([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    private function coreStudent(int $id, int $userId, string $studentNumber, string $email): void
    {
        DB::connection('core')->table('study_programs')->insertOrIgnore([
            'id' => 1,
            'name' => 'Farmasi S1',
        ]);

        DB::connection('core')->table('students')->insert([
            'id' => $id,
            'user_id' => $userId,
            'student_number' => $studentNumber,
            'name' => 'Student Core',
            'email' => $email,
            'study_program_id' => 1,
            'semester' => 6,
            'class_name' => 'Farmasi A',
            'active' => true,
        ]);
    }

    private function coreLecturer(int $id, int $userId, string $lecturerNumber, string $email): void
    {
        DB::connection('core')->table('lecturers')->insert([
            'id' => $id,
            'user_id' => $userId,
            'lecturer_number' => $lecturerNumber,
            'nidn' => $lecturerNumber,
            'nip' => '416200165',
            'email' => $email,
            'active' => true,
        ]);
    }

    private function coreExternalPerson(int $id, int $userId, string $email): void
    {
        DB::connection('core')->table('external_people')->insert([
            'id' => $id,
            'user_id' => $userId,
            'external_number' => 'EXT-001',
            'name' => 'Field Supervisor Core',
            'email' => $email,
            'phone' => '081300000001',
            'institution_name' => 'Apotek Sehat Core',
            'institution_type' => 'apotek',
            'position_title' => 'Preseptor KP',
            'profession' => 'Apoteker',
            'identity_number' => '3276000000000001',
            'address' => 'Karawang',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
