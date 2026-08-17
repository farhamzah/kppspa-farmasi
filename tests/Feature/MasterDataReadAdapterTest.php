<?php

namespace Tests\Feature;

use App\Exceptions\CoreMasterDataUnavailableException;
use App\Models\FieldSupervisor;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;
use App\Services\KpMasterDataReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MasterDataReadAdapterTest extends TestCase
{
    use RefreshDatabase;

    private string $coreDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coreDatabasePath = tempnam(sys_get_temp_dir(), 'core-read-adapter-');
        config()->set('database.connections.core', [
            'driver' => 'sqlite',
            'database' => $this->coreDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('core');
        $this->createCoreSchema();
        $this->seedRows();
    }

    protected function tearDown(): void
    {
        DB::purge('core');

        if (isset($this->coreDatabasePath) && file_exists($this->coreDatabasePath)) {
            unlink($this->coreDatabasePath);
        }

        parent::tearDown();
    }

    public function test_default_read_mode_is_legacy(): void
    {
        $this->assertSame('legacy', config('kp_master_data.read_mode'));
    }

    public function test_student_display_legacy_works(): void
    {
        $data = app(KpMasterDataReadService::class)->getStudentDisplayData(Student::first(), 'legacy');

        $this->assertSame('legacy', $data->source);
        $this->assertSame('Legacy Student', $data->name);
        $this->assertSame('Farmasi Legacy', $data->studyProgramName);
    }

    public function test_student_display_core_preferred_uses_core_when_mapped(): void
    {
        $data = app(KpMasterDataReadService::class)->getStudentDisplayData(Student::first(), 'core_preferred');

        $this->assertSame('core', $data->source);
        $this->assertSame('Core Student', $data->name);
        $this->assertSame('S1 Farmasi', $data->studyProgramName);
    }

    public function test_core_preferred_fallbacks_to_legacy_when_core_missing(): void
    {
        DB::connection('core')->table('students')->delete();

        $data = app(KpMasterDataReadService::class)->getStudentDisplayData(Student::first(), 'core_preferred');

        $this->assertSame('legacy', $data->source);
        $this->assertNotNull($data->error);
    }

    public function test_core_only_throws_controlled_exception_when_core_missing(): void
    {
        DB::connection('core')->table('students')->delete();

        $this->expectException(CoreMasterDataUnavailableException::class);

        app(KpMasterDataReadService::class)->getStudentDisplayData(Student::first(), 'core_only');
    }

    public function test_lecturer_display_legacy_and_core_preferred_work(): void
    {
        $service = app(KpMasterDataReadService::class);
        $legacy = $service->getLecturerDisplayData(Lecturer::first(), 'legacy');
        $core = $service->getLecturerDisplayData(Lecturer::first(), 'core_preferred');

        $this->assertSame('legacy', $legacy->source);
        $this->assertSame('Legacy Lecturer', $legacy->name);
        $this->assertSame('core', $core->source);
        $this->assertSame('Dr. Core Lecturer, M.Farm.', $core->name);
        $this->assertSame('Fakultas Farmasi', $core->departmentName);
    }

    public function test_lecturer_display_falls_back_to_base_name_when_titled_name_is_missing(): void
    {
        DB::connection('core')->table('lecturers')->where('id', 20)->update([
            'display_name_with_title' => null,
            'formal_name' => null,
            'front_title' => null,
            'back_title' => null,
        ]);

        $data = app(KpMasterDataReadService::class)->getLecturerDisplayData(Lecturer::first(), 'core_preferred');

        $this->assertSame('core', $data->source);
        $this->assertSame('Core Lecturer', $data->name);
    }

    public function test_select_lists_work(): void
    {
        $service = app(KpMasterDataReadService::class);

        $this->assertCount(1, $service->listStudentsForSelect('221063', 50, 'core_preferred'));
        $this->assertCount(1, $service->listLecturersForSelect('001234', 50, 'core_preferred'));
    }

    public function test_master_data_read_check_command_is_registered_and_read_only(): void
    {
        $beforeCoreStudents = DB::connection('core')->table('students')->count();
        $beforeLegacyStudents = DB::table('students')->count();

        $this->artisan('kp:master-data-read-check --mode=core_preferred --show-samples')
            ->expectsOutputToContain('Tested mode: core_preferred')
            ->assertSuccessful();

        $this->assertSame($beforeCoreStudents, DB::connection('core')->table('students')->count());
        $this->assertSame($beforeLegacyStudents, DB::table('students')->count());
    }

    public function test_display_helpers_return_labels(): void
    {
        config()->set('kp_master_data.read_mode', 'core_preferred');

        $this->assertSame('221063120001 - Core Student', student_display_label(Student::first()));
        $this->assertSame('0012345601 - Dr. Core Lecturer, M.Farm.', lecturer_display_label(Lecturer::first()));
    }

    public function test_select_values_remain_legacy_ids_and_validation_accepts_legacy_ids(): void
    {
        $student = Student::first();
        $lecturer = Lecturer::first();
        $service = app(KpMasterDataReadService::class);

        $studentDisplay = $service->getStudentDisplayData($student, 'core_preferred');
        $lecturerDisplay = $service->getLecturerDisplayData($lecturer, 'core_preferred');

        $this->assertSame($student->id, $studentDisplay->legacyStudentId);
        $this->assertSame($lecturer->id, $lecturerDisplay->legacyLecturerId);
        $this->assertNotSame($student->id, $studentDisplay->coreStudentId);
        $this->assertNotSame($lecturer->id, $lecturerDisplay->coreLecturerId);

        $this->assertTrue(Validator::make(['student_id' => $student->id], [
            'student_id' => ['required', 'exists:students,id'],
        ])->passes());

        $this->assertTrue(Validator::make(['internal_supervisor_id' => $lecturer->id], [
            'internal_supervisor_id' => ['required', 'exists:lecturers,id'],
        ])->passes());
    }

    public function test_display_adapter_does_not_write_to_core_or_kp(): void
    {
        $before = [
            'kp_students' => DB::table('students')->count(),
            'kp_lecturers' => DB::table('lecturers')->count(),
            'core_students' => DB::connection('core')->table('students')->count(),
            'core_lecturers' => DB::connection('core')->table('lecturers')->count(),
        ];

        $service = app(KpMasterDataReadService::class);
        $service->getStudentDisplayData(Student::first(), 'core_preferred');
        $service->getLecturerDisplayData(Lecturer::first(), 'core_preferred');

        $this->artisan('kp:display-adapter-check --mode=core_preferred --show-samples')
            ->expectsOutputToContain('Read-only counts unchanged: yes')
            ->assertSuccessful();

        $this->assertSame($before['kp_students'], DB::table('students')->count());
        $this->assertSame($before['kp_lecturers'], DB::table('lecturers')->count());
        $this->assertSame($before['core_students'], DB::connection('core')->table('students')->count());
        $this->assertSame($before['core_lecturers'], DB::connection('core')->table('lecturers')->count());
    }

    public function test_display_adapter_check_command_runs(): void
    {
        $this->artisan('kp:display-adapter-check --mode=legacy --show-samples')
            ->expectsOutputToContain('Tested mode: legacy')
            ->expectsOutputToContain('Student select value uses legacy ID: yes')
            ->expectsOutputToContain('Lecturer select value uses legacy ID: yes')
            ->assertSuccessful();
    }

    public function test_core_mode_preflight_passes_for_legacy_and_core_preferred_modes(): void
    {
        $this->artisan('kp:core-mode-preflight --auth-mode=legacy --master-data-mode=legacy --email=admin@sikp.test --show-samples')
            ->expectsOutputToContain('Status: PASS')
            ->expectsOutputToContain('Requested auth mode: legacy')
            ->assertSuccessful();

        $this->artisan('kp:core-mode-preflight --auth-mode=core_bridge --master-data-mode=core_preferred --email=admin@sikp.test --show-samples')
            ->expectsOutputToContain('Status: PASS')
            ->expectsOutputToContain('Requested auth mode: core_bridge')
            ->expectsOutputToContain('Requested master data mode: core_preferred')
            ->assertSuccessful();
    }

    public function test_core_mode_preflight_fails_for_invalid_auth_mode(): void
    {
        $this->artisan('kp:core-mode-preflight --auth-mode=banana --master-data-mode=legacy')
            ->expectsOutputToContain('Status: FAIL')
            ->expectsOutputToContain('Invalid requested auth mode: banana.')
            ->assertFailed();
    }

    public function test_core_mode_preflight_fails_for_invalid_master_data_mode(): void
    {
        $this->artisan('kp:core-mode-preflight --auth-mode=legacy --master-data-mode=banana')
            ->expectsOutputToContain('Status: FAIL')
            ->expectsOutputToContain('Invalid requested master data mode: banana.')
            ->assertFailed();
    }

    public function test_core_mode_preflight_writes_json_report_and_is_read_only(): void
    {
        $beforeReports = glob(storage_path('app/reports/kp-core-mode-preflight-*.json')) ?: [];
        $beforeCounts = [
            'kp_users' => DB::table('users')->count(),
            'kp_students' => DB::table('students')->count(),
            'kp_lecturers' => DB::table('lecturers')->count(),
            'kp_field_supervisors' => DB::table('field_supervisors')->count(),
            'core_users' => DB::connection('core')->table('users')->count(),
            'core_students' => DB::connection('core')->table('students')->count(),
            'core_lecturers' => DB::connection('core')->table('lecturers')->count(),
            'core_user_app_accesses' => DB::connection('core')->table('user_app_accesses')->count(),
        ];

        $this->artisan('kp:core-mode-preflight --auth-mode=legacy --master-data-mode=legacy --email=admin@sikp.test --report-json')
            ->expectsOutputToContain('Status: PASS')
            ->assertSuccessful();

        $afterReports = glob(storage_path('app/reports/kp-core-mode-preflight-*.json')) ?: [];
        $this->assertGreaterThan(count($beforeReports), count($afterReports));
        $this->assertSame($beforeCounts['kp_users'], DB::table('users')->count());
        $this->assertSame($beforeCounts['kp_students'], DB::table('students')->count());
        $this->assertSame($beforeCounts['kp_lecturers'], DB::table('lecturers')->count());
        $this->assertSame($beforeCounts['kp_field_supervisors'], DB::table('field_supervisors')->count());
        $this->assertSame($beforeCounts['core_users'], DB::connection('core')->table('users')->count());
        $this->assertSame($beforeCounts['core_students'], DB::connection('core')->table('students')->count());
        $this->assertSame($beforeCounts['core_lecturers'], DB::connection('core')->table('lecturers')->count());
        $this->assertSame($beforeCounts['core_user_app_accesses'], DB::connection('core')->table('user_app_accesses')->count());
    }

    public function test_core_mode_preflight_keeps_admin_kp_out_of_admin_core(): void
    {
        $this->artisan('kp:core-mode-preflight --auth-mode=core_bridge --master-data-mode=core_preferred --email=admin@sikp.test')
            ->expectsOutputToContain('admin-kp: yes')
            ->expectsOutputToContain('admin-core: no')
            ->assertSuccessful();
    }

    private function createCoreSchema(): void
    {
        Schema::connection('core')->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('display_name_with_title')->nullable();
            $table->string('formal_name')->nullable();
            $table->string('email');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('core')->create('roles', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('label')->nullable();
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
            $table->string('front_title')->nullable();
            $table->string('back_title')->nullable();
            $table->string('display_name_with_title')->nullable();
            $table->string('formal_name')->nullable();
            $table->string('email');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('study_program_id')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
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

    private function seedRows(): void
    {
        DB::connection('core')->table('users')->insert([
            ['id' => 1, 'name' => 'Core Student', 'email' => 'student@sikp.test', 'active' => true],
            ['id' => 2, 'name' => 'Core Lecturer', 'email' => 'lecturer@sikp.test', 'active' => true],
            ['id' => 30, 'name' => 'Admin KP', 'email' => 'admin@sikp.test', 'active' => true],
            ['id' => 40, 'name' => 'Lapangan', 'email' => 'lapangan@sikp.test', 'active' => true],
        ]);
        DB::connection('core')->table('roles')->insert([
            ['id' => 1, 'name' => 'admin-kp', 'label' => 'Admin KP', 'active' => true],
            ['id' => 2, 'name' => 'mahasiswa', 'label' => 'Mahasiswa', 'active' => true],
            ['id' => 3, 'name' => 'pembimbing-lapangan', 'label' => 'Pembimbing Lapangan', 'active' => true],
        ]);
        DB::connection('core')->table('user_roles')->insert([
            ['id' => 1, 'user_id' => 30, 'role_id' => 1],
            ['id' => 2, 'user_id' => 1, 'role_id' => 2],
            ['id' => 3, 'user_id' => 40, 'role_id' => 3],
        ]);
        DB::connection('core')->table('departments')->insert(['id' => 1, 'name' => 'Fakultas Farmasi', 'active' => true]);
        DB::connection('core')->table('study_programs')->insert(['id' => 1, 'department_id' => 1, 'name' => 'S1 Farmasi', 'active' => true]);
        DB::connection('core')->table('students')->insert(['id' => 10, 'user_id' => 1, 'student_number' => '221063120001', 'name' => 'Core Student', 'email' => 'student@sikp.test', 'study_program_id' => 1, 'active' => true]);
        DB::connection('core')->table('lecturers')->insert(['id' => 20, 'user_id' => 2, 'lecturer_number' => '0012345601', 'name' => 'Core Lecturer', 'front_title' => 'Dr.', 'back_title' => 'M.Farm.', 'display_name_with_title' => 'Dr. Core Lecturer, M.Farm.', 'formal_name' => 'Dr. Core Lecturer, M.Farm.', 'email' => 'lecturer@sikp.test', 'department_id' => 1, 'study_program_id' => 1, 'notes' => 'Clinical', 'active' => true]);
        DB::connection('core')->table('user_app_accesses')->insert([
            ['id' => 1, 'user_id' => 30, 'app_code' => 'kppspa-farmasi', 'role_slug' => 'admin-kp', 'is_active' => true],
            ['id' => 2, 'user_id' => 1, 'app_code' => 'kppspa-farmasi', 'role_slug' => 'mahasiswa', 'is_active' => true],
            ['id' => 3, 'user_id' => 40, 'app_code' => 'kppspa-farmasi', 'role_slug' => 'pembimbing-lapangan', 'is_active' => true],
        ]);

        User::create(['name' => 'Admin KP', 'email' => 'admin@sikp.test', 'password' => 'hash', 'status' => 'active', 'core_user_id' => 30]);
        $fieldSupervisorUser = User::create(['name' => 'Lapangan', 'email' => 'lapangan@sikp.test', 'password' => 'hash', 'status' => 'active', 'core_user_id' => 40]);
        $studentUser = User::create(['name' => 'Legacy Student', 'email' => 'student@sikp.test', 'password' => 'hash', 'status' => 'active']);
        $lecturerUser = User::create(['name' => 'Legacy Lecturer', 'email' => 'lecturer@sikp.test', 'password' => 'hash', 'status' => 'active']);

        Student::create(['user_id' => $studentUser->id, 'nim' => '221063120001', 'study_program' => 'Farmasi Legacy', 'semester' => 6, 'class_name' => 'A', 'status' => 'active', 'core_student_id' => 10]);
        Lecturer::create(['user_id' => $lecturerUser->id, 'nidn_nip' => '0012345601', 'study_program' => 'Farmasi Legacy', 'department' => 'Departemen Legacy', 'expertise' => 'Legacy Expert', 'status' => 'active', 'core_lecturer_id' => 20]);
        FieldSupervisor::create(['user_id' => $fieldSupervisorUser->id, 'institution_name' => 'Apotek Test', 'position' => 'Pembimbing', 'status' => 'active', 'core_user_id' => 40]);
    }
}
