<?php

namespace Tests\Feature;

use App\Models\PkpaEnrollment;
use App\Models\PkpaEnrollmentImportRow;
use App\Models\PkpaEnrollmentRequirement;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaProgram;
use App\Models\PkpaStudentGroup;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpaProgramService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Tahap02PkpaEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;
    private array $coreStudents = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('core_farmasi.enabled', true);
        config()->set('core_farmasi.base_url', 'https://core.test');
        config()->set('core_farmasi.client_id', 'client');
        config()->set('core_farmasi.client_secret', 'secret');
        config()->set('core_farmasi.app_code', 'kppspa-farmasi');
        config()->set('my_pspa.student_place_selection_enabled', false);

        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class]);
        $this->admin = $this->makeUser('admin@test.local', ['admin'], 'core-admin-1');
        $this->koordinator = $this->makeUser('koor@test.local', ['koordinator_kp'], 'core-koor-1');
        $this->mahasiswa = $this->makeUser('mhs@test.local', ['mahasiswa'], 'CORE-001');
        $this->fakeCore();
    }

    public function test_admin_and_koordinator_create_enrollment_with_six_requirements(): void
    {
        $program = $this->createProgram('PKPA-26-A');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-enrollments', [
                'pkpa_program_id' => $program->id,
                'core_user_id' => 'CORE-001',
                'student_number' => '231001',
                'notes' => 'Peserta utama',
            ])->assertRedirect();

        $enrollment = PkpaEnrollment::with('requirements.practiceDomain')->where('core_user_id', 'CORE-001')->firstOrFail();
        $this->assertSame('active', $enrollment->status);
        $this->assertSame('231001', $enrollment->student_number);
        $this->assertSame('Andi Farmasi', $enrollment->student_name_snapshot);
        $this->assertSame('core-admin-1', $enrollment->created_by_core_user_id);
        $this->assertSame(6, $enrollment->requirements()->count());
        $this->assertSame(5, $enrollment->requirements()->where('selection_mode', 'direct')->count());

        $government = $enrollment->requirements->first(fn ($requirement) => $requirement->practiceDomain?->code === 'PEM');
        $this->assertNotNull($government);
        $this->assertSame('choose_one', $government->selection_mode);
        $this->assertNull($government->selected_practice_domain_option_id);
        $this->assertSame(1, $government->required_option_count);
        $this->assertSame(1, $enrollment->requirements->where('practiceDomain.code', 'PEM')->count());
        $this->assertDatabaseMissing('pkpa_enrollment_requirements', ['selection_mode' => 'LOKAPOM']);

        app(\App\Services\PkpaEnrollmentRequirementService::class)->ensureRequirements($enrollment, $this->admin);
        $this->assertSame(6, $enrollment->requirements()->count(), 'Requirement harus idempotent.');

        $this->expectException(QueryException::class);
        PkpaEnrollmentRequirement::create($government->replicate(['id'])->fill([])->toArray());
    }

    public function test_core_validation_duplicate_and_authorization_rules(): void
    {
        $program = $this->createProgram('PKPA-26-B');
        $otherProgram = $this->createProgram('PKPA-26-C');

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/management/pkpa-enrollments', ['pkpa_program_id' => $program->id, 'core_user_id' => 'CORE-001'])
            ->assertForbidden();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-enrollments', ['pkpa_program_id' => $program->id, 'core_user_id' => 'CORE-INACTIVE'])
            ->assertSessionHasErrors('core_user_id');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-enrollments', ['pkpa_program_id' => $program->id, 'core_user_id' => 'CORE-LECTURER'])
            ->assertSessionHasErrors('core_user_id');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-enrollments', ['pkpa_program_id' => $program->id, 'core_user_id' => 'CORE-NOACCESS'])
            ->assertSessionHasErrors('core_user_id');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/pkpa-enrollments', ['pkpa_program_id' => $program->id, 'core_user_id' => 'CORE-001'])
            ->assertRedirect();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-enrollments', ['pkpa_program_id' => $program->id, 'core_user_id' => 'CORE-001'])
            ->assertSessionHasErrors('core_user_id');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-enrollments', ['pkpa_program_id' => $otherProgram->id, 'core_user_id' => 'CORE-001'])
            ->assertRedirect();

        $this->assertSame(2, PkpaEnrollment::where('core_user_id', 'CORE-001')->count());
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-enrollments/'.PkpaEnrollment::first()->id.'/cancel', ['cancellation_reason' => 'Mengundurkan diri'])
            ->assertRedirect();
        $this->assertDatabaseHas('pkpa_master_audits', ['action' => 'enrollment_cancelled']);
    }

    public function test_student_groups_membership_history_capacity_and_bulk(): void
    {
        $program = $this->createProgram('PKPA-26-D');
        $otherProgram = $this->createProgram('PKPA-26-E');
        $e1 = $this->enroll($program, 'CORE-001');
        $e2 = $this->enroll($program, 'CORE-002');
        $e3 = $this->enroll($otherProgram, 'CORE-003');

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/management/pkpa-student-groups', [
                'pkpa_program_id' => $program->id,
                'code' => 'A',
                'name' => 'Kelompok A',
                'maximum_members' => 1,
                'status' => 'active',
                'is_active' => 1,
            ])->assertForbidden();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-student-groups', [
                'pkpa_program_id' => $program->id,
                'code' => 'A',
                'name' => 'Kelompok A',
                'maximum_members' => 1,
                'status' => 'active',
                'is_active' => 1,
            ])->assertRedirect();

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/pkpa-student-groups', [
                'pkpa_program_id' => $program->id,
                'code' => 'B',
                'name' => 'Kelompok B',
                'maximum_members' => 3,
                'status' => 'active',
                'is_active' => 1,
            ])->assertRedirect();

        $groupA = PkpaStudentGroup::where('code', 'A')->firstOrFail();
        $groupB = PkpaStudentGroup::where('code', 'B')->firstOrFail();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-student-groups', [
                'pkpa_program_id' => $program->id,
                'code' => 'A',
                'name' => 'Duplikat',
                'status' => 'active',
                'is_active' => 1,
            ])->assertSessionHasErrors('code');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-student-groups/{$groupA->id}/members", ['pkpa_enrollment_id' => $e1->id])
            ->assertRedirect();
        $this->assertSame(1, $e1->activeGroupMembership()->count());

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-student-groups/{$groupA->id}/members", ['pkpa_enrollment_id' => $e2->id])
            ->assertSessionHasErrors('group');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-student-groups/{$groupA->id}/members", ['pkpa_enrollment_id' => $e3->id])
            ->assertSessionHasErrors('group');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-student-groups/{$groupB->id}/members", ['pkpa_enrollment_id' => $e1->id])
            ->assertRedirect();
        $this->assertSame(1, $e1->activeGroupMembership()->count());
        $this->assertSame(2, $e1->groupMemberships()->count(), 'Perpindahan harus menyimpan histori.');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-student-groups/{$groupB->id}/members/bulk", ['enrollment_ids' => [$e2->id]])
            ->assertRedirect();
        $this->assertSame(2, $groupB->fresh()->activeMembers()->count());
        $this->assertDatabaseHas('pkpa_master_audits', ['action' => 'student_group_member_added']);
    }

    public function test_import_preview_and_final_import_only_valid_rows(): void
    {
        $program = $this->createProgram('PKPA-26-F');
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-student-groups', [
                'pkpa_program_id' => $program->id,
                'code' => 'KEL-01',
                'name' => 'Kelompok 01',
                'maximum_members' => 2,
                'status' => 'active',
                'is_active' => 1,
            ])->assertRedirect();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-enrollments/import/template')
            ->assertOk()
            ->assertSee('core_user_id,npm,group_code,notes', false);

        $badFile = UploadedFile::fake()->createWithContent('bad.csv', "core_user_id,password\nCORE-001,secret\n");
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-enrollments/import/preview', ['pkpa_program_id' => $program->id, 'file' => $badFile])
            ->assertSessionHasErrors('file');

        $file = UploadedFile::fake()->createWithContent('peserta.csv', implode("\n", [
            'core_user_id,npm,group_code,notes',
            'CORE-001,231001,KEL-01,valid',
            'CORE-001,231001,KEL-01,duplikat file',
            ',239999,KEL-01,tidak ditemukan',
            'CORE-002,231002,TIDAKADA,group invalid',
        ]));

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-enrollments/import/preview', ['pkpa_program_id' => $program->id, 'file' => $file])
            ->assertRedirect();

        $this->assertSame(0, PkpaEnrollment::count());
        $this->assertSame(1, PkpaEnrollmentImportRow::where('validation_status', 'valid')->count());
        $this->assertSame(1, PkpaEnrollmentImportRow::where('validation_status', 'duplicate_file')->count());
        $this->assertSame(1, PkpaEnrollmentImportRow::where('validation_status', 'not_found')->count());
        $this->assertSame(1, PkpaEnrollmentImportRow::where('validation_status', 'group_not_found')->count());

        $batch = \App\Models\PkpaEnrollmentImportBatch::firstOrFail();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-enrollment-imports/{$batch->id}/run")
            ->assertRedirect();

        $this->assertSame(1, PkpaEnrollment::count());
        $this->assertSame(1, PkpaEnrollment::first()->activeGroupMembership()->count());
        $this->assertDatabaseMissing('pkpa_enrollments', ['core_user_id' => 'CORE-404']);
        $this->assertFalse(PkpaEnrollmentImportRow::query()->get()->contains(fn ($row) => str_contains(json_encode($row->raw_payload), 'secret')));
    }

    public function test_sync_dashboard_and_regression_routes_are_safe(): void
    {
        $program = $this->createProgram('PKPA-26-G');
        $enrollment = $this->enroll($program, 'CORE-001');

        $this->fakeCore(['CORE-001' => $this->student('CORE-001', '231001', 'Andi Baru')]);
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-enrollments/{$enrollment->id}/sync")
            ->assertRedirect();
        $this->assertSame('Andi Baru', $enrollment->fresh()->student_name_snapshot);
        $this->assertSame('CORE-001', $enrollment->fresh()->core_user_id);

        $this->coreStudents = [];
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-enrollments/{$enrollment->id}/sync")
            ->assertRedirect();
        $this->assertSame('Andi Baru', $enrollment->fresh()->student_name_snapshot);
        $this->assertSame('failed', $enrollment->fresh()->last_core_sync_status);

        $this->app['auth']->guard()->logout();
        $this->get('/management/pkpa-enrollments')->assertRedirect('/login');
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/pkpa-student-groups')
            ->assertForbidden();
        $this->get('/')->assertOk();
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/pemilihan-tempat')
            ->assertForbidden();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-programs')
            ->assertOk();
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/dashboard')
            ->assertOk()
            ->assertSee('Terdaftar');
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

    private function createProgram(string $code): PkpaProgram
    {
        return app(PkpaProgramService::class)->create([
            'code' => $code,
            'name' => "Program {$code}",
            'academic_year' => '2026/2027',
            'cohort_name' => 'Angkatan 2026',
        ], $this->admin);
    }

    private function enroll(PkpaProgram $program, string $coreUserId): PkpaEnrollment
    {
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-enrollments', ['pkpa_program_id' => $program->id, 'core_user_id' => $coreUserId])
            ->assertRedirect();

        return PkpaEnrollment::where('pkpa_program_id', $program->id)->where('core_user_id', $coreUserId)->firstOrFail();
    }

    private function fakeCore(array $overrides = []): void
    {
        $this->coreStudents = array_replace([
            'CORE-001' => $this->student('CORE-001', '231001', 'Andi Farmasi'),
            'CORE-002' => $this->student('CORE-002', '231002', 'Siti Farmasi'),
            'CORE-003' => $this->student('CORE-003', '231003', 'Budi Farmasi'),
            'CORE-INACTIVE' => $this->student('CORE-INACTIVE', '231004', 'Ina Nonaktif', ['active' => false]),
            'CORE-LECTURER' => $this->student('CORE-LECTURER', '231005', 'Dosen Bukan Mahasiswa', ['roles' => [['slug' => 'dosen']]]),
            'CORE-NOACCESS' => $this->student('CORE-NOACCESS', '231006', 'No Access'),
        ], $overrides);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-NOACCESS/access')) {
                return Http::response(['has_access' => false, 'roles' => []], 200);
            }
            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/')) {
                return Http::response(['has_access' => true, 'roles' => [['slug' => 'mahasiswa']]], 200);
            }
            foreach ($this->coreStudents as $id => $student) {
                if (str_contains($url, "/internal/directory/students/{$id}") || str_contains($url, "/internal/directory/users/{$id}")) {
                    return Http::response(['data' => $student], 200);
                }
            }
            if (str_contains($url, '/internal/directory/students')) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $queryParams);
                $query = $request->data()['q'] ?? $request->data()['student_number'] ?? $queryParams['q'] ?? $queryParams['student_number'] ?? null;
                $matches = collect($this->coreStudents)->filter(fn ($student) => ! $query || ($student['npm'] ?? null) === $query || str_contains($student['name'], (string) $query))->values()->all();

                return Http::response(['data' => $matches, 'meta' => []], 200);
            }

            return Http::response(null, 404);
        });
    }

    private function student(string $coreUserId, string $npm, string $name, array $extra = []): array
    {
        return array_merge([
            'core_user_id' => $coreUserId,
            'npm' => $npm,
            'name' => $name,
            'email' => strtolower($coreUserId).'@student.test',
            'study_program' => 'Profesi Apoteker',
            'cohort' => '2026',
            'academic_status' => 'aktif',
            'active' => true,
            'roles' => [['slug' => 'mahasiswa']],
        ], $extra);
    }
}
