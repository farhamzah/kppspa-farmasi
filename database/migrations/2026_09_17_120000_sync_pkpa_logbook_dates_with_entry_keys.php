<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pkpa_logbook_entries')
            ->select(['id', 'entry_key', 'entry_date', 'period_start_date', 'period_end_date'])
            ->orderBy('id')
            ->each(function (object $entry): void {
                if (! preg_match('/^RUN:\\d+:(\\d{4}-\\d{2}-\\d{2})$/', (string) $entry->entry_key, $matches)) {
                    return;
                }

                $date = $matches[1];

                if (substr((string) $entry->entry_date, 0, 10) === $date
                    && substr((string) $entry->period_start_date, 0, 10) === $date
                    && substr((string) $entry->period_end_date, 0, 10) === $date) {
                    return;
                }

                DB::table('pkpa_logbook_entries')
                    ->where('id', $entry->id)
                    ->update([
                        'entry_date' => $date,
                        'period_start_date' => $date,
                        'period_end_date' => $date,
                    ]);
            });
    }

    public function down(): void
    {
        // This data repair intentionally has no rollback.
    }
};
