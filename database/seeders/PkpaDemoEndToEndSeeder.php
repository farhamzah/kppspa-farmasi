<?php

namespace Database\Seeders;

use App\Models\PkpaAssessmentScheme;
use App\Models\PkpaEnrollment;
use App\Models\PkpaFinalAssessmentScheme;
use App\Models\PkpaFinalGradeRelease;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramSite;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaPublishedAssignmentSupervisor;
use App\Models\PkpaRotationAcademicReadinessReview;
use App\Models\PkpaRotationAssessment;
use App\Models\PkpaRotationGradeResult;
use App\Models\PkpaRotationRun;
use App\Models\PkpaStudentGroup;
use App\Models\PkpaStudentGroupMember;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpaEnrollmentRequirementService;
use App\Services\PkpaFinalAssessmentSchemeService;
use App\Services\PkpaFinalGradeService;
use App\Services\PkpaProgramService;
use App\Services\PkpaRequirementCompletionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PkpaDemoEndToEndSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PkpaMasterSeeder::class,
            PkpaPortfolioTemplateSeeder::class,
            AdminSeeder::class,
            DemoUserSeeder::class,
        ]);

        $admin = User::where('email', 'farhamzah@ubpkarawang.ac.id')->first()
            ?? User::where('email', 'admin@sikp.test')->firstOrFail();
        $koordinator = User::where('email', 'koordinator@sikp.test')->firstOrFail();
        $studentA = User::where('email', 'mahasiswa@sikp.test')->firstOrFail();
        $studentB = $this->ensureDemoUser('Bima Pratama Farmasi', 'mahasiswa2@sikp.test', ['mahasiswa']);
        $internalSupervisor = User::where('email', 'dosen@sikp.test')->firstOrFail();
        $fieldSupervisor = User::where('email', 'lapangan@sikp.test')->firstOrFail();

        $admin->forceFill(['core_user_id' => $admin->core_user_id ?: 'CORE-DEMO-ADMIN'])->save();
        $koordinator->forceFill(['core_user_id' => $koordinator->core_user_id ?: 'CORE-DEMO-KOORDINATOR'])->save();
        $studentA->forceFill(['core_user_id' => $studentA->core_user_id ?: 'CORE-DEMO-MAHASISWA-1'])->save();
        $studentB->forceFill(['core_user_id' => $studentB->core_user_id ?: 'CORE-DEMO-MAHASISWA-2'])->save();
        $internalSupervisor->forceFill(['core_user_id' => $internalSupervisor->core_user_id ?: 'CORE-DEMO-INTERNAL-1'])->save();
        $fieldSupervisor->forceFill(['core_user_id' => $fieldSupervisor->core_user_id ?: 'CORE-DEMO-FIELD-1'])->save();

        $program = PkpaProgram::where('code', 'PKPA-DEMO-2026')->first();
        if (! $program) {
            $program = app(PkpaProgramService::class)->create([
                'code' => 'PKPA-DEMO-2026',
                'name' => 'PKPA Demo Belajar 2026',
                'academic_year' => '2026/2027',
                'cohort_name' => 'Angkatan Demo',
                'start_date' => '2026-07-20',
                'end_date' => '2026-12-20',
            ], $admin);
        }

        $program->domains()->update([
            'duration_value' => 4,
            'duration_unit' => 'weeks',
            'minimum_effective_days' => 20,
            'minimum_practice_hours' => 160,
            'weight_percentage' => 16.67,
            'updated_by_core_user_id' => $admin->core_user_id,
        ]);
        $program->forceFill([
            'status' => 'active',
            'is_active' => true,
            'activated_at' => now(),
            'activated_by_core_user_id' => $admin->core_user_id,
        ])->save();

        $domain = PkpaPracticeDomain::where('code', 'APT')->firstOrFail();
        $programDomain = $program->domains()->where('practice_domain_id', $domain->id)->firstOrFail();

        $site = PkpaPracticeSite::updateOrCreate(['code' => 'APT-DEMO-UBP'], [
            'practice_domain_id' => $domain->id,
            'name' => 'Apotek Care Demo Karawang',
            'legal_name' => 'Apotek Care Demo Karawang',
            'address' => 'Jl. HS Ronggo Waluyo, Karawang',
            'city' => 'Karawang',
            'province' => 'Jawa Barat',
            'contact_person_name' => 'Apt. Pembimbing Demo',
            'cooperation_start_date' => '2026-01-01',
            'cooperation_end_date' => '2027-12-31',
            'status' => 'active',
            'is_active' => true,
            'created_by_core_user_id' => $admin->core_user_id,
            'updated_by_core_user_id' => $admin->core_user_id,
        ]);

        $programSite = PkpaProgramSite::updateOrCreate([
            'pkpa_program_id' => $program->id,
            'practice_site_id' => $site->id,
        ], [
            'pkpa_program_domain_id' => $programDomain->id,
            'practice_domain_id' => $domain->id,
            'status' => 'active',
            'is_active' => true,
            'default_minimum_students' => 1,
            'default_maximum_students' => 6,
            'created_by_core_user_id' => $admin->core_user_id,
            'updated_by_core_user_id' => $admin->core_user_id,
            'activated_by_core_user_id' => $admin->core_user_id,
            'activated_at' => now(),
        ]);

        $enrollmentA = PkpaEnrollment::updateOrCreate([
            'pkpa_program_id' => $program->id,
            'core_user_id' => $studentA->core_user_id,
        ], [
            'student_number' => 'PKPA-DEMO-001',
            'student_name_snapshot' => $studentA->name,
            'student_email_snapshot' => $studentA->email,
            'study_program_snapshot' => 'Profesi Apoteker',
            'cohort_snapshot' => 'Angkatan Demo',
            'academic_status_snapshot' => 'aktif',
            'core_account_status_snapshot' => 'active',
            'status' => 'active',
            'enrolled_at' => now(),
            'activated_at' => now(),
            'created_by_core_user_id' => $admin->core_user_id,
            'updated_by_core_user_id' => $admin->core_user_id,
        ]);
        $enrollmentB = PkpaEnrollment::updateOrCreate([
            'pkpa_program_id' => $program->id,
            'core_user_id' => $studentB->core_user_id,
        ], [
            'student_number' => 'PKPA-DEMO-002',
            'student_name_snapshot' => $studentB->name,
            'student_email_snapshot' => $studentB->email,
            'study_program_snapshot' => 'Profesi Apoteker',
            'cohort_snapshot' => 'Angkatan Demo',
            'academic_status_snapshot' => 'aktif',
            'core_account_status_snapshot' => 'active',
            'status' => 'active',
            'enrolled_at' => now(),
            'activated_at' => now(),
            'created_by_core_user_id' => $admin->core_user_id,
            'updated_by_core_user_id' => $admin->core_user_id,
        ]);
        app(PkpaEnrollmentRequirementService::class)->ensureRequirements($enrollmentA, $admin);
        app(PkpaEnrollmentRequirementService::class)->ensureRequirements($enrollmentB, $admin);

        $group = PkpaStudentGroup::updateOrCreate(['pkpa_program_id' => $program->id, 'code' => 'KEL-DEMO-01'], [
            'name' => 'Kelompok Demo 01',
            'description' => 'Kelompok dummy untuk belajar alur PKPA end-to-end.',
            'maximum_members' => 6,
            'status' => 'active',
            'is_active' => true,
            'created_by_core_user_id' => $admin->core_user_id,
            'updated_by_core_user_id' => $admin->core_user_id,
        ]);
        PkpaStudentGroupMember::updateOrCreate([
            'pkpa_student_group_id' => $group->id,
            'pkpa_enrollment_id' => $enrollmentA->id,
        ], [
            'joined_at' => now(),
            'status' => 'active',
            'notes' => 'Anggota dummy untuk simulasi.',
            'created_by_core_user_id' => $admin->core_user_id,
            'updated_by_core_user_id' => $admin->core_user_id,
        ]);
        PkpaStudentGroupMember::updateOrCreate([
            'pkpa_student_group_id' => $group->id,
            'pkpa_enrollment_id' => $enrollmentB->id,
        ], [
            'joined_at' => now(),
            'status' => 'active',
            'notes' => 'Anggota dummy untuk simulasi.',
            'created_by_core_user_id' => $admin->core_user_id,
            'updated_by_core_user_id' => $admin->core_user_id,
        ]);

        $requirementA = $enrollmentA->requirements()->where('practice_domain_id', $domain->id)->firstOrFail();
        $requirementB = $enrollmentB->requirements()->where('practice_domain_id', $domain->id)->firstOrFail();
        $plan = PkpaPlacementPlan::updateOrCreate(['code' => 'PLAN-DEMO-2026'], [
            'pkpa_program_id' => $program->id,
            'name' => 'Rencana Penempatan Demo 2026',
            'version_number' => 1,
            'status' => 'locked',
            'is_current' => true,
            'current_key' => 'PROGRAM:'.$program->id,
            'validation_status' => 'valid',
            'validation_summary' => ['demo' => true, 'issues' => 0],
            'last_validated_at' => now(),
            'created_by_core_user_id' => $admin->core_user_id,
            'updated_by_core_user_id' => $admin->core_user_id,
            'validated_by_core_user_id' => $koordinator->core_user_id,
        ]);
        $publication = PkpaPlacementPublication::updateOrCreate(['code' => 'PUB-DEMO-2026'], [
            'pkpa_program_id' => $program->id,
            'pkpa_placement_plan_id' => $plan->id,
            'publication_number' => 1,
            'revision_number' => 0,
            'title' => 'Publikasi Jadwal PKPA Demo 2026',
            'status' => 'published',
            'is_current' => true,
            'current_key' => 'PROGRAM:'.$program->id,
            'published_at' => now(),
            'effective_at' => now(),
            'summary' => ['assignments' => 2, 'demo' => true],
            'validation_snapshot' => ['status' => 'valid'],
            'published_by_core_user_id' => $koordinator->core_user_id,
        ]);
        $assignmentA = PkpaPublishedAssignment::updateOrCreate([
            'pkpa_placement_publication_id' => $publication->id,
            'pkpa_enrollment_requirement_id' => $requirementA->id,
        ], [
            'pkpa_enrollment_id' => $enrollmentA->id,
            'practice_domain_id' => $domain->id,
            'practice_site_id' => $site->id,
            'program_site_id' => $programSite->id,
            'student_core_user_id' => $studentA->core_user_id,
            'student_number_snapshot' => $enrollmentA->student_number,
            'student_name_snapshot' => $enrollmentA->student_name_snapshot,
            'student_group_snapshot' => $group->name,
            'practice_domain_name_snapshot' => $domain->name,
            'practice_site_name_snapshot' => $site->name,
            'practice_site_address_snapshot' => $site->address,
            'start_date' => '2026-07-20',
            'end_date' => '2026-08-14',
            'duration_value_snapshot' => 4,
            'duration_unit_snapshot' => 'weeks',
            'effective_days_snapshot' => 20,
            'practice_hours_snapshot' => 160,
            'status' => 'scheduled',
        ]);
        $assignmentB = PkpaPublishedAssignment::updateOrCreate([
            'pkpa_placement_publication_id' => $publication->id,
            'pkpa_enrollment_requirement_id' => $requirementB->id,
        ], [
            'pkpa_enrollment_id' => $enrollmentB->id,
            'practice_domain_id' => $domain->id,
            'practice_site_id' => $site->id,
            'program_site_id' => $programSite->id,
            'student_core_user_id' => $studentB->core_user_id,
            'student_number_snapshot' => $enrollmentB->student_number,
            'student_name_snapshot' => $enrollmentB->student_name_snapshot,
            'student_group_snapshot' => $group->name,
            'practice_domain_name_snapshot' => $domain->name,
            'practice_site_name_snapshot' => $site->name,
            'practice_site_address_snapshot' => $site->address,
            'start_date' => '2026-07-20',
            'end_date' => '2026-08-14',
            'duration_value_snapshot' => 4,
            'duration_unit_snapshot' => 'weeks',
            'effective_days_snapshot' => 20,
            'practice_hours_snapshot' => 160,
            'status' => 'scheduled',
        ]);
        $this->syncPublishedSupervisors($assignmentA, $internalSupervisor, $fieldSupervisor);
        $this->syncPublishedSupervisors($assignmentB, $internalSupervisor, $fieldSupervisor);

        $runA = PkpaRotationRun::updateOrCreate(['current_key' => 'REQ:'.$requirementA->id], [
            'pkpa_program_id' => $program->id,
            'pkpa_enrollment_id' => $enrollmentA->id,
            'pkpa_enrollment_requirement_id' => $requirementA->id,
            'current_placement_publication_id' => $publication->id,
            'origin_published_assignment_id' => $assignmentA->id,
            'current_published_assignment_id' => $assignmentA->id,
            'practice_domain_id' => $domain->id,
            'practice_site_id' => $site->id,
            'student_core_user_id' => $studentA->core_user_id,
            'scheduled_start_date' => '2026-07-20',
            'scheduled_end_date' => '2026-08-14',
            'actual_start_date' => '2026-07-20',
            'actual_end_date' => '2026-08-14',
            'status' => 'operational_complete',
            'operational_status' => 'completed',
            'publication_sync_status' => 'synced',
            'started_at' => now()->subWeeks(4),
            'operational_completed_at' => now(),
            'created_by_core_user_id' => $admin->core_user_id,
            'updated_by_core_user_id' => $admin->core_user_id,
            'activated_by_core_user_id' => $koordinator->core_user_id,
            'operational_completed_by_core_user_id' => $koordinator->core_user_id,
        ]);
        $runB = PkpaRotationRun::updateOrCreate(['current_key' => 'REQ:'.$requirementB->id], [
            'pkpa_program_id' => $program->id,
            'pkpa_enrollment_id' => $enrollmentB->id,
            'pkpa_enrollment_requirement_id' => $requirementB->id,
            'current_placement_publication_id' => $publication->id,
            'origin_published_assignment_id' => $assignmentB->id,
            'current_published_assignment_id' => $assignmentB->id,
            'practice_domain_id' => $domain->id,
            'practice_site_id' => $site->id,
            'student_core_user_id' => $studentB->core_user_id,
            'scheduled_start_date' => '2026-07-20',
            'scheduled_end_date' => '2026-08-14',
            'actual_start_date' => '2026-07-20',
            'actual_end_date' => '2026-08-14',
            'status' => 'operational_complete',
            'operational_status' => 'completed',
            'publication_sync_status' => 'synced',
            'started_at' => now()->subWeeks(4),
            'operational_completed_at' => now(),
            'created_by_core_user_id' => $admin->core_user_id,
            'updated_by_core_user_id' => $admin->core_user_id,
            'activated_by_core_user_id' => $koordinator->core_user_id,
            'operational_completed_by_core_user_id' => $koordinator->core_user_id,
        ]);
        PkpaRotationAcademicReadinessReview::updateOrCreate(['pkpa_rotation_run_id' => $runA->id], [
            'status' => 'ready_for_assessment',
            'required_competency_count' => 3,
            'verified_competency_count' => 3,
            'required_task_count' => 1,
            'approved_task_count' => 1,
            'operational_complete' => true,
            'blocking_issues' => [],
            'snapshot' => ['demo' => true],
            'reviewed_by_core_user_id' => $koordinator->core_user_id,
            'reviewed_at' => now(),
        ]);
        PkpaRotationAcademicReadinessReview::updateOrCreate(['pkpa_rotation_run_id' => $runB->id], [
            'status' => 'ready_for_assessment',
            'required_competency_count' => 3,
            'verified_competency_count' => 3,
            'required_task_count' => 1,
            'approved_task_count' => 1,
            'operational_complete' => true,
            'blocking_issues' => [],
            'snapshot' => ['demo' => true],
            'reviewed_by_core_user_id' => $koordinator->core_user_id,
            'reviewed_at' => now(),
        ]);

        $wahanaScheme = PkpaAssessmentScheme::updateOrCreate(['pkpa_program_domain_id' => $programDomain->id, 'code' => 'DEMO-APT'], [
            'name' => 'Skema Nilai Apotek Demo',
            'version_number' => 1,
            'minimum_passing_score' => 70,
            'maximum_score' => 100,
            'rounding_precision' => 2,
            'rounding_mode' => 'half_up',
            'status' => 'active',
            'is_current' => true,
            'current_key' => 'DOMAIN:'.$programDomain->id,
            'activated_at' => now(),
            'created_by_core_user_id' => $admin->core_user_id,
            'updated_by_core_user_id' => $admin->core_user_id,
            'activated_by_core_user_id' => $koordinator->core_user_id,
        ]);
        $assessmentA = PkpaRotationAssessment::updateOrCreate(['pkpa_rotation_run_id' => $runA->id], [
            'source_assessment_scheme_id' => $wahanaScheme->id,
            'scheme_code_snapshot' => $wahanaScheme->code,
            'scheme_name_snapshot' => $wahanaScheme->name,
            'scheme_version_snapshot' => 1,
            'maximum_score_snapshot' => 100,
            'rounding_precision_snapshot' => 2,
            'rounding_mode_snapshot' => 'half_up',
            'status' => 'finalized',
            'completion_status' => 'complete',
        ]);
        $assessmentB = PkpaRotationAssessment::updateOrCreate(['pkpa_rotation_run_id' => $runB->id], [
            'source_assessment_scheme_id' => $wahanaScheme->id,
            'scheme_code_snapshot' => $wahanaScheme->code,
            'scheme_name_snapshot' => $wahanaScheme->name,
            'scheme_version_snapshot' => 1,
            'maximum_score_snapshot' => 100,
            'rounding_precision_snapshot' => 2,
            'rounding_mode_snapshot' => 'half_up',
            'status' => 'finalized',
            'completion_status' => 'complete',
        ]);
        PkpaRotationGradeResult::updateOrCreate(['pkpa_rotation_assessment_id' => $assessmentA->id], [
            'pkpa_rotation_run_id' => $runA->id,
            'pkpa_enrollment_id' => $enrollmentA->id,
            'pkpa_enrollment_requirement_id' => $requirementA->id,
            'practice_domain_id' => $domain->id,
            'assessment_scheme_id' => $wahanaScheme->id,
            'raw_total_score' => '88.0000',
            'final_score' => '88.0000',
            'maximum_score' => 100,
            'minimum_passing_score_snapshot' => 70,
            'result_status' => 'finalized',
            'calculation_snapshot' => ['demo' => true],
            'component_snapshot' => [],
            'finalized_at' => now(),
            'finalized_by_core_user_id' => $koordinator->core_user_id,
        ]);
        PkpaRotationGradeResult::updateOrCreate(['pkpa_rotation_assessment_id' => $assessmentB->id], [
            'pkpa_rotation_run_id' => $runB->id,
            'pkpa_enrollment_id' => $enrollmentB->id,
            'pkpa_enrollment_requirement_id' => $requirementB->id,
            'practice_domain_id' => $domain->id,
            'assessment_scheme_id' => $wahanaScheme->id,
            'raw_total_score' => '86.0000',
            'final_score' => '86.0000',
            'maximum_score' => 100,
            'minimum_passing_score_snapshot' => 70,
            'result_status' => 'finalized',
            'calculation_snapshot' => ['demo' => true],
            'component_snapshot' => [],
            'finalized_at' => now(),
            'finalized_by_core_user_id' => $koordinator->core_user_id,
        ]);

        app(PkpaRequirementCompletionService::class)->complete($requirementA->fresh(), 'Data demo Apotek Alya selesai sampai nilai finalized.', $koordinator);
        app(PkpaRequirementCompletionService::class)->complete($requirementB->fresh(), 'Data demo Apotek Bima selesai sampai nilai finalized.', $koordinator);
        $enrollmentA->requirements()->whereKeyNot($requirementA->id)->update(['status' => 'completed']);
        $enrollmentB->requirements()->whereKeyNot($requirementB->id)->update(['status' => 'completed']);

        $scheme = PkpaFinalAssessmentScheme::where('pkpa_program_id', $program->id)->where('code', 'FINAL-DEMO')->first()
            ?: app(PkpaFinalAssessmentSchemeService::class)->create($program, ['code' => 'FINAL-DEMO', 'name' => 'Skema Final Demo PKPA'], $admin);
        if (! $scheme->components()->where('code', 'APT')->exists()) {
            app(PkpaFinalAssessmentSchemeService::class)->saveComponent($scheme, [
                'code' => 'APT',
                'name' => 'Nilai Wahana Apotek',
                'component_type' => 'wahana_grade',
                'source_practice_domain_id' => $domain->id,
                'weight_percentage' => 100,
                'maximum_raw_score' => 100,
                'status' => 'active',
            ], $admin);
        }
        if ($scheme->status !== 'active') {
            app(PkpaFinalAssessmentSchemeService::class)->activate($scheme, $koordinator);
        }

        if (! PkpaFinalGradeRelease::where('pkpa_enrollment_id', $enrollmentA->id)->where('status', 'released')->exists()) {
            $calculation = app(PkpaFinalGradeService::class)->calculate($enrollmentA->fresh(), $admin);
            $result = app(PkpaFinalGradeService::class)->finalize($calculation, $koordinator);
            $decision = app(PkpaFinalGradeService::class)->decide($enrollmentA->fresh(), $result, 'passed', 'Dummy belajar: seluruh requirement selesai dan nilai memenuhi.', $koordinator);
            app(PkpaFinalGradeService::class)->release($result, $decision, $koordinator);
        }
    }

    private function syncPublishedSupervisors(PkpaPublishedAssignment $assignment, User $internalSupervisor, User $fieldSupervisor): void
    {
        PkpaPublishedAssignmentSupervisor::updateOrCreate([
            'pkpa_published_assignment_id' => $assignment->id,
            'supervisor_type' => 'internal',
            'core_user_id' => $internalSupervisor->core_user_id,
        ], [
            'name_snapshot' => $internalSupervisor->name,
            'email_snapshot' => $internalSupervisor->email,
            'role_snapshot' => 'Pembimbing Dalam',
            'is_primary' => true,
            'status' => 'assigned',
        ]);

        PkpaPublishedAssignmentSupervisor::updateOrCreate([
            'pkpa_published_assignment_id' => $assignment->id,
            'supervisor_type' => 'field',
            'core_user_id' => $fieldSupervisor->core_user_id,
        ], [
            'name_snapshot' => $fieldSupervisor->name,
            'email_snapshot' => $fieldSupervisor->email,
            'role_snapshot' => 'Preseptor',
            'position_snapshot' => $fieldSupervisor->fieldSupervisor?->position,
            'is_primary' => true,
            'status' => 'assigned',
        ]);
    }

    private function ensureDemoUser(string $name, string $email, array $roles): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'status' => 'active',
                'must_change_password' => false,
                'profile_completed' => true,
            ]
        );

        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user->fresh();
    }
}
