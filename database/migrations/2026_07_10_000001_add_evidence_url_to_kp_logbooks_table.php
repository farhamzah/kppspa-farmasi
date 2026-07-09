<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kp_logbooks', function (Blueprint $table) {
            if (! Schema::hasColumn('kp_logbooks', 'evidence_url')) {
                $table->text('evidence_url')->nullable()->after('evidence_size');
            }

            if (! Schema::hasColumn('kp_logbooks', 'evidence_url_label')) {
                $table->string('evidence_url_label')->nullable()->after('evidence_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kp_logbooks', function (Blueprint $table) {
            if (Schema::hasColumn('kp_logbooks', 'evidence_url_label')) {
                $table->dropColumn('evidence_url_label');
            }

            if (Schema::hasColumn('kp_logbooks', 'evidence_url')) {
                $table->dropColumn('evidence_url');
            }
        });
    }
};
