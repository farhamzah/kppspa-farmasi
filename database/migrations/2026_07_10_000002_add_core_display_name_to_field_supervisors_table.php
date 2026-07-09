<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_supervisors', function (Blueprint $table): void {
            if (! Schema::hasColumn('field_supervisors', 'core_external_person_id')) {
                $table->unsignedBigInteger('core_external_person_id')->nullable()->after('core_user_id')->index();
            }

            if (! Schema::hasColumn('field_supervisors', 'core_display_name')) {
                $table->string('core_display_name')->nullable()->after('core_external_person_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('field_supervisors', function (Blueprint $table): void {
            if (Schema::hasColumn('field_supervisors', 'core_display_name')) {
                $table->dropColumn('core_display_name');
            }

            if (Schema::hasColumn('field_supervisors', 'core_external_person_id')) {
                $table->dropIndex(['core_external_person_id']);
                $table->dropColumn('core_external_person_id');
            }
        });
    }
};
