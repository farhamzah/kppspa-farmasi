<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoreDirectorySearchControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('core_farmasi.enabled', true);
        config()->set('core_farmasi.base_url', 'https://core.test');
        config()->set('core_farmasi.client_id', 'client');
        config()->set('core_farmasi.client_secret', 'secret');
        config()->set('core_farmasi.app_code', 'kppspa-farmasi');

        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class]);

        $this->admin = $this->makeUser('admin-directory@test.local', ['admin'], 'core-admin-directory');
    }

    public function test_lecturer_directory_only_returns_active_eligible_users_with_access(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/internal/directory/lecturers')) {
                return Http::response([
                    'data' => [
                        $this->lecturer('CORE-DOSEN-OK', 'Dosen Aktif', ['pembimbing_dalam']),
                        $this->lecturer('CORE-DOSEN-INACTIVE', 'Dosen Nonaktif', ['pembimbing_dalam'], false),
                        $this->lecturer('CORE-DOSEN-NOACCESS', 'Dosen Tanpa Akses', ['pembimbing_dalam']),
                        $this->lecturer('CORE-DOSEN-WRONGROLE', 'Dosen Salah Role', ['mahasiswa']),
                    ],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-DOSEN-OK/access')) {
                return Http::response(['has_access' => true], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-DOSEN-INACTIVE/access')) {
                return Http::response(['has_access' => true], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-DOSEN-NOACCESS/access')) {
                return Http::response(['has_access' => false], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-DOSEN-WRONGROLE/access')) {
                return Http::response(['has_access' => true], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.lecturers', ['q' => 'dosen']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.core_user_id', 'CORE-DOSEN-OK')
            ->assertJsonPath('data.0.name', 'Dosen Aktif');
    }

    public function test_field_supervisor_directory_only_returns_active_users_with_access(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/internal/directory/users')) {
                return Http::response([
                    'data' => [
                        $this->userPayload('CORE-FIELD-OK', 'Preseptor Aktif', ['pembimbing_lapangan']),
                        $this->userPayload('CORE-FIELD-NOACCESS', 'Preseptor Tanpa Akses', ['pembimbing_lapangan']),
                        $this->userPayload('CORE-FIELD-WRONGROLE', 'User Biasa', ['admin-kp']),
                    ],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-FIELD-OK/access')) {
                return Http::response(['has_access' => true], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-FIELD-NOACCESS/access')) {
                return Http::response(['has_access' => false], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-FIELD-WRONGROLE/access')) {
                return Http::response(['has_access' => true], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.field-supervisors', ['q' => 'preseptor']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.core_user_id', 'CORE-FIELD-OK')
            ->assertJsonPath('data.0.name', 'Preseptor Aktif');
    }

    public function test_student_directory_only_returns_active_students_with_access(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/internal/directory/students')) {
                return Http::response([
                    'data' => [
                        $this->student('CORE-MHS-OK', 'Mahasiswa Aktif', '231001', ['mahasiswa']),
                        $this->student('CORE-MHS-NOACCESS', 'Mahasiswa Tanpa Akses', '231002', ['mahasiswa']),
                        $this->student('CORE-MHS-INACTIVE', 'Mahasiswa Nonaktif', '231003', ['mahasiswa'], false),
                        $this->student('CORE-MHS-WRONGROLE', 'Bukan Mahasiswa', '231004', ['pembimbing_dalam']),
                    ],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-MHS-OK/access')) {
                return Http::response(['has_access' => true], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-MHS-NOACCESS/access')) {
                return Http::response(['has_access' => false], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-MHS-INACTIVE/access')) {
                return Http::response(['has_access' => true], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-MHS-WRONGROLE/access')) {
                return Http::response(['has_access' => true], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.students', ['q' => 'mahasiswa']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.core_user_id', 'CORE-MHS-OK')
            ->assertJsonPath('data.0.student_number', '231001');
    }

    public function test_non_management_role_cannot_access_core_directory_search(): void
    {
        $student = $this->makeUser('student-directory@test.local', ['mahasiswa'], 'core-student-directory');

        $this->actingAs($student)
            ->withSession(['active_role' => 'mahasiswa'])
            ->getJson(route('management.core-directory.students'))
            ->assertForbidden();
    }

    private function makeUser(string $email, array $roles, string $coreUserId): User
    {
        $user = User::factory()->create([
            'name' => str($email)->before('@')->headline(),
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
            'profile_completed' => true,
            'core_user_id' => $coreUserId,
        ]);

        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user->load('roles');
    }

    private function lecturer(string $coreUserId, string $name, array $roles, bool $active = true): array
    {
        return [
            'core_user_id' => $coreUserId,
            'name' => $name,
            'email' => strtolower($coreUserId).'@core.test',
            'employee_number' => 'EMP-'.$coreUserId,
            'professional_id' => 'STR-'.$coreUserId,
            'active' => $active,
            'roles' => collect($roles)->map(fn ($role) => ['slug' => $role])->all(),
        ];
    }

    private function userPayload(string $coreUserId, string $name, array $roles, bool $active = true): array
    {
        return [
            'id' => $coreUserId,
            'name' => $name,
            'email' => strtolower($coreUserId).'@core.test',
            'employee_number' => 'EMP-'.$coreUserId,
            'active' => $active,
            'roles' => collect($roles)->map(fn ($role) => ['slug' => $role])->all(),
        ];
    }

    private function student(string $coreUserId, string $name, string $studentNumber, array $roles, bool $active = true): array
    {
        return [
            'core_user_id' => $coreUserId,
            'name' => $name,
            'email' => strtolower($coreUserId).'@core.test',
            'student_number' => $studentNumber,
            'study_program' => 'Farmasi S1',
            'cohort' => '2023',
            'active' => $active,
            'roles' => collect($roles)->map(fn ($role) => ['slug' => $role])->all(),
        ];
    }
}
