<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pkpa_logbook_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('pkpa_logbook_attachments', 'attachment_type')) {
                $table->string('attachment_type', 32)->default('file')->after('pkpa_logbook_entry_id');
            }

            if (! Schema::hasColumn('pkpa_logbook_attachments', 'external_url')) {
                $table->text('external_url')->nullable()->after('checksum');
            }

            if (! Schema::hasColumn('pkpa_logbook_attachments', 'link_label')) {
                $table->string('link_label')->nullable()->after('external_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pkpa_logbook_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('pkpa_logbook_attachments', 'link_label')) {
                $table->dropColumn('link_label');
            }

            if (Schema::hasColumn('pkpa_logbook_attachments', 'external_url')) {
                $table->dropColumn('external_url');
            }

            if (Schema::hasColumn('pkpa_logbook_attachments', 'attachment_type')) {
                $table->dropColumn('attachment_type');
            }
        });
    }
};
