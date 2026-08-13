<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpa_portfolio_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->nullable()->index();
            $table->foreignId('pkpa_program_domain_id')->nullable()->index();
            $table->foreignId('practice_domain_id')->index();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('version_number')->default(1);
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('is_current')->default(false)->index();
            $table->string('current_key')->nullable()->index();
            $table->json('export_configuration')->nullable();
            $table->json('integrity_pact')->nullable();
            $table->string('created_by_core_user_id')->nullable()->index();
            $table->string('updated_by_core_user_id')->nullable()->index();
            $table->timestamp('activated_at')->nullable();
            $table->string('activated_by_core_user_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['code', 'version_number'], 'pkpa_portfolio_template_code_version_unique');
        });

        Schema::create('pkpa_portfolio_template_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_portfolio_template_id')->index('pkpa_port_tpl_sections_tpl_id_idx');
            $table->string('code');
            $table->string('title');
            $table->string('source_type', 64)->index();
            $table->string('reviewer_type', 32)->nullable()->index();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('minimum_items')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('requirement_rules')->nullable();
            $table->json('content_schema')->nullable();
            $table->longText('static_content')->nullable();
            $table->timestamps();
            $table->unique(['pkpa_portfolio_template_id', 'code'], 'pkpa_portfolio_section_template_code_unique');
        });

        Schema::create('pkpa_rotation_portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_run_id')->index();
            $table->foreignId('pkpa_portfolio_template_id')->index();
            $table->foreignId('pkpa_enrollment_id')->index();
            $table->foreignId('pkpa_program_id')->index();
            $table->foreignId('practice_domain_id')->index();
            $table->unsignedInteger('portfolio_number')->default(1);
            $table->string('status', 64)->default('draft')->index();
            $table->boolean('is_current')->default(true)->index();
            $table->string('current_key')->index();
            $table->json('identity_snapshot')->nullable();
            $table->json('placement_snapshot')->nullable();
            $table->json('progress_snapshot')->nullable();
            $table->string('integrity_pact_version')->nullable();
            $table->longText('integrity_pact_text')->nullable();
            $table->timestamp('integrity_acknowledged_at')->nullable();
            $table->string('integrity_acknowledged_by_core_user_id')->nullable()->index('pkpa_rotation_portfolios_integrity_by_idx');
            $table->timestamp('submitted_at')->nullable();
            $table->string('submitted_by_core_user_id')->nullable()->index();
            $table->timestamp('field_verified_at')->nullable();
            $table->string('field_verified_by_core_user_id')->nullable()->index('pkpa_rotation_portfolios_field_by_idx');
            $table->timestamp('internal_approved_at')->nullable();
            $table->string('internal_approved_by_core_user_id')->nullable()->index('pkpa_rotation_portfolios_internal_by_idx');
            $table->timestamp('locked_at')->nullable();
            $table->string('locked_by_core_user_id')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->string('published_by_core_user_id')->nullable()->index('pkpa_rotation_portfolios_published_by_idx');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_rotation_run_id', 'is_current'], 'pkpa_rotation_portfolio_current_unique');
            $table->unique('current_key');
        });

        Schema::create('pkpa_portfolio_section_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_portfolio_id')->index('pkpa_port_section_records_portfolio_idx');
            $table->foreignId('pkpa_portfolio_template_section_id')->index('pkpa_port_section_records_tpl_section_idx');
            $table->string('section_code')->index();
            $table->string('source_type', 64)->index();
            $table->string('status', 32)->default('pending')->index();
            $table->json('auto_source_refs')->nullable();
            $table->json('manual_payload')->nullable();
            $table->json('completion_snapshot')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['pkpa_rotation_portfolio_id', 'section_code'], 'pkpa_portfolio_record_section_unique');
        });

        Schema::create('pkpa_portfolio_case_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_portfolio_id')->index('pkpa_port_cases_portfolio_idx');
            $table->string('case_code');
            $table->date('case_date')->nullable();
            $table->string('patient_initials', 16)->nullable();
            $table->string('gender', 32)->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('height_cm', 6, 2)->nullable();
            $table->text('complaint')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('history')->nullable();
            $table->text('allergy')->nullable();
            $table->text('medication_use')->nullable();
            $table->json('drug_data')->nullable();
            $table->json('soap')->nullable();
            $table->text('drp')->nullable();
            $table->text('intervention')->nullable();
            $table->text('monitoring')->nullable();
            $table->text('education')->nullable();
            $table->text('conclusion')->nullable();
            $table->text('references')->nullable();
            $table->boolean('anonymization_confirmed')->default(false);
            $table->json('privacy_warnings')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('created_by_core_user_id')->nullable()->index();
            $table->string('reviewed_by_core_user_id')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_rotation_portfolio_id', 'case_code'], 'pkpa_portfolio_case_code_unique');
        });

        Schema::create('pkpa_portfolio_weekly_reflections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_portfolio_id')->index('pkpa_port_reflections_portfolio_idx');
            $table->unsignedInteger('week_number');
            $table->date('period_start_date')->nullable();
            $table->date('period_end_date')->nullable();
            $table->string('unit')->nullable();
            $table->text('target')->nullable();
            $table->text('achievement')->nullable();
            $table->text('obstacle')->nullable();
            $table->text('solution')->nullable();
            $table->text('reflection')->nullable();
            $table->text('next_plan')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamps();
            $table->unique(['pkpa_rotation_portfolio_id', 'week_number'], 'pkpa_portfolio_reflection_week_unique');
        });

        Schema::create('pkpa_portfolio_self_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_portfolio_id')->index('pkpa_port_self_assessments_portfolio_idx');
            $table->string('aspect');
            $table->unsignedTinyInteger('score');
            $table->text('evidence_experience')->nullable();
            $table->text('strength')->nullable();
            $table->text('weakness')->nullable();
            $table->text('improvement_plan')->nullable();
            $table->text('final_reflection')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('pkpa_portfolio_documentation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_portfolio_id')->index('pkpa_port_docs_portfolio_idx');
            $table->string('category')->nullable();
            $table->date('activity_date')->nullable();
            $table->string('activity');
            $table->text('description')->nullable();
            $table->string('competency_label')->nullable();
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('anonymization_confirmed')->default(false);
            $table->boolean('consent_confirmed')->default(false);
            $table->string('status', 32)->default('draft')->index();
            $table->string('field_reviewed_by_core_user_id')->nullable()->index('pkpa_port_docs_field_reviewed_by_idx');
            $table->timestamp('field_reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pkpa_portfolio_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_portfolio_id')->index('pkpa_port_reviews_portfolio_idx');
            $table->foreignId('pkpa_portfolio_section_record_id')->nullable()->index();
            $table->string('reviewer_type', 32)->index();
            $table->string('reviewer_core_user_id')->index();
            $table->string('action', 32)->index();
            $table->text('comments')->nullable();
            $table->json('privacy_findings')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_portfolio_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_portfolio_id')->index('pkpa_port_publications_portfolio_idx');
            $table->unsignedInteger('publication_number')->default(1);
            $table->string('status', 32)->default('published')->index();
            $table->json('publication_snapshot');
            $table->timestamp('published_at')->nullable();
            $table->string('published_by_core_user_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['pkpa_rotation_portfolio_id', 'publication_number'], 'pkpa_portfolio_publication_number_unique');
        });

        Schema::create('pkpa_portfolio_export_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_rotation_portfolio_id')->index('pkpa_port_exports_portfolio_idx');
            $table->foreignId('pkpa_portfolio_publication_id')->nullable()->index('pkpa_port_exports_publication_idx');
            $table->unsignedInteger('version_number');
            $table->string('output_format', 16)->index();
            $table->string('status', 32)->default('generated')->index();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('checksum', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->string('generated_by_core_user_id')->nullable()->index('pkpa_port_exports_generated_by_idx');
            $table->timestamps();
            $table->unique(['pkpa_rotation_portfolio_id', 'version_number', 'output_format'], 'pkpa_portfolio_export_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpa_portfolio_export_versions');
        Schema::dropIfExists('pkpa_portfolio_publications');
        Schema::dropIfExists('pkpa_portfolio_reviews');
        Schema::dropIfExists('pkpa_portfolio_documentation_items');
        Schema::dropIfExists('pkpa_portfolio_self_assessments');
        Schema::dropIfExists('pkpa_portfolio_weekly_reflections');
        Schema::dropIfExists('pkpa_portfolio_case_reports');
        Schema::dropIfExists('pkpa_portfolio_section_records');
        Schema::dropIfExists('pkpa_rotation_portfolios');
        Schema::dropIfExists('pkpa_portfolio_template_sections');
        Schema::dropIfExists('pkpa_portfolio_templates');
    }
};
