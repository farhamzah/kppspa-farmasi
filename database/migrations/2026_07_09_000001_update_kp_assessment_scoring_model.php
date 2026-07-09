<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureMysqlIndex('kp_scores', 'kp_scores_assignment_fk_idx', ['kp_assignment_id']);
        $this->ensureMysqlIndex('kp_scores', 'kp_scores_component_fk_idx', ['kp_assessment_component_id']);

        Schema::table('kp_scores', function (Blueprint $table) {
            if ($this->indexExists('kp_scores', 'kp_scores_assignment_component_unique')) {
                $table->dropUnique('kp_scores_assignment_component_unique');
            }

            if (! $this->indexExists('kp_scores', 'kp_scores_assignment_component_assessor_unique')) {
                $table->unique(['kp_assignment_id', 'kp_assessment_component_id', 'assessor_user_id'], 'kp_scores_assignment_component_assessor_unique');
            }
        });

        if (! Schema::hasColumn('kp_final_scores', 'attendance_score_override')) {
            Schema::table('kp_final_scores', function (Blueprint $table) {
                $table->decimal('attendance_score_override', 6, 2)->nullable()->after('final_grade');
                $table->text('attendance_note')->nullable()->after('attendance_score_override');
                $table->foreignId('attendance_overridden_by')->nullable()->after('attendance_note')->constrained('users')->nullOnDelete();
                $table->timestamp('attendance_overridden_at')->nullable()->after('attendance_overridden_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kp_final_scores', 'attendance_score_override')) {
            Schema::table('kp_final_scores', function (Blueprint $table) {
                $table->dropConstrainedForeignId('attendance_overridden_by');
                $table->dropColumn(['attendance_score_override', 'attendance_note', 'attendance_overridden_at']);
            });
        }

        Schema::table('kp_scores', function (Blueprint $table) {
            if ($this->indexExists('kp_scores', 'kp_scores_assignment_component_assessor_unique')) {
                $table->dropUnique('kp_scores_assignment_component_assessor_unique');
            }

            if (! $this->indexExists('kp_scores', 'kp_scores_assignment_component_unique')) {
                $table->unique(['kp_assignment_id', 'kp_assessment_component_id'], 'kp_scores_assignment_component_unique');
            }
        });
    }

    private function ensureMysqlIndex(string $table, string $index, array $columns): void
    {
        if (DB::getDriverName() !== 'mysql' || $this->indexExists($table, $index)) {
            return;
        }

        $quotedColumns = collect($columns)
            ->map(fn (string $column): string => '`'.$column.'`')
            ->implode(', ');

        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$quotedColumns})");
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        }

        return collect(Schema::getIndexes($table))->contains(fn (array $item): bool => ($item['name'] ?? null) === $index);
    }
};
