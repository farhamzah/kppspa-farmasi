<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpa_assessment_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_domain_id')->constrained('pkpa_program_domains')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->decimal('minimum_passing_score', 8, 4)->nullable();
            $table->decimal('maximum_score', 8, 4)->default(100);
            $table->unsignedTinyInteger('rounding_precision')->default(2);
            $table->string('rounding_mode', 30)->default('half_up');
            $table->string('status', 30)->default('draft');
            $table->boolean('is_current')->default(false);
            $table->string('current_key', 160)->nullable()->unique();
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('hide_other_assessor_scores_until_submit')->default(true);
            $table->boolean('require_academic_readiness')->default(true);
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->string('activated_by_core_user_id')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_domain_id', 'code', 'version_number'], 'pkpa_scheme_domain_code_version_unique');
        });

        Schema::create('pkpa_assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_assessment_scheme_id')->constrained('pkpa_assessment_schemes')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('component_type', 80);
            $table->string('assessor_type', 40)->default('system');
            $table->string('calculation_method', 40)->default('direct_score');
            $table->decimal('weight_percentage', 8, 4)->default(0);
            $table->decimal('maximum_raw_score', 8, 4)->default(100);
            $table->decimal('minimum_required_score', 8, 4)->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('source_entity_type')->nullable();
            $table->string('source_status_requirement')->nullable();
            $table->boolean('allow_manual_override')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 30)->default('draft');
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_assessment_scheme_id', 'code'], 'pkpa_component_scheme_code_unique');
        });

        Schema::create('pkpa_assessment_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_assessment_component_id')->constrained('pkpa_assessment_components')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('scoring_method', 40)->default('weighted_criteria');
            $table->decimal('maximum_score', 8, 4)->default(100);
            $table->text('instructions')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_assessment_component_id', 'code'], 'pkpa_rubric_component_code_unique');
        });

        Schema::create('pkpa_assessment_rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_assessment_rubric_id');
            $table->foreign(
                'pkpa_assessment_rubric_id',
                'pkpa_rubric_criteria_rubric_fk'
            )->references('id')->on('pkpa_assessment_rubrics')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight_percentage', 8, 4)->default(0);
            $table->decimal('maximum_points', 8, 4)->default(0);
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 30)->default('draft');
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_assessment_rubric_id', 'code'], 'pkpa_criterion_rubric_code_unique');
        });

        Schema::create('pkpa_assessment_rubric_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_assessment_rubric_criterion_id');
            $table->foreign(
                'pkpa_assessment_rubric_criterion_id',
                'pkpa_rubric_levels_criterion_fk'
            )->references('id')->on('pkpa_assessment_rubric_criteria')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('label');
            $table->text('description')->nullable();
            $table->decimal('points', 8, 4)->default(0);
            $table->decimal('minimum_value', 8, 4)->nullable();
            $table->decimal('maximum_value', 8, 4)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 30)->default('draft');
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_assessment_rubric_criterion_id', 'code'], 'pkpa_level_criterion_code_unique');
        });

        Schema::create('pkpa_rotation_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id')->constrained('pkpa_rotation_runs')->cascadeOnDelete();
            $table->foreignId('source_assessment_scheme_id')->constrained('pkpa_assessment_schemes')->restrictOnDelete();
            $table->string('scheme_code_snapshot', 80);
            $table->string('scheme_name_snapshot');
            $table->unsignedInteger('scheme_version_snapshot');
            $table->decimal('maximum_score_snapshot', 8, 4)->default(100);
            $table->decimal('minimum_passing_score_snapshot', 8, 4)->nullable();
            $table->unsignedTinyInteger('rounding_precision_snapshot')->default(2);
            $table->string('rounding_mode_snapshot', 30)->default('half_up');
            $table->string('status', 30)->default('draft');
            $table->string('completion_status', 30)->default('incomplete');
            $table->string('moderation_status', 30)->default('not_required');
            $table->string('grade_release_status', 30)->default('not_released');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->string('finalized_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('pkpa_rotation_run_id', 'pkpa_rotation_assessments_run_unique');
        });

        Schema::create('pkpa_rotation_assessment_assessors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_assessment_id');
            $table->foreign(
                'pkpa_rotation_assessment_id',
                'pkpa_assessors_rotation_assessment_fk'
            )->references('id')->on('pkpa_rotation_assessments')->cascadeOnDelete();
            $table->foreignId('pkpa_assessment_component_id');
            $table->foreign(
                'pkpa_assessment_component_id',
                'pkpa_assessors_component_fk'
            )->references('id')->on('pkpa_assessment_components')->restrictOnDelete();
            $table->string('assessor_type', 40);
            $table->string('core_user_id')->nullable();
            $table->string('name_snapshot')->nullable();
            $table->string('role_snapshot')->nullable();
            $table->foreignId('source_rotation_supervisor_history_id')->nullable();
            $table->foreign(
                'source_rotation_supervisor_history_id',
                'pkpa_assessors_supervisor_history_fk'
            )->references('id')->on('pkpa_rotation_supervisor_histories')->nullOnDelete();
            $table->string('status', 30)->default('assigned');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->unique(['pkpa_rotation_assessment_id', 'pkpa_assessment_component_id', 'assessor_type', 'core_user_id'], 'pkpa_assessor_unique');
        });

        Schema::create('pkpa_rotation_component_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_assessment_id');
            $table->foreign(
                'pkpa_rotation_assessment_id',
                'pkpa_component_scores_assessment_fk'
            )->references('id')->on('pkpa_rotation_assessments')->cascadeOnDelete();
            $table->foreignId('pkpa_assessment_component_id');
            $table->foreign(
                'pkpa_assessment_component_id',
                'pkpa_component_scores_component_fk'
            )->references('id')->on('pkpa_assessment_components')->restrictOnDelete();
            $table->foreignId('assessor_assignment_id')->nullable();
            $table->foreign(
                'assessor_assignment_id',
                'pkpa_component_scores_assessor_fk'
            )->references('id')->on('pkpa_rotation_assessment_assessors')->nullOnDelete();
            $table->string('component_code_snapshot', 80);
            $table->string('component_name_snapshot');
            $table->string('component_type_snapshot', 80);
            $table->decimal('weight_percentage_snapshot', 8, 4);
            $table->string('calculation_method_snapshot', 40);
            $table->decimal('raw_score', 10, 4)->nullable();
            $table->decimal('normalized_score', 10, 4)->nullable();
            $table->decimal('weighted_score', 10, 4)->nullable();
            $table->string('status', 30)->default('not_started');
            $table->text('comments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->json('source_summary')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->unique(['pkpa_rotation_assessment_id', 'pkpa_assessment_component_id', 'assessor_assignment_id'], 'pkpa_component_score_unique');
        });

        Schema::create('pkpa_rotation_rubric_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_component_score_id');
            $table->foreign(
                'pkpa_rotation_component_score_id',
                'pkpa_rubric_scores_component_fk'
            )->references('id')->on('pkpa_rotation_component_scores')->cascadeOnDelete();
            $table->foreignId('source_rubric_id')->constrained('pkpa_assessment_rubrics')->restrictOnDelete();
            $table->foreignId('source_criterion_id')->constrained('pkpa_assessment_rubric_criteria')->restrictOnDelete();
            $table->foreignId('source_level_id')->nullable()->constrained('pkpa_assessment_rubric_levels')->nullOnDelete();
            $table->string('criterion_code_snapshot', 80);
            $table->string('criterion_name_snapshot');
            $table->decimal('criterion_weight_snapshot', 8, 4);
            $table->string('level_code_snapshot', 80)->nullable();
            $table->string('level_label_snapshot')->nullable();
            $table->decimal('level_points_snapshot', 8, 4)->nullable();
            $table->decimal('score_value', 10, 4);
            $table->text('comments')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->unique(['pkpa_rotation_component_score_id', 'source_criterion_id'], 'pkpa_rubric_score_criterion_unique');
        });

        Schema::create('pkpa_assessment_moderations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_assessment_id');
            $table->foreign(
                'pkpa_rotation_assessment_id',
                'pkpa_moderations_assessment_fk'
            )->references('id')->on('pkpa_rotation_assessments')->cascadeOnDelete();
            $table->string('status', 30)->default('draft');
            $table->string('moderation_type', 60)->default('consistency_review');
            $table->text('reason');
            $table->decimal('original_total_score', 10, 4)->nullable();
            $table->decimal('proposed_total_score', 10, 4)->nullable();
            $table->decimal('final_total_score', 10, 4)->nullable();
            $table->json('component_adjustments')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('requested_by_core_user_id')->nullable();
            $table->string('moderated_by_core_user_id')->nullable();
            $table->string('approved_by_core_user_id')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_rotation_grade_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_assessment_id');
            $table->foreign(
                'pkpa_rotation_assessment_id',
                'pkpa_grade_results_assessment_fk'
            )->references('id')->on('pkpa_rotation_assessments')->cascadeOnDelete();
            $table->foreignId('pkpa_rotation_run_id')->constrained('pkpa_rotation_runs')->cascadeOnDelete();
            $table->foreignId('pkpa_enrollment_id')->nullable()->constrained('pkpa_enrollments')->nullOnDelete();
            $table->foreignId('pkpa_enrollment_requirement_id')->nullable();
            $table->foreign(
                'pkpa_enrollment_requirement_id',
                'pkpa_grade_results_requirement_fk'
            )->references('id')->on('pkpa_enrollment_requirements')->nullOnDelete();
            $table->foreignId('practice_domain_id')->nullable()->constrained('pkpa_practice_domains')->nullOnDelete();
            $table->foreignId('assessment_scheme_id')->constrained('pkpa_assessment_schemes')->restrictOnDelete();
            $table->decimal('raw_total_score', 10, 4);
            $table->decimal('moderated_total_score', 10, 4)->nullable();
            $table->decimal('final_score', 10, 4);
            $table->decimal('maximum_score', 10, 4)->default(100);
            $table->decimal('minimum_passing_score_snapshot', 10, 4)->nullable();
            $table->string('result_status', 30)->default('pending');
            $table->json('calculation_snapshot');
            $table->json('component_snapshot');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('finalized_by_core_user_id')->nullable();
            $table->string('released_by_core_user_id')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->timestamps();
            $table->unique(['pkpa_rotation_assessment_id', 'result_status'], 'pkpa_grade_result_status_unique');
        });

        Schema::create('pkpa_grade_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_assessment_id')->constrained('pkpa_rotation_assessments')->cascadeOnDelete();
            $table->string('request_number', 80)->unique();
            $table->string('request_type', 60);
            $table->string('status', 30)->default('draft');
            $table->text('reason');
            $table->text('impact_summary')->nullable();
            $table->string('requested_by_core_user_id')->nullable();
            $table->string('reviewed_by_core_user_id')->nullable();
            $table->string('approved_by_core_user_id')->nullable();
            $table->string('rejected_by_core_user_id')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pkpa_grade_change_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_grade_change_request_id');
            $table->foreign(
                'pkpa_grade_change_request_id',
                'pkpa_grade_change_items_request_fk'
            )->references('id')->on('pkpa_grade_change_requests')->cascadeOnDelete();
            $table->foreignId('pkpa_rotation_component_score_id')->nullable();
            $table->foreign(
                'pkpa_rotation_component_score_id',
                'pkpa_grade_change_items_component_fk'
            )->references('id')->on('pkpa_rotation_component_scores')->nullOnDelete();
            $table->string('field_name', 80);
            $table->decimal('before_value', 10, 4)->nullable();
            $table->decimal('after_value', 10, 4)->nullable();
            $table->text('reason');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_grade_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_grade_result_id');
            $table->foreign(
                'pkpa_rotation_grade_result_id',
                'pkpa_grade_releases_result_fk'
            )->references('id')->on('pkpa_rotation_grade_results')->cascadeOnDelete();
            $table->foreignId('pkpa_rotation_assessment_id');
            $table->foreign(
                'pkpa_rotation_assessment_id',
                'pkpa_grade_releases_assessment_fk'
            )->references('id')->on('pkpa_rotation_assessments')->cascadeOnDelete();
            $table->unsignedInteger('release_number')->default(1);
            $table->string('status', 30)->default('released');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('released_by_core_user_id')->nullable();
            $table->string('withdrawn_by_core_user_id')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->json('student_visible_snapshot');
            $table->timestamps();
            $table->unique(['pkpa_rotation_assessment_id', 'release_number'], 'pkpa_grade_release_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpa_grade_releases');
        Schema::dropIfExists('pkpa_grade_change_request_items');
        Schema::dropIfExists('pkpa_grade_change_requests');
        Schema::dropIfExists('pkpa_rotation_grade_results');
        Schema::dropIfExists('pkpa_assessment_moderations');
        Schema::dropIfExists('pkpa_rotation_rubric_scores');
        Schema::dropIfExists('pkpa_rotation_component_scores');
        Schema::dropIfExists('pkpa_rotation_assessment_assessors');
        Schema::dropIfExists('pkpa_rotation_assessments');
        Schema::dropIfExists('pkpa_assessment_rubric_levels');
        Schema::dropIfExists('pkpa_assessment_rubric_criteria');
        Schema::dropIfExists('pkpa_assessment_rubrics');
        Schema::dropIfExists('pkpa_assessment_components');
        Schema::dropIfExists('pkpa_assessment_schemes');
    }
};
