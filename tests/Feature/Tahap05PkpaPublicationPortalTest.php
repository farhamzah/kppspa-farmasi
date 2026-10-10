<?php

namespace Tests\Feature;

use App\Models\KpAssignment;
use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpPlaceQuota;
use App\Models\PkpaAssessmentComponent;
use App\Models\PkpaAssessmentScheme;
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
use App\Models\PkpaRotationAssessment;
use App\Models\PkpaRotationAssessmentAssessor;
use App\Models\PkpaRotationAssignment;
use App\Models\PkpaRotationComponentScore;
use App\Models\PkpaRotationRun;
use App\Models\PkpaScheduleAcknowledgement;
use App\Models\PkpaSiteAvailabilityPeriod;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpaAttendanceService;
use App\Services\PkpaEnrollmentRequirementService;
use App\Services\PkpaLogbookService;
use App\Services\PkpaPlacementNotificationService;
use App\Services\PkpaProgramService;
use App\Services\PkpaReportService;
use App\Services\PkpaRotationRunService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\PkpaPortfolioTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class, PkpaPortfolioTemplateSeeder::class]);

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
        $this->assertSame(5, PkpaPublishedAssignment::where('pkpa_placement_publication_id', $publication->id)->count());
        $this->assertSame(10, PkpaPublishedAssignmentSupervisor::count());
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

    public function test_date_only_values_do_not_shift_between_plan_publication_and_runtime_and_can_be_repaired(): void
    {
        $publication = $this->publishedFixture('PKPA-05-DATES');
        $assignment = $publication->assignments()->with('sourceAssignment')->firstOrFail();
        $expectedStart = $assignment->sourceAssignment->start_date->toDateString();
        $expectedEnd = $assignment->sourceAssignment->end_date->toDateString();

        $this->assertSame($expectedStart, $assignment->start_date->toDateString());
        $this->assertSame($expectedEnd, $assignment->end_date->toDateString());

        $assignment->sourceAssignment->update(['start_date' => '2026-09-01', 'end_date' => '2026-10-02']);
        $assignment->update(['start_date' => '2026-09-01', 'end_date' => '2026-10-02']);
        $expectedStart = '2026-09-01';
        $expectedEnd = '2026-10-02';

        app(PkpaRotationRunService::class)->createFromPublication($publication->fresh(), $this->admin);
        $run = PkpaRotationRun::where('current_published_assignment_id', $assignment->id)->firstOrFail();
        $this->assertSame($expectedStart, $run->scheduled_start_date->toDateString());
        $this->assertSame($expectedEnd, $run->scheduled_end_date->toDateString());

        DB::table('pkpa_published_assignments')->where('id', $assignment->id)->update([
            'start_date' => '2026-01-31',
            'end_date' => '2026-01-31',
        ]);
        DB::table('pkpa_rotation_runs')->where('id', $run->id)->update([
            'scheduled_start_date' => '2026-01-30',
            'scheduled_end_date' => '2026-01-30',
        ]);

        $this->artisan('pkpa:repair-rotation-dates', ['--program' => $publication->program->code])
            ->expectsOutputToContain('Ini hanya preview')
            ->assertSuccessful();
        $this->assertSame('2026-01-30', $run->fresh()->scheduled_start_date->toDateString());

        $this->artisan('pkpa:repair-rotation-dates', [
            '--program' => $publication->program->code,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame($expectedStart, $assignment->fresh()->start_date->toDateString());
        $this->assertSame($expectedEnd, $assignment->fresh()->end_date->toDateString());
        $this->assertSame($expectedStart, $run->fresh()->scheduled_start_date->toDateString());
        $this->assertSame($expectedEnd, $run->fresh()->scheduled_end_date->toDateString());

        $this->travelTo('2026-10-02 12:00:00');
        $attendance = app(PkpaAttendanceService::class)->save($run->fresh(), [
            'attendance_date' => '2026-10-02',
            'attendance_type' => 'present',
            'check_in_time' => '08:00',
            'check_out_time' => '16:00',
        ], $this->student);
        $logbook = app(PkpaLogbookService::class)->save($run->fresh(), [
            'entry_date' => '2026-10-02',
            'title' => 'Kegiatan hari terakhir',
            'activity_summary' => 'Pelayanan kefarmasian pada hari terakhir PKPA Apotek.',
            'learning_outcomes' => 'Menyelesaikan pelayanan sesuai prosedur.',
            'reflection' => 'Mengevaluasi pelaksanaan PKPA Apotek.',
        ], $this->student);

        $this->assertSame('2026-10-02', $attendance->attendance_date->toDateString());
        $this->assertSame('2026-10-02', $logbook->entry_date->toDateString());
    }

    public function test_student_and_supervisor_portals_read_only_current_snapshot_with_acknowledgement(): void
    {
        $publication = $this->publishedFixture('PKPA-05-B');
        $assignment = $publication->assignments()->with('supervisors')->where('practice_domain_name_snapshot', 'Apotek')->firstOrFail();
        $this->internalSupervisor->forceFill(['name' => 'apt. Farhamzah, S.Si., M.T.I'])->save();
        $this->fieldSupervisor->forceFill(['name' => 'apt. Ayda, S.Farm'])->save();
        $assignment->supervisors()->where('supervisor_type', 'internal')->update(['name_snapshot' => 'Farhamzah']);
        $assignment->supervisors()->where('supervisor_type', 'field')->update(['name_snapshot' => 'Ayda']);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/pkpa-saya')
            ->assertOk()
            ->assertSee('Jadwal resmi PKPA')
            ->assertSee('Jadwal &amp; Wahana', false)
            ->assertSee('Presensi Harian')
            ->assertSee('Logbook Harian')
            ->assertSee($assignment->practice_site_name_snapshot)
            ->assertSee('apt. Farhamzah, S.Si., M.T.I')
            ->assertSee('apt. Ayda, S.Farm');

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
            ->get('/pembimbing-dalam/mahasiswa-bimbingan')
            ->assertOk()
            ->assertSee('Daftar per wahana')
            ->assertSee('Apotek')
            ->assertSee('Pemantauan Mahasiswa')
            ->assertDontSee('Review Logbook')
            ->assertSee($assignment->student_name_snapshot)
            ->assertSee('Aktif di portal');
        $this->actingAs($this->internalSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get("/pembimbing-dalam/jadwal-pkpa/{$assignment->id}")
            ->assertOk()
            ->assertSee('Konfirmasi Baca Jadwal')
            ->assertSee('apt. Farhamzah, S.Si., M.T.I')
            ->assertSee('apt. Ayda, S.Farm');
        $this->actingAs($this->internalSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get("/pembimbing-dalam/mahasiswa-bimbingan/{$assignment->id}")
            ->assertOk()
            ->assertSee($assignment->student_name_snapshot)
            ->assertSee('Aktif di portal');

        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/mahasiswa-pkpa')
            ->assertOk()
            ->assertSee('Daftar per wahana')
            ->assertSee('Mahasiswa Bimbingan')
            ->assertSee('Pemantauan Mahasiswa')
            ->assertSee($assignment->student_name_snapshot)
            ->assertSee('Aktif di portal');
        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get("/pembimbing-lapangan/jadwal-pkpa/{$assignment->id}")
            ->assertOk()
            ->assertSee($assignment->student_name_snapshot)
            ->assertSee('apt. Farhamzah, S.Si., M.T.I')
            ->assertSee('apt. Ayda, S.Farm');
        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get("/pembimbing-lapangan/mahasiswa-pkpa/{$assignment->id}")
            ->assertOk()
            ->assertSee($assignment->student_name_snapshot)
            ->assertSee('Aktif di portal');

        $this->actingAs($this->otherSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get("/pembimbing-dalam/jadwal-pkpa/{$assignment->id}")
            ->assertForbidden();
    }

    public function test_report_center_uses_current_pkpa_publication_and_supports_filters_and_exports(): void
    {
        $publication = $this->publishedFixture('PKPA-05-REPORT');
        $assignment = $publication->assignments()->with('supervisors')->where('practice_domain_name_snapshot', 'Apotek')->firstOrFail();
        $internal = $assignment->supervisors->firstWhere('supervisor_type', 'internal');
        $pbfAssignment = $publication->assignments()->where('practice_domain_name_snapshot', 'Pedagang Besar Farmasi')->firstOrFail();
        $period = KpPeriod::create([
            'name' => $publication->program->code.' - '.$publication->program->name,
            'status' => 'dibuka',
        ]);
        $place = KpPlace::create([
            'name' => $assignment->practice_site_name_snapshot,
            'type' => 'apotek',
            'status' => 'aktif',
        ]);
        $quota = KpPlaceQuota::create([
            'kp_period_id' => $period->id,
            'kp_place_id' => $place->id,
            'quota' => 1,
            'is_open' => true,
        ]);

        $this->assertSame(1, $quota->filledCount());
        $this->assertSame(0, $quota->remainingQuota());
        $this->assertSame('Penuh', $quota->statusLabel());

        $pbfPlace = KpPlace::create([
            'name' => $pbfAssignment->practice_site_name_snapshot,
            'type' => 'distributor',
            'status' => 'aktif',
        ]);
        $pbfQuota = KpPlaceQuota::create([
            'kp_period_id' => $period->id,
            'kp_place_id' => $pbfPlace->id,
            'quota' => 1,
            'is_open' => true,
        ]);

        $this->assertSame(1, $pbfQuota->filledCount());
        $this->assertSame(0, $pbfQuota->remainingQuota());
        $this->assertSame('Penuh', $pbfQuota->statusLabel());

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/recaps/placements?program='.$publication->pkpa_program_id.'&domain='.$assignment->practice_domain_id)
            ->assertOk()
            ->assertSee($assignment->student_name_snapshot)
            ->assertSee($assignment->practice_site_name_snapshot)
            ->assertSee('Cetak A4');

        $filteredRows = app(PkpaReportService::class)->rows('placements', Request::create('/management/recaps/placements', 'GET', [
            'program' => $publication->pkpa_program_id,
            'internal_supervisor' => $internal->core_user_id,
        ]));
        $this->assertCount(1, $filteredRows);
        $this->assertSame($assignment->student_name_snapshot, $filteredRows->first()['Nama Mahasiswa']);

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/recaps/sites/preview?program='.$publication->pkpa_program_id)
            ->assertOk()
            ->assertSee('A4 landscape')
            ->assertSee($assignment->practice_site_name_snapshot);

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/recaps/supervisors/download/pdf?program='.$publication->pkpa_program_id)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/recaps/students/download/excel?program='.$publication->pkpa_program_id)
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $publication->update(['is_current' => false, 'current_key' => null]);
        $fallbackRows = app(PkpaReportService::class)->rows('placements', Request::create('/management/recaps/placements', 'GET', [
            'program' => $publication->pkpa_program_id,
        ]));
        $this->assertCount(5, $fallbackRows);
    }

    public function test_student_detail_and_acknowledge_allow_numeric_like_core_user_id(): void
    {
        $this->student->forceFill(['core_user_id' => '373'])->save();
        $publication = $this->publishedFixture('PKPA-05-NUM');
        $assignment = $publication->assignments()->where('student_core_user_id', '373')->firstOrFail();

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get("/mahasiswa/pkpa-saya/{$assignment->id}")
            ->assertOk()
            ->assertSee($assignment->practice_site_name_snapshot);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->post("/mahasiswa/pkpa-saya/{$assignment->id}/acknowledge")
            ->assertRedirect();

        $this->assertSame(1, PkpaScheduleAcknowledgement::where('pkpa_published_assignment_id', $assignment->id)
            ->where('core_user_id', '373')
            ->where('acknowledgement_type', 'acknowledged')
            ->count());
    }

    public function test_student_portal_pages_use_pkpa_publication_and_rotation_data(): void
    {
        $publication = $this->publishedFixture('PKPA-05-STUDENT-PAGES');
        $assignment = $publication->assignments()->where('practice_domain_name_snapshot', 'Apotek')->firstOrFail();
        $run = PkpaRotationRun::create([
            'pkpa_program_id' => $assignment->publication->pkpa_program_id,
            'pkpa_enrollment_id' => $assignment->pkpa_enrollment_id,
            'pkpa_enrollment_requirement_id' => $assignment->pkpa_enrollment_requirement_id,
            'current_placement_publication_id' => $assignment->pkpa_placement_publication_id,
            'origin_published_assignment_id' => $assignment->id,
            'current_published_assignment_id' => $assignment->id,
            'practice_domain_id' => $assignment->practice_domain_id,
            'practice_site_id' => $assignment->practice_site_id,
            'student_core_user_id' => $assignment->student_core_user_id,
            'scheduled_start_date' => $assignment->start_date,
            'scheduled_end_date' => $assignment->end_date,
            'actual_start_date' => $assignment->start_date,
            'actual_end_date' => $assignment->end_date,
            'status' => 'operational_active',
            'operational_status' => 'active',
            'publication_sync_status' => 'synced',
            'current_key' => 'TEST-RUN-'.$assignment->id,
            'created_by_core_user_id' => $this->koordinator->core_user_id,
            'updated_by_core_user_id' => $this->koordinator->core_user_id,
        ]);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/penempatan-pkpa')
            ->assertOk()
            ->assertSee('Penempatan aktif / terdekat')
            ->assertSee($assignment->practice_site_name_snapshot);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get("/mahasiswa/rotasi-pkpa/{$run->id}")
            ->assertOk()
            ->assertSee($assignment->practice_site_name_snapshot)
            ->assertSee('Detail Rotasi PKPA');

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/jurnal-pkpa')
            ->assertOk()
            ->assertSee('Pilih Rotasi untuk Mengisi Logbook')
            ->assertSee($assignment->practice_site_name_snapshot);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/portofolio-pkpa')
            ->assertOk()
            ->assertSee('Portofolio Digital Rotasi')
            ->assertSee('difokuskan ke wahana Apotek lebih dulu')
            ->assertSee($assignment->practice_site_name_snapshot);
    }

    public function test_locking_plan_immediately_exposes_valid_assignments_to_portals(): void
    {
        $program = $this->createProgram('PKPA-05-LOCK');
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/pkpa-placement-planner/plans', ['pkpa_program_id' => $program->id, 'name' => 'Draft Lock Portal'])
            ->assertRedirect();

        $plan = PkpaPlacementPlan::where('pkpa_program_id', $program->id)->firstOrFail();
        $enrollment = $this->enroll($program, $this->student->core_user_id, '250105');
        $programSite = $this->createProgramSite($program, 'APT', 'APT-LOCK', 4);
        $availability = $programSite->availabilityPeriods()->firstOrFail();
        $internal = $this->internal($program, $programSite->practice_domain_id, $this->internalSupervisor->core_user_id);
        $field = $this->field($programSite->practice_site_id, $this->fieldSupervisor->core_user_id);
        $requirement = $enrollment->requirements()->where('practice_domain_id', $programSite->practice_domain_id)->firstOrFail();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/assignments", $this->assignmentPayload($requirement, $programSite, $availability, $internal, $field, 0))
            ->assertRedirect();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/validate")
            ->assertRedirect();
        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-placement-plans/{$plan->id}/publication-lock")
            ->assertRedirect();

        $publication = PkpaPlacementPublication::where('pkpa_program_id', $program->id)->firstOrFail();
        $assignment = $publication->assignments()->with('supervisors')->firstOrFail();

        $this->assertSame('published', $publication->status);
        $this->assertTrue($publication->is_current);
        $this->assertSame(1, $publication->assignments()->count());
        $this->assertSame('Mahasiswa Tahap 05', $assignment->student_name_snapshot);
        $this->assertTrue($assignment->supervisors->contains(fn ($supervisor) => $supervisor->supervisor_type === 'internal' && $supervisor->core_user_id === $this->internalSupervisor->core_user_id));
        $this->assertTrue($assignment->supervisors->contains(fn ($supervisor) => $supervisor->supervisor_type === 'field' && $supervisor->core_user_id === $this->fieldSupervisor->core_user_id));

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/pkpa-saya')
            ->assertOk()
            ->assertSee($assignment->practice_site_name_snapshot);

        $this->actingAs($this->internalSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/jadwal-pkpa')
            ->assertOk()
            ->assertSee($assignment->student_name_snapshot);
        $this->actingAs($this->internalSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get("/pembimbing-dalam/jadwal-pkpa/{$assignment->id}")
            ->assertOk()
            ->assertSee($assignment->student_name_snapshot);

        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/jadwal-pkpa')
            ->assertOk()
            ->assertSee($assignment->student_name_snapshot);
        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get("/pembimbing-lapangan/jadwal-pkpa/{$assignment->id}")
            ->assertOk()
            ->assertSee($assignment->student_name_snapshot);
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

    public function test_change_request_can_add_preceptor_after_schedule_is_published(): void
    {
        $publication = $this->publishedFixture('PKPA-05-PRESEPTOR');
        $assignment = $publication->assignments()->firstOrFail();
        $newField = $this->field($assignment->practice_site_id, 'CORE-FIELD-05-LATE');

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post("/management/pkpa-publications/{$publication->id}/change-requests", [
                'reason' => 'Preseptor ditetapkan setelah mahasiswa mulai PKPA',
                'request_type' => 'supervisor_change',
                'assignment_id' => $assignment->id,
                'site_field_supervisor_id' => $newField->id,
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

        $revision = PkpaPlacementPublication::whereKeyNot($publication->id)->firstOrFail();
        $revisedAssignment = $revision->assignments()->where('pkpa_enrollment_requirement_id', $assignment->pkpa_enrollment_requirement_id)->with('supervisors')->firstOrFail();
        $this->assertTrue($revisedAssignment->supervisors->contains(fn ($supervisor) => $supervisor->supervisor_type === 'field' && $supervisor->core_user_id === 'CORE-FIELD-05-LATE'));
    }

    public function test_internal_supervisor_can_be_replaced_in_bulk_without_erasing_history(): void
    {
        $publication = $this->publishedFixture('PKPA-05-INTERNAL-REPLACEMENT');
        $assignments = $publication->assignments()
            ->with(['supervisors', 'practiceDomain'])
            ->whereIn('practice_domain_name_snapshot', ['Apotek', 'Pedagang Besar Farmasi'])
            ->get();
        $this->assertCount(2, $assignments);

        foreach ($assignments as $assignment) {
            $this->internal($publication->program, $assignment->practice_domain_id, $this->otherSupervisor->core_user_id);
        }
        app(PkpaRotationRunService::class)->createFromPublication($publication, $this->koordinator);
        $runs = PkpaRotationRun::whereIn('pkpa_enrollment_requirement_id', $assignments->pluck('pkpa_enrollment_requirement_id'))->get();
        $this->assertCount(2, $runs);
        $fieldHistoryIds = $runs->mapWithKeys(fn ($run) => [
            $run->id => $run->supervisorHistories()->where('supervisor_type', 'field')->where('status', 'active')->value('id'),
        ]);
        $aptAssignment = $assignments->firstWhere('practice_domain_name_snapshot', 'Apotek');
        $aptRun = $runs->firstWhere('pkpa_enrollment_requirement_id', $aptAssignment->pkpa_enrollment_requirement_id);
        $programDomain = $publication->program->domains()->where('practice_domain_id', $aptAssignment->practice_domain_id)->firstOrFail();
        $scheme = PkpaAssessmentScheme::create([
            'pkpa_program_domain_id' => $programDomain->id,
            'code' => 'APT-TRANSFER-05',
            'name' => 'Skema Transfer Draf',
            'status' => 'active',
            'is_current' => true,
            'current_key' => 'PROGRAM-DOMAIN:'.$programDomain->id,
            'require_academic_readiness' => false,
        ]);
        $component = PkpaAssessmentComponent::create([
            'pkpa_assessment_scheme_id' => $scheme->id,
            'code' => 'PD-TRANSFER',
            'name' => 'Nilai Pembimbing Dalam',
            'component_type' => 'internal_supervisor_assessment',
            'assessor_type' => 'internal_supervisor',
            'weight_percentage' => 50,
            'maximum_raw_score' => 100,
            'status' => 'active',
        ]);
        $assessment = PkpaRotationAssessment::create([
            'pkpa_rotation_run_id' => $aptRun->id,
            'source_assessment_scheme_id' => $scheme->id,
            'scheme_code_snapshot' => $scheme->code,
            'scheme_name_snapshot' => $scheme->name,
            'scheme_version_snapshot' => 1,
            'status' => 'in_progress',
            'completion_status' => 'partially_complete',
        ]);
        $oldInternalHistory = $aptRun->supervisorHistories()->where('supervisor_type', 'internal')->where('status', 'active')->firstOrFail();
        $oldAssessor = PkpaRotationAssessmentAssessor::create([
            'pkpa_rotation_assessment_id' => $assessment->id,
            'pkpa_assessment_component_id' => $component->id,
            'assessor_type' => 'internal_supervisor',
            'core_user_id' => $oldInternalHistory->core_user_id,
            'name_snapshot' => $oldInternalHistory->name_snapshot,
            'source_rotation_supervisor_history_id' => $oldInternalHistory->id,
            'status' => 'in_progress',
            'assigned_at' => now(),
        ]);
        $draftScore = PkpaRotationComponentScore::create([
            'pkpa_rotation_assessment_id' => $assessment->id,
            'pkpa_assessment_component_id' => $component->id,
            'assessor_assignment_id' => $oldAssessor->id,
            'component_code_snapshot' => $component->code,
            'component_name_snapshot' => $component->name,
            'component_type_snapshot' => $component->component_type,
            'weight_percentage_snapshot' => 50,
            'calculation_method_snapshot' => 'direct_score',
            'raw_score' => 75,
            'normalized_score' => 75,
            'weighted_score' => 37.5,
            'status' => 'draft',
            'comments' => 'Draf dari pembimbing lama.',
        ]);

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get("/management/pkpa-publications/{$publication->id}/internal-supervisor-replacement")
            ->assertOk()
            ->assertSee('Alihkan mahasiswa ke dosen pengganti')
            ->assertSee('Pembimbing saat ini')
            ->assertSee('Nilai draf dialihkan')
            ->assertSee('Pilih semua bimbingan');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-publications/{$publication->id}/internal-supervisor-replacement", [
                'assignment_ids' => $assignments->pluck('id')->all(),
                'replacement_core_user_id' => $this->otherSupervisor->core_user_id,
                'effective_date' => '2026-02-02',
                'reason' => 'Pembimbing lama mengundurkan diri dan tanggung jawab dialihkan.',
                'confirmation' => '1',
            ])
            ->assertRedirect();

        $change = PkpaPlacementChangeRequest::where('request_type', 'internal_supervisor_replacement')->firstOrFail();
        $this->assertSame('draft', $change->status);
        $this->assertSame(2, $change->items()->count());

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get("/management/pkpa-change-requests/{$change->id}")
            ->assertOk()
            ->assertSee('Konfirmasi dan Terapkan')
            ->assertSee('Pembimbing lama')
            ->assertSee('Pembimbing baru');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/pkpa-change-requests/{$change->id}/confirm-internal-supervisor-replacement")
            ->assertRedirect();

        $this->assertSame('applied', $change->fresh()->status);
        $this->assertSame('superseded', $publication->fresh()->status);
        $revision = PkpaPlacementPublication::where('pkpa_program_id', $publication->pkpa_program_id)->where('is_current', true)->firstOrFail();
        $revised = $revision->assignments()->whereIn('pkpa_enrollment_requirement_id', $assignments->pluck('pkpa_enrollment_requirement_id'))->with('supervisors')->get();
        $this->assertCount(2, $revised);
        $this->assertTrue($revised->every(fn ($assignment) => $assignment->supervisors->contains(fn ($supervisor) => $supervisor->supervisor_type === 'internal' && $supervisor->core_user_id === $this->otherSupervisor->core_user_id)));
        $this->assertTrue($assignments->every(fn ($assignment) => $assignment->fresh('supervisors')->supervisors->contains(fn ($supervisor) => $supervisor->supervisor_type === 'internal' && $supervisor->core_user_id !== $this->otherSupervisor->core_user_id)), 'Snapshot publikasi lama harus tetap utuh.');
        $this->assertSame('replaced', $oldAssessor->fresh()->status);
        $replacementAssessor = $assessment->assessors()->where('assessor_type', 'internal_supervisor')->where('core_user_id', $this->otherSupervisor->core_user_id)->firstOrFail();
        $this->assertSame('in_progress', $replacementAssessor->status);
        $this->assertSame($replacementAssessor->id, $draftScore->fresh()->assessor_assignment_id);
        $this->assertTrue((bool) data_get($draftScore->fresh()->source_summary, 'requires_replacement_supervisor_review'));
        $this->assertSame($oldAssessor->core_user_id, data_get($draftScore->fresh()->source_summary, 'supervisor_transfer_history.0.from_core_user_id'));
        $this->actingAs($this->otherSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/penilaian-pkpa')
            ->assertOk()
            ->assertSee('Draf dialihkan dari pembimbing sebelumnya');
        $this->actingAs($this->internalSupervisor)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/penilaian-pkpa')
            ->assertOk()
            ->assertDontSee('Draf dialihkan dari pembimbing sebelumnya');

        foreach ($runs as $run) {
            $run->refresh();
            $this->assertSame($this->otherSupervisor->core_user_id, $run->supervisorHistories()->where('supervisor_type', 'internal')->where('status', 'active')->value('core_user_id'));
            $this->assertTrue($run->supervisorHistories()->where('supervisor_type', 'internal')->where('status', 'ended')->exists());
            $this->assertSame($fieldHistoryIds[$run->id], $run->supervisorHistories()->where('supervisor_type', 'field')->where('status', 'active')->value('id'), 'Preseptor yang tidak berubah tidak boleh dibuat ulang.');
            $this->assertSame('current', $run->publication_sync_status);
        }
    }

    public function test_replacement_can_include_all_domains_of_the_same_student(): void
    {
        $publication = $this->continuityFixture('PKPA-05-CONTINUITY');
        $apt = $publication->assignments->firstWhere('practice_domain_name_snapshot', 'Apotek');
        $change = app(\App\Services\PkpaPlacementChangeRequestService::class)->createInternalSupervisorReplacement(
            $publication, [$apt->id], $this->otherSupervisor->core_user_id, '2026-02-02',
            'Pembimbing lama mengundurkan diri untuk seluruh wahana.', $this->koordinator, true,
        );
        $this->assertSame(5, $change->items()->count());
        $this->assertTrue($change->impact_summary['all_student_domains']);
        $this->assertSame('published', $publication->fresh()->status, 'Membuat preview tidak boleh menerapkan revisi.');
    }

    public function test_carry_replacement_previews_applies_all_domains_and_is_idempotent(): void
    {
        $publication = $this->continuityFixture('PKPA-05-CARRY');
        $service = app(\App\Services\PkpaPlacementChangeRequestService::class);
        $apt = $publication->assignments->firstWhere('practice_domain_name_snapshot', 'Apotek');
        $change = $service->createInternalSupervisorReplacement($publication, [$apt->id], $this->otherSupervisor->core_user_id, '2026-02-02', 'Pergantian awal hanya diterapkan pada Apotek.', $this->koordinator);
        $service->submit($change, $this->koordinator);
        $service->approve($change->refresh(), $this->koordinator);
        $service->apply($change->refresh(), $this->koordinator);
        $count = PkpaPlacementPublication::count();
        $options = ['--program' => 'PKPA-05-CARRY', '--old-core-id' => $this->internalSupervisor->core_user_id];
        $this->artisan('pkpa:audit-supervisor-continuity', ['--program' => 'PKPA-05-CARRY'])
            ->expectsOutputToContain('Berbeda dari wahana sumber')->assertFailed();
        $this->artisan('pkpa:carry-supervisor-replacement', $options)->assertSuccessful();
        $this->assertSame($count, PkpaPlacementPublication::count());
        $this->artisan('pkpa:carry-supervisor-replacement', $options + ['--apply' => true])->assertSuccessful();
        $current = PkpaPlacementPublication::current()->where('pkpa_program_id', $publication->pkpa_program_id)->firstOrFail();
        foreach ($current->assignments()->with('supervisors')->get() as $assignment) {
            $this->assertSame($this->otherSupervisor->core_user_id, $assignment->supervisors->firstWhere('supervisor_type', 'internal')->core_user_id);
            $run = PkpaRotationRun::where('pkpa_enrollment_requirement_id', $assignment->pkpa_enrollment_requirement_id)->firstOrFail();
            $this->assertSame($this->otherSupervisor->core_user_id, $run->supervisorHistories()->where('supervisor_type', 'internal')->where('status', 'active')->value('core_user_id'));
        }
        $count = PkpaPlacementPublication::count();
        $this->artisan('pkpa:carry-supervisor-replacement', $options + ['--apply' => true])->assertSuccessful();
        $this->assertSame($count, PkpaPlacementPublication::count());
        $this->artisan('pkpa:audit-supervisor-continuity', ['--program' => 'PKPA-05-CARRY'])->assertSuccessful();
    }

    public function test_republishing_plan_does_not_restore_ended_supervisor_in_recaps(): void
    {
        $publication = $this->continuityFixture('PKPA-05-ENDED-SNAPSHOT');
        $apt = $publication->assignments->firstWhere('practice_domain_name_snapshot', 'Apotek');
        $source = $apt->sourceAssignment;
        $old = $source->supervisors()->where('supervisor_type', 'internal')->firstOrFail();
        $newEligibility = PkpaInternalSupervisorEligibility::where('pkpa_program_id', $publication->pkpa_program_id)
            ->where('practice_domain_id', $apt->practice_domain_id)->where('core_user_id', $this->otherSupervisor->core_user_id)->firstOrFail();
        $replacement = $old->replicate();
        $replacement->fill(['core_user_id' => $newEligibility->core_user_id, 'name_snapshot' => $newEligibility->name_snapshot, 'internal_supervisor_eligibility_id' => $newEligibility->id])->save();
        $old->update(['status' => 'ended']);
        $publication->update(['status' => 'superseded', 'is_current' => false, 'current_key' => null]);
        $current = app(\App\Services\PkpaPlacementPublicationService::class)->syncLockedPlanToPortal($publication->plan, $this->koordinator);
        $published = $current->assignments()->with('supervisors')->where('pkpa_enrollment_requirement_id', $apt->pkpa_enrollment_requirement_id)->firstOrFail();
        $this->assertCount(1, $published->supervisors->where('supervisor_type', 'internal'));
        $this->assertSame($this->otherSupervisor->core_user_id, $published->supervisors->firstWhere('supervisor_type', 'internal')->core_user_id);
        $rows = app(PkpaReportService::class)->rows('placements', Request::create('/', 'GET', ['program' => $publication->pkpa_program_id]));
        $this->assertSame(user_display_name($this->otherSupervisor, 'pembimbing_dalam'), $rows->firstWhere('Wahana', 'Apotek')['Pembimbing Dalam']);
        $this->assertSame('ended', $old->fresh()->status);
    }

    public function test_carry_replacement_blocks_missing_mapping_without_changes(): void
    {
        $publication = $this->continuityFixture('PKPA-05-CARRY-BLOCK');
        $count = PkpaPlacementPublication::count();
        $this->artisan('pkpa:carry-supervisor-replacement', [
            '--program' => 'PKPA-05-CARRY-BLOCK', '--old-core-id' => $this->internalSupervisor->core_user_id, '--apply' => true,
        ])->assertFailed();
        $this->assertSame($count, PkpaPlacementPublication::count());
        $this->assertSame('published', $publication->fresh()->status);
    }

    public function test_all_domain_replacement_preserves_other_supervisors(): void
    {
        $publication = $this->continuityFixture('PKPA-05-CARRY-OTHER');
        $rs = $publication->assignments->firstWhere('practice_domain_name_snapshot', 'Rumah Sakit');
        $rs->supervisors()->where('supervisor_type', 'internal')->update(['core_user_id' => 'CORE-ANOTHER-LECTURER']);
        $publication->refresh()->load('assignments.supervisors');
        $apt = $publication->assignments->firstWhere('practice_domain_name_snapshot', 'Apotek');
        $change = app(\App\Services\PkpaPlacementChangeRequestService::class)->createInternalSupervisorReplacement(
            $publication, [$apt->id], $this->otherSupervisor->core_user_id, '2026-02-02',
            'Pembimbing lama mengundurkan diri untuk seluruh wahana.', $this->koordinator, true,
        );
        $this->assertSame(4, $change->items()->count());
        $this->assertFalse($change->items()->where('old_published_assignment_id', $rs->id)->exists());
    }

    public function test_carry_replacement_blocks_ineligible_target_without_partial_revision(): void
    {
        $publication = $this->continuityFixture('PKPA-05-CARRY-INACTIVE');
        $service = app(\App\Services\PkpaPlacementChangeRequestService::class);
        $apt = $publication->assignments->firstWhere('practice_domain_name_snapshot', 'Apotek');
        $change = $service->createInternalSupervisorReplacement($publication, [$apt->id], $this->otherSupervisor->core_user_id, '2026-02-02', 'Pergantian awal hanya diterapkan pada Apotek.', $this->koordinator);
        $service->submit($change, $this->koordinator);
        $service->approve($change->refresh(), $this->koordinator);
        $service->apply($change->refresh(), $this->koordinator);
        $pbf = $publication->assignments->firstWhere('practice_domain_name_snapshot', 'Pedagang Besar Farmasi');
        PkpaInternalSupervisorEligibility::where('pkpa_program_id', $publication->pkpa_program_id)
            ->where('practice_domain_id', $pbf->practice_domain_id)->where('core_user_id', $this->otherSupervisor->core_user_id)
            ->update(['core_account_status_snapshot' => 'inactive']);
        $count = PkpaPlacementPublication::count();
        $this->artisan('pkpa:carry-supervisor-replacement', [
            '--program' => 'PKPA-05-CARRY-INACTIVE', '--old-core-id' => $this->internalSupervisor->core_user_id, '--apply' => true,
        ])->assertFailed();
        $this->assertSame($count, PkpaPlacementPublication::count());
    }

    private function continuityFixture(string $code): PkpaPlacementPublication
    {
        $publication = $this->publishedFixture($code);
        foreach ($publication->assignments as $assignment) {
            $eligibility = PkpaInternalSupervisorEligibility::where('pkpa_program_id', $publication->pkpa_program_id)->where('practice_domain_id', $assignment->practice_domain_id)->firstOrFail();
            $eligibility->update(['core_user_id' => $this->internalSupervisor->core_user_id]);
            $assignment->supervisors()->where('supervisor_type', 'internal')->update(['core_user_id' => $this->internalSupervisor->core_user_id]);
            $assignment->sourceAssignment->supervisors()->where('supervisor_type', 'internal')->update(['core_user_id' => $this->internalSupervisor->core_user_id]);
            $this->internal($publication->program, $assignment->practice_domain_id, $this->otherSupervisor->core_user_id);
        }
        app(PkpaRotationRunService::class)->createFromPublication($publication->fresh(), $this->koordinator);

        return $publication->fresh('assignments.supervisors');
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

        foreach (['APT', 'PBF', 'RS', 'IND', 'PEM'] as $index => $domainCode) {
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

        $this->assertSame(5, PkpaRotationAssignment::where('pkpa_placement_plan_id', $plan->id)->count());
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
        $option = $domain->isGovernment() ? $domain->options()->where('code', 'PUSKESMAS')->first() : null;
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
