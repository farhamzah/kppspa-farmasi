<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kp_assignments', 'workday_pattern')) {
            Schema::table('kp_assignments', function (Blueprint $table): void {
                $table->string('workday_pattern', 32)->default('senin_jumat')->after('ended_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kp_assignments', 'workday_pattern')) {
            Schema::table('kp_assignments', function (Blueprint $table): void {
                $table->dropColumn('workday_pattern');
            });
        }
    }
};
