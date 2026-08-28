<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\PkpaProgram;
use App\Models\PkpaEnrollment;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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
                        $this->lecturer('CORE-DOSEN-APPROLE', 'Dosen Dari Role App', []),
                    ],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-DOSEN-OK/access')) {
                return Http::response(['has_access' => true, 'roles' => [['slug' => 'pembimbing-dalam']]], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-DOSEN-INACTIVE/access')) {
                return Http::response(['has_access' => true, 'roles' => [['slug' => 'pembimbing-dalam']]], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-DOSEN-NOACCESS/access')) {
                return Http::response(['has_access' => false], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-DOSEN-WRONGROLE/access')) {
                return Http::response(['has_access' => true, 'roles' => [['slug' => 'mahasiswa']]], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-DOSEN-APPROLE/access')) {
                return Http::response(['has_access' => true, 'roles' => [['slug' => 'pembimbing-dalam']]], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.lecturers', ['q' => 'dosen']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['core_user_id' => 'CORE-DOSEN-OK', 'name' => 'Dosen Aktif'])
            ->assertJsonFragment(['core_user_id' => 'CORE-DOSEN-APPROLE', 'name' => 'Dosen Dari Role App']);
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

    public function test_field_supervisor_directory_falls_back_to_app_access_users_when_directory_results_are_empty(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/internal/directory/users')) {
                return Http::response([
                    'data' => [],
                    'meta' => ['page' => 1, 'limit' => 10, 'total' => 0, 'has_more' => false],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users?') || str_ends_with($url, '/internal/apps/kppspa-farmasi/users')) {
                return Http::response([
                    'data' => [[
                        'user_id' => 501,
                        'app_code' => 'kppspa-farmasi',
                        'roles' => [
                            ['slug' => 'pembimbing-lapangan', 'name' => 'Pembimbing Lapangan'],
                        ],
                        'user' => [
                            'id' => 501,
                            'name' => 'Siti Preseptor',
                            'email' => 'siti.preseptor@mitra.test',
                            'active' => true,
                        ],
                        'profiles' => [
                            'external_person' => [
                                'id' => 45,
                                'user_id' => 501,
                                'display_name_with_title' => 'apt. Siti Preseptor, S.Farm.',
                                'institution_name' => 'Apotek Mitra',
                                'position_title' => 'Apoteker Pendamping',
                                'active' => true,
                            ],
                        ],
                    ]],
                    'meta' => ['page' => 1, 'limit' => 100, 'total' => 1, 'has_more' => false],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/501/access')) {
                return Http::response(['has_access' => true, 'roles' => [['slug' => 'pembimbing-lapangan']]], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.field-supervisors', ['q' => 'siti']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.core_user_id', '501')
            ->assertJsonPath('data.0.name', 'apt. Siti Preseptor, S.Farm.')
            ->assertJsonPath('data.0.email', 'siti.preseptor@mitra.test');
    }

    public function test_field_supervisor_directory_can_show_initial_results_from_app_access_users_without_query(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users?') || str_ends_with($url, '/internal/apps/kppspa-farmasi/users')) {
                return Http::response([
                    'data' => [[
                        'user_id' => 502,
                        'app_code' => 'kppspa-farmasi',
                        'roles' => [
                            ['slug' => 'pembimbing-lapangan', 'name' => 'Pembimbing Lapangan'],
                        ],
                        'user' => [
                            'id' => 502,
                            'name' => 'Budi Mitra',
                            'email' => 'budi.mitra@mitra.test',
                            'active' => true,
                        ],
                        'profiles' => [
                            'external_person' => [
                                'id' => 46,
                                'user_id' => 502,
                                'display_name_with_title' => 'dr. Budi Mitra',
                                'institution_name' => 'RS Mitra',
                                'position_title' => 'Supervisor Klinik',
                                'active' => true,
                            ],
                        ],
                    ]],
                    'meta' => ['page' => 1, 'limit' => 100, 'total' => 1, 'has_more' => false],
                ], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.field-supervisors'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.core_user_id', '502')
            ->assertJsonPath('data.0.name', 'dr. Budi Mitra')
            ->assertJsonPath('data.0.identifier', '502');
    }

    public function test_student_directory_returns_active_students_with_student_role(): void
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

    public function test_student_directory_falls_back_to_users_when_student_directory_empty(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/internal/directory/students')) {
                return Http::response([
                    'data' => [],
                    'meta' => ['page' => 1, 'limit' => 10, 'total' => 0, 'has_more' => false],
                ], 200);
            }

            if (str_contains($url, '/internal/directory/users')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 7,
                            'name' => 'Andi Farmasi',
                            'email' => 'andi@ubpkarawang.ac.id',
                            'identity_type' => 'student',
                            'identity_number' => '231001',
                            'active' => true,
                            'roles' => ['mahasiswa'],
                            'app_accesses' => [
                                ['app_code' => 'kppspa-farmasi', 'role_slug' => 'mahasiswa'],
                            ],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/7/access')) {
                return Http::response(['has_access' => true, 'roles' => [['slug' => 'mahasiswa']]], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.students', ['q' => 'andi']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.core_user_id', '7')
            ->assertJsonPath('data.0.name', 'Andi Farmasi')
            ->assertJsonPath('data.0.student_number', '231001');
    }

    public function test_student_directory_can_show_initial_user_list_without_query(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/internal/directory/students')) {
                return Http::response([
                    'data' => [],
                    'meta' => ['page' => 1, 'limit' => 10, 'total' => 0, 'has_more' => false],
                ], 200);
            }

            if (str_contains($url, '/internal/directory/users')) {
                return Http::response([
                    'data' => [],
                    'meta' => ['page' => 1, 'limit' => 10, 'total' => 0, 'has_more' => false],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users?') || str_ends_with($url, '/internal/apps/kppspa-farmasi/users')) {
                return Http::response([
                    'data' => [[
                        'user_id' => 17,
                        'app_code' => 'kppspa-farmasi',
                        'roles' => [
                            ['slug' => 'mahasiswa', 'name' => 'Mahasiswa'],
                        ],
                        'user' => [
                            'id' => 17,
                            'name' => 'Siti Farmasi',
                            'email' => 'siti@ubpkarawang.ac.id',
                            'active' => true,
                        ],
                        'profiles' => [
                            'student' => [
                                'id' => 201,
                                'user_id' => 17,
                                'student_number' => '231010',
                                'active' => true,
                            ],
                        ],
                    ]],
                    'meta' => ['page' => 1, 'limit' => 100, 'total' => 1, 'has_more' => false],
                ], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.students'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.core_user_id', '17')
            ->assertJsonPath('data.0.name', 'Siti Farmasi')
            ->assertJsonPath('data.0.student_number', '231010');
    }

    public function test_student_directory_falls_back_to_app_access_users_when_directory_results_are_empty(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/internal/directory/students')) {
                return Http::response([
                    'data' => [],
                    'meta' => ['page' => 1, 'limit' => 10, 'total' => 0, 'has_more' => false],
                ], 200);
            }

            if (str_contains($url, '/internal/directory/users')) {
                return Http::response([
                    'data' => [],
                    'meta' => ['page' => 1, 'limit' => 10, 'total' => 0, 'has_more' => false],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users?') || str_ends_with($url, '/internal/apps/kppspa-farmasi/users')) {
                return Http::response([
                    'data' => [[
                        'user_id' => 398,
                        'app_code' => 'kppspa-farmasi',
                        'roles' => [
                            ['slug' => 'mahasiswa', 'name' => 'Mahasiswa'],
                        ],
                        'user' => [
                            'id' => 398,
                            'name' => 'Andina Sahara Agustin',
                            'email' => 'ap26.andinaagustin@mhs.ubpkarawang.ac.id',
                            'active' => true,
                        ],
                        'profiles' => [
                            'student' => [
                                'id' => 1001,
                                'user_id' => 398,
                                'student_number' => '26416248901006',
                                'study_program' => ['name' => 'Profesi Apoteker'],
                                'cohort' => '2026',
                                'active' => true,
                            ],
                        ],
                    ]],
                    'meta' => ['page' => 1, 'limit' => 100, 'total' => 1, 'has_more' => false],
                ], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.students', ['q' => 'andina']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.core_user_id', '398')
            ->assertJsonPath('data.0.name', 'Andina Sahara Agustin')
            ->assertJsonPath('data.0.student_number', '26416248901006')
            ->assertJsonPath('data.0.email', 'ap26.andinaagustin@mhs.ubpkarawang.ac.id');
    }

    public function test_student_directory_can_show_initial_results_from_app_access_users_without_query(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/internal/directory/students')) {
                return Http::response([
                    'data' => [],
                    'meta' => ['page' => 1, 'limit' => 10, 'total' => 0, 'has_more' => false],
                ], 200);
            }

            if (str_contains($url, '/internal/directory/users')) {
                return Http::response([
                    'data' => [],
                    'meta' => ['page' => 1, 'limit' => 10, 'total' => 0, 'has_more' => false],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users?') || str_ends_with($url, '/internal/apps/kppspa-farmasi/users')) {
                return Http::response([
                    'data' => [[
                        'user_id' => 396,
                        'app_code' => 'kppspa-farmasi',
                        'roles' => [
                            ['slug' => 'mahasiswa', 'name' => 'Mahasiswa'],
                        ],
                        'user' => [
                            'id' => 396,
                            'name' => 'Syfa Dwi Andini',
                            'email' => 'ap26.syfaandini@mhs.ubpkarawang.ac.id',
                            'active' => true,
                        ],
                        'profiles' => [
                            'student' => [
                                'id' => 1002,
                                'user_id' => 396,
                                'student_number' => '26416248901008',
                                'study_program' => ['name' => 'Profesi Apoteker'],
                                'cohort' => '2026',
                                'active' => true,
                            ],
                        ],
                    ]],
                    'meta' => ['page' => 1, 'limit' => 100, 'total' => 1, 'has_more' => false],
                ], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.students'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.core_user_id', '396')
            ->assertJsonPath('data.0.name', 'Syfa Dwi Andini')
            ->assertJsonPath('data.0.student_number', '26416248901008');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/internal/apps/kppspa-farmasi/users'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/internal/apps/kppspa-farmasi/users/396/access'));
    }

    public function test_directory_search_uses_nested_user_id_for_access_checks(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/internal/directory/lecturers')) {
                return Http::response([
                    'data' => [[
                        'id' => 10,
                        'user_id' => 10,
                        'name' => 'Profil Dosen',
                        'user' => [
                            'id' => 2,
                            'name' => 'Farhamzah',
                            'email' => 'farhamzah@ubpkarawang.ac.id',
                            'active' => true,
                            'roles' => [['slug' => 'pembimbing_dalam']],
                        ],
                    ]],
                ], 200);
            }

            if (str_contains($url, '/internal/directory/students')) {
                return Http::response([
                    'data' => [[
                        'id' => 30,
                        'user_id' => 30,
                        'student_number' => '231001',
                        'name' => 'Profil Mahasiswa',
                        'user' => [
                            'id' => 7,
                            'name' => 'Andi Farmasi',
                            'email' => 'andi@ubpkarawang.ac.id',
                            'active' => true,
                            'roles' => [['slug' => 'mahasiswa']],
                        ],
                    ]],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/2/access')) {
                return Http::response(['has_access' => true, 'roles' => [['slug' => 'pembimbing-dalam']]], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/7/access')) {
                return Http::response(['has_access' => true, 'roles' => [['slug' => 'mahasiswa']]], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/10/access') || str_contains($url, '/internal/apps/kppspa-farmasi/users/30/access')) {
                return Http::response(['has_access' => false, 'roles' => []], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.lecturers', ['q' => 'farhamzah']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.core_user_id', '2')
            ->assertJsonPath('data.0.name', 'Farhamzah');

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.students', ['q' => 'andi']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.core_user_id', '7')
            ->assertJsonPath('data.0.name', 'Andi Farmasi')
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

    public function test_student_directory_filters_results_by_selected_program_cohort(): void
    {
        $program = PkpaProgram::create([
            'code' => 'PKPA-2026-G1',
            'name' => 'PKPA Farmasi UBP 2026 Gelombang 1',
            'academic_year' => '2026/2027',
            'cohort_name' => 'Profesi Apoteker 2026',
            'status' => 'draft',
            'is_active' => true,
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/internal/directory/students')) {
                return Http::response(['data' => [], 'meta' => []], 200);
            }

            if (str_contains($url, '/internal/directory/users')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 398,
                            'name' => 'Andina Sahara Agustin',
                            'email' => 'ap26.andinaagustin@mhs.ubpkarawang.ac.id',
                            'identity_type' => 'student',
                            'identity_number' => '26416248901006',
                            'active' => true,
                            'roles' => ['mahasiswa'],
                            'app_accesses' => [
                                ['app_code' => 'kppspa-farmasi', 'role_slug' => 'mahasiswa'],
                            ],
                        ],
                        [
                            'id' => 270,
                            'name' => 'Ananda Sofiana Sandi',
                            'email' => 'fm24.anandasandi@mhs.ubpkarawang.ac.id',
                            'identity_type' => 'student',
                            'identity_number' => '24416248201082',
                            'active' => true,
                            'roles' => ['mahasiswa'],
                            'app_accesses' => [
                                ['app_code' => 'kppspa-farmasi', 'role_slug' => 'mahasiswa'],
                            ],
                        ],
                    ],
                    'meta' => [],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/398/access') || str_contains($url, '/internal/apps/kppspa-farmasi/users/270/access')) {
                return Http::response(['has_access' => true, 'roles' => [['slug' => 'mahasiswa']]], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.students', ['q' => 'andi', 'program_id' => $program->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.core_user_id', '398')
            ->assertJsonPath('data.0.email', 'ap26.andinaagustin@mhs.ubpkarawang.ac.id')
            ->assertJsonPath('data.1.core_user_id', '270');
    }

    public function test_student_directory_hides_students_already_enrolled_in_selected_program(): void
    {
        $program = PkpaProgram::create([
            'code' => 'PKPA-2026-G1',
            'name' => 'PKPA Farmasi UBP 2026 Gelombang 1',
            'academic_year' => '2026/2027',
            'cohort_name' => 'Profesi Apoteker 2026',
            'status' => 'draft',
            'is_active' => true,
        ]);

        PkpaEnrollment::create([
            'pkpa_program_id' => $program->id,
            'core_user_id' => '398',
            'student_number' => '26416248901006',
            'student_name_snapshot' => 'Andina Sahara Agustin',
            'student_email_snapshot' => 'ap26.andinaagustin@mhs.ubpkarawang.ac.id',
            'core_account_status_snapshot' => 'active',
            'status' => 'active',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/internal/directory/students')) {
                return Http::response(['data' => [], 'meta' => []], 200);
            }

            if (str_contains($url, '/internal/directory/users')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 398,
                            'name' => 'Andina Sahara Agustin',
                            'email' => 'ap26.andinaagustin@mhs.ubpkarawang.ac.id',
                            'identity_type' => 'student',
                            'identity_number' => '26416248901006',
                            'active' => true,
                            'roles' => ['mahasiswa'],
                            'app_accesses' => [['app_code' => 'kppspa-farmasi', 'role_slug' => 'mahasiswa']],
                        ],
                        [
                            'id' => 396,
                            'name' => 'Syfa Dwi Andini',
                            'email' => 'ap26.syfaandini@mhs.ubpkarawang.ac.id',
                            'identity_type' => 'student',
                            'identity_number' => '26416248901008',
                            'active' => true,
                            'roles' => ['mahasiswa'],
                            'app_accesses' => [['app_code' => 'kppspa-farmasi', 'role_slug' => 'mahasiswa']],
                        ],
                    ],
                    'meta' => [],
                ], 200);
            }

            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/398/access') || str_contains($url, '/internal/apps/kppspa-farmasi/users/396/access')) {
                return Http::response(['has_access' => true, 'roles' => [['slug' => 'mahasiswa']]], 200);
            }

            return Http::response(null, 404);
        });

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson(route('management.core-directory.students', ['q' => 'ap26', 'program_id' => $program->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.core_user_id', '396');
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
