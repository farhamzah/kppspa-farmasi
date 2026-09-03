<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pkpa_portfolio_case_reports', function (Blueprint $table) {
            $table->text('past_medical_history')->nullable();
            $table->text('family_history')->nullable();
            $table->json('drp_items')->nullable();
            $table->text('evaluation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pkpa_portfolio_case_reports', function (Blueprint $table) {
            $table->dropColumn(['past_medical_history', 'family_history', 'drp_items', 'evaluation']);
        });
    }
};
