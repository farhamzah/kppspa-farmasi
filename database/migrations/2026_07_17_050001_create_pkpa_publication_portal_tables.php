<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpa_placement_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->restrictOnDelete();
            $table->foreignId('pkpa_placement_plan_id')->constrained('pkpa_placement_plans')->restrictOnDelete();
            $table->unsignedInteger('publication_number');
            $table->unsignedInteger('revision_number')->default(0);
            $table->string('code', 80);
            $table->string('title');
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('is_current')->default(false)->index();
            $table->string('current_key', 80)->nullable()->unique();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->json('summary')->nullable();
            $table->json('validation_snapshot')->nullable();
            $table->string('published_by_core_user_id', 80)->nullable();
            $table->string('withdrawn_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_id', 'publication_number'], 'pkpa_publications_program_number_unique');
            $table->index(['pkpa_program_id', 'status'], 'pkpa_publications_program_status_index');
        });

        Schema::create('pkpa_published_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_placement_publication_id')->constrained('pkpa_placement_publications')->restrictOnDelete();
            $table->foreignId('source_rotation_assignment_id')->nullable();
            $table->foreign(
                'source_rotation_assignment_id',
                'pkpa_published_assignment_source_fk'
            )->references('id')->on('pkpa_rotation_assignments')->nullOnDelete();
            $table->foreignId('pkpa_enrollment_id')->constrained('pkpa_enrollments')->restrictOnDelete();
            $table->foreignId('pkpa_enrollment_requirement_id');
            $table->foreign(
                'pkpa_enrollment_requirement_id',
                'pkpa_published_assignment_req_fk'
            )->references('id')->on('pkpa_enrollment_requirements')->restrictOnDelete();
            $table->foreignId('practice_domain_id')->nullable()->constrained('pkpa_practice_domains')->nullOnDelete();
            $table->foreignId('practice_domain_option_id')->nullable()->constrained('pkpa_practice_domain_options')->nullOnDelete();
            $table->foreignId('practice_site_id')->nullable()->constrained('pkpa_practice_sites')->nullOnDelete();
            $table->foreignId('program_site_id')->nullable()->constrained('pkpa_program_sites')->nullOnDelete();
            $table->foreignId('availability_period_id')->nullable()->constrained('pkpa_site_availability_periods')->nullOnDelete();
            $table->string('student_core_user_id', 80)->index();
            $table->string('student_number_snapshot', 80)->nullable()->index();
            $table->string('student_name_snapshot')->nullable();
            $table->string('student_group_snapshot')->nullable();
            $table->string('practice_domain_name_snapshot')->nullable();
            $table->string('practice_domain_option_name_snapshot')->nullable();
            $table->string('practice_site_name_snapshot')->nullable();
            $table->text('practice_site_address_snapshot')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('duration_value_snapshot', 8, 2)->nullable();
            $table->string('duration_unit_snapshot', 32)->nullable();
            $table->unsignedInteger('effective_days_snapshot')->nullable();
            $table->unsignedInteger('practice_hours_snapshot')->nullable();
            $table->string('status', 32)->default('scheduled')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['pkpa_placement_publication_id', 'pkpa_enrollment_requirement_id'], 'pkpa_published_assignments_pub_req_unique');
            $table->index(['student_core_user_id', 'status'], 'pkpa_published_assignments_student_status_index');
        });

        Schema::create('pkpa_published_assignment_supervisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_published_assignment_id');
            $table->foreign(
                'pkpa_published_assignment_id',
                'pkpa_pub_supervisors_assignment_fk'
            )->references('id')->on('pkpa_published_assignments')->restrictOnDelete();
            $table->foreignId('source_assignment_supervisor_id')->nullable();
            $table->foreign(
                'source_assignment_supervisor_id',
                'pkpa_pub_supervisors_source_fk'
            )->references('id')->on('pkpa_rotation_assignment_supervisors')->nullOnDelete();
            $table->string('supervisor_type', 20)->index();
            $table->string('core_user_id', 80)->index();
            $table->string('name_snapshot')->nullable();
            $table->string('email_snapshot')->nullable();
            $table->string('role_snapshot')->nullable();
            $table->string('position_snapshot')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->string('status', 32)->default('assigned')->index();
            $table->timestamps();
            $table->index(['supervisor_type', 'core_user_id'], 'pkpa_published_supervisors_type_core_index');
        });

        Schema::create('pkpa_schedule_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_placement_publication_id');
            $table->foreign(
                'pkpa_placement_publication_id',
                'pkpa_ack_publication_fk'
            )->references('id')->on('pkpa_placement_publications')->cascadeOnDelete();
            $table->foreignId('pkpa_published_assignment_id')->nullable();
            $table->foreign(
                'pkpa_published_assignment_id',
                'pkpa_ack_assignment_fk'
            )->references('id')->on('pkpa_published_assignments')->cascadeOnDelete();
            $table->string('core_user_id', 80)->index();
            $table->string('audience_type', 32)->index();
            $table->string('acknowledgement_type', 32)->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('ip_address_hash', 96)->nullable();
            $table->string('user_agent_summary')->nullable();
            $table->timestamps();
            $table->unique(['pkpa_placement_publication_id', 'pkpa_published_assignment_id', 'core_user_id', 'audience_type', 'acknowledgement_type'], 'pkpa_schedule_ack_unique');
        });

        Schema::create('pkpa_placement_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->restrictOnDelete();
            $table->foreignId('pkpa_placement_publication_id');
            $table->foreign(
                'pkpa_placement_publication_id',
                'pkpa_change_requests_publication_fk'
            )->references('id')->on('pkpa_placement_publications')->restrictOnDelete();
            $table->string('request_number', 80);
            $table->string('request_type', 50)->index();
            $table->string('status', 32)->default('draft')->index();
            $table->text('reason');
            $table->json('impact_summary')->nullable();
            $table->string('requested_by_core_user_id', 80)->nullable();
            $table->string('reviewed_by_core_user_id', 80)->nullable();
            $table->string('approved_by_core_user_id', 80)->nullable();
            $table->string('rejected_by_core_user_id', 80)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_id', 'request_number'], 'pkpa_change_requests_program_number_unique');
        });

        Schema::create('pkpa_placement_change_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_placement_change_request_id');
            $table->foreign(
                'pkpa_placement_change_request_id',
                'pkpa_change_items_request_fk'
            )->references('id')->on('pkpa_placement_change_requests')->cascadeOnDelete();
            $table->foreignId('old_published_assignment_id')->nullable();
            $table->foreign(
                'old_published_assignment_id',
                'pkpa_change_items_old_assignment_fk'
            )->references('id')->on('pkpa_published_assignments')->nullOnDelete();
            $table->foreignId('pkpa_enrollment_id')->nullable()->constrained('pkpa_enrollments')->nullOnDelete();
            $table->foreignId('pkpa_enrollment_requirement_id')->nullable();
            $table->foreign(
                'pkpa_enrollment_requirement_id',
                'pkpa_change_items_requirement_fk'
            )->references('id')->on('pkpa_enrollment_requirements')->nullOnDelete();
            $table->string('change_type', 50)->index();
            $table->json('before_snapshot')->nullable();
            $table->json('proposed_snapshot')->nullable();
            $table->foreignId('applied_published_assignment_id')->nullable();
            $table->foreign(
                'applied_published_assignment_id',
                'pkpa_change_items_applied_assignment_fk'
            )->references('id')->on('pkpa_published_assignments')->nullOnDelete();
            $table->string('validation_status', 32)->default('pending')->index();
            $table->json('validation_messages')->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 80)->index();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('recipient_core_user_id', 80)->index();
            $table->string('recipient_email_snapshot')->nullable();
            $table->string('channel', 20)->index();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->text('failure_message')->nullable();
            $table->string('notification_key', 160)->unique();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id'], 'pkpa_notification_deliveries_entity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpa_notification_deliveries');
        Schema::dropIfExists('pkpa_placement_change_request_items');
        Schema::dropIfExists('pkpa_placement_change_requests');
        Schema::dropIfExists('pkpa_schedule_acknowledgements');
        Schema::dropIfExists('pkpa_published_assignment_supervisors');
        Schema::dropIfExists('pkpa_published_assignments');
        Schema::dropIfExists('pkpa_placement_publications');
    }
};
