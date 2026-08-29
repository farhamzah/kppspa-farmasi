<?php

namespace Tests\Feature;

use App\Models\PkpaEnrollment;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPlacementActionBatch;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramSite;
use App\Models\PkpaRotationAssignment;
use App\Models\PkpaSiteAvailabilityPeriod;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpaEnrollmentRequirementService;
use App\Services\PkpaProgramService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Tahap04PkpaPlacementPlannerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('my_pkpa.student_place_selection_enabled', false);
        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class]);
        $this->admin = $this->makeUser('admin04@test.local', ['admin'], 'core-admin-04');
        $this->koordinator = $this->makeUser('koor04@test.local', ['koordinator_kp'], 'core-koor-04');
        $this->mahasiswa = $this->makeUser('mhs04@test.local', ['mahasiswa'], 'CORE-MHS-04');
    }

    public function test_plan_version_current_authorization_and_schema(): void
    {
        $program = $this->createProgram('PKPA-04-A');

        $this->assertTrue(Schema::hasTable('pkpa_placement_plans'));
        $this->assertTrue(Schema::hasTable('pkpa_rotation_assignments'));
        $this->assertTrue(Schema::hasTable('pkpa_placement_validation_issues'));

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/management/pkpa-placement-planner/plans', ['pkpa_program_id' => $program->id])
            ->assertForbidden();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-placement-planner/plans', ['pkpa_program_id' => $program->id, 'name' => 'Draft utama'])
            ->assertRedirect();

        $plan = PkpaPlacementPlan::firstOrFail();
        $this->assertTrue($plan->is_current);
        $this->assertSame('PROGRAM:'.$program->id, $plan->current_key);
        $this->assertSame('core-admin-04', $plan->created_by_core_user_id);

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/clone", ['copy_assignments' => 1])
            ->assertRedirect();

        $clone = PkpaPlacementPlan::whereKeyNot($plan->id)->firstOrFail();
        $this->assertSame(2, $clone->version_number);
        $this->assertFalse($clone->is_current);

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-placement-plans/{$clone->id}/current")
            ->assertRedirect();
        $this->assertSame(1, PkpaPlacementPlan::where('is_current', true)->count());
        $this->assertTrue($clone->fresh()->is_current);
    }

    public function test_individual_assignment_validates_site_dates_capacity_supervisors_and_requirement_status(): void
    {
        [$program, $plan, $programSite, $availability, $internal, $field] = $this->placementFixture('PKPA-04-B', capacity: 1);
        $enrollment = $this->enroll($program, 'CORE-STUDENT-04-1', '240001');
        $requirement = $enrollment->requirements()->where('practice_domain_id', $programSite->practice_domain_id)->firstOrFail();

        $payload = $this->assignmentPayload($requirement, $programSite, $availability, $internal, $field);
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/assignments", $payload)
            ->assertRedirect();

        $assignment = PkpaRotationAssignment::firstOrFail();
        $this->assertSame('valid', $assignment->status);
        $this->assertSame('planned', $requirement->fresh()->status);
        $this->assertSame(2, $assignment->supervisors()->count());
        $this->assertDatabaseHas('pkpa_master_audits', ['action' => 'rotation_assignment_created']);

        $other = $this->enroll($program, 'CORE-STUDENT-04-2', '240002');
        $otherRequirement = $other->requirements()->where('practice_domain_id', $programSite->practice_domain_id)->firstOrFail();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/assignments", $this->assignmentPayload($otherRequirement, $programSite, $availability, $internal, $field))
            ->assertSessionHasErrors('assignment');

        $rsSite = $this->createProgramSite($program, 'RS', 'RS-04-B', 5);
        $rsRequirement = $enrollment->requirements()->where('practice_domain_id', $rsSite->practice_domain_id)->firstOrFail();
        $rsInternal = $this->internal($program, $rsSite->practice_domain_id, 'CORE-DOSEN-RS');
        $rsField = $this->field($rsSite->practice_site_id, 'CORE-FIELD-RS');
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/assignments", $this->assignmentPayload($rsRequirement, $rsSite, $rsSite->availabilityPeriods()->first(), $rsInternal, $rsField))
            ->assertSessionHasErrors('assignment');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->delete("/management/pkpa-rotation-assignments/{$assignment->id}")
            ->assertRedirect();
        $this->assertSoftDeleted('pkpa_rotation_assignments', ['id' => $assignment->id]);
        $this->assertSame('pending', $requirement->fresh()->status);
    }

    public function test_government_assignment_uses_single_choose_one_requirement_option(): void
    {
        [$program, $plan] = $this->basicPlan('PKPA-04-C');
        $programSite = $this->createProgramSite($program, 'PEM', 'PEM-04-C', 4);
        $availability = $programSite->availabilityPeriods()->first();
        $internal = $this->internal($program, $programSite->practice_domain_id, 'CORE-DOSEN-PEM');
        $field = $this->field($programSite->practice_site_id, 'CORE-FIELD-PEM');
        $enrollment = $this->enroll($program, 'CORE-STUDENT-04-3', '240003');
        $requirement = $enrollment->requirements()->where('practice_domain_id', $programSite->practice_domain_id)->firstOrFail();

        $this->assertSame('choose_one', $requirement->selection_mode);
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/assignments", $this->assignmentPayload($requirement, $programSite, $availability, $internal, $field))
            ->assertRedirect();

        $this->assertSame(1, $enrollment->requirements()->where('practice_domain_id', $programSite->practice_domain_id)->count());
        $this->assertSame($programSite->practice_domain_option_id, $requirement->fresh()->selected_practice_domain_option_id);
    }

    public function test_bulk_preview_apply_undo_validation_export_and_lock_guard(): void
    {
        [$program, $plan, $programSite, $availability, $internal, $field] = $this->placementFixture('PKPA-04-D', capacity: 5);
        $first = $this->enroll($program, 'CORE-STUDENT-04-4', '240004');
        $second = $this->enroll($program, 'CORE-STUDENT-04-5', '240005');
        $domainId = $programSite->practice_domain_id;

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/bulk-preview", [
                'practice_domain_id' => $domainId,
                'pkpa_program_site_id' => $programSite->id,
                'pkpa_site_availability_period_id' => $availability->id,
                'start_date' => '2026-02-01',
                'end_date' => '2026-02-28',
                'internal_supervisor_eligibility_id' => $internal->id,
                'site_field_supervisor_id' => $field->id,
                'enrollment_ids' => [$first->id, $second->id],
            ])->assertRedirect();

        $batch = PkpaPlacementActionBatch::with('items')->firstOrFail();
        $this->assertSame(0, PkpaRotationAssignment::count(), 'Preview tidak boleh mengubah database assignment.');
        $this->assertSame(2, $batch->items->where('result_status', 'valid')->count());

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-placement-batches/{$batch->id}/apply")
            ->assertRedirect();
        $this->assertSame(2, PkpaRotationAssignment::count());

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-placement-batches/{$batch->id}/undo")
            ->assertRedirect();
        $this->assertSame(0, PkpaRotationAssignment::query()->whereNull('deleted_at')->count());

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/validate")
            ->assertRedirect();
        $this->assertSame('needs_revision', $plan->fresh()->status);
        $this->assertDatabaseHas('pkpa_placement_validation_issues', ['issue_code' => 'ASSIGNMENT_MISSING']);

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get("/management/pkpa-placement-plans/{$plan->id}/export")
            ->assertOk()
            ->assertHeader('content-disposition');

        $plan->update(['status' => 'locked', 'validation_status' => 'valid']);
        $requirement = $first->requirements()->where('practice_domain_id', $domainId)->firstOrFail();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/assignments", $this->assignmentPayload($requirement, $programSite, $availability, $internal, $field))
            ->assertSessionHasErrors('plan');
    }

    public function test_options_endpoint_filters_sites_availability_and_supervisors_by_domain(): void
    {
        [$program, $plan, $programSite, $availability, $internal, $field] = $this->placementFixture('PKPA-04-E', capacity: 3);
        $otherSite = $this->createProgramSite($program, 'RS', 'RS-PKPA-04-E', 2);
        $otherInternal = $this->internal($program, $otherSite->practice_domain_id, 'CORE-DOSEN-RS-04-E');
        $otherField = $this->field($otherSite->practice_site_id, 'CORE-FIELD-RS-04-E');

        $response = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->getJson("/management/pkpa-placement-plans/{$plan->id}/options?practice_domain_id={$programSite->practice_domain_id}")
            ->assertOk();

        $response->assertJsonPath('program_sites.0.id', $programSite->id);
        $response->assertJsonMissing(['id' => $otherSite->id]);
        $response->assertJsonPath('program_sites.0.availability.0.id', $availability->id);
        $response->assertJsonPath('program_sites.0.field_supervisors.0.id', $field->id);
        $response->assertJsonMissing(['id' => $otherField->id]);
        $response->assertJsonPath('internal_supervisors.0.id', $internal->id);
        $response->assertJsonMissing(['id' => $otherInternal->id]);

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-placement-planner?program_id='.$program->id.'&plan_id='.$plan->id)
            ->assertOk()
            ->assertSee($field->name_snapshot);
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
        $program->domains()->update(['duration_value' => 4, 'duration_unit' => 'weeks', 'minimum_effective_days' => 4]);

        return $program->refresh();
    }

    private function basicPlan(string $code): array
    {
        $program = $this->createProgram($code);
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-placement-planner/plans', ['pkpa_program_id' => $program->id, 'name' => 'Draft '.$code])
            ->assertRedirect();

        return [$program, PkpaPlacementPlan::where('pkpa_program_id', $program->id)->firstOrFail()];
    }

    private function placementFixture(string $code, int $capacity = 8): array
    {
        [$program, $plan] = $this->basicPlan($code);
        $programSite = $this->createProgramSite($program, 'APT', 'APT-'.$code, $capacity);
        $availability = $programSite->availabilityPeriods()->firstOrFail();
        $internal = $this->internal($program, $programSite->practice_domain_id, 'CORE-DOSEN-'.$code);
        $field = $this->field($programSite->practice_site_id, 'CORE-FIELD-'.$code);

        return [$program, $plan, $programSite, $availability, $internal, $field];
    }

    private function createProgramSite(PkpaProgram $program, string $domainCode, string $siteCode, int $capacity): PkpaProgramSite
    {
        $domain = PkpaPracticeDomain::where('code', $domainCode)->firstOrFail();
        $programDomain = $program->domains()->where('practice_domain_id', $domain->id)->firstOrFail();
        $option = $domain->isGovernment() ? $domain->options()->where('code', 'PUSKESMAS')->first() : null;
        $site = PkpaPracticeSite::create([
            'practice_domain_id' => $domain->id,
            'practice_domain_option_id' => $option?->id,
            'code' => $siteCode,
            'name' => 'Tempat '.$siteCode,
            'city' => 'Karawang',
            'province' => 'Jawa Barat',
            'cooperation_start_date' => '2026-01-01',
            'cooperation_end_date' => '2026-12-31',
            'status' => 'active',
            'is_active' => true,
        ]);
        $programSite = PkpaProgramSite::create([
            'pkpa_program_id' => $program->id,
            'practice_site_id' => $site->id,
            'pkpa_program_domain_id' => $programDomain->id,
            'practice_domain_id' => $domain->id,
            'practice_domain_option_id' => $option?->id,
            'status' => 'active',
            'is_active' => true,
        ]);
        PkpaSiteAvailabilityPeriod::create([
            'pkpa_program_site_id' => $programSite->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-03-31',
            'minimum_students' => 1,
            'maximum_students' => $capacity,
            'reserved_slots' => 0,
            'operational_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'daily_start_time' => '08:00',
            'daily_end_time' => '16:00',
            'status' => 'available',
        ]);

        return $programSite->fresh('availabilityPeriods');
    }

    private function internal(PkpaProgram $program, int $domainId, string $coreUserId): PkpaInternalSupervisorEligibility
    {
        return PkpaInternalSupervisorEligibility::create([
            'pkpa_program_id' => $program->id,
            'practice_domain_id' => $domainId,
            'core_user_id' => $coreUserId,
            'name_snapshot' => 'Dosen '.$coreUserId,
            'core_account_status_snapshot' => 'active',
            'role_snapshot' => 'pembimbing_dalam',
            'maximum_active_students' => 10,
            'maximum_students_per_program' => 20,
            'effective_start_date' => '2026-01-01',
            'effective_end_date' => '2026-12-31',
            'status' => 'active',
        ]);
    }

    private function field(int $practiceSiteId, string $coreUserId): PkpaSiteFieldSupervisor
    {
        return PkpaSiteFieldSupervisor::create([
            'practice_site_id' => $practiceSiteId,
            'core_user_id' => $coreUserId,
            'name_snapshot' => 'Preseptor '.$coreUserId,
            'core_account_status_snapshot' => 'active',
            'role_snapshot' => 'pembimbing_lapangan',
            'maximum_active_students' => 10,
            'effective_start_date' => '2026-01-01',
            'effective_end_date' => '2026-12-31',
            'status' => 'active',
        ]);
    }

    private function enroll(PkpaProgram $program, string $coreUserId, string $npm): PkpaEnrollment
    {
        $enrollment = PkpaEnrollment::create([
            'pkpa_program_id' => $program->id,
            'core_user_id' => $coreUserId,
            'student_number' => $npm,
            'student_name_snapshot' => 'Mahasiswa '.$npm,
            'core_account_status_snapshot' => 'active',
            'status' => 'active',
            'created_by_core_user_id' => $this->admin->core_user_id,
            'updated_by_core_user_id' => $this->admin->core_user_id,
        ]);
        app(PkpaEnrollmentRequirementService::class)->ensureRequirements($enrollment, $this->admin);

        return $enrollment->fresh('requirements');
    }

    private function assignmentPayload($requirement, PkpaProgramSite $programSite, PkpaSiteAvailabilityPeriod $availability, PkpaInternalSupervisorEligibility $internal, PkpaSiteFieldSupervisor $field): array
    {
        return [
            'pkpa_enrollment_requirement_id' => $requirement->id,
            'pkpa_program_site_id' => $programSite->id,
            'pkpa_site_availability_period_id' => $availability->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'internal_supervisor_eligibility_id' => $internal->id,
            'site_field_supervisor_id' => $field->id,
        ];
    }
}
