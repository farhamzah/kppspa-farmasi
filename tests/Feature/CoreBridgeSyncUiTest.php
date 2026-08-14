<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoreBridgeSyncUiTest extends TestCase
{
    use RefreshDatabase;

    private string $coreDatabasePath;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->admin = $this->user('Admin KP', 'admin@test.local', ['admin']);

        $this->coreDatabasePath = tempnam(sys_get_temp_dir(), 'core-sync-ui-');
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

    public function test_admin_can_sync_single_user_from_core_without_copying_password(): void
    {
        $target = $this->user('SYALZHABILLA YASMIN UBPKARAWANG', 'fm24.syalzhabillayasmin@mhs.ubpkarawang.ac.id', ['mahasiswa']);
        $oldPassword = $target->password;
        $this->coreUser(81, 'SYALZHABILLA YASMIN', $target->email, ['mahasiswa']);

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/admin/users/'.$target->id.'/sync-core')
            ->assertRedirect()
            ->assertSessionHas('status');

        $target->refresh();
        $this->assertSame('SYALZHABILLA YASMIN', $target->name);
        $this->assertSame(81, (int) $target->core_user_id);
        $this->assertSame($oldPassword, $target->password);
        $this->assertEqualsCanonicalizing(['mahasiswa'], $target->roles()->pluck('name')->all());
    }

    public function test_admin_can_bulk_sync_core_users(): void
    {
        $first = $this->user('Nama Lama Satu', 'student-one@sikp.test', ['mahasiswa']);
        $second = $this->user('Nama Lama Dua', 'student-two@sikp.test', ['mahasiswa']);
        $this->coreUser(91, 'Nama Core Satu', $first->email, ['mahasiswa']);
        $this->coreUser(92, 'Nama Core Dua', $second->email, ['mahasiswa']);

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/admin/users/sync-core')
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('Nama Core Satu', $first->fresh()->name);
        $this->assertSame('Nama Core Dua', $second->fresh()->name);
    }

    public function test_user_can_refresh_own_profile_from_core(): void
    {
        $user = $this->user('Nama Lokal Lama', 'student-self@sikp.test', ['mahasiswa']);
        config()->set('core_farmasi.enabled', true);
        config()->set('core_farmasi.base_url', 'https://core.test');
        config()->set('core_farmasi.app_code', 'kppspa-farmasi');
        config()->set('core_farmasi.client_id', 'client-id');
        config()->set('core_farmasi.client_secret', 'client-secret');

        Http::fake([
            'https://core.test/api/v1/internal/apps/kppspa-farmasi/users*' => Http::response([
                'data' => [[
                    'user_id' => 101,
                    'app_code' => 'kppspa-farmasi',
                    'roles' => [['slug' => 'mahasiswa', 'name' => 'Mahasiswa']],
                    'user' => [
                        'id' => 101,
                        'name' => 'Nama Core Baru',
                        'email' => $user->email,
                        'active' => true,
                    ],
                    'profiles' => [
                        'student' => [
                            'id' => 301,
                            'user_id' => 101,
                            'student_number' => 'PSPA301',
                            'student_class' => 'PSPA-A',
                            'name' => 'Nama Core Baru',
                            'email' => $user->email,
                            'active' => true,
                            'study_program' => ['name' => 'Profesi Apoteker'],
                        ],
                        'lecturer' => null,
                        'external_person' => null,
                    ],
                ]],
                'meta' => ['total' => 1, 'has_more' => false],
            ]),
        ]);

        $this->actingAs($user)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/profile/sync-core')
            ->assertRedirect('/profil-saya')
            ->assertSessionHas('status');

        $this->assertSame('Nama Core Baru', $user->fresh()->name);
        $this->assertDatabaseHas('students', [
            'user_id' => $user->id,
            'core_student_id' => 301,
            'nim' => 'PSPA301',
        ]);
    }

    private function user(string $name, string $email, array $roles): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('local-password'),
            'status' => 'active',
        ]);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user;
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

    private function coreUser(int $id, string $name, string $email, array $accessRoles): void
    {
        DB::connection('core')->table('users')->insert([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('core-secret'),
            'active' => true,
            'must_change_password' => false,
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
}
