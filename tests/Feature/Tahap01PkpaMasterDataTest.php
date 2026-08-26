<?php

namespace Tests\Feature;

use App\Models\PkpaMasterAudit;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeDomainOption;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramDomain;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Tahap01PkpaMasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class]);
        $this->admin = $this->makeUser('admin@test.local', ['admin'], 'core-admin-1');
        $this->koordinator = $this->makeUser('koor@test.local', ['koordinator_kp'], 'core-koor-1');
        $this->mahasiswa = $this->makeUser('mhs@test.local', ['mahasiswa'], 'core-mhs-1');
    }

    public function test_default_domains_and_government_options_are_idempotent(): void
    {
        $this->seed(PkpaMasterSeeder::class);
        $this->seed(PkpaMasterSeeder::class);

        $this->assertSame(5, PkpaPracticeDomain::whereIn('code', ['APT', 'PBF', 'RS', 'IND', 'PEM'])->count());
        $government = PkpaPracticeDomain::where('code', 'PEM')->firstOrFail();
        $this->assertDatabaseHas('pkpa_practice_domain_options', ['practice_domain_id' => $government->id, 'code' => 'LOKAPOM', 'name' => 'Loka BPOM']);
        $this->assertDatabaseHas('pkpa_practice_domain_options', ['practice_domain_id' => $government->id, 'code' => 'DINKES', 'name' => 'Dinas Kesehatan']);
        $this->assertDatabaseHas('pkpa_practice_domain_options', ['practice_domain_id' => $government->id, 'code' => 'PUSKESMAS', 'name' => 'Puskesmas']);
        $this->assertSame(3, $government->options()->whereIn('code', ['LOKAPOM', 'DINKES', 'PUSKESMAS'])->count());
        $this->assertSame(0, PkpaPracticeSite::count(), 'Seeder produksi tidak boleh membuat tempat praktik palsu.');
    }

    public function test_admin_and_koordinator_can_create_program_but_student_cannot(): void
    {
        $payload = $this->programPayload('PKPA-2026-A');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-programs', $payload)
            ->assertRedirect();

        $program = PkpaProgram::where('code', 'PKPA-2026-A')->firstOrFail();
        $this->assertSame('draft', $program->status);
        $this->assertFalse($program->is_active);
        $this->assertSame(5, $program->domains()->where('is_active', true)->count());
        $this->assertDatabaseHas('pkpa_programs', ['code' => 'PKPA-2026-A', 'created_by_core_user_id' => 'core-admin-1']);
        $this->assertDatabaseHas('pkpa_master_audits', ['action' => 'program_created', 'actor_core_user_id' => 'core-admin-1']);

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/pkpa-programs', $this->programPayload('PKPA-2026-B'))
            ->assertRedirect();

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/management/pkpa-programs', $this->programPayload('PKPA-2026-C'))
            ->assertForbidden();
    }

    public function test_program_date_validation_and_unique_program_domain_constraint(): void
    {
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-programs', $this->programPayload('BAD-DATE', ['start_date' => '2026-02-10', 'end_date' => '2026-02-01']))
            ->assertSessionHasErrors('end_date');

        $program = $this->createProgram();
        $domainId = PkpaPracticeDomain::where('code', 'APT')->value('id');

        $this->expectException(QueryException::class);
        PkpaProgramDomain::create([
            'pkpa_program_id' => $program->id,
            'practice_domain_id' => $domainId,
            'is_required' => true,
            'selection_mode' => 'direct',
            'minimum_option_count' => 0,
        ]);
    }

    public function test_program_cannot_activate_until_duration_complete_then_can_activate(): void
    {
        $program = $this->createProgram();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-programs/{$program->id}/status", ['status' => 'active'])
            ->assertSessionHasErrors('status');

        $payload = ['domains' => []];
        foreach ($program->domains()->get() as $domainConfig) {
            $payload['domains'][$domainConfig->id] = [
                'selection_mode' => $domainConfig->selection_mode,
                'minimum_option_count' => $domainConfig->minimum_option_count,
                'duration_value' => 4,
                'duration_unit' => 'weeks',
                'minimum_effective_days' => 20,
                'minimum_practice_hours' => null,
                'weight_percentage' => null,
                'instructions' => 'Ikuti ketentuan resmi program.',
            ];
        }

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->put("/management/pkpa-programs/{$program->id}/configure", $payload)
            ->assertRedirect();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-programs/{$program->id}/status", ['status' => 'active'])
            ->assertRedirect();

        $program->refresh();
        $this->assertSame('active', $program->status);
        $this->assertTrue($program->is_active);
        $this->assertDatabaseHas('pkpa_master_audits', ['action' => 'program_activated', 'actor_core_user_id' => 'core-admin-1']);
    }

    public function test_system_domain_and_system_options_are_protected(): void
    {
        $domain = PkpaPracticeDomain::where('code', 'PEM')->firstOrFail();
        $option = $domain->options()->where('code', 'LOKAPOM')->firstOrFail();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->delete("/management/pkpa-practice-domains/{$domain->id}")
            ->assertSessionHasErrors('domain');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->delete("/management/pkpa-practice-domains/{$domain->id}/options/{$option->id}")
            ->assertSessionHasErrors('option');

        $this->assertNull($domain->fresh()->deleted_at);
        $this->assertNull($option->fresh()->deleted_at);
    }

    public function test_legacy_puskesmas_can_be_cleaned_up_and_counted_under_government(): void
    {
        $government = PkpaPracticeDomain::where('code', 'PEM')->firstOrFail();
        $legacy = PkpaPracticeDomain::create([
            'code' => 'PKM',
            'name' => 'Puskesmas',
            'short_name' => 'PKM',
            'description' => 'Legacy standalone domain.',
            'is_system' => true,
            'is_active' => false,
            'sort_order' => 60,
        ]);

        $site = PkpaPracticeSite::create([
            'practice_domain_id' => $legacy->id,
            'practice_domain_option_id' => null,
            'code' => 'PKM-001',
            'name' => 'Puskesmas Test',
            'city' => 'Karawang',
            'province' => 'Jawa Barat',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-practice-domains');

        $response->assertOk();
        $domains = $response->viewData('domains');
        $governmentFromList = $domains->getCollection()->firstWhere('code', 'PEM');
        $this->assertSame(1, $governmentFromList->display_practice_sites_count);

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->delete("/management/pkpa-practice-domains/{$legacy->id}")
            ->assertRedirect('/management/pkpa-practice-domains');

        $puskesmasOption = $government->fresh()->options()->where('code', 'PUSKESMAS')->firstOrFail();
        $site->refresh();

        $this->assertSame($government->id, $site->practice_domain_id);
        $this->assertSame($puskesmasOption->id, $site->practice_domain_option_id);
        $this->assertSoftDeleted('pkpa_practice_domains', ['id' => $legacy->id]);
    }

    public function test_additional_domain_and_duplicate_code_rules_work(): void
    {
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-practice-domains', [
                'code' => 'KLINIK',
                'name' => 'Klinik',
                'short_name' => 'Klinik',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pkpa_practice_domains', ['code' => 'KLINIK', 'is_system' => false]);

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-practice-domains', [
                'code' => 'KLINIK',
                'name' => 'Klinik Duplikat',
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_options_are_scoped_to_correct_domain(): void
    {
        $government = PkpaPracticeDomain::where('code', 'PEM')->firstOrFail();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-practice-domains/{$government->id}/options", [
            'code' => 'BALAI',
            'name' => 'Balai POM',
            'is_active' => 1,
        ])->assertRedirect();

        $balai = PkpaPracticeDomainOption::where('practice_domain_id', $government->id)->where('code', 'BALAI')->firstOrFail();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->put("/management/pkpa-practice-domains/{$government->id}/options/{$balai->id}", [
                'code' => 'BALAI',
                'name' => 'Balai POM Jawa Barat',
                'is_active' => 0,
            ])->assertRedirect();

        $this->assertDatabaseHas('pkpa_practice_domain_options', ['id' => $balai->id, 'name' => 'Balai POM Jawa Barat', 'is_active' => false]);
        $this->assertDatabaseHas('pkpa_master_audits', ['action' => 'practice_domain_option_updated']);

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-practice-domains/{$government->id}/options", [
            'code' => 'BALAI',
            'name' => 'Balai POM Duplikat',
            'is_active' => 1,
        ])->assertSessionHasErrors('code');
    }

    public function test_practice_site_rules_for_government_option_and_status_filters(): void
    {
        $government = PkpaPracticeDomain::where('code', 'PEM')->firstOrFail();
        $apotek = PkpaPracticeDomain::where('code', 'APT')->firstOrFail();
        $lokaPom = $government->options()->where('code', 'LOKAPOM')->firstOrFail();
        $dinkes = $government->options()->where('code', 'DINKES')->firstOrFail();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-practice-sites', $this->sitePayload('PEM-01', $government->id, null))
            ->assertSessionHasErrors('practice_domain_option_id');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-practice-sites', $this->sitePayload('APT-01', $apotek->id, $lokaPom->id))
            ->assertSessionHasErrors('practice_domain_option_id');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-practice-sites', $this->sitePayload('PEM-01', $government->id, $lokaPom->id, ['city' => 'Karawang']))
            ->assertRedirect();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-practice-sites', $this->sitePayload('APT-01', $apotek->id, null, ['name' => 'Apotek UBP', 'city' => 'Bekasi', 'cooperation_end_date' => now()->subDay()->format('Y-m-d'), 'status' => 'inactive', 'is_active' => 0]))
            ->assertRedirect();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-practice-sites', $this->sitePayload('PEM-01', $government->id, $dinkes->id))
            ->assertSessionHasErrors('code');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-practice-sites', $this->sitePayload('BAD-DATE', $apotek->id, null, ['cooperation_start_date' => '2026-02-10', 'cooperation_end_date' => '2026-02-01']))
            ->assertSessionHasErrors('cooperation_end_date');

        $site = PkpaPracticeSite::where('code', 'APT-01')->firstOrFail();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get("/management/pkpa-practice-sites/{$site->id}")
            ->assertOk()
            ->assertSee('Apotek UBP');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-practice-sites?practice_domain_id='.$government->id)
            ->assertOk()
            ->assertSee('Loka POM Test')
            ->assertDontSee('Apotek UBP');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-practice-sites?cooperation=expired')
            ->assertOk()
            ->assertSee('Apotek UBP')
            ->assertDontSee('Loka POM Test');
    }

    public function test_practice_site_authorization_and_audit(): void
    {
        $apotek = PkpaPracticeDomain::where('code', 'APT')->firstOrFail();

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/pkpa-practice-sites', $this->sitePayload('APT-KOOR', $apotek->id))
            ->assertRedirect();

        $this->assertDatabaseHas('pkpa_practice_sites', ['code' => 'APT-KOOR', 'created_by_core_user_id' => 'core-koor-1']);
        $this->assertDatabaseHas('pkpa_master_audits', ['action' => 'practice_site_created', 'actor_core_user_id' => 'core-koor-1']);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/management/pkpa-practice-sites', $this->sitePayload('APT-MHS', $apotek->id))
            ->assertForbidden();
    }

    public function test_master_routes_are_authenticated_and_active_role_is_verified(): void
    {
        $this->get('/management/pkpa-programs')->assertRedirect('/login');

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-programs')
            ->assertForbidden();
    }

    public function test_landing_core_baseline_and_war_lock_remain_intact(): void
    {
        config()->set('my_pkpa.student_place_selection_enabled', false);

        $this->get('/')->assertOk()->assertSee('MY PKPA');

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/pemilihan-tempat')
            ->assertForbidden();
    }

    private function createProgram(): PkpaProgram
    {
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-programs', $this->programPayload('PKPA-READY-01'))
            ->assertRedirect();

        return PkpaProgram::where('code', 'PKPA-READY-01')->with('domains.practiceDomain.options')->firstOrFail();
    }

    private function programPayload(string $code, array $overrides = []): array
    {
        return array_merge([
            'code' => $code,
            'name' => 'Program PKPA 2026',
            'academic_year' => '2026/2027',
            'cohort_name' => 'Angkatan 2026',
            'semester' => 'ganjil',
            'start_date' => '2026-02-01',
            'end_date' => '2026-08-31',
            'description' => 'Program uji Tahap 01.',
        ], $overrides);
    }

    private function sitePayload(string $code, int $domainId, ?int $optionId = null, array $overrides = []): array
    {
        return array_merge([
            'practice_domain_id' => $domainId,
            'practice_domain_option_id' => $optionId,
            'code' => $code,
            'name' => str_starts_with($code, 'PEM') ? 'Loka POM Test' : 'Tempat Praktik Test',
            'city' => 'Karawang',
            'province' => 'Jawa Barat',
            'status' => 'active',
            'is_active' => 1,
            'cooperation_start_date' => '2026-01-01',
            'cooperation_end_date' => '2026-12-31',
        ], $overrides);
    }

    private function makeUser(string $email, array $roles, string $coreUserId): User
    {
        $user = User::create([
            'name' => 'User Test',
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
            'core_user_id' => $coreUserId,
        ]);

        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user;
    }
}
