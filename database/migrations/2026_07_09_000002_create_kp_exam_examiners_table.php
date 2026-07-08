<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kp_exam_examiners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kp_exam_id')->constrained('kp_exams')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['kp_exam_id', 'lecturer_id']);
        });

        DB::table('kp_exams')
            ->whereNotNull('examiner_id')
            ->orderBy('id')
            ->get(['id', 'examiner_id'])
            ->each(function ($exam): void {
                DB::table('kp_exam_examiners')->insertOrIgnore([
                    'kp_exam_id' => $exam->id,
                    'lecturer_id' => $exam->examiner_id,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('kp_exam_examiners');
    }
};
