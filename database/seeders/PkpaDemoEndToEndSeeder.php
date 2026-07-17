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
use App\Models\PkpaRotationAcademicReadinessReview;
use App\Models\PkpaRotationAssessment;
use App\Models\PkpaRotationGradeResult;
use App\Models\PkpaRotationRun;
use App\Models\PkpaStudentGroup;
use App\Models\PkpaStudentGroupMember;
use App\Models\User;
use App\Services\PkpaEnrollmentRequirementService;
use App\Services\PkpaFinalAssessmentSchemeService;
use App\Services\PkpaFinalGradeService;
use App\Services\PkpaProgramService;
use App\Services\PkpaRequirementCompletionService;
use Illuminate\Database\Seeder;

class PkpaDemoEndToEndSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class, PkpaMasterSeeder::class, AdminSeeder::class, DemoUserSeeder::class]);

        $admin = User::where('email', 'farhamzah@ubpkarawang.ac.id')->first()
            ?? User::where('email', 'admin@sikp.test')->firstOrFail();
        $koordinator = User::where('email', 'koordinator@sikp.test')->firstOrFail();
        $student = User::where('email', 'mahasiswa@sikp.test')->firstOrFail();

        $admin->forceFill(['core_user_id' => $admin->core_user_id ?: 'CORE-DEMO-ADMIN'])->save();
        $koordinator->forceFill(['core_user_id' => $koordinator->core_user_id ?: 'CORE-DEMO-KOORDINATOR'])->save();
        $student->forceFill(['core_user_id' => $student->core_user_id ?: 'CORE-DEMO-MAHASISWA'])->save();

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

        $enrollment = PkpaEnrollment::updateOrCreate([
            'pkpa_program_id' => $program->id,
            'core_user_id' => $student->core_user_id,
        ], [
            'student_number' => 'PKPA-DEMO-001',
            'student_name_snapshot' => 'Mahasiswa Demo',
            'student_email_snapshot' => $student->email,
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
        app(PkpaEnrollmentRequirementService::class)->ensureRequirements($enrollment, $admin);

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
            'pkpa_enrollment_id' => $enrollment->id,
        ], [
            'joined_at' => now(),
            'status' => 'active',
            'notes' => 'Anggota dummy untuk simulasi.',
            'created_by_core_user_id' => $admin->core_user_id,
            'updated_by_core_user_id' => $admin->core_user_id,
        ]);

        $requirement = $enrollment->requirements()->where('practice_domain_id', $domain->id)->firstOrFail();
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
            'summary' => ['assignments' => 1, 'demo' => true],
            'validation_snapshot' => ['status' => 'valid'],
            'published_by_core_user_id' => $koordinator->core_user_id,
        ]);
        $assignment = PkpaPublishedAssignment::updateOrCreate([
            'pkpa_placement_publication_id' => $publication->id,
            'pkpa_enrollment_requirement_id' => $requirement->id,
        ], [
            'pkpa_enrollment_id' => $enrollment->id,
            'practice_domain_id' => $domain->id,
            'practice_site_id' => $site->id,
            'program_site_id' => $programSite->id,
            'student_core_user_id' => $student->core_user_id,
            'student_number_snapshot' => $enrollment->student_number,
            'student_name_snapshot' => $enrollment->student_name_snapshot,
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

        $run = PkpaRotationRun::updateOrCreate(['current_key' => 'REQ:'.$requirement->id], [
            'pkpa_program_id' => $program->id,
            'pkpa_enrollment_id' => $enrollment->id,
            'pkpa_enrollment_requirement_id' => $requirement->id,
            'current_placement_publication_id' => $publication->id,
            'origin_published_assignment_id' => $assignment->id,
            'current_published_assignment_id' => $assignment->id,
            'practice_domain_id' => $domain->id,
            'practice_site_id' => $site->id,
            'student_core_user_id' => $student->core_user_id,
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
        PkpaRotationAcademicReadinessReview::updateOrCreate(['pkpa_rotation_run_id' => $run->id], [
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
        $assessment = PkpaRotationAssessment::updateOrCreate(['pkpa_rotation_run_id' => $run->id], [
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
        PkpaRotationGradeResult::updateOrCreate(['pkpa_rotation_assessment_id' => $assessment->id], [
            'pkpa_rotation_run_id' => $run->id,
            'pkpa_enrollment_id' => $enrollment->id,
            'pkpa_enrollment_requirement_id' => $requirement->id,
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

        app(PkpaRequirementCompletionService::class)->complete($requirement->fresh(), 'Data demo Apotek selesai sampai nilai finalized.', $koordinator);
        $enrollment->requirements()->whereKeyNot($requirement->id)->update(['status' => 'completed']);

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

        if (! PkpaFinalGradeRelease::where('pkpa_enrollment_id', $enrollment->id)->where('status', 'released')->exists()) {
            $calculation = app(PkpaFinalGradeService::class)->calculate($enrollment->fresh(), $admin);
            $result = app(PkpaFinalGradeService::class)->finalize($calculation, $koordinator);
            $decision = app(PkpaFinalGradeService::class)->decide($enrollment->fresh(), $result, 'passed', 'Dummy belajar: seluruh requirement selesai dan nilai memenuhi.', $koordinator);
            app(PkpaFinalGradeService::class)->release($result, $decision, $koordinator);
        }
    }
}
