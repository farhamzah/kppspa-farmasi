<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kp_scores', function (Blueprint $table) {
            $table->dropUnique('kp_scores_assignment_component_unique');
            $table->unique(['kp_assignment_id', 'kp_assessment_component_id', 'assessor_user_id'], 'kp_scores_assignment_component_assessor_unique');
        });

        Schema::table('kp_final_scores', function (Blueprint $table) {
            $table->decimal('attendance_score_override', 6, 2)->nullable()->after('final_grade');
            $table->text('attendance_note')->nullable()->after('attendance_score_override');
            $table->foreignId('attendance_overridden_by')->nullable()->after('attendance_note')->constrained('users')->nullOnDelete();
            $table->timestamp('attendance_overridden_at')->nullable()->after('attendance_overridden_by');
        });
    }

    public function down(): void
    {
        Schema::table('kp_final_scores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_overridden_by');
            $table->dropColumn(['attendance_score_override', 'attendance_note', 'attendance_overridden_at']);
        });

        Schema::table('kp_scores', function (Blueprint $table) {
            $table->dropUnique('kp_scores_assignment_component_assessor_unique');
            $table->unique(['kp_assignment_id', 'kp_assessment_component_id'], 'kp_scores_assignment_component_unique');
        });
    }
};
