<?php

namespace Tests\Feature;

use App\Models\PkpaAssessmentScheme;
use App\Models\PkpaEnrollment;
use App\Models\PkpaFinalAssessmentScheme;
use App\Models\PkpaFinalGradeRelease;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgramSite;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaRotationAcademicReadinessReview;
use App\Models\PkpaRotationAssessment;
use App\Models\PkpaRotationGradeResult;
use App\Models\PkpaRotationRun;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpaEnrollmentRequirementService;
use App\Services\PkpaFinalAssessmentSchemeService;
use App\Services\PkpaFinalGradeService;
use App\Services\PkpaProgramService;
use App\Services\PkpaRequirementCompletionService;
use Database\Seeders\PkpaMasterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Tahap09PkpaFinalProgramTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $student;
    private User $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PkpaMasterSeeder::class]);
        $this->admin = $this->makeUser('admin09@test.local', ['admin'], 'CORE-ADMIN-09');
        $this->koordinator = $this->makeUser('koor09@test.local', ['koordinator_kp'], 'CORE-KOOR-09');
        $this->student = $this->makeUser('student09@test.local', ['mahasiswa'], 'CORE-STUDENT-09');
        $this->otherStudent = $this->makeUser('other09@test.local', ['mahasiswa'], 'CORE-OTHER-09');
    }

    public function test_schema_completion_final_grade_decision_release_and_visibility(): void
    {
        foreach (['pkpa_final_assessment_schemes', 'pkpa_enrollment_requirement_completions', 'pkpa_final_grade_calculations', 'pkpa_graduation_decisions', 'pkpa_final_grade_releases'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} harus tersedia.");
        }
        $fixture = $this->fixtureWithFinalizedWahanaGrade('88.0000');
        $schemeService = app(PkpaFinalAssessmentSchemeService::class);
        $scheme = $schemeService->create($fixture['program'], ['code' => 'FINAL-09', 'name' => 'Skema Final Tahap 09'], $this->admin);
        $schemeService->saveComponent($scheme, ['code' => 'APT', 'name' => 'Apotek', 'component_type' => 'wahana_grade', 'source_practice_domain_id' => $fixture['domain']->id, 'weight_percentage' => 100, 'maximum_raw_score' => 100, 'status' => 'active'], $this->admin);
        $schemeService->activate($scheme, $this->koordinator);

        $completion = app(PkpaRequirementCompletionService::class)->complete($fixture['requirement'], 'Seluruh prasyarat wahana terpenuhi.', $this->koordinator);
        $this->assertSame('completed', $completion->status);
        $this->assertSame('completed', $fixture['requirement']->fresh()->status);
        $fixture['enrollment']->requirements()->whereKeyNot($fixture['requirement']->id)->update(['status' => 'completed']);

        $calculation = app(PkpaFinalGradeService::class)->calculate($fixture['enrollment']->fresh(), $this->admin);
        $this->assertSame('calculated', $calculation->status);
        $this->assertSame('88.0000', $calculation->final_score);
        $result = app(PkpaFinalGradeService::class)->finalize($calculation, $this->koordinator);
        $this->assertSame('finalized', $result->result_status);

        $decision = app(PkpaFinalGradeService::class)->decide($fixture['enrollment']->fresh(), $result, 'passed', 'Memenuhi seluruh requirement dan nilai akhir.', $this->koordinator);
        $this->assertSame('passed', $decision->decision);
        $release = app(PkpaFinalGradeService::class)->release($result, $decision, $this->koordinator);
        $this->assertSame($release->id, app(PkpaFinalGradeService::class)->release($result->fresh(), $decision, $this->koordinator)->id);

        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/hasil-akhir-pkpa')
            ->assertOk()
            ->assertSee('88.0000')
            ->assertSee('bukan dokumen resmi universitas');
        $this->actingAs($this->otherStudent)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/hasil-akhir-pkpa')
            ->assertOk()
            ->assertDontSee('88.0000');
    }

    public function test_final_scheme_requires_exact_weight_and_routes_are_protected(): void
    {
        $fixture = $this->fixtureWithFinalizedWahanaGrade('80.0000');
        $service = app(PkpaFinalAssessmentSchemeService::class);
        $scheme = $service->create($fixture['program'], ['code' => 'BAD-09', 'name' => 'Bad Weight'], $this->admin);
        $service->saveComponent($scheme, ['code' => 'APT', 'name' => 'Apotek', 'component_type' => 'wahana_grade', 'source_practice_domain_id' => $fixture['domain']->id, 'weight_percentage' => 90, 'maximum_raw_score' => 100, 'status' => 'active'], $this->admin);

        $this->expectException(ValidationException::class);
        $service->activate($scheme, $this->koordinator);
    }

    public function test_management_and_student_routes(): void
    {
        $this->actingAs($this->student)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/pkpa-final-program')
            ->assertForbidden();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-final-program')
            ->assertOk()
            ->assertSee('Penyelesaian Program PKPA');
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/pkpa-final-program/export')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    private function fixtureWithFinalizedWahanaGrade(string $score): array
    {
        $program = app(PkpaProgramService::class)->create(['code' => 'PKPA-09-'.str()->random(5), 'name' => 'Program Tahap 09', 'academic_year' => '2026/2027', 'cohort_name' => 'Angkatan 2026', 'start_date' => '2026-07-13', 'end_date' => '2026-07-31'], $this->admin);
        $domain = PkpaPracticeDomain::where('code', 'APT')->firstOrFail();
        $programDomain = $program->domains()->where('practice_domain_id', $domain->id)->firstOrFail();
        $site = PkpaPracticeSite::create(['practice_domain_id' => $domain->id, 'code' => 'SITE-09-'.str()->random(5), 'name' => 'Apotek Final Tahap 09', 'city' => 'Karawang', 'province' => 'Jawa Barat', 'cooperation_start_date' => '2026-01-01', 'cooperation_end_date' => '2026-12-31', 'status' => 'active', 'is_active' => true]);
        $programSite = PkpaProgramSite::create(['pkpa_program_id' => $program->id, 'practice_site_id' => $site->id, 'pkpa_program_domain_id' => $programDomain->id, 'practice_domain_id' => $domain->id, 'status' => 'active', 'is_active' => true]);
        $enrollment = PkpaEnrollment::create(['pkpa_program_id' => $program->id, 'core_user_id' => $this->student->core_user_id, 'student_number' => '290009', 'student_name_snapshot' => 'Mahasiswa Tahap 09', 'core_account_status_snapshot' => 'active', 'status' => 'active']);
        app(PkpaEnrollmentRequirementService::class)->ensureRequirements($enrollment, $this->admin);
        $requirement = $enrollment->requirements()->where('practice_domain_id', $domain->id)->firstOrFail();
        $plan = PkpaPlacementPlan::create(['pkpa_program_id' => $program->id, 'code' => 'PLAN-09-'.str()->random(5), 'name' => 'Plan Tahap 09', 'version_number' => 1, 'status' => 'locked', 'is_current' => true, 'current_key' => 'PROGRAM:'.$program->id, 'validation_status' => 'valid']);
        $publication = PkpaPlacementPublication::create(['pkpa_program_id' => $program->id, 'pkpa_placement_plan_id' => $plan->id, 'publication_number' => 1, 'revision_number' => 0, 'code' => 'PUB-09-'.str()->random(5), 'title' => 'Publikasi Tahap 09', 'status' => 'published', 'is_current' => true, 'current_key' => 'PROGRAM:'.$program->id, 'published_at' => now()]);
        $assignment = PkpaPublishedAssignment::create(['pkpa_placement_publication_id' => $publication->id, 'pkpa_enrollment_id' => $enrollment->id, 'pkpa_enrollment_requirement_id' => $requirement->id, 'practice_domain_id' => $domain->id, 'practice_site_id' => $site->id, 'program_site_id' => $programSite->id, 'student_core_user_id' => $this->student->core_user_id, 'student_number_snapshot' => '290009', 'student_name_snapshot' => 'Mahasiswa Tahap 09', 'practice_domain_name_snapshot' => $domain->name, 'practice_site_name_snapshot' => $site->name, 'start_date' => '2026-07-13', 'end_date' => '2026-07-17', 'status' => 'scheduled']);
        $run = PkpaRotationRun::create(['pkpa_program_id' => $program->id, 'pkpa_enrollment_id' => $enrollment->id, 'pkpa_enrollment_requirement_id' => $requirement->id, 'current_placement_publication_id' => $publication->id, 'origin_published_assignment_id' => $assignment->id, 'current_published_assignment_id' => $assignment->id, 'practice_domain_id' => $domain->id, 'practice_site_id' => $site->id, 'student_core_user_id' => $this->student->core_user_id, 'scheduled_start_date' => '2026-07-13', 'scheduled_end_date' => '2026-07-17', 'status' => 'operational_complete', 'operational_status' => 'completed', 'publication_sync_status' => 'synced', 'operational_completed_at' => now(), 'current_key' => 'REQ:'.$requirement->id]);
        PkpaRotationAcademicReadinessReview::create(['pkpa_rotation_run_id' => $run->id, 'status' => 'ready_for_assessment', 'required_competency_count' => 0, 'verified_competency_count' => 0, 'required_task_count' => 0, 'approved_task_count' => 0, 'operational_complete' => true, 'blocking_issues' => [], 'snapshot' => ['source' => 'test'], 'reviewed_by_core_user_id' => $this->koordinator->core_user_id, 'reviewed_at' => now()]);
        $wahanaScheme = PkpaAssessmentScheme::create(['pkpa_program_domain_id' => $programDomain->id, 'code' => 'W-A', 'name' => 'Wahana A', 'version_number' => 1, 'maximum_score' => 100, 'rounding_precision' => 2, 'rounding_mode' => 'half_up', 'status' => 'active', 'is_current' => true]);
        $assessment = PkpaRotationAssessment::create(['pkpa_rotation_run_id' => $run->id, 'source_assessment_scheme_id' => $wahanaScheme->id, 'scheme_code_snapshot' => 'W-A', 'scheme_name_snapshot' => 'Wahana A', 'scheme_version_snapshot' => 1, 'maximum_score_snapshot' => 100, 'rounding_precision_snapshot' => 2, 'rounding_mode_snapshot' => 'half_up', 'status' => 'finalized', 'completion_status' => 'complete']);
        $grade = PkpaRotationGradeResult::create(['pkpa_rotation_assessment_id' => $assessment->id, 'pkpa_rotation_run_id' => $run->id, 'pkpa_enrollment_id' => $enrollment->id, 'pkpa_enrollment_requirement_id' => $requirement->id, 'practice_domain_id' => $domain->id, 'assessment_scheme_id' => $wahanaScheme->id, 'raw_total_score' => $score, 'final_score' => $score, 'maximum_score' => 100, 'result_status' => 'finalized', 'calculation_snapshot' => ['test' => true], 'component_snapshot' => [], 'finalized_at' => now(), 'finalized_by_core_user_id' => $this->koordinator->core_user_id]);

        return compact('program', 'domain', 'enrollment', 'requirement', 'run', 'grade');
    }

    private function makeUser(string $email, array $roles, string $coreUserId): User
    {
        $user = User::factory()->create(['name' => str($email)->before('@')->headline(), 'email' => $email, 'password' => Hash::make('password'), 'status' => 'active', 'profile_completed' => true, 'core_user_id' => $coreUserId]);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user->load('roles');
    }
}
