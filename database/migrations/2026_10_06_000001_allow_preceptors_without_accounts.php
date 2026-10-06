<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pkpa_site_field_supervisors', function (Blueprint $table) {
            $table->string('core_user_id', 80)->nullable()->change();
        });
        foreach (['pkpa_rotation_assignment_supervisors', 'pkpa_published_assignment_supervisors'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('core_user_id', 80)->nullable()->change();
            });
        }
        Schema::table('pkpa_rotation_supervisor_histories', function (Blueprint $table) {
            $table->string('core_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Names recorded without accounts must remain recoverable on rollback.
    }
};
