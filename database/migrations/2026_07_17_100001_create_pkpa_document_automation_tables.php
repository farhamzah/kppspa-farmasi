<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpa_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('scope_type', 40)->default('program')->index();
            $table->json('output_formats')->nullable();
            $table->boolean('requires_number')->default(false);
            $table->boolean('requires_signatory')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pkpa_document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_document_type_id')->constrained('pkpa_document_types')->restrictOnDelete();
            $table->foreignId('pkpa_program_id')->nullable()->constrained('pkpa_programs')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('name');
            $table->unsignedInteger('version_number')->default(1);
            $table->string('status', 30)->default('draft')->index();
            $table->boolean('is_current')->default(false)->index();
            $table->string('current_key', 160)->nullable()->unique();
            $table->string('template_engine', 40)->default('html');
            $table->string('template_file_disk', 40)->nullable();
            $table->string('template_file_path')->nullable();
            $table->string('template_file_checksum', 96)->nullable();
            $table->longText('template_content')->nullable();
            $table->json('available_placeholders')->nullable();
            $table->string('page_size', 20)->default('A4');
            $table->string('orientation', 20)->default('portrait');
            $table->json('margin_config')->nullable();
            $table->json('header_config')->nullable();
            $table->json('footer_config')->nullable();
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->text('instructions')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->string('activated_by_core_user_id', 80)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_document_type_id', 'pkpa_program_id', 'code', 'version_number'], 'pkpa_doc_templates_type_program_code_version_unique');
        });

        Schema::create('pkpa_document_numbering_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_document_type_id')->constrained('pkpa_document_types')->restrictOnDelete();
            $table->foreignId('pkpa_program_id')->nullable()->constrained('pkpa_programs')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('name');
            $table->string('pattern');
            $table->string('sequence_scope', 40)->default('document_type');
            $table->string('reset_policy', 40)->default('never');
            $table->unsignedBigInteger('current_sequence')->default(0);
            $table->string('prefix', 80)->nullable();
            $table->string('suffix', 80)->nullable();
            $table->string('year_format', 20)->default('Y');
            $table->string('month_format', 20)->default('m');
            $table->string('status', 30)->default('draft')->index();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_document_type_id', 'pkpa_program_id', 'code'], 'pkpa_doc_numbering_type_program_code_unique');
        });

        Schema::create('pkpa_document_signatory_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_program_id')->nullable()->constrained('pkpa_programs')->cascadeOnDelete();
            $table->foreignId('pkpa_document_type_id')->nullable()->constrained('pkpa_document_types')->nullOnDelete();
            $table->string('signatory_role', 80);
            $table->string('core_user_id', 80)->nullable();
            $table->string('name_snapshot');
            $table->string('title_snapshot')->nullable();
            $table->string('employee_number_snapshot', 80)->nullable();
            $table->string('signature_mode', 40)->default('name_only');
            $table->date('effective_start_date');
            $table->date('effective_end_date');
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamp('last_core_synced_at')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['pkpa_program_id', 'pkpa_document_type_id', 'status'], 'pkpa_doc_signatory_scope_status_index');
        });

        Schema::create('pkpa_generated_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_document_type_id')->constrained('pkpa_document_types')->restrictOnDelete();
            $table->foreignId('pkpa_document_template_id')->nullable()->constrained('pkpa_document_templates')->nullOnDelete();
            $table->foreignId('pkpa_program_id')->nullable()->constrained('pkpa_programs')->nullOnDelete();
            $table->string('scope_type', 40)->default('program')->index();
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            $table->string('document_number', 160)->nullable()->unique();
            $table->date('document_date')->nullable();
            $table->string('title');
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->json('generation_context')->nullable();
            $table->string('approval_status', 30)->default('not_required')->index();
            $table->string('approved_by_core_user_id', 80)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('published_by_core_user_id', 80)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('cancelled_by_core_user_id', 80)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('generation_key', 160)->unique();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->string('updated_by_core_user_id', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['scope_type', 'scope_id', 'status'], 'pkpa_generated_documents_scope_status_index');
        });

        Schema::create('pkpa_generated_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_generated_document_id');
            $table->foreign(
                'pkpa_generated_document_id',
                'pkpa_doc_versions_document_fk'
            )->references('id')->on('pkpa_generated_documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('output_format', 20);
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('checksum', 96);
            $table->string('generation_status', 30)->default('generated')->index();
            $table->text('generation_log_summary')->nullable();
            $table->string('generated_by_core_user_id', 80)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pkpa_generated_document_id', 'version_number', 'output_format'], 'pkpa_doc_versions_document_version_format_unique');
        });

        Schema::create('pkpa_document_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_generated_document_id');
            $table->foreign(
                'pkpa_generated_document_id',
                'pkpa_doc_recipients_document_fk'
            )->references('id')->on('pkpa_generated_documents')->cascadeOnDelete();
            $table->string('recipient_type', 40)->index();
            $table->string('core_user_id', 80)->nullable()->index();
            $table->string('name_snapshot')->nullable();
            $table->string('email_snapshot')->nullable();
            $table->string('organization_snapshot')->nullable();
            $table->string('access_scope', 40)->default('portal');
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->unique(['pkpa_generated_document_id', 'recipient_type', 'core_user_id'], 'pkpa_doc_recipients_unique');
        });

        Schema::create('pkpa_document_distribution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_generated_document_id');
            $table->foreign(
                'pkpa_generated_document_id',
                'pkpa_doc_distribution_document_fk'
            )->references('id')->on('pkpa_generated_documents')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('pkpa_document_recipients')->nullOnDelete();
            $table->string('channel', 30)->index();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->string('distributed_by_core_user_id', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('pkpa_document_generation_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpa_generated_document_id')->nullable();
            $table->foreign(
                'pkpa_generated_document_id',
                'pkpa_doc_jobs_document_fk'
            )->references('id')->on('pkpa_generated_documents')->nullOnDelete();
            $table->string('generation_key', 160)->unique();
            $table->string('status', 30)->default('queued')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->json('requested_formats')->nullable();
            $table->json('request_snapshot')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('created_by_core_user_id', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpa_document_generation_jobs');
        Schema::dropIfExists('pkpa_document_distribution_logs');
        Schema::dropIfExists('pkpa_document_recipients');
        Schema::dropIfExists('pkpa_generated_document_versions');
        Schema::dropIfExists('pkpa_generated_documents');
        Schema::dropIfExists('pkpa_document_signatory_configs');
        Schema::dropIfExists('pkpa_document_numbering_rules');
        Schema::dropIfExists('pkpa_document_templates');
        Schema::dropIfExists('pkpa_document_types');
    }
};
