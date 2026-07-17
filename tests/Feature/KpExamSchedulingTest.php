<?php

namespace Tests\Feature;

use App\Models\FieldSupervisor;
use App\Models\KpAssignment;
use App\Models\KpExam;
use App\Models\KpExamRequest;
use App\Models\KpFinalReport;
use App\Models\KpLogbook;
use App\Models\KpReportGuidanceLog;
use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpRegistration;
use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KpExamSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;
    private Student $student;
    private User $supervisorUser;
    private Lecturer $supervisor;
    private User $examinerUser;
    private Lecturer $examiner;
    private User $secondExaminerUser;
    private Lecturer $secondExaminer;
    private Lecturer $nonExaminer;
    private User $fieldUser;
    private KpAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('admin-exam@test.local', ['admin']);
        $this->koordinator = $this->makeUser('koordinator-exam@test.local', ['koordinator_kp']);
        $this->mahasiswa = $this->makeUser('mahasiswa-exam@test.local', ['mahasiswa']);
        $this->student = $this->makeStudent($this->mahasiswa, '2210631230901');
        $this->supervisorUser = $this->makeUser('supervisor-exam@test.local', ['pembimbing_dalam']);
        $this->supervisor = Lecturer::create(['user_id' => $this->supervisorUser->id, 'nidn_nip' => '991101', 'status' => 'active']);
        $this->examinerUser = $this->makeUser('examiner-exam@test.local', ['penguji']);
        $this->examiner = Lecturer::create(['user_id' => $this->examinerUser->id, 'nidn_nip' => '991102', 'status' => 'active']);
        $this->secondExaminerUser = $this->makeUser('second-examiner-exam@test.local', ['penguji']);
        $this->secondExaminer = Lecturer::create(['user_id' => $this->secondExaminerUser->id, 'nidn_nip' => '991106', 'status' => 'active']);
        $nonExaminerUser = $this->makeUser('not-examiner@test.local', ['pembimbing_dalam']);
        $this->nonExaminer = Lecturer::create(['user_id' => $nonExaminerUser->id, 'nidn_nip' => '991103', 'status' => 'active']);
        $this->fieldUser = $this->makeUser('field-exam@test.local', ['pembimbing_lapangan']);
        $field = FieldSupervisor::create(['user_id' => $this->fieldUser->id, 'institution_name' => 'Apotek Sehat', 'position' => 'Supervisor', 'status' => 'active']);
        $this->assignment = $this->makeAssignment($this->student, $this->supervisor, $field);
    }

    public function test_login_page_opens_and_student_registration_sidebar_has_single_active_item(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('MY PSPA');

        $response = $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/pendaftaran-kp');

        $response->assertOk()->assertSee('Pendaftaran PKPA')->assertSee('Berkas PKPA');
        $this->assertSame(1, substr_count($response->getContent(), 'bg-cyan-700 text-white'));
    }

    public function test_student_can_only_submit_exam_request_after_final_report_is_approved_and_cannot_duplicate(): void
    {
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/sidang/ajukan', ['request_note' => 'Mohon dijadwalkan.'])
            ->assertSessionHasErrors('exam');

        $this->approvedFinalReport();

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/sidang')
            ->assertOk()
            ->assertSee('Ajukan Sidang');

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/sidang/ajukan', ['request_note' => 'Siap sidang.'])
            ->assertRedirect();

        $this->assertDatabaseHas('kp_exam_requests', ['kp_assignment_id' => $this->assignment->id, 'status' => 'diajukan']);
        $this->assertDatabaseHas('kp_exam_logs', ['action' => 'request_submitted']);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/sidang/ajukan')
            ->assertSessionHasErrors('exam');
    }

    public function test_admin_and_koordinator_can_monitor_exam_requests_but_field_supervisor_cannot(): void
    {
        $this->approvedFinalReport();
        $this->submittedExamRequest();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/exam-requests')
            ->assertOk()
            ->assertSee('Pengajuan Sidang');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/exam-requests')
            ->assertOk();

        $this->actingAs($this->fieldUser)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/management/exam-requests')
            ->assertForbidden();
    }

    public function test_koordinator_can_schedule_exam_and_student_supervisor_examiner_can_see_it(): void
    {
        $request = $this->submittedExamRequest();

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/exam-requests/'.$request->id.'/schedule', $this->validSchedulePayload())
            ->assertRedirect();

        $exam = KpExam::first();
        $this->assertSame('dijadwalkan', $request->fresh()->status);
        $this->assertEqualsCanonicalizing([$this->examiner->id, $this->secondExaminer->id], $exam->examiners()->pluck('lecturers.id')->all());
        $this->assertSame($this->examiner->id, $exam->examiner_id);
        $this->assertDatabaseHas('kp_exam_logs', ['action' => 'exam_scheduled']);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/sidang')
            ->assertOk()
            ->assertSee('Jadwal Sidang');

        $this->actingAs($this->supervisorUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/jadwal-sidang/'.$exam->id)
            ->assertOk()
            ->assertSee('Input nilai sidang akan tersedia pada tahap berikutnya.');

        $this->actingAs($this->examinerUser)->withSession(['active_role' => 'penguji'])
            ->get('/penguji/jadwal-sidang/'.$exam->id)
            ->assertOk()
            ->assertSee('Input nilai penguji akan tersedia pada tahap berikutnya.');

        $this->actingAs($this->secondExaminerUser)->withSession(['active_role' => 'penguji'])
            ->get('/penguji/jadwal-sidang/'.$exam->id)
            ->assertOk();
    }

    public function test_schedule_validation_rejects_invalid_examiner_time_room_and_link(): void
    {
        $request = $this->submittedExamRequest();

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/exam-requests/'.$request->id.'/schedule', $this->validSchedulePayload(['examiner_ids' => [$this->examiner->id]]))
            ->assertSessionHasErrors('examiner_ids');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/exam-requests/'.$request->id.'/schedule', $this->validSchedulePayload(['examiner_ids' => [$this->examiner->id, $this->nonExaminer->id]]))
            ->assertSessionHasErrors('examiner_ids');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/exam-requests/'.$request->id.'/schedule', $this->validSchedulePayload(['end_time' => '08:00']))
            ->assertSessionHasErrors('end_time');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/exam-requests/'.$request->id.'/schedule', $this->validSchedulePayload(['mode' => 'offline', 'room' => null]))
            ->assertSessionHasErrors('room');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/exam-requests/'.$request->id.'/schedule', $this->validSchedulePayload(['mode' => 'online', 'room' => null, 'meeting_link' => null]))
            ->assertSessionHasErrors('meeting_link');
    }

    public function test_supervisor_and_examiner_can_only_open_their_own_exam_schedule(): void
    {
        $exam = $this->scheduledExam();
        $otherSupervisorUser = $this->makeUser('other-supervisor-exam@test.local', ['pembimbing_dalam']);
        Lecturer::create(['user_id' => $otherSupervisorUser->id, 'nidn_nip' => '991104', 'status' => 'active']);
        $otherExaminerUser = $this->makeUser('other-examiner-exam@test.local', ['penguji']);
        Lecturer::create(['user_id' => $otherExaminerUser->id, 'nidn_nip' => '991105', 'status' => 'active']);

        $this->actingAs($otherSupervisorUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/jadwal-sidang/'.$exam->id)
            ->assertForbidden();

        $this->actingAs($otherExaminerUser)->withSession(['active_role' => 'penguji'])
            ->get('/penguji/jadwal-sidang/'.$exam->id)
            ->assertForbidden();
    }

    public function test_internal_supervisor_can_also_be_examiner_when_they_have_penguji_role(): void
    {
        $this->supervisorUser->roles()->syncWithoutDetaching(Role::where('name', 'penguji')->value('id'));
        $request = $this->submittedExamRequest();

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/exam-requests/'.$request->id.'/schedule', $this->validSchedulePayload([
                'examiner_ids' => [$this->supervisor->id, $this->examiner->id],
            ]))
            ->assertRedirect();

        $exam = KpExam::firstOrFail();
        $this->assertEqualsCanonicalizing([$this->supervisor->id, $this->examiner->id], $exam->examiners()->pluck('lecturers.id')->all());
    }

    public function test_admin_can_cancel_and_complete_exam_with_logs(): void
    {
        $exam = $this->scheduledExam();

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/exams/'.$exam->id.'/cancel', ['reason' => 'Jadwal bentrok.'])
            ->assertRedirect();

        $this->assertSame('dibatalkan', $exam->fresh()->status);
        $this->assertDatabaseHas('kp_exam_logs', ['action' => 'exam_cancelled']);

        $exam->update(['status' => 'dijadwalkan']);

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/exams/'.$exam->id.'/complete', ['note' => 'Selesai.'])
            ->assertRedirect();

        $this->assertSame('selesai', $exam->fresh()->status);
        $this->assertDatabaseHas('kp_exam_logs', ['action' => 'exam_completed']);
    }

    private function validSchedulePayload(array $overrides = []): array
    {
        return array_merge([
            'examiner_ids' => [$this->examiner->id, $this->secondExaminer->id],
            'exam_date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'mode' => 'hybrid',
            'room' => 'Ruang Sidang 1',
            'meeting_link' => 'https://meet.example.test/sidang-kp',
            'note' => 'Sidang tahap awal.',
        ], $overrides);
    }

    private function submittedExamRequest(): KpExamRequest
    {
        $this->approvedFinalReport();

        return KpExamRequest::firstOrCreate(
            ['kp_assignment_id' => $this->assignment->id],
            ['requested_by' => $this->mahasiswa->id, 'status' => 'diajukan', 'submitted_at' => now()]
        );
    }

    private function scheduledExam(): KpExam
    {
        $request = $this->submittedExamRequest();

        return KpExam::create([
            'kp_exam_request_id' => $request->id,
            'kp_assignment_id' => $this->assignment->id,
            'supervisor_id' => $this->supervisor->id,
            'examiner_id' => $this->examiner->id,
            'exam_date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'mode' => 'offline',
            'room' => 'Ruang Sidang 1',
            'status' => 'dijadwalkan',
            'scheduled_by' => $this->admin->id,
            'scheduled_at' => now(),
        ]);
    }

    private function approvedFinalReport(): KpFinalReport
    {
        if (! $this->assignment->logbooks()->where('status', 'disetujui')->exists()) {
            KpLogbook::create([
                'kp_assignment_id' => $this->assignment->id,
                'activity_date' => now()->toDateString(),
                'activity_title' => 'Kegiatan KP',
                'start_time' => '08:00',
                'end_time' => '12:00',
                'activity_description' => 'Kegiatan lapangan.',
                'learning_outcome' => 'Memahami kegiatan lapangan.',
                'status' => 'disetujui',
                'submitted_at' => now(),
                'validated_by' => $this->fieldUser->id,
                'validated_at' => now(),
            ]);
        }

        for ($i = 1; $i <= 8; $i++) {
            KpReportGuidanceLog::firstOrCreate(
                [
                    'kp_assignment_id' => $this->assignment->id,
                    'guidance_date' => now()->subDays($i)->toDateString(),
                    'topic' => 'Bimbingan laporan '.$i,
                ],
                [
                    'status' => 'disetujui',
                    'submitted_at' => now()->subDays($i),
                    'validated_by' => $this->supervisorUser->id,
                    'validated_at' => now()->subDays($i),
                ]
            );
        }

        return KpFinalReport::firstOrCreate(
            ['kp_assignment_id' => $this->assignment->id],
            [
                'current_version' => 1,
                'status' => 'disetujui',
                'final_document_url' => 'https://docs.google.com/document/d/final',
                'internal_review_status' => 'disetujui',
                'internal_reviewed_by' => $this->supervisorUser->id,
                'internal_reviewed_at' => now(),
                'field_review_status' => 'disetujui',
                'field_reviewed_by' => $this->fieldUser->id,
                'field_reviewed_at' => now(),
                'approved_at' => now(),
            ]
        );
    }

    private function makeAssignment(Student $student, Lecturer $lecturer, FieldSupervisor $field): KpAssignment
    {
        $period = KpPeriod::create(['name' => 'KP Genap 2026', 'status' => 'dibuka']);
        $place = KpPlace::create(['name' => 'Apotek Sehat', 'type' => 'apotek', 'status' => 'aktif']);
        $registration = KpRegistration::create(['kp_period_id' => $period->id, 'student_id' => $student->id, 'status' => 'terverifikasi']);

        return KpAssignment::create([
            'kp_period_id' => $period->id,
            'kp_registration_id' => $registration->id,
            'student_id' => $student->id,
            'kp_place_id' => $place->id,
            'internal_supervisor_id' => $lecturer->id,
            'field_supervisor_id' => $field->id,
            'status' => 'aktif',
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'active_key' => $period->id.'-'.$student->id,
        ]);
    }

    private function makeStudent(User $user, string $nim): Student
    {
        $user->forceFill(['profile_completed' => true])->save();

        return Student::create(['user_id' => $user->id, 'nim' => $nim, 'study_program' => 'Farmasi', 'semester' => 6, 'phone' => '081234567890', 'status' => 'active']);
    }

    private function makeUser(string $email, array $roles): User
    {
        $user = User::create(['name' => 'User Test', 'email' => $email, 'password' => Hash::make('password'), 'status' => 'active']);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user;
    }
}
