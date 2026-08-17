<?php

namespace Tests\Feature;

use App\Models\Core\CoreUser;
use App\Models\Core\CoreUserAppAccess;
use App\Services\CoreIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CoreReadIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $coreDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coreDatabasePath = tempnam(sys_get_temp_dir(), 'core-read-');

        config()->set('database.connections.core', [
            'driver' => 'sqlite',
            'database' => $this->coreDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('core');
        $this->createCoreSchema();
        $this->seedCoreRows();
        $this->seedLocalFieldSupervisorProfile();
    }

    protected function tearDown(): void
    {
        DB::purge('core');

        if (isset($this->coreDatabasePath) && file_exists($this->coreDatabasePath)) {
            unlink($this->coreDatabasePath);
        }

        parent::tearDown();
    }

    public function test_core_models_use_core_connection(): void
    {
        $this->assertSame('core', (new CoreUser())->getConnectionName());
        $this->assertSame('core', (new CoreUserAppAccess())->getConnectionName());
    }

    public function test_core_models_are_read_only(): void
    {
        $this->expectException(RuntimeException::class);

        CoreUser::query()->firstOrFail()->save();
    }

    public function test_core_identity_service_reads_core_data(): void
    {
        $service = app(CoreIdentityService::class);

        $admin = $service->findUserByEmail('ADMIN@sikp.test');
        $student = $service->findStudentByNim('221063120001');
        $lecturer = $service->findLecturerByNumberOrEmail('0012345601', null);

        $this->assertNotNull($admin);
        $this->assertSame(['admin-kp'], $service->getUserRoles($admin->id)->pluck('name')->all());
        $this->assertTrue($service->userHasAppAccess($admin->id, 'kppspa-farmasi', 'admin-kp'));
        $this->assertSame('mahasiswa@sikp.test', $student?->user?->email);
        $this->assertSame('koordinator@sikp.test', $lecturer?->user?->email);
        $this->assertSame(4, $service->getKpUsersSummary()['users']);
    }

    public function test_core_health_check_is_registered_and_read_only(): void
    {
        $beforeCoreUsers = DB::connection('core')->table('users')->count();
        $beforeLocalProfiles = DB::table('field_supervisors')->count();

        $this->artisan('kp:core-health-check')
            ->expectsOutputToContain('Core health check passed.')
            ->assertSuccessful();

        $this->assertSame($beforeCoreUsers, DB::connection('core')->table('users')->count());
        $this->assertSame($beforeLocalProfiles, DB::table('field_supervisors')->count());
    }

    private function createCoreSchema(): void
    {
        Schema::connection('core')->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable();
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

        Schema::connection('core')->create('departments', function ($table): void {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('core')->create('study_programs', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('core')->create('students', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('student_number');
            $table->string('name');
            $table->string('email');
            $table->unsignedBigInteger('study_program_id');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('core')->create('lecturers', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('lecturer_number');
            $table->string('name');
            $table->string('email');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('study_program_id')->nullable();
            $table->boolean('active')->default(true);
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

    private function seedCoreRows(): void
    {
        DB::connection('core')->table('users')->insert([
            ['id' => 1, 'name' => 'Admin KP', 'email' => 'admin@sikp.test', 'password' => 'hash', 'active' => true],
            ['id' => 2, 'name' => 'Mahasiswa', 'email' => 'mahasiswa@sikp.test', 'password' => 'hash', 'active' => true],
            ['id' => 3, 'name' => 'Koordinator', 'email' => 'koordinator@sikp.test', 'password' => 'hash', 'active' => true],
            ['id' => 4, 'name' => 'Lapangan', 'email' => 'lapangan@sikp.test', 'password' => 'hash', 'active' => true],
        ]);

        DB::connection('core')->table('roles')->insert([
            ['id' => 1, 'name' => 'admin-kp', 'label' => 'Admin KP', 'active' => true],
            ['id' => 2, 'name' => 'mahasiswa', 'label' => 'Mahasiswa', 'active' => true],
            ['id' => 3, 'name' => 'pembimbing-lapangan', 'label' => 'Pembimbing Lapangan', 'active' => true],
        ]);

        DB::connection('core')->table('user_roles')->insert([
            ['id' => 1, 'user_id' => 1, 'role_id' => 1],
            ['id' => 2, 'user_id' => 2, 'role_id' => 2],
            ['id' => 3, 'user_id' => 4, 'role_id' => 3],
        ]);

        DB::connection('core')->table('departments')->insert(['id' => 1, 'name' => 'Fakultas Farmasi', 'active' => true]);
        DB::connection('core')->table('study_programs')->insert(['id' => 1, 'department_id' => 1, 'name' => 'S1 Farmasi', 'active' => true]);
        DB::connection('core')->table('students')->insert(['id' => 1, 'user_id' => 2, 'student_number' => '221063120001', 'name' => 'Mahasiswa', 'email' => 'mahasiswa@sikp.test', 'study_program_id' => 1, 'active' => true]);
        DB::connection('core')->table('lecturers')->insert(['id' => 1, 'user_id' => 3, 'lecturer_number' => '0012345601', 'name' => 'Koordinator', 'email' => 'koordinator@sikp.test', 'department_id' => 1, 'study_program_id' => 1, 'active' => true]);

        DB::connection('core')->table('user_app_accesses')->insert([
            ['id' => 1, 'user_id' => 1, 'app_code' => 'kppspa-farmasi', 'role_slug' => 'admin-kp', 'is_active' => true],
            ['id' => 2, 'user_id' => 2, 'app_code' => 'kppspa-farmasi', 'role_slug' => 'mahasiswa', 'is_active' => true],
            ['id' => 3, 'user_id' => 4, 'app_code' => 'kppspa-farmasi', 'role_slug' => 'pembimbing-lapangan', 'is_active' => true],
        ]);
    }

    private function seedLocalFieldSupervisorProfile(): void
    {
        DB::table('users')->insert([
            'id' => 100,
            'name' => 'Lapangan',
            'email' => 'lapangan@sikp.test',
            'password' => 'hash',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('field_supervisors')->insert([
            'id' => 100,
            'user_id' => 100,
            'institution_name' => 'Apotek Test',
            'position' => 'Pembimbing',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
