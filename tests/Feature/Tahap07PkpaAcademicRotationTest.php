<?php

namespace Tests\Feature;

use App\Models\PkpaAttendanceRecord;
use App\Models\PkpaEnrollment;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgramSite;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaPublishedAssignmentSupervisor;
use App\Models\PkpaRotationRun;
use App\Models\PkpaSpecialTaskSubmission;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpaAcademicReadinessService;
use App\Services\PkpaCompetencyManagementService;
use App\Services\PkpaEnrollmentRequirementService;
use App\Services\PkpaProgramService;
use App\Services\PkpaRotationCompetencyService;
use App\Services\PkpaRotationGuidanceService;
use App\Services\PkpaRotationOperationRuleService;
use App\Services\PkpaRotationReportService;
use App\Services\PkpaRotationRunService;
use App\Services\PkpaSpecialTaskService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Tahap07PkpaAcademicRotationTest extends TestCase
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
        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class]);
        $this->admin = $this->makeUser('admin07@test.local', ['admin'], 'CORE-ADMIN-07');
        $this->koordinator = $this->makeUser('koor07@test.local', ['koordinator_kp'], 'CORE-KOOR-07');
        $this->student = $this->makeUser('student07@test.local', ['mahasiswa'], 'CORE-STUDENT-07');
        $this->otherStudent = $this->makeUser('other-student07@test.local', ['mahasiswa'], 'CORE-OTHER-STUDENT-07');
        $this->fieldSupervisor = $this->makeUser('field07@test.local', ['pembimbing_lapangan'], 'CORE-FIELD-07');
        $this->internalSupervisor = $this->makeUser('internal07@test.local', ['pembimbing_dalam'], 'CORE-INTERNAL-07');
        $this->otherSupervisor = $this->makeUser('other-supervisor07@test.local', ['pembimbing_dalam'], 'CORE-OTHER-INTERNAL-07');
    }

    public function test_schema_builder_checklist_snapshot_and_competency_reviews(): void
    {
        $fixture = $this->runtimeFixture();
        $programDomain = $fixture['requirement']->programDomain;

        foreach (['pkpa_competency_sets', 'pkpa_rotation_competency_records', 'pkpa_rotation_competency_evidences', 'pkpa_special_task_templates', 'pkpa_rotation_reports', 'pkpa_rotation_academic_readiness_reviews'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} harus tersedia.");
        }

        $management = app(PkpaCompetencyManagementService::class);
        $set = $management->createSet($programDomain, ['code' => 'APT-SET-07', 'name' => 'Set Kompetensi Apotek'], $this->admin);
        $management->saveItem($set, [
            'code' => 'K-001',
            'title' => 'Skrining resep',
            'evidence_required' => true,
            'minimum_evidence_count' => 1,
            'is_required' => true,
        ], $this->admin);
        $management->activate($set, $this->koordinator);

        $rotationCompetency = app(PkpaRotationCompetencyService::class);
        $this->assertSame(1, $rotationCompetency->ensureChecklist($fixture['run'], $this->admin));
        $this->assertSame(0, $rotationCompetency->ensureChecklist($fixture['run'], $this->admin), 'Checklist harus idempotent.');
        $record = $fixture['run']->competencyRecords()->firstOrFail();
        $management->saveItem($set, [
            'code' => 'K-001',
            'title' => 'Judul sudah berubah',
            'evidence_required' => true,
            'minimum_evidence_count' => 1,
            'is_required' => true,
        ], $this->admin);
        $this->assertSame('Skrining resep', $record->fresh()->competency_title_snapshot, 'Snapshot rotasi tidak ikut berubah saat master diedit.');

        $this->expectException(ValidationException::class);
        $rotationCompetency->submit($record->fresh(), $this->student);
    }

    public function test_competency_evidence_task_report_guidance_and_readiness_flow(): void
    {
        Storage::fake('local');
        $fixture = $this->runtimeFixture();
        $programDomain = $fixture['requirement']->programDomain;
        $this->prepareAcademicMaster($programDomain);
        $run = $fixture['run'];

        app(PkpaRotationCompetencyService::class)->ensureChecklist($run, $this->admin);
        $record = $run->competencyRecords()->firstOrFail();
        $evidence = app(PkpaRotationCompetencyService::class)->addEvidence($record, ['evidence_type' => 'file', 'title' => 'Bukti skrining'], UploadedFile::fake()->image('bukti.png'), $this->student);
        $this->assertSame('local', $evidence->disk);
        app(PkpaRotationCompetencyService::class)->submit($record->fresh(), $this->student);

        $this->expectException(ValidationException::class);
        app(PkpaRotationCompetencyService::class)->fieldReview($record->fresh(), 'verified', null, $this->otherSupervisor);
    }

    public function test_full_academic_flow_ready_for_assessment_without_grades_or_requirement_completion(): void
    {
        Storage::fake('local');
        $fixture = $this->runtimeFixture();
        $programDomain = $fixture['requirement']->programDomain;
        $this->prepareAcademicMaster($programDomain);
        $run = $fixture['run'];
        $run->update(['status' => 'operational_complete', 'operational_completed_at' => now()]);

        $competencies = app(PkpaRotationCompetencyService::class);
        $competencies->ensureChecklist($run, $this->admin);
        $record = $run->competencyRecords()->firstOrFail();
        $competencies->addEvidence($record, ['evidence_type' => 'text_note', 'title' => 'Catatan', 'description' => 'Sudah ditunjukkan'], null, $this->student);
        $competencies->submit($record->fresh(), $this->student);
        $competencies->fieldReview($record->fresh(), 'verified', 'Kompeten secara observasi.', $this->fieldSupervisor);
        $competencies->internalComment($record->fresh(), 'Sudah dimonitor.', $this->internalSupervisor);

        $tasks = app(PkpaSpecialTaskService::class);
        $this->assertSame(1, $tasks->assignFromTemplates($run, $this->admin));
        $task = $run->specialTasks()->firstOrFail();
        $submission = $tasks->saveSubmission($task, ['title' => 'Kasus pelayanan', 'submit' => true], UploadedFile::fake()->create('tugas.pdf', 20, 'application/pdf'), $this->student);
        $tasks->review($submission, 'approved', 'Disetujui.', $this->internalSupervisor);

        $reports = app(PkpaRotationReportService::class);
        $report = $reports->reportForRun($run, $this->student);
        $version = $reports->uploadVersion($report, UploadedFile::fake()->create('laporan.pdf', 30, 'application/pdf'), ['change_summary' => 'Versi awal'], $this->student);
        $reports->submit($report->fresh(), $this->student);
        $reports->fieldConfirm($report->fresh(), 'Sesuai praktik.', $this->fieldSupervisor);
        $reports->internalReview($report->fresh(), 'approved', 'Laporan disetujui untuk siap dinilai.', $this->internalSupervisor);

        $guidance = app(PkpaRotationGuidanceService::class)->record($run, [
            'topic' => 'Review laporan rotasi',
            'guidance_type' => 'document_review',
            'guidance_date' => '2026-07-17',
            'supervisor_notes' => 'Perbaiki format sitasi.',
            'supervisor_type' => 'internal',
        ], $this->internalSupervisor);
        app(PkpaRotationGuidanceService::class)->acknowledge($guidance, $this->student);

        $readiness = app(PkpaAcademicReadinessService::class)->review($run->fresh(), $this->koordinator);
        $this->assertSame('ready_for_assessment', $readiness->status);
        $this->assertDatabaseHas('pkpa_notification_deliveries', [
            'event_type' => 'pkpa_academic_readiness_ready_for_assessment',
            'entity_type' => $readiness::class,
            'entity_id' => $readiness->id,
            'channel' => 'database',
            'status' => 'pending',
        ]);
        $this->assertNotSame('completed', $fixture['requirement']->fresh()->status);
        $this->assertDatabaseMissing('pkpa_rotation_academic_readiness_reviews', ['snapshot' => 'score']);

        $this->expectException(ValidationException::class);
        $reports->downloadVersion($version, $this->otherStudent);
    }

    public function test_routes_are_role_protected_and_export_available(): void
    {
        $fixture = $this->runtimeFixture();

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/pkpa-academics')
            ->assertForbidden();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-academics')
            ->assertOk()
            ->assertSee('Akademik Rotasi PKPA');
        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get("/mahasiswa/akademik-rotasi/{$fixture['run']->id}")
            ->assertOk()
            ->assertSee('Detail Akademik Rotasi');
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-academics/export')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    private function prepareAcademicMaster($programDomain): void
    {
        $management = app(PkpaCompetencyManagementService::class);
        $set = $management->createSet($programDomain, ['code' => 'APT-SET-READY', 'name' => 'Set Siap Nilai'], $this->admin);
        $management->saveItem($set, ['code' => 'K-READY', 'title' => 'Kompetensi Siap Nilai', 'evidence_required' => true, 'minimum_evidence_count' => 1, 'is_required' => true], $this->admin);
        $management->activate($set, $this->koordinator);
        app(PkpaSpecialTaskService::class)->saveTemplate($programDomain, [
            'code' => 'TASK-READY',
            'title' => 'Tugas Kasus',
            'submission_type' => 'document',
            'status' => 'active',
            'is_required' => true,
            'internal_supervisor_review_required' => true,
        ], $this->admin);
        app(PkpaRotationReportService::class)->saveTemplate($programDomain, [
            'code' => 'REPORT-READY',
            'name' => 'Laporan Rotasi',
            'status' => 'active',
            'is_current' => true,
            'field_supervisor_confirmation_required' => true,
            'internal_supervisor_approval_required' => true,
            'allowed_file_types' => ['application/pdf'],
            'maximum_file_size_kb' => 5120,
        ], $this->admin);
    }

    private function runtimeFixture(): array
    {
        $program = app(PkpaProgramService::class)->create([
            'code' => 'PKPA-07-'.str()->random(4),
            'name' => 'Program Tahap 07',
            'academic_year' => '2026/2027',
            'cohort_name' => 'Angkatan 2026',
            'start_date' => '2026-07-13',
            'end_date' => '2026-07-31',
        ], $this->admin);
        $domain = PkpaPracticeDomain::where('code', 'APT')->firstOrFail();
        $programDomain = $program->domains()->where('practice_domain_id', $domain->id)->firstOrFail();
        $site = PkpaPracticeSite::create(['practice_domain_id' => $domain->id, 'code' => 'APT-07-'.str()->random(4), 'name' => 'Apotek Akademik Tahap 07', 'city' => 'Karawang', 'province' => 'Jawa Barat', 'cooperation_start_date' => '2026-01-01', 'cooperation_end_date' => '2026-12-31', 'status' => 'active', 'is_active' => true]);
        $programSite = PkpaProgramSite::create(['pkpa_program_id' => $program->id, 'practice_site_id' => $site->id, 'pkpa_program_domain_id' => $programDomain->id, 'practice_domain_id' => $domain->id, 'status' => 'active', 'is_active' => true]);
        $enrollment = PkpaEnrollment::create(['pkpa_program_id' => $program->id, 'core_user_id' => $this->student->core_user_id, 'student_number' => '270007', 'student_name_snapshot' => 'Mahasiswa Tahap 07', 'core_account_status_snapshot' => 'active', 'status' => 'active']);
        app(PkpaEnrollmentRequirementService::class)->ensureRequirements($enrollment, $this->admin);
        $requirement = $enrollment->requirements()->where('practice_domain_id', $domain->id)->firstOrFail();
        $plan = PkpaPlacementPlan::create(['pkpa_program_id' => $program->id, 'code' => 'PLAN-07-'.str()->random(4), 'name' => 'Plan Tahap 07', 'version_number' => 1, 'status' => 'locked', 'is_current' => true, 'current_key' => 'PROGRAM:'.$program->id, 'validation_status' => 'valid']);
        $publication = PkpaPlacementPublication::create(['pkpa_program_id' => $program->id, 'pkpa_placement_plan_id' => $plan->id, 'publication_number' => 1, 'revision_number' => 0, 'code' => 'PUB-07-'.str()->random(4), 'title' => 'Publikasi Tahap 07', 'status' => 'published', 'is_current' => true, 'current_key' => 'PROGRAM:'.$program->id, 'published_at' => now()]);
        $assignment = PkpaPublishedAssignment::create(['pkpa_placement_publication_id' => $publication->id, 'pkpa_enrollment_id' => $enrollment->id, 'pkpa_enrollment_requirement_id' => $requirement->id, 'practice_domain_id' => $domain->id, 'practice_site_id' => $site->id, 'program_site_id' => $programSite->id, 'student_core_user_id' => $this->student->core_user_id, 'student_number_snapshot' => '270007', 'student_name_snapshot' => 'Mahasiswa Tahap 07', 'practice_domain_name_snapshot' => $domain->name, 'practice_site_name_snapshot' => $site->name, 'start_date' => '2026-07-13', 'end_date' => '2026-07-17', 'status' => 'scheduled']);
        foreach ([['internal', $this->internalSupervisor], ['field', $this->fieldSupervisor]] as [$type, $user]) {
            PkpaPublishedAssignmentSupervisor::create(['pkpa_published_assignment_id' => $assignment->id, 'supervisor_type' => $type, 'core_user_id' => $user->core_user_id, 'name_snapshot' => $user->name, 'role_snapshot' => $type, 'is_primary' => true, 'status' => 'assigned']);
        }
        app(PkpaRotationOperationRuleService::class)->save($requirement->programDomain, ['attendance_required' => false, 'logbook_required' => false], $this->admin);
        app(PkpaRotationRunService::class)->createFromPublication($publication, $this->admin);
        $run = PkpaRotationRun::firstOrFail();
        app(PkpaRotationRunService::class)->activate($run, $this->koordinator);
        PkpaAttendanceRecord::create(['pkpa_rotation_run_id' => $run->id, 'attendance_date' => '2026-07-17', 'attendance_type' => 'present', 'submission_status' => 'approved', 'active_key' => 'RUN:'.$run->id.':2026-07-17']);

        return compact('program', 'programDomain', 'site', 'enrollment', 'requirement', 'plan', 'publication', 'assignment', 'run');
    }

    private function makeUser(string $email, array $roles, string $coreUserId): User
    {
        $user = User::factory()->create(['name' => str($email)->before('@')->headline(), 'email' => $email, 'password' => Hash::make('password'), 'status' => 'active', 'profile_completed' => true, 'core_user_id' => $coreUserId]);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user->load('roles');
    }
}
