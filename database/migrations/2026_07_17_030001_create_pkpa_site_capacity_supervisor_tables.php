<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpa_program_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->restrictOnDelete();
            $table->foreignId('practice_site_id')->constrained('pkpa_practice_sites')->restrictOnDelete();
            $table->foreignId('pkpa_program_domain_id')->constrained('pkpa_program_domains')->restrictOnDelete();
            $table->foreignId('practice_domain_id');
            $table->foreign(
                'practice_domain_id',
                'pkpa_program_sites_domain_fk'
            )->references('id')->on('pkpa_practice_domains')->restrictOnDelete();
            $table->foreignId('practice_domain_option_id')->nullable()->constrained('pkpa_practice_domain_options')->nullOnDelete();
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('registration_notes')->nullable();
            $table->text('operational_notes')->nullable();
            $table->text('requirements_notes')->nullable();
            $table->unsignedInteger('default_minimum_students')->nullable();
            $table->unsignedInteger('default_maximum_students')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->string('activated_by_core_user_id', 80)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_id', 'practice_site_id'], 'pkpa_program_sites_program_site_unique');
            $table->index(['pkpa_program_id', 'practice_domain_id'], 'pkpa_program_sites_program_domain_index');
        });

        Schema::create('pkpa_site_availability_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_site_id')->constrained('pkpa_program_sites')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('minimum_students')->nullable();
            $table->unsignedInteger('maximum_students');
            $table->unsignedInteger('reserved_slots')->default(0);
            $table->json('operational_days')->nullable();
            $table->time('daily_start_time')->nullable();
            $table->time('daily_end_time')->nullable();
            $table->string('status', 32)->default('available')->index();
            $table->text('notes')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['pkpa_program_site_id', 'start_date', 'end_date'], 'pkpa_site_availability_period_index');
        });

        Schema::create('pkpa_site_field_supervisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_site_id')->constrained('pkpa_practice_sites')->restrictOnDelete();
            $table->string('core_user_id', 80);
            $table->string('name_snapshot')->nullable();
            $table->string('email_snapshot')->nullable();
            $table->string('professional_id_snapshot')->nullable();
            $table->string('core_account_status_snapshot', 50)->nullable();
            $table->string('role_snapshot')->nullable();
            $table->string('position_title')->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->unsignedInteger('maximum_active_students')->nullable();
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamp('last_core_synced_at')->nullable();
            $table->string('last_core_sync_status', 32)->nullable();
            $table->text('last_core_sync_message')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['practice_site_id', 'core_user_id'], 'pkpa_site_field_supervisors_site_core_unique');
            $table->index('core_account_status_snapshot', 'pkpa_site_field_core_status_idx');
            $table->index('last_core_sync_status', 'pkpa_site_field_sync_status_idx');
        });

        Schema::create('pkpa_internal_supervisor_eligibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->restrictOnDelete();
            $table->foreignId('practice_domain_id');
            $table->foreign(
                'practice_domain_id',
                'pkpa_internal_elig_domain_fk'
            )->references('id')->on('pkpa_practice_domains')->restrictOnDelete();
            $table->string('core_user_id', 80);
            $table->string('name_snapshot')->nullable();
            $table->string('email_snapshot')->nullable();
            $table->string('lecturer_id_snapshot')->nullable();
            $table->string('core_account_status_snapshot', 50)->nullable();
            $table->string('role_snapshot')->nullable();
            $table->unsignedInteger('maximum_active_students')->nullable();
            $table->unsignedInteger('maximum_students_per_program')->nullable();
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamp('last_core_synced_at')->nullable();
            $table->string('last_core_sync_status', 32)->nullable();
            $table->text('last_core_sync_message')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_program_id', 'practice_domain_id', 'core_user_id'], 'pkpa_internal_elig_program_domain_core_unique');
            $table->index('core_account_status_snapshot', 'pkpa_internal_elig_core_status_idx');
            $table->index('last_core_sync_status', 'pkpa_internal_elig_sync_status_idx');
        });

        Schema::create('pkpa_supervisor_unavailability_periods', function (Blueprint $table) {
            $table->id();
            $table->string('supervisor_type', 20)->index();
            $table->foreignId('internal_supervisor_eligibility_id')->nullable();
            $table->foreign(
                'internal_supervisor_eligibility_id',
                'pkpa_supervisor_unavail_internal_fk'
            )->references('id')->on('pkpa_internal_supervisor_eligibilities')->cascadeOnDelete();
            $table->foreignId('site_field_supervisor_id')->nullable();
            $table->foreign(
                'site_field_supervisor_id',
                'pkpa_supervisor_unavail_field_fk'
            )->references('id')->on('pkpa_site_field_supervisors')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('status', 32)->default('active')->index();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['supervisor_type', 'start_date', 'end_date'], 'pkpa_supervisor_unavailability_type_period_index');
        });

        Schema::create('pkpa_supervisor_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('supervisor_type', 20)->index();
            $table->string('core_user_id', 80)->index();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('status', 32)->index();
            $table->text('message')->nullable();
            $table->json('synced_fields')->nullable();
            $table->string('synced_by_core_user_id', 80)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['target_type', 'target_id'], 'pkpa_supervisor_sync_logs_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpa_supervisor_sync_logs');
        Schema::dropIfExists('pkpa_supervisor_unavailability_periods');
        Schema::dropIfExists('pkpa_internal_supervisor_eligibilities');
        Schema::dropIfExists('pkpa_site_field_supervisors');
        Schema::dropIfExists('pkpa_site_availability_periods');
        Schema::dropIfExists('pkpa_program_sites');
    }
};
