<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->admin->roles()->sync([Role::where('name', 'admin')->first()->id]);
    }

    public function test_admin_can_open_user_management_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->get('/admin/users');

        $response->assertOk()->assertSee('Manajemen Pengguna');
    }

    public function test_mahasiswa_cannot_open_user_management_page(): void
    {
        $user = User::create([
            'name' => 'Mahasiswa',
            'email' => 'mhs@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->roles()->sync([Role::where('name', 'mahasiswa')->first()->id]);

        $response = $this->actingAs($user)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_admin_can_create_student_user(): void
    {
        $role = Role::where('name', 'mahasiswa')->first();

        $response = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/admin/users', [
                'name' => 'Mahasiswa Baru',
                'email' => 'baru@test.local',
                'password' => 'password',
                'password_confirmation' => 'password',
                'status' => 'active',
                'roles' => [$role->id],
                'profile_type' => 'mahasiswa',
                'nim' => '221010099',
                'study_program' => 'Farmasi',
                'semester' => 6,
                'class_name' => 'A',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'baru@test.local']);
        $this->assertDatabaseHas('students', ['nim' => '221010099']);
    }

    public function test_admin_can_create_lecturer_multi_role_user(): void
    {
        $roles = Role::whereIn('name', ['pembimbing_dalam', 'penguji'])->pluck('id')->all();

        $response = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/admin/users', [
                'name' => 'Dosen Baru',
                'email' => 'dosenbaru@test.local',
                'password' => 'password',
                'password_confirmation' => 'password',
                'status' => 'active',
                'roles' => $roles,
                'profile_type' => 'dosen',
                'nidn_nip' => '0099887766',
                'employee_number' => 'DOS099',
                'study_program' => 'Farmasi',
                'department' => 'Farmasi Klinis',
            ]);

        $response->assertRedirect();
        $user = User::where('email', 'dosenbaru@test.local')->first();
        $this->assertCount(2, $user->roles);
        $this->assertDatabaseHas('lecturers', ['nidn_nip' => '0099887766']);
    }

    public function test_admin_cannot_toggle_own_status(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/admin/users/'.$this->admin->id.'/toggle-status');

        $response->assertSessionHasErrors('user');
        $this->assertSame('active', $this->admin->fresh()->status);
    }

    public function test_admin_can_toggle_other_user_status(): void
    {
        $user = User::create([
            'name' => 'Target',
            'email' => 'target@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->roles()->sync([Role::where('name', 'mahasiswa')->first()->id]);

        $response = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/admin/users/'.$user->id.'/toggle-status');

        $response->assertRedirect();
        $this->assertSame('inactive', $user->fresh()->status);
    }
}
