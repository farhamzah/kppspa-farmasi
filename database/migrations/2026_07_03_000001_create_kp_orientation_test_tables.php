<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kp_orientation_tests', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->enum('type', ['pre', 'post']);
            $table->text('description')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->unsignedInteger('total_points')->default(100);
            $table->timestamps();

            $table->unique('type');
        });

        Schema::create('kp_orientation_test_questions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('kp_orientation_test_id');
            $table->text('question_text');
            $table->json('choices');
            $table->unsignedTinyInteger('correct_choice_index');
            $table->text('explanation')->nullable();
            $table->unsignedInteger('points')->default(10);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('kp_orientation_test_id', 'kp_orient_questions_test_id_idx');
            $table->foreign('kp_orientation_test_id', 'kp_orient_questions_test_id_fk')
                ->references('id')
                ->on('kp_orientation_tests')
                ->cascadeOnDelete();
        });

        Schema::create('kp_orientation_test_attempts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('kp_orientation_test_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('status', ['submitted'])->default('submitted');
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('max_score')->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('kp_orientation_test_id', 'kp_orient_attempts_test_id_idx');
            $table->index('student_id', 'kp_orient_attempts_student_id_idx');
            $table->index('user_id', 'kp_orient_attempts_user_id_idx');
            $table->unique(['kp_orientation_test_id', 'student_id'], 'kp_orient_attempt_test_student_unique');
            $table->foreign('kp_orientation_test_id', 'kp_orient_attempts_test_id_fk')
                ->references('id')
                ->on('kp_orientation_tests')
                ->cascadeOnDelete();
            $table->foreign('student_id', 'kp_orient_attempts_student_id_fk')
                ->references('id')
                ->on('students')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'kp_orient_attempts_user_id_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::create('kp_orientation_test_answers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('kp_orientation_test_attempt_id');
            $table->unsignedBigInteger('kp_orientation_test_question_id');
            $table->unsignedTinyInteger('selected_choice_index');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('points_awarded')->default(0);
            $table->timestamps();

            $table->index('kp_orientation_test_attempt_id', 'kp_orient_answers_attempt_id_idx');
            $table->index('kp_orientation_test_question_id', 'kp_orient_answers_question_id_idx');
            $table->unique(['kp_orientation_test_attempt_id', 'kp_orientation_test_question_id'], 'kp_orientation_answer_unique');
            $table->foreign('kp_orientation_test_attempt_id', 'kp_orient_answers_attempt_id_fk')
                ->references('id')
                ->on('kp_orientation_test_attempts')
                ->cascadeOnDelete();
            $table->foreign('kp_orientation_test_question_id', 'kp_orient_answers_question_id_fk')
                ->references('id')
                ->on('kp_orientation_test_questions')
                ->cascadeOnDelete();
        });

        $this->seedDefaultTests();
    }

    public function down(): void
    {
        Schema::dropIfExists('kp_orientation_test_answers');
        Schema::dropIfExists('kp_orientation_test_attempts');
        Schema::dropIfExists('kp_orientation_test_questions');
        Schema::dropIfExists('kp_orientation_tests');
    }

    private function seedDefaultTests(): void
    {
        $now = now();
        $description = 'Evaluasi pemahaman pembekalan kerja praktik tentang attitude, etika, keselamatan, komunikasi profesional, dokumentasi, serta budaya kerja di rumah sakit, apotek, industri obat, dan industri kosmetik.';

        $tests = [
            ['title' => 'Pre-Test Pembekalan Kerja Praktik Mahasiswa Farmasi', 'type' => 'pre'],
            ['title' => 'Post-Test Pembekalan Kerja Praktik Mahasiswa Farmasi', 'type' => 'post'],
        ];

        $questions = $this->questions();

        foreach ($tests as $test) {
            $testId = DB::table('kp_orientation_tests')->insertGetId([
                'title' => $test['title'],
                'type' => $test['type'],
                'description' => $description,
                'status' => 'aktif',
                'total_points' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($questions as $index => $question) {
                DB::table('kp_orientation_test_questions')->insert([
                    'kp_orientation_test_id' => $testId,
                    'question_text' => $question['question_text'],
                    'choices' => json_encode($question['choices']),
                    'correct_choice_index' => $question['correct_choice_index'],
                    'explanation' => $question['explanation'],
                    'points' => 10,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questions(): array
    {
        return [
            [
                'question_text' => 'Setelah mengikuti pembekalan, prinsip utama yang harus dipegang mahasiswa selama kerja praktik adalah...',
                'choices' => [
                    'Menyelesaikan tugas secepat mungkin meskipun belum memahami prosedur',
                    'Mengutamakan keselamatan, etika, kepatuhan SOP, dan sikap profesional',
                    'Mengambil sebanyak mungkin foto kegiatan untuk bukti laporan',
                    'Melakukan semua pekerjaan sendiri agar terlihat mampu',
                ],
                'correct_choice_index' => 1,
                'explanation' => 'Mahasiswa KP harus mengutamakan keselamatan, etika, kepatuhan SOP, dan sikap profesional. Pengetahuan saja tidak cukup tanpa attitude dan integritas.',
            ],
            [
                'question_text' => 'Makna prinsip "Aktif belajar, bukan bertindak tanpa izin" adalah...',
                'choices' => [
                    'Mahasiswa boleh mencoba semua proses selama tujuannya belajar',
                    'Mahasiswa harus aktif bertanya dan mencatat, tetapi tetap mengikuti arahan pembimbing',
                    'Mahasiswa cukup diam dan menunggu semua instruksi diberikan',
                    'Mahasiswa boleh menggantikan peran pembimbing bila sudah memahami teori',
                ],
                'correct_choice_index' => 1,
                'explanation' => 'Mahasiswa harus aktif belajar, bertanya, dan mencatat. Namun tindakan di tempat praktik tetap harus mengikuti izin dan arahan pembimbing.',
            ],
            [
                'question_text' => 'Dalam komunikasi profesional, pola 3K yang tepat adalah...',
                'choices' => [
                    'Kritik, koreksi, dan keputusan',
                    'Klarifikasi, konfirmasi, dan catat',
                    'Kecepatan, ketegasan, dan keberanian',
                    'Kerahasiaan, kebebasan, dan kebiasaan',
                ],
                'correct_choice_index' => 1,
                'explanation' => 'Pola 3K adalah klarifikasi, konfirmasi, dan catat. Pola ini membantu mencegah salah paham instruksi di tempat praktik.',
            ],
            [
                'question_text' => 'Mahasiswa KP menerima instruksi dari pembimbing, tetapi belum memahami langkah yang harus dilakukan. Sikap yang paling tepat adalah...',
                'choices' => [
                    'Langsung mengerjakan agar tidak terlihat lambat',
                    'Bertanya dengan sopan untuk memastikan instruksi sebelum bertindak',
                    'Meminta teman mengerjakan tugas tersebut',
                    'Mencatat saja tanpa perlu bertanya',
                ],
                'correct_choice_index' => 1,
                'explanation' => 'Jika belum paham, mahasiswa harus bertanya dengan sopan sebelum bertindak. Lebih baik bertanya daripada menyebabkan kesalahan.',
            ],
            [
                'question_text' => 'Contoh pelanggaran etika digital selama kerja praktik adalah...',
                'choices' => [
                    'Mencatat pembelajaran di logbook pribadi',
                    'Meminta izin sebelum mengambil dokumentasi kegiatan',
                    'Mengunggah foto resep, dokumen mutu, atau area produksi tanpa izin',
                    'Menyimpan jadwal kerja praktik di kalender pribadi',
                ],
                'correct_choice_index' => 2,
                'explanation' => 'Memfoto atau mengunggah resep, data pasien, dokumen mutu, batch record, formula, atau area produksi tanpa izin dapat melanggar kerahasiaan dan aturan tempat praktik.',
            ],
            [
                'question_text' => 'Di rumah sakit, mahasiswa melihat adanya kemungkinan ketidaksesuaian identitas pasien dengan resep. Tindakan yang benar adalah...',
                'choices' => [
                    'Melanjutkan proses karena resep sudah ada',
                    'Menebak berdasarkan nama yang paling mirip',
                    'Mengubah data agar sesuai dengan resep',
                    'Menghentikan proses sementara dan melapor kepada pembimbing',
                ],
                'correct_choice_index' => 3,
                'explanation' => 'Ketidaksesuaian identitas pasien harus segera dilaporkan kepada pembimbing. Mahasiswa tidak boleh menebak, mengubah data, atau melanjutkan proses sendiri.',
            ],
            [
                'question_text' => 'Di apotek, pelanggan meminta obat keras atau antibiotik tanpa resep. Respons mahasiswa yang paling profesional adalah...',
                'choices' => [
                    'Memberikan obat karena pelanggan membutuhkan',
                    'Mengarahkan pelanggan kepada apoteker untuk mendapatkan penjelasan yang aman',
                    'Memberikan obat dengan dosis yang lebih rendah',
                    'Menolak dengan nada keras agar pelanggan tidak meminta lagi',
                ],
                'correct_choice_index' => 1,
                'explanation' => 'Mahasiswa tidak boleh memberikan obat keras atau antibiotik tanpa supervisi. Sikap yang tepat adalah mengarahkan pelanggan kepada apoteker.',
            ],
            [
                'question_text' => 'Dalam industri obat, mengapa mahasiswa tidak boleh mengubah catatan penimbangan atau dokumen batch sendiri?',
                'choices' => [
                    'Karena dokumen hanya boleh ditulis dengan pulpen tertentu',
                    'Karena perubahan data tanpa prosedur dapat merusak integritas data dan sistem mutu',
                    'Karena mahasiswa tidak perlu memahami dokumentasi',
                    'Karena dokumen batch tidak berhubungan dengan mutu produk',
                ],
                'correct_choice_index' => 1,
                'explanation' => 'Dokumen batch dan catatan penimbangan berkaitan dengan integritas data dan ketertelusuran mutu. Perubahan data harus mengikuti prosedur dan kewenangan.',
            ],
            [
                'question_text' => 'Perilaku yang paling sesuai di lingkungan industri kosmetik berbasis CPKB adalah...',
                'choices' => [
                    'Menjaga higiene personal, memakai APD sesuai aturan, dan mencegah kontaminasi',
                    'Membuat klaim produk sendiri agar terlihat kreatif',
                    'Mencoba mencampur bahan kosmetik tanpa arahan',
                    'Mengabaikan hairnet karena produk kosmetik bukan obat',
                ],
                'correct_choice_index' => 0,
                'explanation' => 'Dalam lingkungan CPKB, mahasiswa harus menjaga higiene, menggunakan APD sesuai aturan, menjaga kebersihan, dan mencegah kontaminasi.',
            ],
            [
                'question_text' => 'Jika mahasiswa melihat kesalahan, hampir terjadi kesalahan, atau kondisi tidak aman selama KP, sikap yang paling tepat adalah...',
                'choices' => [
                    'Diam agar tidak menimbulkan masalah',
                    'Menyalahkan teman yang berada di lokasi',
                    'Segera melapor kepada pembimbing dengan menyampaikan fakta dan mengikuti arahan',
                    'Menghapus catatan agar tidak menjadi temuan',
                ],
                'correct_choice_index' => 2,
                'explanation' => 'Sikap profesional adalah segera melapor kepada pembimbing, menyampaikan fakta, tidak menutupi kesalahan, dan mengikuti prosedur perbaikan.',
            ],
        ];
    }
};
