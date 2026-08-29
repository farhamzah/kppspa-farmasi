<?php

namespace Tests\Feature;

use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpPlaceQuota;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpaProgramService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KpManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $koordinator;

    private User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class]);
        $this->admin = $this->makeUser('admin@test.local', ['admin']);
        $this->koordinator = $this->makeUser('koordinator@test.local', ['koordinator_kp']);
        $this->mahasiswa = $this->makeUser('mahasiswa@test.local', ['mahasiswa']);
    }

    public function test_admin_and_koordinator_can_open_period_page(): void
    {
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])->get('/management/kp-periods')->assertOk()->assertSee('Periode PKPA');
        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])->get('/management/kp-periods')->assertOk()->assertSee('Periode PKPA');
    }

    public function test_mahasiswa_cannot_open_period_page(): void
    {
        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/kp-periods')
            ->assertForbidden();
    }

    public function test_admin_can_create_period_and_date_validation_runs(): void
    {
        $invalid = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/management/kp-periods', [
                'name' => 'KP Ganjil 2025',
                'status' => 'draft',
                'registration_start_at' => '2026-01-10 08:00:00',
                'registration_end_at' => '2026-01-01 08:00:00',
            ]);

        $invalid->assertSessionHasErrors('registration_end_at');

        $valid = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/management/kp-periods', [
                'name' => 'KP Ganjil 2025',
                'academic_year' => '2025/2026',
                'semester' => 'ganjil',
                'status' => 'dibuka',
                'registration_start_at' => '2026-01-01 08:00:00',
                'registration_end_at' => '2026-01-10 08:00:00',
                'selection_start_at' => '2026-01-11 08:00:00',
                'selection_end_at' => '2026-01-12 08:00:00',
                'kp_start_date' => '2026-02-01',
                'kp_end_date' => '2026-03-01',
            ]);

        $valid->assertRedirect();
        $this->assertDatabaseHas('kp_periods', ['name' => 'KP Ganjil 2025', 'status' => 'dibuka']);
    }

    public function test_admin_can_create_place(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/management/kp-places', [
                'name' => 'Apotek Sehat',
                'type' => 'apotek',
                'city' => 'Karawang',
                'status' => 'aktif',
                'email' => 'apotek@test.local',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kp_places', ['name' => 'Apotek Sehat', 'type' => 'apotek']);
    }

    public function test_admin_can_create_quota_and_duplicate_is_rejected(): void
    {
        [$period, $place] = $this->periodAndPlace();

        $response = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/management/kp-place-quotas', [
                'kp_period_id' => $period->id,
                'kp_place_id' => $place->id,
                'quota' => 10,
                'is_open' => 1,
                'notes' => 'Kuota awal',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kp_place_quotas', ['kp_period_id' => $period->id, 'kp_place_id' => $place->id, 'quota' => 10]);
        $this->assertDatabaseHas('kp_quota_logs', ['action' => 'created', 'new_quota' => 10]);

        $duplicate = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/management/kp-place-quotas', [
                'kp_period_id' => $period->id,
                'kp_place_id' => $place->id,
                'quota' => 5,
                'is_open' => 1,
            ]);

        $duplicate->assertSessionHasErrors('kp_place_id');
    }

    public function test_quota_create_page_mirrors_pkpa_programs_and_sites_when_legacy_catalog_is_empty(): void
    {
        $program = app(PkpaProgramService::class)->create([
            'code' => 'PKPA-KUOTA-01',
            'name' => 'PKPA Farmasi UBP 2026 Gelombang 1',
            'academic_year' => '2026/2027',
            'cohort_name' => 'Angkatan 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
            'status' => 'active',
        ], $this->admin);
        $domain = PkpaPracticeDomain::where('code', 'APT')->firstOrFail();
        PkpaPracticeSite::create([
            'practice_domain_id' => $domain->id,
            'code' => 'APT-KUOTA-01',
            'name' => 'Apotek Uji Kuota',
            'city' => 'Karawang',
            'province' => 'Jawa Barat',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->assertSame(0, KpPeriod::count());
        $this->assertSame(0, KpPlace::count());

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->get('/management/kp-place-quotas/create')
            ->assertOk()
            ->assertSee($program->code.' - '.$program->name)
            ->assertSee('Apotek Uji Kuota');

        $this->assertDatabaseHas('kp_periods', ['name' => $program->code.' - '.$program->name]);
        $this->assertDatabaseHas('kp_places', ['name' => 'Apotek Uji Kuota', 'type' => 'apotek']);
    }

    public function test_quota_update_and_toggle_create_logs(): void
    {
        [$period, $place] = $this->periodAndPlace();
        $quota = KpPlaceQuota::create([
            'kp_period_id' => $period->id,
            'kp_place_id' => $place->id,
            'quota' => 5,
            'is_open' => true,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->put('/management/kp-place-quotas/'.$quota->id, [
                'kp_period_id' => $period->id,
                'kp_place_id' => $place->id,
                'quota' => 8,
                'is_open' => 1,
                'notes' => 'Tambah kuota',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kp_quota_logs', ['kp_place_quota_id' => $quota->id, 'action' => 'quota_increased', 'old_quota' => 5, 'new_quota' => 8]);

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/management/kp-place-quotas/'.$quota->id.'/toggle-open')
            ->assertRedirect();

        $this->assertDatabaseHas('kp_quota_logs', ['kp_place_quota_id' => $quota->id, 'action' => 'closed']);
    }

    public function test_dashboard_shows_kp_summary(): void
    {
        $this->periodAndPlace();

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->get('/koordinator/dashboard')
            ->assertOk()
            ->assertSee('Ringkasan Master PKPA');
    }

    private function periodAndPlace(): array
    {
        $period = KpPeriod::create([
            'name' => 'KP Genap 2025',
            'status' => 'dibuka',
        ]);

        $place = KpPlace::create([
            'name' => 'Apotek Sehat',
            'type' => 'apotek',
            'status' => 'aktif',
        ]);

        return [$period, $place];
    }

    private function makeUser(string $email, array $roles): User
    {
        $user = User::create([
            'name' => 'User Test',
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user;
    }
}
