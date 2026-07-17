<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpa_rotation_operation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_domain_id')->constrained('pkpa_program_domains')->cascadeOnDelete();
            $table->boolean('attendance_required')->default(true);
            $table->boolean('require_check_in')->default(true);
            $table->boolean('require_check_out')->default(true);
            $table->boolean('allow_manual_attendance_time')->default(true);
            $table->boolean('logbook_required')->default(true);
            $table->string('logbook_frequency', 32)->default('daily');
            $table->unsignedInteger('minimum_logbook_entries')->default(0);
            $table->unsignedInteger('minimum_approved_attendance_days')->default(0);
            $table->unsignedInteger('maximum_backdate_days')->nullable();
            $table->unsignedInteger('submission_deadline_days')->nullable();
            $table->boolean('field_supervisor_approval_required')->default(true);
            $table->boolean('internal_supervisor_monitoring_enabled')->default(true);
            $table->boolean('allow_student_edit_after_submit')->default(false);
            $table->boolean('completion_requires_all_approved')->default(true);
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('active_key')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->unique(['pkpa_program_domain_id', 'active_key'], 'pkpa_operation_rule_active_unique');
            $table->index(['pkpa_program_domain_id', 'is_active'], 'pkpa_operation_rules_domain_active_idx');
        });

        Schema::create('pkpa_rotation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->cascadeOnDelete();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->cascadeOnDelete();
            $table->foreignId('pkpa_enrollment_requirement_id')->constrained('pkpa_enrollment_requirements')->cascadeOnDelete();
            $table->foreignId('current_placement_publication_id');
            $table->foreign(
                'current_placement_publication_id',
                'pkpa_rotation_runs_publication_fk'
            )->references('id')->on('pkpa_placement_publications')->cascadeOnDelete();
            $table->foreignId('origin_published_assignment_id');
            $table->foreign(
                'origin_published_assignment_id',
                'pkpa_rotation_runs_origin_assignment_fk'
            )->references('id')->on('pkpa_published_assignments')->cascadeOnDelete();
            $table->foreignId('current_published_assignment_id');
            $table->foreign(
                'current_published_assignment_id',
                'pkpa_rotation_runs_current_assignment_fk'
            )->references('id')->on('pkpa_published_assignments')->cascadeOnDelete();
            $table->foreignId('practice_domain_id')->constrained('pkpa_practice_domains')->restrictOnDelete();
            $table->foreignId('practice_domain_option_id')->nullable()->constrained('pkpa_practice_domain_options')->nullOnDelete();
            $table->foreignId('practice_site_id')->constrained('pkpa_practice_sites')->restrictOnDelete();
            $table->string('student_core_user_id');
            $table->date('scheduled_start_date');
            $table->date('scheduled_end_date');
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->string('status', 48)->default('scheduled');
            $table->string('operational_status', 48)->default('not_started');
            $table->string('publication_sync_status', 48)->default('current');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('operational_completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->string('activated_by_core_user_id')->nullable();
            $table->string('operational_completed_by_core_user_id')->nullable();
            $table->string('current_key')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_enrollment_requirement_id', 'current_key'], 'pkpa_rotation_run_current_requirement_unique');
            $table->unique('origin_published_assignment_id', 'pkpa_rotation_run_origin_assignment_unique');
            $table->index(['student_core_user_id', 'status'], 'pkpa_rotation_runs_student_status_idx');
            $table->index(['current_placement_publication_id', 'status'], 'pkpa_rotation_runs_publication_status_idx');
            $table->index(['publication_sync_status', 'status'], 'pkpa_rotation_runs_sync_status_idx');
        });

        Schema::create('pkpa_rotation_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id');
            $table->foreign(
                'pkpa_rotation_run_id',
                'pkpa_supervisor_histories_run_fk'
            )->references('id')->on('pkpa_rotation_runs')->cascadeOnDelete();
            $table->string('from_status', 48)->nullable();
            $table->string('to_status', 48);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->string('changed_by_core_user_id')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['pkpa_rotation_run_id', 'changed_at'], 'pkpa_rotation_status_run_changed_idx');
        });

        Schema::create('pkpa_rotation_supervisor_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id')->constrained('pkpa_rotation_runs')->cascadeOnDelete();
            $table->string('supervisor_type', 24);
            $table->string('core_user_id');
            $table->string('name_snapshot');
            $table->string('role_snapshot')->nullable();
            $table->foreignId('source_published_assignment_supervisor_id')->nullable();
            $table->foreign(
                'source_published_assignment_supervisor_id',
                'pkpa_supervisor_histories_source_fk'
            )->references('id')->on('pkpa_published_assignment_supervisors')->nullOnDelete();
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('active_key')->nullable();
            $table->text('change_reason')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->timestamps();
            $table->unique(['pkpa_rotation_run_id', 'supervisor_type', 'active_key'], 'pkpa_rotation_supervisor_active_unique');
            $table->index(['core_user_id', 'supervisor_type', 'status'], 'pkpa_rotation_supervisor_core_status_idx');
        });

        Schema::create('pkpa_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id')->constrained('pkpa_rotation_runs')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('attendance_type', 48)->default('present');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->unsignedInteger('calculated_minutes')->nullable();
            $table->string('status', 48)->default('active');
            $table->string('submission_status', 48)->default('draft');
            $table->text('student_notes')->nullable();
            $table->text('field_supervisor_notes')->nullable();
            $table->string('source', 48)->default('student_manual');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('submitted_by_core_user_id')->nullable();
            $table->string('reviewed_by_core_user_id')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->string('active_key')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_rotation_run_id', 'attendance_date', 'active_key'], 'pkpa_attendance_active_date_unique');
            $table->index(['pkpa_rotation_run_id', 'submission_status'], 'pkpa_attendance_run_submission_idx');
        });

        Schema::create('pkpa_attendance_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_attendance_record_id');
            $table->foreign(
                'pkpa_attendance_record_id',
                'pkpa_attendance_corrections_record_fk'
            )->references('id')->on('pkpa_attendance_records')->cascadeOnDelete();
            $table->string('request_type', 48);
            $table->string('status', 48)->default('draft');
            $table->text('reason');
            $table->json('before_snapshot')->nullable();
            $table->json('proposed_snapshot')->nullable();
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
            $table->index(['pkpa_attendance_record_id', 'status'], 'pkpa_attendance_corrections_record_status_idx');
        });

        Schema::create('pkpa_logbook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id')->constrained('pkpa_rotation_runs')->cascadeOnDelete();
            $table->date('entry_date')->nullable();
            $table->date('period_start_date')->nullable();
            $table->date('period_end_date')->nullable();
            $table->string('title');
            $table->text('activity_summary');
            $table->text('learning_outcomes');
            $table->text('reflection');
            $table->text('problems_encountered')->nullable();
            $table->text('follow_up_plan')->nullable();
            $table->unsignedInteger('practice_minutes')->nullable();
            $table->string('status', 48)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('field_reviewed_at')->nullable();
            $table->timestamp('internal_reviewed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('submitted_by_core_user_id')->nullable();
            $table->string('created_by_core_user_id')->nullable();
            $table->string('updated_by_core_user_id')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->string('entry_key')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_rotation_run_id', 'entry_key'], 'pkpa_logbook_entry_unique_key');
            $table->index(['pkpa_rotation_run_id', 'status'], 'pkpa_logbook_run_status_idx');
        });

        Schema::create('pkpa_logbook_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_logbook_entry_id')->constrained('pkpa_logbook_entries')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('disk', 64);
            $table->string('path');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('file_size');
            $table->string('checksum', 128)->nullable();
            $table->string('status', 32)->default('active');
            $table->string('uploaded_by_core_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['pkpa_logbook_entry_id', 'status'], 'pkpa_logbook_attachment_entry_status_idx');
        });

        Schema::create('pkpa_logbook_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_logbook_entry_id')->constrained('pkpa_logbook_entries')->cascadeOnDelete();
            $table->string('reviewer_type', 48);
            $table->string('reviewer_core_user_id');
            $table->string('action', 48);
            $table->text('comments')->nullable();
            $table->timestamp('reviewed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['pkpa_logbook_entry_id', 'action'], 'pkpa_logbook_review_entry_action_idx');
            $table->index(['reviewer_core_user_id', 'reviewer_type'], 'pkpa_logbook_review_reviewer_idx');
        });

        Schema::create('pkpa_rotation_progress_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id')->constrained('pkpa_rotation_runs')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('scheduled_days_elapsed')->default(0);
            $table->unsignedInteger('scheduled_days_total')->default(0);
            $table->unsignedInteger('attendance_expected_count')->default(0);
            $table->unsignedInteger('attendance_submitted_count')->default(0);
            $table->unsignedInteger('attendance_approved_count')->default(0);
            $table->unsignedInteger('attendance_problem_count')->default(0);
            $table->unsignedInteger('logbook_expected_count')->default(0);
            $table->unsignedInteger('logbook_submitted_count')->default(0);
            $table->unsignedInteger('logbook_approved_count')->default(0);
            $table->unsignedInteger('logbook_revision_count')->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->string('progress_status', 48)->default('not_started');
            $table->json('blocking_issues')->nullable();
            $table->string('generated_by', 64)->default('system');
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->unique(['pkpa_rotation_run_id', 'snapshot_date'], 'pkpa_rotation_progress_daily_unique');
        });

        Schema::create('pkpa_rotation_publication_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id');
            $table->foreign(
                'pkpa_rotation_run_id',
                'pkpa_publication_sync_run_fk'
            )->references('id')->on('pkpa_rotation_runs')->cascadeOnDelete();
            $table->foreignId('old_published_assignment_id')->nullable();
            $table->foreign(
                'old_published_assignment_id',
                'pkpa_publication_sync_old_assignment_fk'
            )->references('id')->on('pkpa_published_assignments')->nullOnDelete();
            $table->foreignId('new_published_assignment_id')->nullable();
            $table->foreign(
                'new_published_assignment_id',
                'pkpa_publication_sync_new_assignment_fk'
            )->references('id')->on('pkpa_published_assignments')->nullOnDelete();
            $table->string('change_type', 64);
            $table->string('status', 48);
            $table->string('impact_level', 32)->default('low');
            $table->text('message')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->string('processed_by_core_user_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['pkpa_rotation_run_id', 'status'], 'pkpa_publication_sync_run_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpa_rotation_publication_sync_logs');
        Schema::dropIfExists('pkpa_rotation_progress_snapshots');
        Schema::dropIfExists('pkpa_logbook_reviews');
        Schema::dropIfExists('pkpa_logbook_attachments');
        Schema::dropIfExists('pkpa_logbook_entries');
        Schema::dropIfExists('pkpa_attendance_correction_requests');
        Schema::dropIfExists('pkpa_attendance_records');
        Schema::dropIfExists('pkpa_rotation_supervisor_histories');
        Schema::dropIfExists('pkpa_rotation_status_histories');
        Schema::dropIfExists('pkpa_rotation_runs');
        Schema::dropIfExists('pkpa_rotation_operation_rules');
    }
};
