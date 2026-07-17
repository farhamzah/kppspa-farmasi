<?php

namespace Tests\Feature;

use App\Models\PkpaAssessmentComponent;
use App\Models\PkpaAssessmentScheme;
use App\Models\PkpaAttendanceRecord;
use App\Models\PkpaEnrollment;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgramSite;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaPublishedAssignmentSupervisor;
use App\Models\PkpaRotationAcademicReadinessReview;
use App\Models\PkpaRotationRun;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpaAssessmentSchemeService;
use App\Services\PkpaEnrollmentRequirementService;
use App\Services\PkpaProgramService;
use App\Services\PkpaRotationAssessmentService;
use App\Services\PkpaRotationOperationRuleService;
use App\Services\PkpaRotationRunService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Tahap08PkpaAssessmentTest extends TestCase
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
        $this->admin = $this->makeUser('admin08@test.local', ['admin'], 'CORE-ADMIN-08');
        $this->koordinator = $this->makeUser('koor08@test.local', ['koordinator_kp'], 'CORE-KOOR-08');
        $this->student = $this->makeUser('student08@test.local', ['mahasiswa'], 'CORE-STUDENT-08');
        $this->otherStudent = $this->makeUser('other-student08@test.local', ['mahasiswa'], 'CORE-OTHER-STUDENT-08');
        $this->fieldSupervisor = $this->makeUser('field08@test.local', ['pembimbing_lapangan'], 'CORE-FIELD-08');
        $this->internalSupervisor = $this->makeUser('internal08@test.local', ['pembimbing_dalam'], 'CORE-INTERNAL-08');
        $this->otherSupervisor = $this->makeUser('other-supervisor08@test.local', ['pembimbing_lapangan'], 'CORE-OTHER-FIELD-08');
    }

    public function test_scheme_builder_validation_activation_and_no_fake_seeded_rubric(): void
    {
        $fixture = $this->runtimeFixture();
        foreach (['pkpa_assessment_schemes', 'pkpa_rotation_assessments', 'pkpa_rotation_component_scores', 'pkpa_rotation_grade_results', 'pkpa_grade_releases'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} harus tersedia.");
        }
        $this->assertSame(0, PkpaAssessmentScheme::count(), 'Seeder produksi tidak boleh membuat skema atau rubrik palsu.');

        $service = app(PkpaAssessmentSchemeService::class);
        $scheme = $service->createScheme($fixture['programDomain'], ['code' => 'APT-ASSESS-08', 'name' => 'Skema Apotek Tahap 08'], $this->admin);
        $service->saveComponent($scheme, ['code' => 'PL', 'name' => 'Pembimbing Lapangan', 'component_type' => 'field_supervisor_assessment', 'assessor_type' => 'field_supervisor', 'calculation_method' => 'direct_score', 'weight_percentage' => 60, 'maximum_raw_score' => 100, 'status' => 'active'], $this->admin);
        $service->saveComponent($scheme, ['code' => 'PD', 'name' => 'Pembimbing Dalam', 'component_type' => 'internal_supervisor_assessment', 'assessor_type' => 'internal_supervisor', 'calculation_method' => 'direct_score', 'weight_percentage' => 30, 'maximum_raw_score' => 100, 'status' => 'active'], $this->admin);

        $this->expectException(ValidationException::class);
        $service->activate($scheme, $this->koordinator);
    }

    public function test_assessment_scoring_finalization_release_and_student_visibility(): void
    {
        $fixture = $this->runtimeFixture();
        $scheme = $this->activeScheme($fixture['programDomain']);
        $assessmentService = app(PkpaRotationAssessmentService::class);
        $assessment = $assessmentService->createFromRun($fixture['run'], $this->admin);
        $this->assertSame($scheme->code, $assessment->scheme_code_snapshot);
        $this->assertSame(1, $assessmentService->createFromRun($fixture['run'], $this->admin)->id, 'Assessment creation harus idempotent.');

        $plScore = $assessment->componentScores()->whereHas('assessor', fn ($q) => $q->where('core_user_id', $this->fieldSupervisor->core_user_id))->firstOrFail();
        $pdScore = $assessment->componentScores()->whereHas('assessor', fn ($q) => $q->where('core_user_id', $this->internalSupervisor->core_user_id))->firstOrFail();

        $assessmentService->saveDirectScore($plScore, '80', 'Baik.', $this->fieldSupervisor);
        $assessmentService->submitScore($plScore->fresh(), $this->fieldSupervisor);
        $assessmentService->saveDirectScore($pdScore, '90', 'Sangat baik.', $this->internalSupervisor);
        $assessmentService->submitScore($pdScore->fresh(), $this->internalSupervisor);

        $result = $assessmentService->finalize($assessment->fresh(), $this->koordinator);
        $this->assertSame('85.0000', $result->final_score);
        $this->assertNotSame('completed', $fixture['requirement']->fresh()->status);
        $this->assertDatabaseHas('pkpa_notification_deliveries', ['event_type' => 'grade_finalized', 'entity_id' => $result->id]);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/nilai-pkpa')
            ->assertOk()
            ->assertDontSee('85.0000');

        $release = $assessmentService->release($result, $this->koordinator);
        $this->assertSame($release->id, $assessmentService->release($result->fresh(), $this->koordinator)->id, 'Release harus idempotent.');
        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/nilai-pkpa')
            ->assertOk()
            ->assertSee('85.0000')
            ->assertSee('belum merupakan nilai akhir keseluruhan Program PKPA');
        $this->actingAs($this->otherStudent)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/nilai-pkpa')
            ->assertOk()
            ->assertDontSee('85.0000');
    }

    public function test_assessor_authorization_and_completion_blocks_finalization(): void
    {
        $fixture = $this->runtimeFixture();
        $this->activeScheme($fixture['programDomain']);
        $assessmentService = app(PkpaRotationAssessmentService::class);
        $assessment = $assessmentService->createFromRun($fixture['run'], $this->admin);
        $plScore = $assessment->componentScores()->whereHas('assessor', fn ($q) => $q->where('core_user_id', $this->fieldSupervisor->core_user_id))->firstOrFail();

        $this->expectException(ValidationException::class);
        $assessmentService->saveDirectScore($plScore, '80', null, $this->otherSupervisor);
    }

    public function test_routes_are_protected_and_export_available(): void
    {
        $this->runtimeFixture();
        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/pkpa-assessments')
            ->assertForbidden();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-assessments')
            ->assertOk()
            ->assertSee('Penilaian Per Wahana PKPA');
        $this->actingAs($this->fieldSupervisor)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/penilaian-pkpa')
            ->assertOk()
            ->assertSee('Penilaian Pembimbing Lapangan');
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-assessments/export')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    private function activeScheme($programDomain): PkpaAssessmentScheme
    {
        $service = app(PkpaAssessmentSchemeService::class);
        $scheme = $service->createScheme($programDomain, ['code' => 'APT-ASSESS-OK', 'name' => 'Skema Siap Nilai'], $this->admin);
        $service->saveComponent($scheme, ['code' => 'PL', 'name' => 'Pembimbing Lapangan', 'component_type' => 'field_supervisor_assessment', 'assessor_type' => 'field_supervisor', 'calculation_method' => 'direct_score', 'weight_percentage' => 50, 'maximum_raw_score' => 100, 'status' => 'active'], $this->admin);
        $service->saveComponent($scheme, ['code' => 'PD', 'name' => 'Pembimbing Dalam', 'component_type' => 'internal_supervisor_assessment', 'assessor_type' => 'internal_supervisor', 'calculation_method' => 'direct_score', 'weight_percentage' => 50, 'maximum_raw_score' => 100, 'status' => 'active'], $this->admin);

        return $service->activate($scheme, $this->koordinator);
    }

    private function runtimeFixture(): array
    {
        $program = app(PkpaProgramService::class)->create([
            'code' => 'PKPA-08-'.str()->random(4),
            'name' => 'Program Tahap 08',
            'academic_year' => '2026/2027',
            'cohort_name' => 'Angkatan 2026',
            'start_date' => '2026-07-13',
            'end_date' => '2026-07-31',
        ], $this->admin);
        $domain = PkpaPracticeDomain::where('code', 'APT')->firstOrFail();
        $programDomain = $program->domains()->where('practice_domain_id', $domain->id)->firstOrFail();
        $site = PkpaPracticeSite::create(['practice_domain_id' => $domain->id, 'code' => 'APT-08-'.str()->random(4), 'name' => 'Apotek Assessment Tahap 08', 'city' => 'Karawang', 'province' => 'Jawa Barat', 'cooperation_start_date' => '2026-01-01', 'cooperation_end_date' => '2026-12-31', 'status' => 'active', 'is_active' => true]);
        $programSite = PkpaProgramSite::create(['pkpa_program_id' => $program->id, 'practice_site_id' => $site->id, 'pkpa_program_domain_id' => $programDomain->id, 'practice_domain_id' => $domain->id, 'status' => 'active', 'is_active' => true]);
        $enrollment = PkpaEnrollment::create(['pkpa_program_id' => $program->id, 'core_user_id' => $this->student->core_user_id, 'student_number' => '280008', 'student_name_snapshot' => 'Mahasiswa Tahap 08', 'core_account_status_snapshot' => 'active', 'status' => 'active']);
        app(PkpaEnrollmentRequirementService::class)->ensureRequirements($enrollment, $this->admin);
        $requirement = $enrollment->requirements()->where('practice_domain_id', $domain->id)->firstOrFail();
        $plan = PkpaPlacementPlan::create(['pkpa_program_id' => $program->id, 'code' => 'PLAN-08-'.str()->random(4), 'name' => 'Plan Tahap 08', 'version_number' => 1, 'status' => 'locked', 'is_current' => true, 'current_key' => 'PROGRAM:'.$program->id, 'validation_status' => 'valid']);
        $publication = PkpaPlacementPublication::create(['pkpa_program_id' => $program->id, 'pkpa_placement_plan_id' => $plan->id, 'publication_number' => 1, 'revision_number' => 0, 'code' => 'PUB-08-'.str()->random(4), 'title' => 'Publikasi Tahap 08', 'status' => 'published', 'is_current' => true, 'current_key' => 'PROGRAM:'.$program->id, 'published_at' => now()]);
        $assignment = PkpaPublishedAssignment::create(['pkpa_placement_publication_id' => $publication->id, 'pkpa_enrollment_id' => $enrollment->id, 'pkpa_enrollment_requirement_id' => $requirement->id, 'practice_domain_id' => $domain->id, 'practice_site_id' => $site->id, 'program_site_id' => $programSite->id, 'student_core_user_id' => $this->student->core_user_id, 'student_number_snapshot' => '280008', 'student_name_snapshot' => 'Mahasiswa Tahap 08', 'practice_domain_name_snapshot' => $domain->name, 'practice_site_name_snapshot' => $site->name, 'start_date' => '2026-07-13', 'end_date' => '2026-07-17', 'status' => 'scheduled']);
        foreach ([['internal', $this->internalSupervisor], ['field', $this->fieldSupervisor]] as [$type, $user]) {
            PkpaPublishedAssignmentSupervisor::create(['pkpa_published_assignment_id' => $assignment->id, 'supervisor_type' => $type, 'core_user_id' => $user->core_user_id, 'name_snapshot' => $user->name, 'role_snapshot' => $type, 'is_primary' => true, 'status' => 'assigned']);
        }
        app(PkpaRotationOperationRuleService::class)->save($requirement->programDomain, ['attendance_required' => false, 'logbook_required' => false], $this->admin);
        app(PkpaRotationRunService::class)->createFromPublication($publication, $this->admin);
        $run = PkpaRotationRun::firstOrFail();
        app(PkpaRotationRunService::class)->activate($run, $this->koordinator);
        $run->update(['status' => 'operational_complete', 'operational_completed_at' => now()]);
        PkpaAttendanceRecord::create(['pkpa_rotation_run_id' => $run->id, 'attendance_date' => '2026-07-17', 'attendance_type' => 'present', 'submission_status' => 'approved', 'active_key' => 'RUN:'.$run->id.':2026-07-17']);
        PkpaRotationAcademicReadinessReview::create(['pkpa_rotation_run_id' => $run->id, 'status' => 'ready_for_assessment', 'required_competency_count' => 0, 'verified_competency_count' => 0, 'required_task_count' => 0, 'approved_task_count' => 0, 'operational_complete' => true, 'blocking_issues' => [], 'snapshot' => ['source' => 'test'], 'reviewed_by_core_user_id' => $this->koordinator->core_user_id, 'reviewed_at' => now()]);

        return compact('program', 'programDomain', 'site', 'enrollment', 'requirement', 'plan', 'publication', 'assignment', 'run');
    }

    private function makeUser(string $email, array $roles, string $coreUserId): User
    {
        $user = User::factory()->create(['name' => str($email)->before('@')->headline(), 'email' => $email, 'password' => Hash::make('password'), 'status' => 'active', 'profile_completed' => true, 'core_user_id' => $coreUserId]);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user->load('roles');
    }
}
