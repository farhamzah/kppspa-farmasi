<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpa_competency_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_domain_id')->constrained('pkpa_program_domains')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('status', 32)->default('draft');
            $table->boolean('is_current')->default(false);
            $table->string('current_key')->nullable();
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->text('instructions')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->string('activated_by_core_user_id')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_domain_id', 'code'], 'pkpa_competency_sets_domain_code_unique');
            $table->unique(['pkpa_program_domain_id', 'version_number'], 'pkpa_competency_sets_domain_version_unique');
            $table->unique(['pkpa_program_domain_id', 'current_key'], 'pkpa_competency_sets_current_unique');
            $table->index(['status', 'is_current'], 'pkpa_competency_sets_status_current_idx');
        });

        Schema::create('pkpa_competency_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_competency_set_id')->constrained('pkpa_competency_sets')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_competency_set_id', 'code'], 'pkpa_competency_categories_set_code_unique');
        });

        Schema::create('pkpa_competency_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_competency_set_id')->constrained('pkpa_competency_sets')->cascadeOnDelete();
            $table->foreignId('pkpa_competency_category_id')->nullable()->constrained('pkpa_competency_categories')->nullOnDelete();
            $table->string('code', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('achievement_criteria')->nullable();
            $table->text('evidence_instructions')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('evidence_required')->default(false);
            $table->unsignedInteger('minimum_evidence_count')->default(0);
            $table->boolean('verification_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_competency_set_id', 'code'], 'pkpa_competency_items_set_code_unique');
        });

        Schema::create('pkpa_rotation_competency_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id')->constrained('pkpa_rotation_runs')->cascadeOnDelete();
            $table->foreignId('source_competency_set_id')->nullable();
            $table->foreign(
                'source_competency_set_id',
                'pkpa_competency_records_set_fk'
            )->references('id')->on('pkpa_competency_sets')->nullOnDelete();
            $table->foreignId('source_competency_item_id')->nullable();
            $table->foreign(
                'source_competency_item_id',
                'pkpa_competency_records_item_fk'
            )->references('id')->on('pkpa_competency_items')->nullOnDelete();
            $table->string('competency_code_snapshot', 80);
            $table->string('competency_title_snapshot');
            $table->text('competency_description_snapshot')->nullable();
            $table->text('achievement_criteria_snapshot')->nullable();
            $table->boolean('is_required_snapshot')->default(true);
            $table->boolean('evidence_required_snapshot')->default(false);
            $table->unsignedInteger('minimum_evidence_count_snapshot')->default(0);
            $table->string('status', 48)->default('pending');
            $table->text('student_notes')->nullable();
            $table->timestamp('demonstrated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('revision_requested_at')->nullable();
            $table->string('verified_by_core_user_id')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->timestamps();
            $table->unique(['pkpa_rotation_run_id', 'source_competency_item_id'], 'pkpa_rotation_competency_item_unique');
            $table->index(['pkpa_rotation_run_id', 'status'], 'pkpa_competency_records_run_status_idx');
        });

        Schema::create('pkpa_rotation_competency_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_competency_record_id');
            $table->foreign(
                'pkpa_rotation_competency_record_id',
                'pkpa_competency_evidences_record_fk'
            )->references('id')->on('pkpa_rotation_competency_records')->cascadeOnDelete();
            $table->string('evidence_type', 48);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('logbook_entry_id')->nullable()->constrained('pkpa_logbook_entries')->nullOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained('pkpa_attendance_records')->nullOnDelete();
            $table->string('external_reference_url')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('stored_filename')->nullable();
            $table->string('disk', 64)->nullable();
            $table->string('path')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->string('status', 32)->default('active');
            $table->string('uploaded_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['pkpa_rotation_competency_record_id', 'status'], 'pkpa_competency_evidence_record_status_index');
        });

        Schema::create('pkpa_rotation_competency_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_competency_record_id');
            $table->foreign(
                'pkpa_rotation_competency_record_id',
                'pkpa_competency_reviews_record_fk'
            )->references('id')->on('pkpa_rotation_competency_records')->cascadeOnDelete();
            $table->string('reviewer_type', 48);
            $table->string('reviewer_core_user_id');
            $table->string('action', 48);
            $table->text('comments')->nullable();
            $table->timestamp('reviewed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['reviewer_core_user_id', 'reviewer_type'], 'pkpa_competency_reviews_reviewer_index');
        });

        Schema::create('pkpa_special_task_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_domain_id')->constrained('pkpa_program_domains')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('submission_type', 48)->default('document');
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('minimum_submissions')->default(1);
            $table->integer('due_offset_days')->nullable();
            $table->boolean('allow_multiple_versions')->default(true);
            $table->boolean('field_supervisor_review_required')->default(false);
            $table->boolean('internal_supervisor_review_required')->default(true);
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_domain_id', 'code'], 'pkpa_task_templates_domain_code_unique');
        });

        Schema::create('pkpa_rotation_special_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id')->constrained('pkpa_rotation_runs')->cascadeOnDelete();
            $table->foreignId('source_special_task_template_id')->nullable();
            $table->foreign(
                'source_special_task_template_id',
                'pkpa_rotation_tasks_template_fk'
            )->references('id')->on('pkpa_special_task_templates')->nullOnDelete();
            $table->string('task_code_snapshot', 80);
            $table->string('task_title_snapshot');
            $table->text('task_description_snapshot')->nullable();
            $table->text('instructions_snapshot')->nullable();
            $table->string('submission_type_snapshot', 48)->default('document');
            $table->boolean('is_required_snapshot')->default(true);
            $table->boolean('field_supervisor_review_required_snapshot')->default(false);
            $table->boolean('internal_supervisor_review_required_snapshot')->default(true);
            $table->date('due_date')->nullable();
            $table->string('status', 48)->default('assigned');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_rotation_run_id', 'source_special_task_template_id'], 'pkpa_rotation_task_template_unique');
            $table->index(['pkpa_rotation_run_id', 'status'], 'pkpa_rotation_tasks_run_status_idx');
        });

        Schema::create('pkpa_special_task_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_special_task_id');
            $table->foreign(
                'pkpa_rotation_special_task_id',
                'pkpa_task_submissions_task_fk'
            )->references('id')->on('pkpa_rotation_special_tasks')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('stored_filename')->nullable();
            $table->string('disk', 64)->nullable();
            $table->string('path')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->text('submission_notes')->nullable();
            $table->string('status', 48)->default('draft');
            $table->string('submitted_by_core_user_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_rotation_special_task_id', 'version_number'], 'pkpa_task_submission_version_unique');
        });

        Schema::create('pkpa_special_task_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_special_task_submission_id');
            $table->foreign(
                'pkpa_special_task_submission_id',
                'pkpa_task_reviews_submission_fk'
            )->references('id')->on('pkpa_special_task_submissions')->cascadeOnDelete();
            $table->string('reviewer_type', 48);
            $table->string('reviewer_core_user_id');
            $table->string('action', 48);
            $table->text('comments')->nullable();
            $table->timestamp('reviewed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_rotation_report_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_domain_id')->constrained('pkpa_program_domains')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->json('required_sections')->nullable();
            $table->json('allowed_file_types')->nullable();
            $table->unsignedInteger('maximum_file_size_kb')->nullable();
            $table->boolean('field_supervisor_confirmation_required')->default(false);
            $table->boolean('internal_supervisor_approval_required')->default(true);
            $table->integer('submission_deadline_offset_days')->nullable();
            $table->string('status', 32)->default('draft');
            $table->boolean('is_current')->default(false);
            $table->string('current_key')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_domain_id', 'code'], 'pkpa_report_templates_domain_code_unique');
            $table->unique(['pkpa_program_domain_id', 'current_key'], 'pkpa_report_templates_current_unique');
        });

        Schema::create('pkpa_rotation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id')->constrained('pkpa_rotation_runs')->cascadeOnDelete();
            $table->foreignId('source_report_template_id')->nullable();
            $table->foreign(
                'source_report_template_id',
                'pkpa_rotation_reports_template_fk'
            )->references('id')->on('pkpa_rotation_report_templates')->nullOnDelete();
            $table->string('report_code', 80)->nullable();
            $table->string('title');
            $table->string('status', 48)->default('draft');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('field_confirmed_at')->nullable();
            $table->timestamp('internal_approved_at')->nullable();
            $table->timestamp('revision_requested_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('field_confirmed_by_core_user_id')->nullable();
            $table->string('internal_approved_by_core_user_id')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique('pkpa_rotation_run_id', 'pkpa_rotation_reports_run_unique');
            $table->index(['status']);
        });

        Schema::create('pkpa_rotation_report_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_report_id')->constrained('pkpa_rotation_reports')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('original_filename')->nullable();
            $table->string('stored_filename')->nullable();
            $table->string('disk', 64)->nullable();
            $table->string('path')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->text('change_summary')->nullable();
            $table->text('submission_notes')->nullable();
            $table->string('status', 48)->default('draft');
            $table->string('uploaded_by_core_user_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_rotation_report_id', 'version_number'], 'pkpa_report_version_unique');
        });

        Schema::table('pkpa_rotation_reports', function (Blueprint $table) {
            $table->foreign('current_version_id', 'pkpa_reports_current_version_fk')->references('id')->on('pkpa_rotation_report_versions')->nullOnDelete();
        });

        Schema::create('pkpa_rotation_guidance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id')->constrained('pkpa_rotation_runs')->cascadeOnDelete();
            $table->foreignId('pkpa_rotation_report_id')->nullable();
            $table->foreign(
                'pkpa_rotation_report_id',
                'pkpa_guidance_report_fk'
            )->references('id')->on('pkpa_rotation_reports')->nullOnDelete();
            $table->foreignId('report_version_id')->nullable()->constrained('pkpa_rotation_report_versions')->nullOnDelete();
            $table->string('guidance_type', 48);
            $table->date('guidance_date');
            $table->string('supervisor_type', 48);
            $table->string('supervisor_core_user_id');
            $table->string('topic');
            $table->text('student_summary')->nullable();
            $table->text('supervisor_notes')->nullable();
            $table->text('follow_up_actions')->nullable();
            $table->string('status', 48)->default('recorded');
            $table->timestamp('student_acknowledged_at')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['pkpa_rotation_run_id', 'guidance_date'], 'pkpa_guidance_run_date_index');
        });

        Schema::create('pkpa_rotation_academic_readiness_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id');
            $table->foreign(
                'pkpa_rotation_run_id',
                'pkpa_readiness_run_fk'
            )->references('id')->on('pkpa_rotation_runs')->cascadeOnDelete();
            $table->string('status', 48)->default('not_ready');
            $table->unsignedInteger('required_competency_count')->default(0);
            $table->unsignedInteger('verified_competency_count')->default(0);
            $table->unsignedInteger('required_task_count')->default(0);
            $table->unsignedInteger('approved_task_count')->default(0);
            $table->string('report_status', 48)->nullable();
            $table->boolean('operational_complete')->default(false);
            $table->json('blocking_issues')->nullable();
            $table->json('snapshot')->nullable();
            $table->string('reviewed_by_core_user_id')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->index(['pkpa_rotation_run_id', 'status'], 'pkpa_readiness_run_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpa_rotation_academic_readiness_reviews');
        Schema::dropIfExists('pkpa_rotation_guidance_sessions');
        Schema::table('pkpa_rotation_reports', function (Blueprint $table) {
            $table->dropForeign('pkpa_reports_current_version_fk');
        });
        Schema::dropIfExists('pkpa_rotation_report_versions');
        Schema::dropIfExists('pkpa_rotation_reports');
        Schema::dropIfExists('pkpa_rotation_report_templates');
        Schema::dropIfExists('pkpa_special_task_reviews');
        Schema::dropIfExists('pkpa_special_task_submissions');
        Schema::dropIfExists('pkpa_rotation_special_tasks');
        Schema::dropIfExists('pkpa_special_task_templates');
        Schema::dropIfExists('pkpa_rotation_competency_reviews');
        Schema::dropIfExists('pkpa_rotation_competency_evidences');
        Schema::dropIfExists('pkpa_rotation_competency_records');
        Schema::dropIfExists('pkpa_competency_items');
        Schema::dropIfExists('pkpa_competency_categories');
        Schema::dropIfExists('pkpa_competency_sets');
    }
};
