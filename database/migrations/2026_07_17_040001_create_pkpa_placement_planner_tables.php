<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpa_placement_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->unsignedInteger('version_number');
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('is_current')->default(false)->index();
            $table->string('current_key', 80)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('validation_status', 32)->default('not_validated')->index();
            $table->json('validation_summary')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->string('validated_by_core_user_id', 80)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_id', 'version_number'], 'pkpa_placement_plans_program_version_unique');
            $table->index(['pkpa_program_id', 'is_current'], 'pkpa_placement_plans_program_current_index');
        });

        Schema::create('pkpa_rotation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_placement_plan_id')->constrained('pkpa_placement_plans')->restrictOnDelete();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->restrictOnDelete();
            $table->foreignId('pkpa_enrollment_requirement_id')->constrained('pkpa_enrollment_requirements')->restrictOnDelete();
            $table->foreignId('pkpa_program_domain_id')->constrained('pkpa_program_domains')->restrictOnDelete();
            $table->foreignId('practice_domain_id')->constrained('pkpa_practice_domains')->restrictOnDelete();
            $table->foreignId('selected_practice_domain_option_id')->nullable();
            $table->foreign(
                'selected_practice_domain_option_id',
                'pkpa_rotation_domain_option_fk'
            )->references('id')->on('pkpa_practice_domain_options')->nullOnDelete();
            $table->foreignId('pkpa_program_site_id')->nullable()->constrained('pkpa_program_sites')->nullOnDelete();
            $table->foreignId('pkpa_site_availability_period_id')->nullable();
            $table->foreign(
                'pkpa_site_availability_period_id',
                'pkpa_rotation_availability_fk'
            )->references('id')->on('pkpa_site_availability_periods')->nullOnDelete();
            $table->foreignId('practice_site_id')->nullable()->constrained('pkpa_practice_sites')->nullOnDelete();
            $table->foreignId('student_group_id_snapshot')->nullable()->constrained('pkpa_student_groups')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('planned_duration_value', 8, 2)->nullable();
            $table->string('planned_duration_unit', 32)->nullable();
            $table->unsignedInteger('calculated_effective_days')->nullable();
            $table->unsignedInteger('calculated_practice_hours')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('planning_source', 32)->default('individual')->index();
            $table->string('validation_status', 32)->default('not_validated')->index();
            $table->timestamp('last_validated_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_placement_plan_id', 'pkpa_enrollment_requirement_id'], 'pkpa_rotation_assignments_plan_req_unique');
            $table->index(['pkpa_placement_plan_id', 'pkpa_enrollment_id'], 'pkpa_rotation_assignments_plan_enrollment_index');
            $table->index(['pkpa_site_availability_period_id', 'start_date', 'end_date'], 'pkpa_rotation_assignments_availability_period_index');
        });

        Schema::create('pkpa_rotation_assignment_supervisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_assignment_id');
            $table->foreign(
                'pkpa_rotation_assignment_id',
                'pkpa_assignment_supervisors_assignment_fk'
            )->references('id')->on('pkpa_rotation_assignments')->restrictOnDelete();
            $table->string('supervisor_type', 20)->index();
            $table->foreignId('internal_supervisor_eligibility_id')->nullable();
            $table->foreign(
                'internal_supervisor_eligibility_id',
                'pkpa_assignment_supervisors_internal_fk'
            )->references('id')->on('pkpa_internal_supervisor_eligibilities')->nullOnDelete();
            $table->foreignId('site_field_supervisor_id')->nullable();
            $table->foreign(
                'site_field_supervisor_id',
                'pkpa_assignment_supervisors_field_fk'
            )->references('id')->on('pkpa_site_field_supervisors')->nullOnDelete();
            $table->string('core_user_id', 80);
            $table->string('name_snapshot')->nullable();
            $table->string('role_snapshot')->nullable();
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->boolean('is_primary')->default(true)->index();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['pkpa_rotation_assignment_id', 'supervisor_type', 'status'], 'pkpa_assignment_supervisors_active_index');
        });

        Schema::create('pkpa_placement_action_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_placement_plan_id')->constrained('pkpa_placement_plans')->restrictOnDelete();
            $table->string('action_type', 40)->index();
            $table->string('status', 32)->default('previewed')->index();
            $table->text('description')->nullable();
            $table->unsignedInteger('affected_count')->default(0);
            $table->json('request_summary')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('reverted_by_core_user_id', 80)->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_placement_action_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_action_batch_id');
            $table->foreign(
                'placement_action_batch_id',
                'pkpa_action_items_batch_fk'
            )->references('id')->on('pkpa_placement_action_batches')->cascadeOnDelete();
            $table->foreignId('pkpa_enrollment_id')->nullable()->constrained('pkpa_enrollments')->nullOnDelete();
            $table->foreignId('pkpa_enrollment_requirement_id')->nullable();
            $table->foreign(
                'pkpa_enrollment_requirement_id',
                'pkpa_action_items_requirement_fk'
            )->references('id')->on('pkpa_enrollment_requirements')->nullOnDelete();
            $table->foreignId('pkpa_rotation_assignment_id')->nullable();
            $table->foreign(
                'pkpa_rotation_assignment_id',
                'pkpa_action_items_assignment_fk'
            )->references('id')->on('pkpa_rotation_assignments')->nullOnDelete();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->string('result_status', 32)->default('pending')->index();
            $table->json('validation_messages')->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_placement_validation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_placement_plan_id')->constrained('pkpa_placement_plans')->cascadeOnDelete();
            $table->string('scope_type', 32)->default('full_plan')->index();
            $table->json('scope_payload')->nullable();
            $table->string('status', 32)->default('running')->index();
            $table->unsignedInteger('total_assignments')->default(0);
            $table->unsignedInteger('valid_assignments')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_placement_validation_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_validation_run_id');
            $table->foreign(
                'placement_validation_run_id',
                'pkpa_validation_issues_run_fk'
            )->references('id')->on('pkpa_placement_validation_runs')->cascadeOnDelete();
            $table->foreignId('pkpa_rotation_assignment_id')->nullable();
            $table->foreign(
                'pkpa_rotation_assignment_id',
                'pkpa_validation_issues_assignment_fk'
            )->references('id')->on('pkpa_rotation_assignments')->nullOnDelete();
            $table->foreignId('pkpa_enrollment_id')->nullable()->constrained('pkpa_enrollments')->nullOnDelete();
            $table->foreignId('pkpa_enrollment_requirement_id')->nullable();
            $table->foreign(
                'pkpa_enrollment_requirement_id',
                'pkpa_validation_issues_requirement_fk'
            )->references('id')->on('pkpa_enrollment_requirements')->nullOnDelete();
            $table->string('issue_code', 80)->index();
            $table->string('severity', 20)->index();
            $table->string('category', 40)->index();
            $table->text('message');
            $table->text('suggested_action')->nullable();
            $table->json('context')->nullable();
            $table->boolean('is_resolved')->default(false)->index();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by_core_user_id', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpa_placement_validation_issues');
        Schema::dropIfExists('pkpa_placement_validation_runs');
        Schema::dropIfExists('pkpa_placement_action_batch_items');
        Schema::dropIfExists('pkpa_placement_action_batches');
        Schema::dropIfExists('pkpa_rotation_assignment_supervisors');
        Schema::dropIfExists('pkpa_rotation_assignments');
        Schema::dropIfExists('pkpa_placement_plans');
    }
};
