<?php

namespace Database\Seeders;

use App\Models\FieldSupervisor;
use App\Models\KpAssessmentComponent;
use App\Models\KpAssignment;
use App\Models\KpDocument;
use App\Models\KpDocumentRequirement;
use App\Models\KpExam;
use App\Models\KpExamLog;
use App\Models\KpExamRequest;
use App\Models\KpFinalReport;
use App\Models\KpFinalReportFile;
use App\Models\KpFinalScore;
use App\Models\KpLogbook;
use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpPlaceFieldSupervisor;
use App\Models\KpPlaceQuota;
use App\Models\KpPlaceSelection;
use App\Models\KpRegistration;
use App\Models\KpScore;
use App\Models\KpScoreLog;
use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DemoEndToEndSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $admin = $this->user('Admin SI-KP', 'admin@sikp.test', ['admin']);
        $coordinator = $this->user('Koordinator KP', 'koordinator@sikp.test', ['koordinator_kp', 'pembimbing_dalam']);
        $studentAUser = $this->user('Alya Putri Farmasi', 'mahasiswa@sikp.test', ['mahasiswa']);
        $studentBUser = $this->user('Bima Pratama Farmasi', 'mahasiswa2@sikp.test', ['mahasiswa']);
        $lecturerAUser = $this->user('Dr. Rina Kartika, M.Farm', 'dosen@sikp.test', ['pembimbing_dalam']);
        $lecturerBUser = $this->user('Apt. Dodi Saputra, M.Farm', 'dosen2@sikp.test', ['pembimbing_dalam']);
        $fieldUser = $this->user('Sari Wulandari, S.Farm', 'lapangan@sikp.test', ['pembimbing_lapangan']);
        $examinerUser = $this->user('Dr. Hendra Wijaya, M.Farm', 'penguji@sikp.test', ['penguji']);

        $studentA = $this->student($studentAUser, '221063120001');
        $studentB = $this->student($studentBUser, '221063120002');
        $lecturerA = $this->lecturer($lecturerAUser, '198801012020121001');
        $lecturerB = $this->lecturer($lecturerBUser, '198902022021121002');
        $examiner = $this->lecturer($examinerUser, '198503032019031003');
        $fieldSupervisor = $this->fieldSupervisor($fieldUser);

        $period = $this->period($coordinator);
        [$apotekQuota, $rsQuota] = $this->placesAndQuotas($period, $coordinator, $fieldSupervisor);
        $requirements = $this->requirements($period, $coordinator);

        $registrationA = $this->registration($period, $studentA, $coordinator, 'KP-'.now()->year.'-9001');
        $registrationB = $this->registration($period, $studentB, $coordinator, 'KP-'.now()->year.'-9002');
        $this->approvedDocuments($registrationA, $requirements, $coordinator);
        $this->approvedDocuments($registrationB, $requirements, $coordinator);

        $selectionA = $this->selection($period, $registrationA, $studentA, $apotekQuota, $studentAUser);
        $selectionB = $this->selection($period, $registrationB, $studentB, $rsQuota, $studentBUser);

        $assignmentA = $this->assignment($period, $registrationA, $selectionA, $studentA, $lecturerA, $fieldSupervisor, $coordinator, 'berjalan');
        $assignmentB = $this->assignment($period, $registrationB, $selectionB, $studentB, $lecturerB, $fieldSupervisor, $coordinator, 'aktif');

        $this->logbooksComplete($assignmentA, $fieldUser);
        $this->logbooksInProgress($assignmentB, $fieldUser);

        $this->approvedFinalReport($assignmentA, $lecturerAUser);
        $this->draftFinalReport($assignmentB, $studentBUser);

        $exam = $this->completedExam($assignmentA, $studentAUser, $coordinator, $lecturerA, $examiner);
        $components = $this->assessmentComponents($period, $coordinator);
        $this->publishedScores($assignmentA, $exam, $components, $lecturerAUser, $fieldUser, $examinerUser, $coordinator);
    }

    private function user(string $name, string $email, array $roles): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
                'status' => 'active',
                'must_change_password' => false,
                'profile_completed' => true,
            ]
        );

        $roleIds = Role::whereIn('name', $roles)->pluck('id')->all();
        $user->roles()->sync($roleIds);

        return $user->refresh();
    }

    private function student(User $user, string $nim): Student
    {
        return Student::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nim' => $nim,
                'study_program' => 'Farmasi',
                'semester' => 7,
                'class_name' => 'Farmasi A',
                'phone' => '08123456'.substr($nim, -4),
                'address' => 'Karawang',
                'gender' => 'perempuan',
                'birth_place' => 'Karawang',
                'birth_date' => '2003-08-12',
                'status' => 'active',
                'profile_completed_at' => now(),
            ]
        );
    }

    private function lecturer(User $user, string $nidn): Lecturer
    {
        return Lecturer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nidn_nip' => $nidn,
                'employee_number' => $nidn,
                'study_program' => 'Farmasi',
                'department' => 'Farmakologi dan Farmasi Klinik',
                'expertise' => 'Farmasi Klinik',
                'phone' => '08223344'.substr($nidn, -4),
                'address' => 'Universitas Buana Perjuangan Karawang',
                'status' => 'active',
                'profile_completed_at' => now(),
            ]
        );
    }

    private function fieldSupervisor(User $user): FieldSupervisor
    {
        return FieldSupervisor::updateOrCreate(
            ['user_id' => $user->id],
            [
                'institution_name' => 'Apotek Sehat UBP',
                'position' => 'Apoteker Penanggung Jawab',
                'phone' => '083812345678',
                'address' => 'Karawang',
                'status' => 'active',
                'profile_completed_at' => now(),
            ]
        );
    }

    private function period(User $coordinator): KpPeriod
    {
        return KpPeriod::updateOrCreate(
            ['name' => 'KP Farmasi Demo 2026'],
            [
                'academic_year' => '2025/2026',
                'semester' => 'genap',
                'registration_start_at' => now()->subDays(14),
                'registration_end_at' => now()->addDays(14),
                'document_verification_start_at' => now()->subDays(13),
                'document_verification_end_at' => now()->addDays(15),
                'selection_start_at' => now()->subDays(7),
                'selection_end_at' => now()->addDays(7),
                'kp_start_date' => now()->subDays(5)->toDateString(),
                'kp_end_date' => now()->addDays(45)->toDateString(),
                'status' => 'dibuka',
                'description' => 'Periode demo end-to-end untuk UAT dan presentasi MVP.',
                'created_by' => $coordinator->id,
                'updated_by' => $coordinator->id,
            ]
        );
    }

    private function placesAndQuotas(KpPeriod $period, User $coordinator, FieldSupervisor $fieldSupervisor): array
    {
        $places = [
            ['name' => 'Apotek Sehat UBP', 'type' => 'apotek', 'city' => 'Karawang', 'quota' => 2],
            ['name' => 'RS Mitra Farmasi', 'type' => 'rumah_sakit', 'city' => 'Karawang', 'quota' => 1],
            ['name' => 'Puskesmas Karawang', 'type' => 'puskesmas', 'city' => 'Karawang', 'quota' => 1],
        ];

        $quotas = [];
        foreach ($places as $item) {
            $place = KpPlace::updateOrCreate(
                ['name' => $item['name']],
                [
                    'type' => $item['type'],
                    'address' => 'Jl. Demo KP No. 12, '.$item['city'],
                    'city' => $item['city'],
                    'province' => 'Jawa Barat',
                    'contact_person' => 'PIC '.$item['name'],
                    'phone' => '0267-123456',
                    'email' => str($item['name'])->slug()->append('@demo.test')->toString(),
                    'description' => 'Tempat KP demo untuk presentasi SI-KP.',
                    'status' => 'aktif',
                    'created_by' => $coordinator->id,
                    'updated_by' => $coordinator->id,
                ]
            );

            KpPlaceFieldSupervisor::updateOrCreate(
                ['kp_place_id' => $place->id, 'field_supervisor_id' => $fieldSupervisor->id],
                ['status' => 'aktif', 'created_by' => $coordinator->id]
            );

            $quotas[] = KpPlaceQuota::updateOrCreate(
                ['kp_period_id' => $period->id, 'kp_place_id' => $place->id],
                [
                    'quota' => $item['quota'],
                    'is_open' => true,
                    'notes' => 'Kuota demo UAT.',
                    'created_by' => $coordinator->id,
                    'updated_by' => $coordinator->id,
                ]
            );
        }

        return $quotas;
    }

    private function requirements(KpPeriod $period, User $coordinator): array
    {
        $names = ['KRS', 'Transkrip sementara', 'Bukti pembayaran', 'Surat permohonan KP'];

        return collect($names)->map(function (string $name, int $index) use ($period, $coordinator) {
            return KpDocumentRequirement::updateOrCreate(
                ['kp_period_id' => $period->id, 'name' => $name],
                [
                    'description' => 'Dokumen '.$name.' untuk pendaftaran KP.',
                    'is_required' => true,
                    'allowed_file_types' => 'pdf,jpg,jpeg,png',
                    'max_file_size_mb' => 5,
                    'sort_order' => $index + 1,
                    'status' => 'aktif',
                    'created_by' => $coordinator->id,
                    'updated_by' => $coordinator->id,
                ]
            );
        })->all();
    }

    private function registration(KpPeriod $period, Student $student, User $coordinator, string $number): KpRegistration
    {
        return KpRegistration::updateOrCreate(
            ['kp_period_id' => $period->id, 'student_id' => $student->id],
            [
                'registration_number' => $number,
                'status' => 'terverifikasi',
                'notes' => 'Data demo terverifikasi.',
                'submitted_at' => now()->subDays(10),
                'verified_by' => $coordinator->id,
                'verified_at' => now()->subDays(9),
                'verification_note' => 'Berkas demo lengkap dan valid.',
            ]
        );
    }

    private function approvedDocuments(KpRegistration $registration, array $requirements, User $reviewer): void
    {
        foreach ($requirements as $requirement) {
            KpDocument::updateOrCreate(
                [
                    'kp_registration_id' => $registration->id,
                    'kp_document_requirement_id' => $requirement->id,
                ],
                [
                    'original_filename' => str($requirement->name)->slug()->append('.pdf')->toString(),
                    'file_path' => 'demo/berkas/'.$registration->id.'/'.str($requirement->name)->slug().'.pdf',
                    'file_disk' => 'local',
                    'file_mime' => 'application/pdf',
                    'file_size' => 256000,
                    'status' => 'disetujui',
                    'review_note' => 'Dokumen demo disetujui.',
                    'uploaded_at' => now()->subDays(10),
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now()->subDays(9),
                ]
            );
        }
    }

    private function selection(KpPeriod $period, KpRegistration $registration, Student $student, KpPlaceQuota $quota, User $selectedBy): KpPlaceSelection
    {
        return KpPlaceSelection::updateOrCreate(
            ['active_key' => $period->id.'-'.$student->id],
            [
                'kp_period_id' => $period->id,
                'kp_registration_id' => $registration->id,
                'student_id' => $student->id,
                'kp_place_id' => $quota->kp_place_id,
                'kp_place_quota_id' => $quota->id,
                'selected_at' => now()->subDays(6),
                'selected_by' => $selectedBy->id,
                'status' => 'aktif',
                'note' => 'Pilihan tempat demo.',
            ]
        );
    }

    private function assignment(KpPeriod $period, KpRegistration $registration, KpPlaceSelection $selection, Student $student, Lecturer $lecturer, FieldSupervisor $fieldSupervisor, User $actor, string $status): KpAssignment
    {
        return KpAssignment::updateOrCreate(
            ['active_key' => $period->id.'-'.$student->id],
            [
                'kp_period_id' => $period->id,
                'kp_registration_id' => $registration->id,
                'kp_place_selection_id' => $selection->id,
                'student_id' => $student->id,
                'kp_place_id' => $selection->kp_place_id,
                'internal_supervisor_id' => $lecturer->id,
                'field_supervisor_id' => $fieldSupervisor->id,
                'status' => $status,
                'assigned_by' => $actor->id,
                'assigned_at' => now()->subDays(5),
                'started_at' => now()->subDays(5)->toDateString(),
                'ended_at' => now()->addDays(45)->toDateString(),
                'note' => 'Penempatan demo lengkap.',
            ]
        );
    }

    private function logbooksComplete(KpAssignment $assignment, User $validator): void
    {
        foreach ([1, 2, 3] as $day) {
            $activityDate = Carbon::today()->subDays(4 - $day);

            KpLogbook::updateOrCreate(
                ['kp_assignment_id' => $assignment->id, 'activity_date' => $activityDate],
                [
                    'start_time' => '08:00',
                    'end_time' => '15:00',
                    'activity_title' => 'Kegiatan KP Hari '.$day,
                    'activity_description' => 'Melakukan observasi pelayanan farmasi dan pencatatan kegiatan harian.',
                    'learning_outcome' => 'Memahami alur pelayanan dan dokumentasi farmasi.',
                    'obstacle' => 'Adaptasi alur kerja tempat KP.',
                    'solution' => 'Diskusi dengan pembimbing lapangan.',
                    'status' => 'disetujui',
                    'submitted_at' => now()->subDays(3),
                    'validated_by' => $validator->id,
                    'validated_at' => now()->subDays(2),
                    'validation_note' => 'Logbook demo disetujui.',
                ]
            );
        }
    }

    private function logbooksInProgress(KpAssignment $assignment, User $validator): void
    {
        KpLogbook::updateOrCreate(
            ['kp_assignment_id' => $assignment->id, 'activity_date' => Carbon::today()->subDays(2)],
            [
                'start_time' => '08:00',
                'end_time' => '14:30',
                'activity_title' => 'Pengenalan SOP Tempat KP',
                'activity_description' => 'Mempelajari SOP pelayanan dan dokumentasi obat.',
                'learning_outcome' => 'Memahami SOP awal tempat KP.',
                'status' => 'menunggu_validasi',
                'submitted_at' => now()->subDay(),
            ]
        );

        KpLogbook::updateOrCreate(
            ['kp_assignment_id' => $assignment->id, 'activity_date' => Carbon::today()->subDay()],
            [
                'start_time' => '08:00',
                'end_time' => '14:00',
                'activity_title' => 'Pelayanan Resep Dasar',
                'activity_description' => 'Membantu observasi pelayanan resep dasar.',
                'learning_outcome' => 'Memahami proses skrining awal resep.',
                'status' => 'revisi',
                'submitted_at' => now(),
                'validated_by' => $validator->id,
                'validated_at' => now(),
                'validation_note' => 'Tambahkan detail hasil pembelajaran.',
            ]
        );
    }

    private function approvedFinalReport(KpAssignment $assignment, User $reviewer): void
    {
        $report = KpFinalReport::updateOrCreate(
            ['kp_assignment_id' => $assignment->id],
            [
                'current_version' => 1,
                'status' => 'disetujui',
                'submitted_at' => now()->subDays(2),
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now()->subDay(),
                'review_note' => 'Laporan akhir demo disetujui.',
                'approved_at' => now()->subDay(),
            ]
        );

        KpFinalReportFile::updateOrCreate(
            ['kp_final_report_id' => $report->id, 'version' => 1],
            [
                'original_filename' => 'laporan-akhir-demo.pdf',
                'file_path' => 'demo/laporan/'.$assignment->id.'/laporan-akhir-demo.pdf',
                'file_disk' => 'local',
                'file_mime' => 'application/pdf',
                'file_size' => 1024000,
                'uploaded_by' => $assignment->student->user_id,
                'uploaded_at' => now()->subDays(2),
                'note' => 'File laporan demo.',
            ]
        );
    }

    private function draftFinalReport(KpAssignment $assignment, User $studentUser): void
    {
        $report = KpFinalReport::updateOrCreate(
            ['kp_assignment_id' => $assignment->id],
            ['current_version' => 1, 'status' => 'draft', 'review_note' => null]
        );

        KpFinalReportFile::updateOrCreate(
            ['kp_final_report_id' => $report->id, 'version' => 1],
            [
                'original_filename' => 'draft-laporan-bima.docx',
                'file_path' => 'demo/laporan/'.$assignment->id.'/draft-laporan-bima.docx',
                'file_disk' => 'local',
                'file_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'file_size' => 512000,
                'uploaded_by' => $studentUser->id,
                'uploaded_at' => now()->subDay(),
                'note' => 'Draft laporan sedang berjalan.',
            ]
        );
    }

    private function completedExam(KpAssignment $assignment, User $studentUser, User $coordinator, Lecturer $supervisor, Lecturer $examiner): KpExam
    {
        $request = KpExamRequest::updateOrCreate(
            ['kp_assignment_id' => $assignment->id],
            [
                'requested_by' => $studentUser->id,
                'status' => 'dijadwalkan',
                'request_note' => 'Pengajuan sidang demo.',
                'submitted_at' => now()->subDay(),
                'reviewed_by' => $coordinator->id,
                'reviewed_at' => now()->subDay(),
                'review_note' => 'Pengajuan sidang demo disetujui.',
            ]
        );

        $exam = KpExam::updateOrCreate(
            ['kp_assignment_id' => $assignment->id],
            [
                'kp_exam_request_id' => $request->id,
                'supervisor_id' => $supervisor->id,
                'examiner_id' => $examiner->id,
                'exam_date' => now()->subDay()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
                'mode' => 'offline',
                'room' => 'Ruang Sidang Farmasi 1',
                'meeting_link' => null,
                'status' => 'selesai',
                'scheduled_by' => $coordinator->id,
                'scheduled_at' => now()->subDays(2),
                'note' => 'Sidang demo selesai.',
            ]
        );

        KpExamLog::updateOrCreate(
            ['kp_exam_request_id' => $request->id, 'kp_exam_id' => $exam->id, 'action' => 'exam_completed'],
            [
                'user_id' => $coordinator->id,
                'old_status' => 'dijadwalkan',
                'new_status' => 'selesai',
                'note' => 'Sidang demo selesai.',
            ]
        );

        return $exam;
    }

    private function assessmentComponents(KpPeriod $period, User $coordinator): array
    {
        $rows = [
            ['pembimbing_dalam', 'Kualitas laporan', 20, 1],
            ['pembimbing_dalam', 'Progres bimbingan', 10, 2],
            ['pembimbing_lapangan', 'Kedisiplinan', 20, 3],
            ['pembimbing_lapangan', 'Keterampilan kerja', 20, 4],
            ['penguji', 'Presentasi', 15, 5],
            ['penguji', 'Penguasaan materi', 15, 6],
        ];

        return collect($rows)->map(function (array $row) use ($period, $coordinator) {
            return KpAssessmentComponent::updateOrCreate(
                ['kp_period_id' => $period->id, 'assessor_type' => $row[0], 'component_name' => $row[1]],
                [
                    'description' => 'Komponen nilai demo '.$row[1].'.',
                    'weight' => $row[2],
                    'max_score' => 100,
                    'sort_order' => $row[3],
                    'is_required' => true,
                    'status' => 'aktif',
                    'created_by' => $coordinator->id,
                    'updated_by' => $coordinator->id,
                ]
            );
        })->all();
    }

    private function publishedScores(KpAssignment $assignment, KpExam $exam, array $components, User $internal, User $field, User $examiner, User $coordinator): void
    {
        $assessorUsers = [
            'pembimbing_dalam' => $internal,
            'pembimbing_lapangan' => $field,
            'penguji' => $examiner,
        ];

        $scores = [
            'Kualitas laporan' => 88,
            'Progres bimbingan' => 90,
            'Kedisiplinan' => 92,
            'Keterampilan kerja' => 87,
            'Presentasi' => 86,
            'Penguasaan materi' => 85,
        ];

        foreach ($components as $component) {
            $scoreValue = $scores[$component->component_name] ?? 85;
            $score = KpScore::updateOrCreate(
                ['kp_assignment_id' => $assignment->id, 'kp_assessment_component_id' => $component->id, 'assessor_user_id' => $assessorUsers[$component->assessor_type]->id],
                [
                    'kp_exam_id' => $component->assessor_type === 'penguji' ? $exam->id : null,
                    'assessor_type' => $component->assessor_type,
                    'score' => $scoreValue,
                    'weighted_score' => round(($scoreValue * (float) $component->weight) / 100, 2),
                    'note' => 'Nilai demo UAT.',
                    'status' => 'locked',
                    'submitted_at' => now()->subDay(),
                    'locked_at' => now(),
                ]
            );

            KpScoreLog::updateOrCreate(
                ['kp_assignment_id' => $assignment->id, 'kp_score_id' => $score->id, 'action' => 'score_locked'],
                [
                    'user_id' => $coordinator->id,
                    'old_status' => 'submitted',
                    'new_status' => 'locked',
                    'note' => 'Nilai demo dikunci.',
                    'metadata' => ['score' => $scoreValue],
                ]
            );
        }

        $finalScore = KpFinalScore::updateOrCreate(
            ['kp_assignment_id' => $assignment->id],
            [
                'attendance_score_override' => 100,
                'attendance_note' => 'Nilai kehadiran demo lengkap.',
                'attendance_overridden_by' => $coordinator->id,
                'attendance_overridden_at' => now(),
                'status' => 'published',
                'calculated_at' => now(),
                'finalized_by' => $coordinator->id,
                'finalized_at' => now(),
                'published_at' => now(),
                'note' => 'Nilai akhir demo sudah dipublikasikan.',
            ]
        );
        $final = app(\App\Support\KpScoreCalculator::class)->breakdown($assignment->fresh())['final_score'];
        $finalScore->update([
            'final_score' => $final,
            'final_grade' => $this->grade($final),
        ]);
        $finalScore = $finalScore->fresh();

        KpScoreLog::updateOrCreate(
            ['kp_assignment_id' => $assignment->id, 'kp_final_score_id' => $finalScore->id, 'action' => 'final_score_published'],
            [
                'user_id' => $coordinator->id,
                'old_status' => 'locked',
                'new_status' => 'published',
                'note' => 'Final score demo dipublikasikan.',
                'metadata' => ['final_score' => $final, 'grade' => $finalScore->final_grade],
            ]
        );
    }

    private function grade(float $score): string
    {
        return match (true) {
            $score >= 85 => 'A',
            $score >= 75 => 'B',
            $score >= 65 => 'C',
            $score >= 50 => 'D',
            default => 'E',
        };
    }
}
