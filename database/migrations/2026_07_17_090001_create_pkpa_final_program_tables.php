<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpa_remedial_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('applies_to', 60)->default('wahana');
            $table->string('trigger_type', 60)->default('coordinator_decision');
            $table->unsignedInteger('maximum_attempts')->nullable();
            $table->string('score_replacement_policy', 60)->default('latest');
            $table->decimal('maximum_replacement_score', 10, 4)->nullable();
            $table->boolean('require_coordinator_approval')->default(true);
            $table->boolean('require_new_rotation')->default(false);
            $table->boolean('require_new_assessment')->default(false);
            $table->string('status', 30)->default('draft');
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_id', 'code'], 'pkpa_remedial_policies_program_code_unique');
        });

        Schema::create('pkpa_final_assessment_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->decimal('maximum_score', 10, 4)->default(100);
            $table->decimal('minimum_passing_score', 10, 4)->nullable();
            $table->unsignedTinyInteger('rounding_precision')->default(2);
            $table->string('rounding_mode', 30)->default('half_up');
            $table->boolean('require_all_wahana_completed')->default(true);
            $table->boolean('require_all_wahana_minimum_score')->default(false);
            $table->boolean('require_program_components_complete')->default(false);
            $table->foreignId('remedial_policy_id')->nullable()->constrained('pkpa_remedial_policies')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->boolean('is_current')->default(false);
            $table->string('current_key', 160)->nullable()->unique();
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->text('instructions')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->string('activated_by_core_user_id')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_id', 'code', 'version_number'], 'pkpa_final_scheme_program_code_version_unique');
        });

        Schema::create('pkpa_program_assessment_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('assessment_type', 60)->default('custom');
            $table->decimal('maximum_score', 10, 4)->default(100);
            $table->decimal('minimum_required_score', 10, 4)->nullable();
            $table->string('assessor_type', 60)->default('coordinator');
            $table->string('rubric_source_type', 60)->nullable();
            $table->text('instructions')->nullable();
            $table->unsignedInteger('attempt_limit')->nullable();
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_id', 'code'], 'pkpa_program_templates_program_code_unique');
        });

        Schema::create('pkpa_final_assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_final_assessment_scheme_id');
            $table->foreign(
                'pkpa_final_assessment_scheme_id',
                'pkpa_final_components_scheme_fk'
            )->references('id')->on('pkpa_final_assessment_schemes')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('component_type', 60)->default('wahana_grade');
            $table->foreignId('source_practice_domain_id')->nullable();
            $table->foreign(
                'source_practice_domain_id',
                'pkpa_final_components_domain_fk'
            )->references('id')->on('pkpa_practice_domains')->nullOnDelete();
            $table->foreignId('source_program_assessment_template_id')->nullable();
            $table->foreign(
                'source_program_assessment_template_id',
                'pkpa_final_components_template_fk'
            )->references('id')->on('pkpa_program_assessment_templates')->nullOnDelete();
            $table->decimal('weight_percentage', 10, 4)->default(0);
            $table->decimal('maximum_raw_score', 10, 4)->default(100);
            $table->decimal('minimum_required_score', 10, 4)->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('calculation_method', 40)->default('normalized');
            $table->string('score_selection_policy', 60)->default('current_final');
            $table->boolean('allow_missing')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 30)->default('draft');
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_final_assessment_scheme_id', 'code'], 'pkpa_final_component_scheme_code_unique');
        });

        Schema::create('pkpa_program_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->cascadeOnDelete();
            $table->foreignId('pkpa_program_assessment_template_id');
            $table->foreign(
                'pkpa_program_assessment_template_id',
                'pkpa_program_assessments_template_fk'
            )->references('id')->on('pkpa_program_assessment_templates')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('attempt_number')->default(1);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->decimal('raw_score', 10, 4)->nullable();
            $table->decimal('final_score', 10, 4)->nullable();
            $table->decimal('minimum_required_score_snapshot', 10, 4)->nullable();
            $table->json('assessment_snapshot')->nullable();
            $table->string('result_status', 30)->default('pending');
            $table->unsignedInteger('row_version')->default(1);
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->string('finalized_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_enrollment_id', 'pkpa_program_assessment_template_id', 'attempt_number'], 'pkpa_program_assessment_attempt_unique');
        });

        Schema::create('pkpa_program_assessment_assessors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_assessment_id');
            $table->foreign(
                'pkpa_program_assessment_id',
                'pkpa_program_assessors_assessment_fk'
            )->references('id')->on('pkpa_program_assessments')->cascadeOnDelete();
            $table->string('core_user_id');
            $table->string('name_snapshot')->nullable();
            $table->string('role_snapshot')->nullable();
            $table->string('assessor_type', 60)->default('coordinator');
            $table->string('status', 30)->default('assigned');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_program_component_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_assessment_id');
            $table->foreign(
                'pkpa_program_assessment_id',
                'pkpa_program_scores_assessment_fk'
            )->references('id')->on('pkpa_program_assessments')->cascadeOnDelete();
            $table->foreignId('pkpa_program_assessment_assessor_id')->nullable();
            $table->foreign(
                'pkpa_program_assessment_assessor_id',
                'pkpa_program_scores_assessor_fk'
            )->references('id')->on('pkpa_program_assessment_assessors')->nullOnDelete();
            $table->decimal('raw_score', 10, 4)->nullable();
            $table->decimal('normalized_score', 10, 4)->nullable();
            $table->decimal('final_score', 10, 4)->nullable();
            $table->text('comments')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_remedial_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->cascadeOnDelete();
            $table->foreignId('pkpa_enrollment_requirement_id')->nullable();
            $table->foreign(
                'pkpa_enrollment_requirement_id',
                'pkpa_remedial_cases_requirement_fk'
            )->references('id')->on('pkpa_enrollment_requirements')->nullOnDelete();
            $table->foreignId('pkpa_rotation_grade_result_id')->nullable();
            $table->foreign(
                'pkpa_rotation_grade_result_id',
                'pkpa_remedial_cases_grade_result_fk'
            )->references('id')->on('pkpa_rotation_grade_results')->nullOnDelete();
            $table->foreignId('pkpa_program_assessment_id')->nullable()->constrained('pkpa_program_assessments')->nullOnDelete();
            $table->foreignId('pkpa_remedial_policy_id')->nullable()->constrained('pkpa_remedial_policies')->nullOnDelete();
            $table->string('case_type', 60)->default('custom');
            $table->string('status', 30)->default('draft');
            $table->text('reason');
            $table->json('policy_snapshot')->nullable();
            $table->string('opened_by_core_user_id')->nullable();
            $table->string('approved_by_core_user_id')->nullable();
            $table->string('closed_by_core_user_id')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pkpa_remedial_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_remedial_case_id')->constrained('pkpa_remedial_cases')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->foreignId('source_rotation_run_id')->nullable()->constrained('pkpa_rotation_runs')->nullOnDelete();
            $table->foreignId('remedial_rotation_run_id')->nullable()->constrained('pkpa_rotation_runs')->nullOnDelete();
            $table->foreignId('source_grade_result_id')->nullable()->constrained('pkpa_rotation_grade_results')->nullOnDelete();
            $table->foreignId('new_grade_result_id')->nullable()->constrained('pkpa_rotation_grade_results')->nullOnDelete();
            $table->foreignId('source_program_assessment_id')->nullable()->constrained('pkpa_program_assessments')->nullOnDelete();
            $table->foreignId('new_program_assessment_id')->nullable()->constrained('pkpa_program_assessments')->nullOnDelete();
            $table->string('status', 30)->default('planned');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('selected_score', 10, 4)->nullable();
            $table->text('selection_reason')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->unique(['pkpa_remedial_case_id', 'attempt_number'], 'pkpa_remedial_attempts_case_attempt_unique');
        });

        Schema::create('pkpa_enrollment_requirement_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_enrollment_requirement_id');
            $table->foreign(
                'pkpa_enrollment_requirement_id',
                'pkpa_completion_requirement_fk'
            )->references('id')->on('pkpa_enrollment_requirements')->cascadeOnDelete();
            $table->foreignId('pkpa_enrollment_id');
            $table->foreign(
                'pkpa_enrollment_id',
                'pkpa_completion_enrollment_fk'
            )->references('id')->on('pkpa_enrollments')->cascadeOnDelete();
            $table->foreignId('practice_domain_id');
            $table->foreign(
                'practice_domain_id',
                'pkpa_completion_domain_fk'
            )->references('id')->on('pkpa_practice_domains')->restrictOnDelete();
            $table->foreignId('selected_practice_domain_option_id')->nullable();
            $table->foreign(
                'selected_practice_domain_option_id',
                'pkpa_completion_domain_option_fk'
            )->references('id')->on('pkpa_practice_domain_options')->nullOnDelete();
            $table->foreignId('rotation_run_id')->nullable()->constrained('pkpa_rotation_runs')->nullOnDelete();
            $table->foreignId('rotation_grade_result_id')->nullable();
            $table->foreign(
                'rotation_grade_result_id',
                'pkpa_completion_grade_result_fk'
            )->references('id')->on('pkpa_rotation_grade_results')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->string('completion_basis', 60)->default('explicit_coordinator');
            $table->json('operational_complete_snapshot')->nullable();
            $table->json('academic_readiness_snapshot')->nullable();
            $table->json('grade_snapshot')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('completion_reason')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->string('completed_by_core_user_id')->nullable();
            $table->string('reopened_by_core_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_final_grade_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->cascadeOnDelete();
            $table->foreignId('pkpa_final_assessment_scheme_id');
            $table->foreign(
                'pkpa_final_assessment_scheme_id',
                'pkpa_final_calculations_scheme_fk'
            )->references('id')->on('pkpa_final_assessment_schemes')->restrictOnDelete();
            $table->unsignedInteger('calculation_number')->default(1);
            $table->string('status', 30)->default('draft');
            $table->json('source_snapshot')->nullable();
            $table->json('component_results')->nullable();
            $table->decimal('raw_total_score', 10, 4)->nullable();
            $table->decimal('moderated_total_score', 10, 4)->nullable();
            $table->decimal('final_score', 10, 4)->nullable();
            $table->json('rounding_snapshot')->nullable();
            $table->json('blocking_issues')->nullable();
            $table->json('warning_issues')->nullable();
            $table->string('calculated_by_core_user_id')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
            $table->unique(['pkpa_enrollment_id', 'calculation_number'], 'pkpa_final_calculations_enrollment_number_unique');
        });

        Schema::create('pkpa_final_grade_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->cascadeOnDelete();
            $table->foreignId('pkpa_final_grade_calculation_id');
            $table->foreign(
                'pkpa_final_grade_calculation_id',
                'pkpa_final_results_calculation_fk'
            )->references('id')->on('pkpa_final_grade_calculations')->restrictOnDelete();
            $table->foreignId('pkpa_final_assessment_scheme_id');
            $table->foreign(
                'pkpa_final_assessment_scheme_id',
                'pkpa_final_results_scheme_fk'
            )->references('id')->on('pkpa_final_assessment_schemes')->restrictOnDelete();
            $table->decimal('raw_total_score', 10, 4);
            $table->decimal('moderated_total_score', 10, 4)->nullable();
            $table->decimal('final_score', 10, 4);
            $table->decimal('maximum_score', 10, 4)->default(100);
            $table->decimal('minimum_passing_score_snapshot', 10, 4)->nullable();
            $table->string('result_status', 30)->default('finalized');
            $table->json('source_snapshot');
            $table->json('calculation_snapshot');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('finalized_by_core_user_id')->nullable();
            $table->string('released_by_core_user_id')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->timestamps();
        });

        Schema::create('pkpa_final_grade_moderations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_final_grade_calculation_id');
            $table->foreign(
                'pkpa_final_grade_calculation_id',
                'pkpa_final_moderations_calculation_fk'
            )->references('id')->on('pkpa_final_grade_calculations')->cascadeOnDelete();
            $table->string('status', 30)->default('completed');
            $table->text('reason');
            $table->decimal('original_total_score', 10, 4)->nullable();
            $table->decimal('final_total_score', 10, 4)->nullable();
            $table->json('adjustments')->nullable();
            $table->string('moderated_by_core_user_id')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_final_grade_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->cascadeOnDelete();
            $table->foreignId('pkpa_final_grade_result_id')->nullable();
            $table->foreign(
                'pkpa_final_grade_result_id',
                'pkpa_final_change_requests_result_fk'
            )->references('id')->on('pkpa_final_grade_results')->nullOnDelete();
            $table->string('request_number', 80)->unique();
            $table->string('status', 30)->default('submitted');
            $table->text('reason');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->string('requested_by_core_user_id')->nullable();
            $table->string('approved_by_core_user_id')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pkpa_graduation_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->cascadeOnDelete();
            $table->foreignId('pkpa_final_grade_result_id')->nullable();
            $table->foreign(
                'pkpa_final_grade_result_id',
                'pkpa_graduation_decisions_result_fk'
            )->references('id')->on('pkpa_final_grade_results')->nullOnDelete();
            $table->unsignedInteger('decision_number')->default(1);
            $table->string('decision_status', 30)->default('pending');
            $table->string('decision', 40)->nullable();
            $table->json('readiness_snapshot')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_by_core_user_id')->nullable();
            $table->timestamps();
            $table->unique(['pkpa_enrollment_id', 'decision_number'], 'pkpa_graduation_decisions_enrollment_number_unique');
        });

        Schema::create('pkpa_graduation_decision_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_graduation_decision_id');
            $table->foreign(
                'pkpa_graduation_decision_id',
                'pkpa_graduation_changes_decision_fk'
            )->references('id')->on('pkpa_graduation_decisions')->cascadeOnDelete();
            $table->string('status', 30)->default('submitted');
            $table->text('reason');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->string('requested_by_core_user_id')->nullable();
            $table->string('approved_by_core_user_id')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_final_grade_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_final_grade_result_id');
            $table->foreign(
                'pkpa_final_grade_result_id',
                'pkpa_final_releases_result_fk'
            )->references('id')->on('pkpa_final_grade_results')->cascadeOnDelete();
            $table->foreignId('pkpa_graduation_decision_id')->nullable();
            $table->foreign(
                'pkpa_graduation_decision_id',
                'pkpa_final_releases_decision_fk'
            )->references('id')->on('pkpa_graduation_decisions')->nullOnDelete();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->cascadeOnDelete();
            $table->unsignedInteger('release_number')->default(1);
            $table->string('status', 30)->default('released');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('released_by_core_user_id')->nullable();
            $table->string('withdrawn_by_core_user_id')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->json('student_visible_snapshot');
            $table->timestamps();
            $table->unique(['pkpa_enrollment_id', 'release_number'], 'pkpa_final_releases_enrollment_number_unique');
        });

        Schema::create('pkpa_enrollment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->string('changed_by_core_user_id')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpa_enrollment_status_histories');
        Schema::dropIfExists('pkpa_final_grade_releases');
        Schema::dropIfExists('pkpa_graduation_decision_changes');
        Schema::dropIfExists('pkpa_graduation_decisions');
        Schema::dropIfExists('pkpa_final_grade_change_requests');
        Schema::dropIfExists('pkpa_final_grade_moderations');
        Schema::dropIfExists('pkpa_final_grade_results');
        Schema::dropIfExists('pkpa_final_grade_calculations');
        Schema::dropIfExists('pkpa_enrollment_requirement_completions');
        Schema::dropIfExists('pkpa_remedial_attempts');
        Schema::dropIfExists('pkpa_remedial_cases');
        Schema::dropIfExists('pkpa_program_component_scores');
        Schema::dropIfExists('pkpa_program_assessment_assessors');
        Schema::dropIfExists('pkpa_program_assessments');
        Schema::dropIfExists('pkpa_final_assessment_components');
        Schema::dropIfExists('pkpa_program_assessment_templates');
        Schema::dropIfExists('pkpa_final_assessment_schemes');
        Schema::dropIfExists('pkpa_remedial_policies');
    }
};
