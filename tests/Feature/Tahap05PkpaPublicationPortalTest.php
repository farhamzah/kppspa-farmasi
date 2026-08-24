<?php

namespace Tests\Feature;

use App\Models\KpAssignment;
use App\Models\PkpaEnrollment;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaNotificationDelivery;
use App\Models\PkpaPlacementChangeRequest;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramSite;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaPublishedAssignmentSupervisor;
use App\Models\PkpaRotationAssignment;
use App\Models\PkpaScheduleAcknowledgement;
use App\Models\PkpaSiteAvailabilityPeriod;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpaEnrollmentRequirementService;
use App\Services\PkpaPlacementNotificationService;
use App\Services\PkpaProgramService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Tahap05PkpaPublicationPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $student;
    private User $otherStudent;
    private User $internalSupervisor;
    private User $fieldSupervisor;
    private User $otherSupervisor;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('my_pkpa.student_place_selection_enabled', false);
        config()->set('my_pkpa.database_notifications_enabled', true);
        config()->set('my_pkpa.email_notifications_enabled', false);
        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class]);

        $this->admin = $this->makeUser('admin05@test.local', ['admin'], 'CORE-ADMIN-05');
        $this->koordinator = $this->makeUser('koor05@test.local', ['koordinator_kp'], 'CORE-KOOR-05');
        $this->student = $this->makeUser('student05@test.local', ['mahasiswa'], 'CORE-STUDENT-05');
        $this->otherStudent = $this->makeUser('other-student05@test.local', ['mahasiswa'], 'CORE-STUDENT-OTHER-05');
        $this->internalSupervisor = $this->makeUser('internal05@test.local', ['pembimbing_dalam'], 'CORE-INTERNAL-05-APT');
        $this->fieldSupervisor = $this->makeUser('field05@test.local', ['pembimbing_lapangan'], 'CORE-FIELD-05-APT');
        $this->otherSupervisor = $this->makeUser('other-supervisor05@test.local', ['pembimbing_dalam'], 'CORE-INTERNAL-OTHER-05');
    }

    public function test_schema_publish_snapshot_export_and_notification_delivery(): void
    {
        [$program, $plan] = $this->readyLockedPlan('PKPA-05-A');

        $this->assertTrue(Schema::hasTable('pkpa_placement_publications'));
        $this->assertTrue(Schema::hasTable('pkpa_published_assignments'));
        $this->assertTrue(Schema::hasTable('pkpa_schedule_acknowledgements'));
        $this->assertTrue(Schema::hasTable('pkpa_placement_change_requests'));
        $this->assertTrue(Schema::hasTable('pkpa_notification_deliveries'));

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/publish", ['confirmation' => $program->code])
            ->assertForbidden();

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/publish", [
                'title' => 'Jadwal Resmi '.$program->code,
                'confirmation' => $program->code,
            ])
            ->assertRedirect();

        $publication = PkpaPlacementPublication::firstOrFail();
        $this->assertSame('published', $publication->status);
        $this->assertTrue($publication->is_current);
        $this->assertSame(6, PkpaPublishedAssignment::where('pkpa_placement_publication_id', $publication->id)->count());
        $this->assertSame(12, PkpaPublishedAssignmentSupervisor::count());
        $this->assertSame(0, KpAssignment::count(), 'Tahap 05 tidak boleh menulis tabel legacy KP assignment.');

        app(PkpaPlacementNotificationService::class)->createPublicationNotifications($publication, 'placement_published');
        app(PkpaPlacementNotificationService::class)->sendPending($this->koordinator);
        $this->assertGreaterThanOrEqual(3, PkpaNotificationDelivery::where('channel', 'database')->where('status', 'sent')->count());
        $this->assertGreaterThanOrEqual(3, PkpaNotificationDelivery::where('channel', 'mail')->where('status', 'skipped')->count());
        $this->assertDatabaseHas('pkpa_notification_deliveries', ['channel' => 'mail', 'status' => 'skipped', 'failure_code' => 'email_disabled']);

        $count = PkpaNotificationDelivery::count();
        app(PkpaPlacementNotificationService::class)->createPublicationNotifications($publication, 'placement_published');
        $this->assertSame($count, PkpaNotificationDelivery::count(), 'Notification key harus idempotent.');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get("/management/pkpa-publications/{$publication->id}/export")
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_student_and_supervisor_portals_read_only_current_snapshot_with_acknowledgement(): void
    {
        $publication = $this->publishedFixture('PKPA-05-B');
        $assignment = $publication->assignments()->with('supervisors')->where('practice_domain_name_snapshot', 'Apotek')->firstOrFail();

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/pkpa-saya')
            ->assertOk()
            ->assertSee('Jadwal resmi PKPA')
            ->assertSee($assignment->practice_site_name_snapshot);

        $this->actingAs($this->otherStudent)->withSession(['active_role' => 'mahasiswa'])
            ->get("/mahasiswa/pkpa-saya/{$assignment->id}")
            ->assertForbidden();

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->post("/mahasiswa/pkpa-saya/{$assignment->id}/acknowledge")
            ->assertRedirect();
        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->post("/mahasiswa/pkpa-saya/{$assignment->id}/acknowledge")
            ->assertRedirect();
        $this->assertSame(1, PkpaScheduleAcknowledgement::where('pkpa_published_assignment_id', $assignment->id)
            ->where('core_user_id', $this->student->core_user_id)
            ->where('acknowledgement_type', 'acknowledged')
            ->count());

        $this->actingAs($this->internalSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/jadwal-pkpa')
            ->assertOk()
            ->assertSee($assignment->student_name_snapshot);
        $this->actingAs($this->internalSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get("/pembimbing-dalam/jadwal-pkpa/{$assignment->id}")
            ->assertOk()
            ->assertSee('Konfirmasi Baca Jadwal');

        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get("/pembimbing-lapangan/jadwal-pkpa/{$assignment->id}")
            ->assertOk()
            ->assertSee($assignment->student_name_snapshot);

        $this->actingAs($this->otherSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get("/pembimbing-dalam/jadwal-pkpa/{$assignment->id}")
            ->assertForbidden();
    }

    public function test_change_request_creates_new_revision_and_withdrawal_removes_current_publication(): void
    {
        $publication = $this->publishedFixture('PKPA-05-C');
        $assignment = $publication->assignments()->where('practice_domain_name_snapshot', 'Apotek')->firstOrFail();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-publications/{$publication->id}/change-requests", [
                'reason' => 'Penyesuaian jadwal tempat praktik',
                'request_type' => 'date_change',
                'assignment_id' => $assignment->id,
                'start_date' => '2026-03-10',
                'end_date' => '2026-03-10',
                'notes' => 'Tanggal revisi resmi',
            ])
            ->assertRedirect();

        $change = PkpaPlacementChangeRequest::firstOrFail();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-change-requests/{$change->id}/submit")
            ->assertRedirect();
        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-change-requests/{$change->id}/approve")
            ->assertRedirect();
        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-change-requests/{$change->id}/apply")
            ->assertRedirect();

        $this->assertSame('superseded', $publication->fresh()->status);
        $revision = PkpaPlacementPublication::where('id', '!=', $publication->id)->firstOrFail();
        $this->assertSame('published', $revision->status);
        $this->assertTrue($revision->is_current);
        $this->assertSame('2026-03-10', $revision->assignments()->where('pkpa_enrollment_requirement_id', $assignment->pkpa_enrollment_requirement_id)->firstOrFail()->start_date->toDateString());
        $this->assertSame('2026-02-02', $assignment->fresh()->start_date->toDateString(), 'Snapshot lama tidak boleh berubah.');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-publications/{$revision->id}/withdraw", ['withdrawal_reason' => 'Jadwal diganti oleh keputusan program'])
            ->assertRedirect();

        $this->assertSame('withdrawn', $revision->fresh()->status);
        $this->assertFalse($revision->fresh()->is_current);
    }

    private function publishedFixture(string $code): PkpaPlacementPublication
    {
        [$program, $plan] = $this->readyLockedPlan($code);
        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/publish", [
                'title' => 'Jadwal Resmi '.$program->code,
                'confirmation' => $program->code,
            ])
            ->assertRedirect();

        return PkpaPlacementPublication::with('assignments.supervisors')->firstOrFail();
    }

    private function readyLockedPlan(string $code): array
    {
        $program = $this->createProgram($code);
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-placement-planner/plans', ['pkpa_program_id' => $program->id, 'name' => 'Draft '.$code])
            ->assertRedirect();
        $plan = PkpaPlacementPlan::where('pkpa_program_id', $program->id)->firstOrFail();
        $enrollment = $this->enroll($program, $this->student->core_user_id, '250005');

        foreach (['APT', 'PKM', 'PBF', 'RS', 'IND', 'PEM'] as $index => $domainCode) {
            $programSite = $this->createProgramSite($program, $domainCode, $domainCode.'-'.$code, 4);
            $availability = $programSite->availabilityPeriods()->firstOrFail();
            $internalCore = $domainCode === 'APT' ? $this->internalSupervisor->core_user_id : 'CORE-INTERNAL-05-'.$domainCode;
            $fieldCore = $domainCode === 'APT' ? $this->fieldSupervisor->core_user_id : 'CORE-FIELD-05-'.$domainCode;
            $internal = $this->internal($program, $programSite->practice_domain_id, $internalCore);
            $field = $this->field($programSite->practice_site_id, $fieldCore);
            $requirement = $enrollment->requirements()->where('practice_domain_id', $programSite->practice_domain_id)->firstOrFail();

            $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
                ->post("/management/pkpa-placement-plans/{$plan->id}/assignments", $this->assignmentPayload($requirement, $programSite, $availability, $internal, $field, $index))
                ->assertRedirect();
        }

        $this->assertSame(6, PkpaRotationAssignment::where('pkpa_placement_plan_id', $plan->id)->count());
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/validate")
            ->assertRedirect();
        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/publication-lock")
            ->assertRedirect();

        return [$program->refresh(), $plan->refresh()];
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
        $program->domains()->update(['duration_value' => 1, 'duration_unit' => 'days', 'minimum_effective_days' => 1]);

        return $program->refresh();
    }

    private function createProgramSite(PkpaProgram $program, string $domainCode, string $siteCode, int $capacity): PkpaProgramSite
    {
        $domain = PkpaPracticeDomain::where('code', $domainCode)->firstOrFail();
        $programDomain = $program->domains()->where('practice_domain_id', $domain->id)->firstOrFail();
        $option = $domain->isGovernment() ? $domain->options()->where('code', 'LOKAPOM')->first() : null;
        $site = PkpaPracticeSite::create([
            'practice_domain_id' => $domain->id,
            'practice_domain_option_id' => $option?->id,
            'code' => $siteCode,
            'name' => 'Tempat '.$domain->name,
            'address' => 'Jl. PKPA '.$domain->name,
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
            'end_date' => '2026-04-30',
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
            'email_snapshot' => str($coreUserId)->lower().'@test.local',
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
            'email_snapshot' => str($coreUserId)->lower().'@test.local',
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
            'student_name_snapshot' => 'Mahasiswa Tahap 05',
            'core_account_status_snapshot' => 'active',
            'status' => 'active',
            'created_by_core_user_id' => $this->admin->core_user_id,
            'updated_by_core_user_id' => $this->admin->core_user_id,
        ]);
        app(PkpaEnrollmentRequirementService::class)->ensureRequirements($enrollment, $this->admin);

        return $enrollment->fresh('requirements');
    }

    private function assignmentPayload($requirement, PkpaProgramSite $programSite, PkpaSiteAvailabilityPeriod $availability, PkpaInternalSupervisorEligibility $internal, PkpaSiteFieldSupervisor $field, int $index): array
    {
        $dates = ['2026-02-02', '2026-02-03', '2026-02-04', '2026-02-05', '2026-02-06', '2026-02-09'];

        return [
            'pkpa_enrollment_requirement_id' => $requirement->id,
            'pkpa_program_site_id' => $programSite->id,
            'pkpa_site_availability_period_id' => $availability->id,
            'start_date' => $dates[$index],
            'end_date' => $dates[$index],
            'internal_supervisor_eligibility_id' => $internal->id,
            'site_field_supervisor_id' => $field->id,
        ];
    }
}
