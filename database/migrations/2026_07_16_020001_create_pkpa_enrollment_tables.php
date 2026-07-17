<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpa_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->restrictOnDelete();
            $table->string('core_user_id', 80);
            $table->string('student_number', 80)->nullable()->index();
            $table->string('student_name_snapshot')->nullable();
            $table->string('student_email_snapshot')->nullable();
            $table->string('study_program_snapshot')->nullable();
            $table->string('cohort_snapshot')->nullable();
            $table->string('academic_status_snapshot')->nullable();
            $table->string('core_account_status_snapshot', 50)->nullable()->index();
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_core_synced_at')->nullable();
            $table->string('last_core_sync_status', 32)->nullable()->index();
            $table->text('last_core_sync_message')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->string('cancelled_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_id', 'core_user_id'], 'pkpa_enrollments_program_core_unique');
        });

        Schema::create('pkpa_enrollment_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->cascadeOnDelete();
            $table->foreignId('pkpa_program_domain_id')->constrained('pkpa_program_domains')->restrictOnDelete();
            $table->foreignId('practice_domain_id')->constrained('pkpa_practice_domains')->restrictOnDelete();
            $table->string('selection_mode', 32)->default('direct');
            $table->unsignedInteger('required_option_count')->default(0);
            $table->foreignId('selected_practice_domain_option_id')->nullable();
            $table->foreign(
                'selected_practice_domain_option_id',
                'pkpa_enroll_req_domain_option_fk'
            )->references('id')->on('pkpa_practice_domain_options')->nullOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedTinyInteger('completion_percentage')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('waived_at')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->unique(['pkpa_enrollment_id', 'pkpa_program_domain_id'], 'pkpa_enrollment_req_enrollment_domain_unique');
        });

        Schema::create('pkpa_student_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('maximum_members')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_id', 'code'], 'pkpa_student_groups_program_code_unique');
        });

        Schema::create('pkpa_student_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_student_group_id')->constrained('pkpa_student_groups')->restrictOnDelete();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->restrictOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->text('notes')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->index(['pkpa_enrollment_id', 'status'], 'pkpa_group_members_enrollment_status_index');
        });

        Schema::create('pkpa_enrollment_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->restrictOnDelete();
            $table->string('original_filename')->nullable();
            $table->string('stored_filename')->nullable();
            $table->string('status', 32)->default('uploaded')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_enrollment_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('pkpa_enrollment_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('core_user_id', 80)->nullable();
            $table->string('student_number', 80)->nullable();
            $table->string('student_name')->nullable();
            $table->string('email')->nullable();
            $table->string('group_code', 50)->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('validation_status', 50)->default('invalid')->index();
            $table->json('validation_messages')->nullable();
            $table->string('resolved_core_user_id', 80)->nullable();
            $table->foreignId('resolved_enrollment_id')->nullable()->constrained('pkpa_enrollments')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->index(['import_batch_id', 'validation_status'], 'pkpa_import_rows_batch_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpa_enrollment_import_rows');
        Schema::dropIfExists('pkpa_enrollment_import_batches');
        Schema::dropIfExists('pkpa_student_group_members');
        Schema::dropIfExists('pkpa_student_groups');
        Schema::dropIfExists('pkpa_enrollment_requirements');
        Schema::dropIfExists('pkpa_enrollments');
    }
};
