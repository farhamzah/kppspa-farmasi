<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pkpa_portfolio_signed_documents')) {
            Schema::create('pkpa_portfolio_signed_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pkpa_rotation_portfolio_id');
                $table->unsignedInteger('version_number');
                $table->string('disk')->default('local');
                $table->string('path');
                $table->string('download_filename');
                $table->unsignedBigInteger('file_size');
                $table->string('checksum', 64);
                $table->string('content_checksum', 64);
                $table->string('uploaded_by_core_user_id', 80);
                $table->timestamp('signatures_confirmed_at');
                $table->timestamps();
                $table->unique(['pkpa_rotation_portfolio_id', 'version_number'], 'pkpa_signed_document_version_unique');
            });
        }
        Schema::table('pkpa_portfolio_signed_documents', function (Blueprint $table) {
            $table->foreign('pkpa_rotation_portfolio_id', 'pkpa_signed_document_portfolio_fk')
                ->references('id')->on('pkpa_rotation_portfolios')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpa_portfolio_signed_documents');
    }
};
