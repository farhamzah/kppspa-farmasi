<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoreProfileReadOnlyDisplayTest extends TestCase
{
    use RefreshDatabase;

    private string $coreDatabasePath;

    private string $corePublicStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->coreDatabasePath = tempnam(sys_get_temp_dir(), 'core-profile-');
        config()->set('database.connections.core', [
            'driver' => 'sqlite',
            'database' => $this->coreDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('core_farmasi.profile_url', 'https://core.test/profile');

        DB::purge('core');
        $this->createCoreSchema();

        $this->corePublicStoragePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'core-profile-storage-'.uniqid();
        File::makeDirectory($this->corePublicStoragePath.DIRECTORY_SEPARATOR.'profile-photos', 0755, true);
        file_put_contents($this->corePublicStoragePath.DIRECTORY_SEPARATOR.'profile-photos'.DIRECTORY_SEPARATOR.'core.jpg', 'fake-image');
        config()->set('core_farmasi.storage_public_path', $this->corePublicStoragePath);
    }

    protected function tearDown(): void
    {
        DB::purge('core');

        if (isset($this->coreDatabasePath) && file_exists($this->coreDatabasePath)) {
            unlink($this->coreDatabasePath);
        }

        if (isset($this->corePublicStoragePath) && is_dir($this->corePublicStoragePath)) {
            File::deleteDirectory($this->corePublicStoragePath);
        }

        parent::tearDown();
    }

    public function test_profile_page_uses_active_role_context_for_multi_role_user(): void
    {
        $this->coreLecturerProfile();

        $user = User::create([
            'name' => 'Legacy Multi',
            'email' => 'multi-profile@sikp.test',
            'password' => Hash::make('password'),
            'status' => 'active',
            'profile_completed' => true,
            'core_user_id' => 10,
        ]);
        $user->roles()->sync(Role::whereIn('name', ['mahasiswa', 'pembimbing_dalam'])->pluck('id'));
        $user->student()->create(['nim' => '240001']);
        $user->lecturer()->create(['nidn_nip' => '0012345601', 'core_lecturer_id' => 20]);

        $this->actingAs($user)
            ->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/profil-saya')
            ->assertOk()
            ->assertSee('Profil Resmi Core')
            ->assertSee('Profil Dosen')
            ->assertSee('Dr. Dosen Core, M.Farm.')
            ->assertSee('Farmasi S1')
            ->assertSee('Teknologi Sediaan Farmasi')
            ->assertSee('Data Operasional KP')
            ->assertSee('https://core.test/storage/profile-photos/core.jpg', false)
            ->assertSee('Belum ada data operasional tambahan')
            ->assertDontSee('CORE LECTURER ID');

        $this->actingAs($user)
            ->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/profile/edit')
            ->assertOk()
            ->assertSee('Bidang Keahlian/Expertise')
            ->assertDontSee('Ubah Foto')
            ->assertDontSee('Nomor Induk Mahasiswa');

        $this->actingAs($user)
            ->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/profile/core-avatar')
            ->assertOk();
    }

    public function test_core_managed_profile_fields_are_read_only_from_kp_update(): void
    {
        $this->coreLecturerProfile();

        $user = User::create([
            'name' => 'Legacy Dosen',
            'email' => 'multi-profile@sikp.test',
            'password' => Hash::make('password'),
            'status' => 'active',
            'profile_completed' => true,
            'core_user_id' => 10,
        ]);
        $user->roles()->sync(Role::whereIn('name', ['pembimbing_dalam'])->pluck('id'));
        $lecturer = $user->lecturer()->create([
            'nidn_nip' => '0012345601',
            'phone' => '0800-local',
            'department' => 'Departemen Lokal',
            'expertise' => 'Farmasetika',
            'core_lecturer_id' => 20,
        ]);
        $beforeCoreUsers = DB::connection('core')->table('users')->count();

        $this->actingAs($user)
            ->withSession(['active_role' => 'pembimbing_dalam'])
            ->put('/profile', [
                'phone' => '0999-should-not-save',
                'department' => 'Departemen Should Not Save',
                'expertise' => 'Teknologi Sediaan',
            ])
            ->assertRedirect('/profil-saya');

        $lecturer->refresh();
        $this->assertSame('0800-local', $lecturer->phone);
        $this->assertSame('Departemen Lokal', $lecturer->department);
        $this->assertSame('Teknologi Sediaan', $lecturer->expertise);
        $this->assertTrue($user->fresh()->profile_completed);
        $this->assertSame($beforeCoreUsers, DB::connection('core')->table('users')->count());
        $this->assertDatabaseHas('lecturers', [
            'id' => 20,
            'phone' => '081234567890',
        ], 'core');
    }

    public function test_titled_core_lecturer_name_is_used_for_admin_and_field_supervisor_context(): void
    {
        $this->coreLecturerProfile();

        $user = User::create([
            'name' => 'Legacy Plain',
            'email' => 'multi-profile@sikp.test',
            'password' => Hash::make('password'),
            'status' => 'active',
            'profile_completed' => true,
            'core_user_id' => 10,
        ]);
        $user->roles()->sync(Role::whereIn('name', ['admin', 'pembimbing_lapangan'])->pluck('id'));
        $user->lecturer()->create(['nidn_nip' => '0012345601', 'core_lecturer_id' => 20]);

        $this->actingAs($user)
            ->withSession(['active_role' => 'admin'])
            ->get('/profil-saya')
            ->assertOk()
            ->assertSee('Dr. Dosen Core, M.Farm.');

        $this->actingAs($user)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/profil-saya')
            ->assertOk()
            ->assertSee('Dr. Dosen Core, M.Farm.')
            ->assertSee('Profil Dosen');
    }

    private function createCoreSchema(): void
    {
        Schema::connection('core')->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('username')->nullable();
            $table->string('identity_type')->nullable();
            $table->string('identity_number')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->string('display_name_with_title')->nullable();
            $table->string('formal_name')->nullable();
            $table->boolean('active')->default(true);
        });

        Schema::connection('core')->create('faculties', function ($table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->boolean('active')->default(true);
        });

        Schema::connection('core')->create('departments', function ($table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->boolean('active')->default(true);
        });

        Schema::connection('core')->create('study_programs', function ($table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->unsignedBigInteger('faculty_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->boolean('active')->default(true);
        });

        Schema::connection('core')->create('students', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('student_number')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->nullable();
            $table->boolean('active')->default(true);
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('study_program_id')->nullable();
        });

        Schema::connection('core')->create('lecturers', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('lecturer_number')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nidk')->nullable();
            $table->string('nip')->nullable();
            $table->string('nuptk')->nullable();
            $table->string('national_id_number')->nullable();
            $table->string('name')->nullable();
            $table->string('front_title')->nullable();
            $table->string('back_title')->nullable();
            $table->string('display_name_with_title')->nullable();
            $table->string('formal_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('study_program_id')->nullable();
            $table->text('notes')->nullable();
        });
    }

    private function coreLecturerProfile(): void
    {
        DB::connection('core')->table('users')->insert([
            'id' => 10,
            'name' => 'Dosen Core',
            'email' => 'multi-profile@sikp.test',
            'username' => '0012345601',
            'identity_type' => 'lecturer',
            'identity_number' => '3275010101010001',
            'profile_photo_path' => 'profile-photos/core.jpg',
            'display_name_with_title' => null,
            'formal_name' => null,
            'active' => true,
        ]);
        DB::connection('core')->table('faculties')->insert(['id' => 1, 'code' => 'FF', 'name' => 'Fakultas Farmasi', 'active' => true]);
        DB::connection('core')->table('departments')->insert(['id' => 1, 'code' => 'TSF', 'name' => 'Teknologi Sediaan Farmasi', 'active' => true]);
        DB::connection('core')->table('study_programs')->insert(['id' => 1, 'code' => 'S1F', 'name' => 'Farmasi S1', 'faculty_id' => 1, 'department_id' => 1, 'active' => true]);
        DB::connection('core')->table('lecturers')->insert([
            'id' => 20,
            'user_id' => 10,
            'lecturer_number' => '0012345601',
            'nidn' => '0012345601',
            'nip' => '198001012010011001',
            'national_id_number' => '3275010101010001',
            'name' => 'Dosen Core',
            'front_title' => 'Dr.',
            'back_title' => 'M.Farm.',
            'display_name_with_title' => 'Dr. Dosen Core, M.Farm.',
            'formal_name' => 'Dr. Dosen Core, M.Farm.',
            'email' => 'multi-profile@sikp.test',
            'phone' => '081234567890',
            'address' => 'Alamat Core',
            'birth_place' => 'Karawang',
            'birth_date' => '1980-01-01',
            'active' => true,
            'department_id' => 1,
            'study_program_id' => 1,
            'notes' => 'Teknologi Farmasi',
        ]);
    }
}
