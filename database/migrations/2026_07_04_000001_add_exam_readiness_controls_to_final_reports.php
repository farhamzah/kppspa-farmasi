<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kp_final_reports', function (Blueprint $table): void {
            $table->string('final_document_url', 2048)->nullable()->after('review_note');
            $table->string('final_document_label')->nullable()->after('final_document_url');
            $table->string('internal_review_status')->default('pending')->after('approved_at');
            $table->foreignId('internal_reviewed_by')->nullable()->after('internal_review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('internal_reviewed_at')->nullable()->after('internal_reviewed_by');
            $table->text('internal_review_note')->nullable()->after('internal_reviewed_at');
            $table->string('field_review_status')->default('pending')->after('internal_review_note');
            $table->foreignId('field_reviewed_by')->nullable()->after('field_review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('field_reviewed_at')->nullable()->after('field_reviewed_by');
            $table->text('field_review_note')->nullable()->after('field_reviewed_at');
        });

        Schema::create('kp_report_guidance_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kp_assignment_id')->constrained('kp_assignments')->cascadeOnDelete();
            $table->date('guidance_date');
            $table->string('topic');
            $table->text('student_note')->nullable();
            $table->enum('status', ['draft', 'menunggu_validasi', 'disetujui', 'revisi', 'ditolak'])->default('menunggu_validasi');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_note')->nullable();
            $table->timestamps();

            $table->index(['kp_assignment_id', 'status'], 'kp_report_guidance_assignment_status_idx');
        });

        DB::table('kp_final_reports')
            ->where('status', 'disetujui')
            ->update([
                'internal_review_status' => 'disetujui',
                'field_review_status' => 'disetujui',
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('kp_report_guidance_logs');

        Schema::table('kp_final_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('internal_reviewed_by');
            $table->dropConstrainedForeignId('field_reviewed_by');
            $table->dropColumn([
                'final_document_url',
                'final_document_label',
                'internal_review_status',
                'internal_reviewed_at',
                'internal_review_note',
                'field_review_status',
                'field_reviewed_at',
                'field_review_note',
            ]);
        });
    }
};
