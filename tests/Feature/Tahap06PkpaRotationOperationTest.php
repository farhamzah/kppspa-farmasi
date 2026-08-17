<?php

namespace Tests\Feature;

use App\Models\KpAssignment;
use App\Models\PkpaEnrollment;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramSite;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaPublishedAssignmentSupervisor;
use App\Models\PkpaRotationOperationRule;
use App\Models\PkpaRotationProgressSnapshot;
use App\Models\PkpaRotationRun;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpaAttendanceService;
use App\Services\PkpaEnrollmentRequirementService;
use App\Services\PkpaLogbookService;
use App\Services\PkpaProgramService;
use App\Services\PkpaRotationOperationRuleService;
use App\Services\PkpaRotationProgressService;
use App\Services\PkpaRotationPublicationSyncService;
use App\Services\PkpaRotationRunService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Tahap06PkpaRotationOperationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $student;
    private User $otherStudent;
    private User $fieldSupervisor;
    private User $internalSupervisor;
    private User $otherSupervisor;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('my_pkpa.rotation_operations_enabled', true);
        config()->set('my_pkpa.logbook_attachment_disk', 'local');
        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class]);
        $this->admin = $this->makeUser('admin06@test.local', ['admin'], 'CORE-ADMIN-06');
        $this->koordinator = $this->makeUser('koor06@test.local', ['koordinator_kp'], 'CORE-KOOR-06');
        $this->student = $this->makeUser('student06@test.local', ['mahasiswa'], 'CORE-STUDENT-06');
        $this->otherStudent = $this->makeUser('other-student06@test.local', ['mahasiswa'], 'CORE-STUDENT-OTHER-06');
        $this->fieldSupervisor = $this->makeUser('field06@test.local', ['pembimbing_lapangan'], 'CORE-FIELD-06');
        $this->internalSupervisor = $this->makeUser('internal06@test.local', ['pembimbing_dalam'], 'CORE-INTERNAL-06');
        $this->otherSupervisor = $this->makeUser('other-supervisor06@test.local', ['pembimbing_dalam'], 'CORE-INTERNAL-OTHER-06');
    }

    public function test_schema_rules_runtime_and_activation_from_current_publication(): void
    {
        $fixture = $this->publishedFixture();
        $programDomain = $fixture['requirement']->programDomain;

        foreach ([
            'pkpa_rotation_operation_rules',
            'pkpa_rotation_runs',
            'pkpa_rotation_status_histories',
            'pkpa_rotation_supervisor_histories',
            'pkpa_attendance_records',
            'pkpa_attendance_correction_requests',
            'pkpa_logbook_entries',
            'pkpa_logbook_attachments',
            'pkpa_logbook_reviews',
            'pkpa_rotation_progress_snapshots',
            'pkpa_rotation_publication_sync_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} harus tersedia.");
        }

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->post("/management/pkpa-publications/{$fixture['publication']->id}/rotation-runs")
            ->assertForbidden();

        app(PkpaRotationOperationRuleService::class)->save($programDomain, [
            'logbook_frequency' => 'flexible',
            'minimum_logbook_entries' => 1,
            'minimum_approved_attendance_days' => 1,
        ], $this->admin);
        $this->assertSame(1, PkpaRotationOperationRule::where('pkpa_program_domain_id', $programDomain->id)->where('is_active', true)->count());

        $stats = app(PkpaRotationRunService::class)->createFromPublication($fixture['publication'], $this->admin);
        $this->assertSame(['created' => 1, 'existing' => 0], $stats);
        $this->assertSame(['created' => 0, 'existing' => 1], app(PkpaRotationRunService::class)->createFromPublication($fixture['publication'], $this->admin));

        $run = PkpaRotationRun::with('supervisorHistories')->firstOrFail();
        $this->assertSame('ready', $run->status);
        $this->assertSame(2, $run->supervisorHistories()->where('status', 'active')->count());
        $this->assertSame(0, KpAssignment::count(), 'Tahap 06 tidak boleh menulis assignment legacy KP.');

        $this->expectException(ValidationException::class);
        app(PkpaRotationRunService::class)->activate($run, $this->admin);
    }

    public function test_attendance_logbook_private_attachment_progress_and_completion_contract(): void
    {
        Storage::fake('local');
        $run = $this->activatedRun();
        $attendanceService = app(PkpaAttendanceService::class);
        $logbookService = app(PkpaLogbookService::class);

        $attendance = $attendanceService->save($run, [
            'attendance_date' => '2026-07-17',
            'attendance_type' => 'present',
            'check_in_time' => '08:00',
            'check_out_time' => '16:00',
            'student_notes' => 'Praktik pelayanan farmasi.',
        ], $this->student);
        $attendanceService->submit($attendance, $this->student);

        $this->expectException(ValidationException::class);
        $attendanceService->review($attendance->fresh(), 'approved', null, $this->internalSupervisor);
    }

    public function test_full_operational_flow_review_sync_and_private_attachment_authorization(): void
    {
        Storage::fake('local');
        $run = $this->activatedRun();
        $attendanceService = app(PkpaAttendanceService::class);
        $logbookService = app(PkpaLogbookService::class);

        $attendance = $attendanceService->save($run, [
            'attendance_date' => '2026-07-17',
            'attendance_type' => 'present',
            'check_in_time' => '08:00',
            'check_out_time' => '16:00',
        ], $this->student);
        $attendanceService->submit($attendance, $this->student);
        $attendanceService->review($attendance->fresh(), 'approved', null, $this->fieldSupervisor);

        $logbook = $logbookService->save($run, [
            'entry_date' => '2026-07-17',
            'title' => 'Pelayanan resep',
            'activity_summary' => 'Mempelajari alur skrining resep dan pelayanan obat.',
            'learning_outcomes' => 'Memahami validasi resep dan komunikasi pasien.',
            'reflection' => 'Perlu lebih teliti saat membaca aturan pakai.',
            'practice_minutes' => 480,
        ], $this->student);
        $attachment = $logbookService->storeAttachment($logbook, UploadedFile::fake()->image('bukti.png'), $this->student);
        $logbookService->submit($logbook, $this->student);
        $logbookService->fieldReview($logbook->fresh(), 'approved', 'Baik.', $this->fieldSupervisor);
        $logbookService->internalReview($logbook->fresh(), 'Monitoring sudah dibaca.', $this->internalSupervisor);

        $this->expectException(ValidationException::class);
        $logbookService->downloadResponse($attachment, $this->otherSupervisor);
    }

    public function test_progress_completion_and_publication_sync_review_rules(): void
    {
        $fixture = $this->publishedFixture();
        app(PkpaRotationOperationRuleService::class)->save($fixture['requirement']->programDomain, [
            'attendance_required' => false,
            'logbook_required' => false,
            'logbook_frequency' => 'flexible',
            'minimum_logbook_entries' => 0,
            'minimum_approved_attendance_days' => 0,
        ], $this->admin);
        app(PkpaRotationRunService::class)->createFromPublication($fixture['publication'], $this->admin);
        $run = PkpaRotationRun::firstOrFail();
        app(PkpaRotationRunService::class)->activate($run, $this->koordinator);

        [$ready] = app(PkpaRotationProgressService::class)->isReadyForOperationalCompletion($run->fresh());
        $this->assertTrue($ready);
        app(PkpaRotationRunService::class)->markAwaitingReview($run->fresh(), $this->koordinator);
        app(PkpaRotationRunService::class)->operationalComplete($run->fresh(), 'Checklist nihil kewajiban sudah lengkap.', $this->koordinator);
        $this->assertSame('operational_complete', $run->fresh()->status);
        $this->assertNotSame('completed', $fixture['requirement']->fresh()->status, 'Operational complete tidak boleh otomatis menyelesaikan requirement akademik.');
        $this->assertDatabaseHas('pkpa_rotation_progress_snapshots', ['pkpa_rotation_run_id' => $run->id]);

        $newPublication = $this->revisionPublication($fixture);
        $stats = app(PkpaRotationPublicationSyncService::class)->sync($newPublication, $this->koordinator);
        $this->assertSame(1, $stats['review_required']);
        $this->assertSame('review_required', $run->fresh()->publication_sync_status);
    }

    private function activatedRun(): PkpaRotationRun
    {
        $fixture = $this->publishedFixture();
        app(PkpaRotationOperationRuleService::class)->save($fixture['requirement']->programDomain, [
            'logbook_frequency' => 'flexible',
            'minimum_logbook_entries' => 1,
            'minimum_approved_attendance_days' => 1,
        ], $this->admin);
        app(PkpaRotationRunService::class)->createFromPublication($fixture['publication'], $this->admin);
        $run = PkpaRotationRun::firstOrFail();
        app(PkpaRotationRunService::class)->activate($run, $this->koordinator);

        return $run->fresh();
    }

    private function publishedFixture(): array
    {
        $program = app(PkpaProgramService::class)->create([
            'code' => 'PKPA-06-'.str()->random(4),
            'name' => 'Program Tahap 06',
            'academic_year' => '2026/2027',
            'cohort_name' => 'Angkatan 2026',
            'start_date' => '2026-07-13',
            'end_date' => '2026-07-31',
        ], $this->admin);
        $domain = PkpaPracticeDomain::where('code', 'APT')->firstOrFail();
        $programDomain = $program->domains()->where('practice_domain_id', $domain->id)->firstOrFail();
        $site = PkpaPracticeSite::create([
            'practice_domain_id' => $domain->id,
            'code' => 'APT-06-'.str()->random(4),
            'name' => 'Apotek Mitra Tahap 06',
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
            'status' => 'active',
            'is_active' => true,
        ]);
        $enrollment = PkpaEnrollment::create([
            'pkpa_program_id' => $program->id,
            'core_user_id' => $this->student->core_user_id,
            'student_number' => '260006',
            'student_name_snapshot' => 'Mahasiswa Tahap 06',
            'core_account_status_snapshot' => 'active',
            'status' => 'active',
        ]);
        app(PkpaEnrollmentRequirementService::class)->ensureRequirements($enrollment, $this->admin);
        $requirement = $enrollment->requirements()->where('practice_domain_id', $domain->id)->firstOrFail();
        $plan = PkpaPlacementPlan::create([
            'pkpa_program_id' => $program->id,
            'code' => 'PLAN-06-'.str()->random(4),
            'name' => 'Rencana Tahap 06',
            'version_number' => 1,
            'status' => 'locked',
            'is_current' => true,
            'current_key' => 'PROGRAM:'.$program->id,
            'validation_status' => 'valid',
        ]);
        $publication = PkpaPlacementPublication::create([
            'pkpa_program_id' => $program->id,
            'pkpa_placement_plan_id' => $plan->id,
            'publication_number' => 1,
            'revision_number' => 0,
            'code' => 'PUB-06-'.str()->random(4),
            'title' => 'Publikasi Tahap 06',
            'status' => 'published',
            'is_current' => true,
            'current_key' => 'PROGRAM:'.$program->id,
            'published_at' => now(),
        ]);
        $assignment = PkpaPublishedAssignment::create([
            'pkpa_placement_publication_id' => $publication->id,
            'pkpa_enrollment_id' => $enrollment->id,
            'pkpa_enrollment_requirement_id' => $requirement->id,
            'practice_domain_id' => $domain->id,
            'practice_site_id' => $site->id,
            'program_site_id' => $programSite->id,
            'student_core_user_id' => $this->student->core_user_id,
            'student_number_snapshot' => '260006',
            'student_name_snapshot' => 'Mahasiswa Tahap 06',
            'practice_domain_name_snapshot' => $domain->name,
            'practice_site_name_snapshot' => $site->name,
            'start_date' => '2026-07-13',
            'end_date' => '2026-07-17',
            'status' => 'scheduled',
        ]);
        foreach ([['internal', $this->internalSupervisor], ['field', $this->fieldSupervisor]] as [$type, $user]) {
            PkpaPublishedAssignmentSupervisor::create([
                'pkpa_published_assignment_id' => $assignment->id,
                'supervisor_type' => $type,
                'core_user_id' => $user->core_user_id,
                'name_snapshot' => $user->name,
                'role_snapshot' => $type === 'field' ? 'Pembimbing Lapangan' : 'Pembimbing Dalam',
                'is_primary' => true,
                'status' => 'assigned',
            ]);
        }

        return compact('program', 'programDomain', 'site', 'enrollment', 'requirement', 'plan', 'publication', 'assignment');
    }

    private function revisionPublication(array $fixture): PkpaPlacementPublication
    {
        $fixture['publication']->update(['status' => 'superseded', 'is_current' => false, 'current_key' => null]);
        $newSite = PkpaPracticeSite::create([
            'practice_domain_id' => $fixture['site']->practice_domain_id,
            'code' => 'APT-06-REV',
            'name' => 'Apotek Revisi Tahap 06',
            'city' => 'Karawang',
            'province' => 'Jawa Barat',
            'cooperation_start_date' => '2026-01-01',
            'cooperation_end_date' => '2026-12-31',
            'status' => 'active',
            'is_active' => true,
        ]);
        $publication = PkpaPlacementPublication::create([
            'pkpa_program_id' => $fixture['program']->id,
            'pkpa_placement_plan_id' => $fixture['plan']->id,
            'publication_number' => 2,
            'revision_number' => 1,
            'code' => 'PUB-06-REV',
            'title' => 'Publikasi Revisi Tahap 06',
            'status' => 'published',
            'is_current' => true,
            'current_key' => 'PROGRAM:'.$fixture['program']->id,
            'published_at' => now(),
        ]);
        $assignment = PkpaPublishedAssignment::create([
            'pkpa_placement_publication_id' => $publication->id,
            'pkpa_enrollment_id' => $fixture['enrollment']->id,
            'pkpa_enrollment_requirement_id' => $fixture['requirement']->id,
            'practice_domain_id' => $fixture['site']->practice_domain_id,
            'practice_site_id' => $newSite->id,
            'student_core_user_id' => $this->student->core_user_id,
            'student_number_snapshot' => '260006',
            'student_name_snapshot' => 'Mahasiswa Tahap 06',
            'practice_domain_name_snapshot' => 'Apotek',
            'practice_site_name_snapshot' => $newSite->name,
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-24',
            'status' => 'scheduled',
        ]);
        foreach ([['internal', $this->internalSupervisor], ['field', $this->fieldSupervisor]] as [$type, $user]) {
            PkpaPublishedAssignmentSupervisor::create([
                'pkpa_published_assignment_id' => $assignment->id,
                'supervisor_type' => $type,
                'core_user_id' => $user->core_user_id,
                'name_snapshot' => $user->name,
                'role_snapshot' => $type === 'field' ? 'Pembimbing Lapangan' : 'Pembimbing Dalam',
                'is_primary' => true,
                'status' => 'assigned',
            ]);
        }

        return $publication;
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
}
