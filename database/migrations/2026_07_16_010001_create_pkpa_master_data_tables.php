<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpa_programs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('academic_year', 20)->nullable()->index();
            $table->string('cohort_name')->nullable();
            $table->string('semester', 30)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->dateTime('registration_start_at')->nullable();
            $table->dateTime('registration_end_at')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->string('activated_by_core_user_id', 80)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('pkpa_practice_domains', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('short_name', 80)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pkpa_practice_domain_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_domain_id')->constrained('pkpa_practice_domains')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['practice_domain_id', 'code'], 'pkpa_domain_options_domain_code_unique');
        });

        Schema::create('pkpa_program_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->constrained('pkpa_programs')->cascadeOnDelete();
            $table->foreignId('practice_domain_id')->constrained('pkpa_practice_domains')->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->string('selection_mode', 32)->default('direct');
            $table->unsignedInteger('minimum_option_count')->default(0);
            $table->decimal('duration_value', 8, 2)->nullable();
            $table->string('duration_unit', 32)->nullable();
            $table->unsignedInteger('minimum_effective_days')->nullable();
            $table->unsignedInteger('minimum_practice_hours')->nullable();
            $table->decimal('weight_percentage', 5, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->unique(['pkpa_program_id', 'practice_domain_id'], 'pkpa_program_domains_program_domain_unique');
        });

        Schema::create('pkpa_practice_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_domain_id')->constrained('pkpa_practice_domains')->restrictOnDelete();
            $table->foreignId('practice_domain_option_id')->nullable()->constrained('pkpa_practice_domain_options')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('village')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('province')->nullable()->index();
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone', 50)->nullable();
            $table->date('cooperation_start_date')->nullable();
            $table->date('cooperation_end_date')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['cooperation_start_date', 'cooperation_end_date'], 'pkpa_sites_cooperation_dates_index');
        });

        Schema::create('pkpa_master_audits', function (Blueprint $table) {
            $table->id();
            $table->string('actor_core_user_id', 80)->nullable()->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('action', 80)->index();
            $table->string('entity_type', 120)->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpa_master_audits');
        Schema::dropIfExists('pkpa_practice_sites');
        Schema::dropIfExists('pkpa_program_domains');
        Schema::dropIfExists('pkpa_practice_domain_options');
        Schema::dropIfExists('pkpa_practice_domains');
        Schema::dropIfExists('pkpa_programs');
    }
};
