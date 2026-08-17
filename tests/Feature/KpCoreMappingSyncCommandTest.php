<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KpCoreMappingSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $coreDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coreDatabasePath = tempnam(sys_get_temp_dir(), 'core-map-');

        config()->set('database.connections.core', [
            'driver' => 'sqlite',
            'database' => $this->coreDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('core');
        $this->createCoreSchema();
        $this->seedCoreRows();
        $this->seedLegacyRows();
    }

    protected function tearDown(): void
    {
        DB::purge('core');

        if (isset($this->coreDatabasePath) && file_exists($this->coreDatabasePath)) {
            unlink($this->coreDatabasePath);
        }

        parent::tearDown();
    }

    public function test_mapping_columns_exist(): void
    {
        foreach ([
            'users' => 'core_user_id',
            'students' => 'core_student_id',
            'lecturers' => 'core_lecturer_id',
            'field_supervisors' => 'core_user_id',
        ] as $table => $mappingColumn) {
            $this->assertTrue(Schema::hasColumn($table, $mappingColumn));
            $this->assertTrue(Schema::hasColumn($table, 'core_synced_at'));
            $this->assertTrue(Schema::hasColumn($table, 'core_sync_status'));
            $this->assertTrue(Schema::hasColumn($table, 'core_sync_note'));
        }
    }

    public function test_dry_run_maps_rows_without_writing(): void
    {
        $this->artisan('kp:sync-core-mapping --dry-run --show-samples')
            ->expectsOutputToContain('users: set=6')
            ->expectsOutputToContain('students: set=1')
            ->expectsOutputToContain('lecturers: set=3')
            ->expectsOutputToContain('field_supervisors: set=1')
            ->assertSuccessful();

        $this->assertNull(DB::table('users')->where('email', 'admin@sikp.test')->value('core_user_id'));
    }

    public function test_execute_requires_confirmation(): void
    {
        $this->artisan('kp:sync-core-mapping --execute')
            ->expectsOutputToContain('Execute refused: missing --confirm-execute.')
            ->assertFailed();
    }

    public function test_execute_sets_local_mapping_columns_and_does_not_write_core(): void
    {
        $beforeCoreUsers = DB::connection('core')->table('users')->count();

        $this->artisan('kp:sync-core-mapping --execute --confirm-execute')
            ->expectsOutputToContain('Core mapping columns synced.')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('users')->where('email', 'admin@sikp.test')->value('core_user_id'));
        $this->assertSame(10, DB::table('students')->where('nim', '221063120001')->value('core_student_id'));
        $this->assertSame(20, DB::table('lecturers')->where('nidn_nip', '0012345601')->value('core_lecturer_id'));
        $this->assertSame(4, DB::table('field_supervisors')->where('id', 1)->value('core_user_id'));
        $this->assertSame('synced', DB::table('users')->where('email', 'admin@sikp.test')->value('core_sync_status'));
        $this->assertSame($beforeCoreUsers, DB::connection('core')->table('users')->count());
    }

    private function createCoreSchema(): void
    {
        Schema::connection('core')->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('core')->create('students', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('student_number');
            $table->string('email');
            $table->timestamps();
        });

        Schema::connection('core')->create('lecturers', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('lecturer_number');
            $table->string('email');
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
            ['id' => 1, 'name' => 'Admin', 'email' => 'admin@sikp.test', 'active' => true],
            ['id' => 2, 'name' => 'Student', 'email' => 'mahasiswa@sikp.test', 'active' => true],
            ['id' => 3, 'name' => 'Lecturer', 'email' => 'koordinator@sikp.test', 'active' => true],
            ['id' => 4, 'name' => 'Field', 'email' => 'lapangan@sikp.test', 'active' => true],
            ['id' => 5, 'name' => 'Employee', 'email' => 'employee@sikp.test', 'active' => true],
            ['id' => 6, 'name' => 'Email Lecturer', 'email' => 'email-lecturer@sikp.test', 'active' => true],
        ]);

        DB::connection('core')->table('students')->insert([
            ['id' => 10, 'user_id' => 2, 'student_number' => '221063120001', 'email' => 'mahasiswa@sikp.test'],
        ]);

        DB::connection('core')->table('lecturers')->insert([
            ['id' => 20, 'user_id' => 3, 'lecturer_number' => '0012345601', 'email' => 'koordinator@sikp.test'],
            ['id' => 21, 'user_id' => 5, 'lecturer_number' => 'EMP-001', 'email' => 'employee@sikp.test'],
            ['id' => 22, 'user_id' => 6, 'lecturer_number' => 'EMAIL-FALLBACK', 'email' => 'email-lecturer@sikp.test'],
        ]);

        DB::connection('core')->table('user_app_accesses')->insert([
            ['id' => 1, 'user_id' => 4, 'app_code' => 'kppspa-farmasi', 'role_slug' => 'pembimbing-lapangan', 'is_active' => true],
        ]);
    }

    private function seedLegacyRows(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Admin', 'email' => 'admin@sikp.test', 'password' => 'hash', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Student', 'email' => 'mahasiswa@sikp.test', 'password' => 'hash', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Lecturer', 'email' => 'koordinator@sikp.test', 'password' => 'hash', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Field', 'email' => 'lapangan@sikp.test', 'password' => 'hash', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Employee', 'email' => 'employee@sikp.test', 'password' => 'hash', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Email Lecturer', 'email' => 'email-lecturer@sikp.test', 'password' => 'hash', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('students')->insert([
            ['id' => 1, 'user_id' => 2, 'nim' => '221063120001', 'study_program' => 'Farmasi', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('lecturers')->insert([
            ['id' => 1, 'user_id' => 3, 'nidn_nip' => '0012345601', 'employee_number' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'user_id' => 5, 'nidn_nip' => null, 'employee_number' => 'EMP-001', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'user_id' => 6, 'nidn_nip' => null, 'employee_number' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('field_supervisors')->insert([
            ['id' => 1, 'user_id' => 4, 'institution_name' => 'Apotek Test', 'position' => 'Pembimbing', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
