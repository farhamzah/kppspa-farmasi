<?php

namespace Tests\Feature;

use App\Models\PkpaEnrollment;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramSite;
use App\Models\PkpaSiteAvailabilityPeriod;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpaProgramService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Tahap03PkpaCapacitySupervisorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;
    private array $coreUsers = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('core_farmasi.enabled', true);
        config()->set('core_farmasi.base_url', 'https://core.test');
        config()->set('core_farmasi.client_id', 'client');
        config()->set('core_farmasi.client_secret', 'secret');
        config()->set('core_farmasi.app_code', 'kppspa-farmasi');
        config()->set('my_pkpa.student_place_selection_enabled', false);

        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class]);
        $this->admin = $this->makeUser('admin03@test.local', ['admin'], 'core-admin-03');
        $this->koordinator = $this->makeUser('koor03@test.local', ['koordinator_kp'], 'core-koor-03');
        $this->mahasiswa = $this->makeUser('mhs03@test.local', ['mahasiswa'], 'CORE-MHS-03');
        $this->fakeCore();
    }

    public function test_program_site_rules_and_authorization(): void
    {
        $program = $this->createProgram('PKPA-03-A');
        $site = $this->createSite('APT-03-A', 'APT');
        $inactiveSite = $this->createSite('APT-03-I', 'APT', ['status' => 'inactive', 'is_active' => false]);
        $expiredSite = $this->createSite('APT-03-X', 'APT', ['cooperation_end_date' => now()->subDay()->format('Y-m-d')]);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/management/pkpa-program-sites', ['pkpa_program_id' => $program->id, 'practice_site_id' => $site->id, 'status' => 'active'])
            ->assertForbidden();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-program-sites', ['pkpa_program_id' => $program->id, 'practice_site_id' => $inactiveSite->id, 'status' => 'active'])
            ->assertSessionHasErrors('practice_site_id');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-program-sites', ['pkpa_program_id' => $program->id, 'practice_site_id' => $expiredSite->id, 'status' => 'active'])
            ->assertSessionHasErrors('practice_site_id');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/pkpa-program-sites', [
                'pkpa_program_id' => $program->id,
                'practice_site_id' => $site->id,
                'status' => 'active',
                'default_maximum_students' => 12,
            ])->assertRedirect();

        $programSite = PkpaProgramSite::firstOrFail();
        $this->assertSame('core-koor-03', $programSite->created_by_core_user_id);
        $this->assertSame($site->practice_domain_id, $programSite->practice_domain_id);
        $this->assertDatabaseHas('pkpa_master_audits', ['action' => 'program_site_created']);

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-program-sites', ['pkpa_program_id' => $program->id, 'practice_site_id' => $site->id, 'status' => 'active'])
            ->assertSessionHasErrors('practice_site_id');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-program-sites/{$programSite->id}/deactivate")
            ->assertRedirect();
        $this->assertFalse($programSite->fresh()->is_active);
    }

    public function test_site_availability_validations_and_cancellation(): void
    {
        $programSite = $this->createProgramSite('PKPA-03-B', 'APT-03-B');

        $valid = [
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'minimum_students' => 1,
            'maximum_students' => 10,
            'reserved_slots' => 2,
            'operational_days' => ['monday', 'tuesday', 'wednesday'],
            'daily_start_time' => '08:00',
            'daily_end_time' => '16:00',
            'status' => 'available',
        ];

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-program-sites/{$programSite->id}/availability", $valid)
            ->assertRedirect();
        $this->assertSame(1, PkpaSiteAvailabilityPeriod::count());

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-program-sites/{$programSite->id}/availability", $valid + ['end_date' => '2026-03-10'])
            ->assertSessionHasErrors('start_date');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-program-sites/{$programSite->id}/availability", array_merge($valid, ['start_date' => '2025-12-01', 'end_date' => '2025-12-10']))
            ->assertSessionHasErrors('start_date');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-program-sites/{$programSite->id}/availability", array_merge($valid, ['start_date' => '2026-03-01', 'end_date' => '2026-03-10', 'reserved_slots' => 20]))
            ->assertSessionHasErrors('reserved_slots');

        $period = PkpaSiteAvailabilityPeriod::firstOrFail();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-program-sites/{$programSite->id}/availability/{$period->id}/cancel")
            ->assertRedirect();
        $this->assertSame('cancelled', $period->fresh()->status);
    }

    public function test_field_supervisor_is_resolved_from_core_and_can_be_synced_or_blocked(): void
    {
        $programSite = $this->createProgramSite('PKPA-03-C', 'APT-03-C');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-program-sites/{$programSite->id}/field-supervisors", [
                'core_user_id' => 'CORE-FIELD-1',
                'position_title' => 'Preseptor',
                'maximum_active_students' => 8,
                'status' => 'active',
            ])->assertRedirect();

        $supervisor = PkpaSiteFieldSupervisor::firstOrFail();
        $this->assertSame('Preseptor Satu', $supervisor->name_snapshot);
        $this->assertSame('active', $supervisor->core_account_status_snapshot);
        $this->assertFalse(Schema::hasColumn('pkpa_site_field_supervisors', 'password'));

        foreach (['CORE-INACTIVE', 'CORE-DOSEN-1', 'CORE-NOACCESS', 'CORE-MISSING'] as $badCoreId) {
            $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
                ->post("/management/pkpa-program-sites/{$programSite->id}/field-supervisors", [
                    'core_user_id' => $badCoreId,
                    'status' => 'active',
                ])->assertSessionHasErrors('core_user_id');
        }

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-program-sites/{$programSite->id}/field-supervisors", [
                'core_user_id' => 'CORE-FIELD-1',
                'status' => 'active',
            ])->assertSessionHasErrors('core_user_id');

        $this->fakeCore(['CORE-FIELD-1' => $this->corePerson('CORE-FIELD-1', 'Preseptor Satu Baru', ['pembimbing_lapangan'])]);
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-program-sites/{$programSite->id}/field-supervisors/{$supervisor->id}/sync")
            ->assertRedirect();
        $this->assertSame('Preseptor Satu Baru', $supervisor->fresh()->name_snapshot);
        $this->assertDatabaseHas('pkpa_supervisor_sync_logs', ['supervisor_type' => 'field', 'status' => 'success']);

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-program-sites/{$programSite->id}/field-supervisors/{$supervisor->id}/unavailability", [
                'start_date' => '2026-02-10',
                'end_date' => '2026-02-12',
                'reason' => 'Dinas luar',
            ])->assertRedirect();
        $this->assertDatabaseHas('pkpa_supervisor_unavailability_periods', ['site_field_supervisor_id' => $supervisor->id, 'reason' => 'Dinas luar']);
    }

    public function test_internal_supervisor_eligibility_and_unavailability(): void
    {
        $program = $this->createProgram('PKPA-03-D');
        $activeDomainCount = $program->domains()->where('is_active', true)->count();

        $payload = [
            'pkpa_program_id' => $program->id,
            'maximum_active_students' => 12,
            'maximum_students_per_program' => 20,
            'status' => 'active',
        ];

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/pkpa-internal-supervisors', $payload)
            ->assertRedirect();

        $this->assertSame($activeDomainCount, PkpaInternalSupervisorEligibility::count());

        $eligibility = PkpaInternalSupervisorEligibility::firstOrFail();
        $this->assertSame('Dosen Satu', $eligibility->name_snapshot);
        $this->assertSame('core-koor-03', $eligibility->created_by_core_user_id);

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-internal-supervisors', $payload)
            ->assertRedirect();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-internal-supervisors/{$eligibility->id}/unavailability", [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-05',
                'reason' => 'Pelatihan',
            ])->assertRedirect();
        $this->assertSame($activeDomainCount, \App\Models\PkpaSupervisorUnavailabilityPeriod::where('supervisor_type', 'internal')->where('reason', 'Pelatihan')->count());

        $this->fakeCore(['CORE-DOSEN-1' => $this->corePerson('CORE-DOSEN-1', 'Dosen Satu Baru', ['pembimbing_dalam'])]);
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-internal-supervisors/{$eligibility->id}/sync")
            ->assertRedirect();
        $this->assertSame($activeDomainCount, PkpaInternalSupervisorEligibility::where('name_snapshot', 'Dosen Satu Baru')->count());

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-internal-supervisors/{$eligibility->id}/deactivate")
            ->assertRedirect();
        $this->assertSame($activeDomainCount, PkpaInternalSupervisorEligibility::where('status', 'inactive')->count());
    }

    public function test_readiness_dashboard_requires_capacity_and_supervisors_without_creating_placement(): void
    {
        $program = $this->createProgram('PKPA-03-E');
        $programSite = $this->createProgramSite('PKPA-03-E', 'APT-03-E', $program);
        $this->createEnrollment($program, 'CORE-STUDENT-1');
        $this->createEnrollment($program, 'CORE-STUDENT-2');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-placement-readiness?program_id='.$program->id)
            ->assertOk()
            ->assertSee('Belum siap')
            ->assertSee('Belum ada availability period')
            ->assertSee('Belum ada Pembimbing Dalam eligible')
            ->assertSee('belum memiliki Pembimbing Lapangan aktif');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-program-sites/{$programSite->id}/availability", [
                'start_date' => '2026-02-01',
                'end_date' => '2026-02-28',
                'minimum_students' => 1,
                'maximum_students' => 3,
                'reserved_slots' => 0,
                'operational_days' => ['monday'],
                'status' => 'available',
            ])->assertRedirect();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-program-sites/{$programSite->id}/field-supervisors", ['core_user_id' => 'CORE-FIELD-1', 'status' => 'active'])
            ->assertRedirect();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-internal-supervisors', [
                'pkpa_program_id' => $program->id,
                'status' => 'active',
            ])->assertRedirect();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-placement-readiness?program_id='.$program->id)
            ->assertOk()
            ->assertSee('Siap menyusun penempatan')
            ->assertSee('Belum siap');

        $this->assertSame(0, \App\Models\KpAssignment::count(), 'Tahap 03 tidak boleh membuat penempatan atau assignment.');
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/pkpa-placement-readiness?program_id='.$program->id)
            ->assertForbidden();
        $this->get('/')->assertOk()->assertSee('MY PKPA');
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])->get('/dashboard')->assertRedirect('/admin/dashboard');
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
        $program = app(PkpaProgramService::class)->create([
            'code' => $code,
            'name' => "Program {$code}",
            'academic_year' => '2026/2027',
            'cohort_name' => 'Angkatan 2026',
            'start_date' => '2026-02-01',
            'end_date' => '2026-08-31',
        ], $this->admin);

        foreach ($program->domains()->get() as $programDomain) {
            $programDomain->update(['duration_value' => 4, 'duration_unit' => 'weeks']);
        }

        return $program->refresh();
    }

    private function createSite(string $code, string $domainCode, array $overrides = []): PkpaPracticeSite
    {
        $domain = PkpaPracticeDomain::where('code', $domainCode)->firstOrFail();
        $option = $domain->isGovernment() ? $domain->options()->where('code', 'PUSKESMAS')->first() : null;

        return PkpaPracticeSite::create(array_merge([
            'practice_domain_id' => $domain->id,
            'practice_domain_option_id' => $option?->id,
            'code' => $code,
            'name' => "Tempat {$code}",
            'city' => 'Karawang',
            'province' => 'Jawa Barat',
            'cooperation_start_date' => '2026-01-01',
            'cooperation_end_date' => '2026-12-31',
            'status' => 'active',
            'is_active' => true,
            'created_by_core_user_id' => $this->admin->core_user_id,
            'updated_by_core_user_id' => $this->admin->core_user_id,
        ], $overrides));
    }

    private function createProgramSite(string $programCode, string $siteCode, ?PkpaProgram $program = null): PkpaProgramSite
    {
        $program ??= $this->createProgram($programCode);
        $site = $this->createSite($siteCode, 'APT');
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-program-sites', [
                'pkpa_program_id' => $program->id,
                'practice_site_id' => $site->id,
                'status' => 'active',
                'default_minimum_students' => 1,
                'default_maximum_students' => 10,
            ])->assertRedirect();

        return PkpaProgramSite::where('pkpa_program_id', $program->id)->where('practice_site_id', $site->id)->firstOrFail();
    }

    private function createEnrollment(PkpaProgram $program, string $coreUserId): PkpaEnrollment
    {
        return PkpaEnrollment::create([
            'pkpa_program_id' => $program->id,
            'core_user_id' => $coreUserId,
            'student_number' => str_replace('CORE-STUDENT-', '23000', $coreUserId),
            'student_name_snapshot' => "Mahasiswa {$coreUserId}",
            'core_account_status_snapshot' => 'active',
            'status' => 'active',
            'created_by_core_user_id' => $this->admin->core_user_id,
            'updated_by_core_user_id' => $this->admin->core_user_id,
        ]);
    }

    private function fakeCore(array $overrides = []): void
    {
        $this->coreUsers = array_replace([
            'CORE-DOSEN-1' => $this->corePerson('CORE-DOSEN-1', 'Dosen Satu', ['pembimbing_dalam']),
            'CORE-FIELD-1' => $this->corePerson('CORE-FIELD-1', 'Preseptor Satu', ['pembimbing_lapangan']),
            'CORE-INACTIVE' => $this->corePerson('CORE-INACTIVE', 'Akun Nonaktif', ['pembimbing_dalam'], false),
            'CORE-NOACCESS' => $this->corePerson('CORE-NOACCESS', 'Tanpa Access', ['pembimbing_dalam']),
        ], $overrides);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/internal/apps/kppspa-farmasi/users?') || str_ends_with($url, '/internal/apps/kppspa-farmasi/users')) {
                return Http::response([
                    'data' => collect($this->coreUsers)
                        ->filter(fn ($person, $id) => $id !== 'CORE-NOACCESS')
                        ->map(fn ($person, $id) => [
                            'user_id' => $id,
                            'app_code' => 'kppspa-farmasi',
                            'roles' => $person['roles'],
                            'user' => [
                                'id' => $person['core_user_id'],
                                'name' => $person['name'],
                                'email' => $person['email'],
                                'active' => $person['active'],
                            ],
                            'profiles' => [
                                'lecturer' => [
                                    'nidn' => $person['employee_number'],
                                    'lecturer_number' => $person['employee_number'],
                                    'employee_number' => $person['employee_number'],
                                ],
                            ],
                        ])
                        ->values()
                        ->all(),
                    'meta' => ['page' => 1, 'limit' => 500, 'total' => count($this->coreUsers), 'has_more' => false],
                ], 200);
            }
            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/CORE-NOACCESS/access')) {
                return Http::response(['has_access' => false, 'roles' => []], 200);
            }
            if (str_contains($url, '/internal/apps/kppspa-farmasi/users/')) {
                return Http::response(['has_access' => true, 'roles' => [['slug' => 'pembimbing_dalam']]], 200);
            }
            foreach ($this->coreUsers as $id => $person) {
                if (str_contains($url, "/internal/directory/lecturers/{$id}") || str_contains($url, "/internal/directory/users/{$id}")) {
                    return Http::response(['data' => $person], 200);
                }
            }

            return Http::response(null, 404);
        });
    }

    private function corePerson(string $coreUserId, string $name, array $roles, bool $active = true): array
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
}
