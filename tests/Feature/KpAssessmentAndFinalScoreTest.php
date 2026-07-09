<?php

namespace Tests\Feature;

use App\Models\FieldSupervisor;
use App\Models\KpAssessmentComponent;
use App\Models\KpAssignment;
use App\Models\KpExam;
use App\Models\KpExamRequest;
use App\Models\KpFinalScore;
use App\Models\KpLogbook;
use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpRegistration;
use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\KpAssessmentService;
use App\Support\KpScoreCalculator;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KpAssessmentAndFinalScoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;
    private User $supervisorUser;
    private User $fieldUser;
    private User $examinerUser;
    private Lecturer $supervisor;
    private Lecturer $examiner;
    private FieldSupervisor $field;
    private KpAssignment $assignment;
    private KpExam $exam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('admin-score@test.local', ['admin']);
        $this->koordinator = $this->makeUser('koor-score@test.local', ['koordinator_kp']);
        $this->mahasiswa = $this->makeUser('student-score@test.local', ['mahasiswa']);
        $student = Student::create(['user_id' => $this->mahasiswa->id, 'nim' => '2210631231001', 'study_program' => 'Farmasi', 'semester' => 6, 'phone' => '081234567890', 'status' => 'active']);
        $this->mahasiswa->forceFill(['profile_completed' => true])->save();
        $this->supervisorUser = $this->makeUser('supervisor-score@test.local', ['pembimbing_dalam']);
        $this->supervisor = Lecturer::create(['user_id' => $this->supervisorUser->id, 'nidn_nip' => '881101', 'status' => 'active']);
        $this->fieldUser = $this->makeUser('field-score@test.local', ['pembimbing_lapangan']);
        $this->field = FieldSupervisor::create(['user_id' => $this->fieldUser->id, 'institution_name' => 'Apotek Sehat', 'position' => 'Supervisor', 'status' => 'active']);
        $this->examinerUser = $this->makeUser('examiner-score@test.local', ['penguji']);
        $this->examiner = Lecturer::create(['user_id' => $this->examinerUser->id, 'nidn_nip' => '881102', 'status' => 'active']);
        $this->assignment = $this->makeAssignment($student);
        $request = KpExamRequest::create(['kp_assignment_id' => $this->assignment->id, 'requested_by' => $this->mahasiswa->id, 'status' => 'dijadwalkan', 'submitted_at' => now()]);
        $this->exam = KpExam::create(['kp_exam_request_id' => $request->id, 'kp_assignment_id' => $this->assignment->id, 'supervisor_id' => $this->supervisor->id, 'examiner_id' => $this->examiner->id, 'exam_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00', 'mode' => 'offline', 'room' => 'R1', 'status' => 'dijadwalkan']);
    }

    public function test_admin_and_koordinator_can_manage_assessment_components(): void
    {
        $period = $this->assignment->period;

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/assessment-components')
            ->assertOk();

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/assessment-components', [
                'kp_period_id' => $period->id,
                'assessor_type' => 'pembimbing_dalam',
                'component_name' => 'Kualitas laporan',
                'weight' => 30,
                'max_score' => 100,
                'status' => 'aktif',
                'is_required' => 1,
            ])->assertRedirect();

        $this->assertDatabaseHas('kp_assessment_components', ['component_name' => 'Kualitas laporan']);
        $this->assertDatabaseHas('kp_score_logs', ['action' => 'component_created']);
    }

    public function test_default_assessment_rubric_is_created_per_period(): void
    {
        app(KpAssessmentService::class)->ensureDefaultComponents($this->assignment->period, $this->koordinator);

        $this->assertDatabaseHas('kp_assessment_components', ['kp_period_id' => $this->assignment->kp_period_id, 'assessor_type' => 'pembimbing_lapangan', 'component_name' => 'Komunikasi dan Kerjasama', 'weight' => 30]);
        $this->assertDatabaseHas('kp_assessment_components', ['kp_period_id' => $this->assignment->kp_period_id, 'assessor_type' => 'pembimbing_dalam', 'component_name' => 'Laporan KP', 'weight' => 50]);
        $this->assertDatabaseHas('kp_assessment_components', ['kp_period_id' => $this->assignment->kp_period_id, 'assessor_type' => 'penguji', 'component_name' => 'Penguasaan Materi KP', 'weight' => 50]);
    }

    public function test_each_assessor_can_score_only_their_own_assignment_and_invalid_score_is_rejected(): void
    {
        [$internal] = $this->components();
        $otherUser = $this->makeUser('other-supervisor-score@test.local', ['pembimbing_dalam']);
        Lecturer::create(['user_id' => $otherUser->id, 'nidn_nip' => '881103', 'status' => 'active']);

        $payload = ['scores' => [['component_id' => $internal->id, 'score' => 90, 'note' => 'Baik']]];
        $this->actingAs($this->supervisorUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->post('/pembimbing-dalam/penilaian/'.$this->assignment->id.'/save', $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('kp_scores', ['kp_assignment_id' => $this->assignment->id, 'score' => 90, 'weighted_score' => 27]);

        $this->actingAs($otherUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->post('/pembimbing-dalam/penilaian/'.$this->assignment->id.'/save', $payload)
            ->assertForbidden();

        $this->actingAs($this->supervisorUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->post('/pembimbing-dalam/penilaian/'.$this->assignment->id.'/save', ['scores' => [['component_id' => $internal->id, 'score' => 120]]])
            ->assertSessionHasErrors('scores.0.score');
    }

    public function test_field_supervisor_and_examiner_can_score_assigned_records_only(): void
    {
        [, $fieldComponent, $examinerComponent] = $this->components();
        $otherStudentUser = $this->makeUser('other-field-score-student@test.local', ['mahasiswa']);
        $otherStudentUser->update(['name' => 'Mahasiswa Nilai Lain']);
        $otherStudent = Student::create(['user_id' => $otherStudentUser->id, 'nim' => '2210631231888', 'study_program' => 'Farmasi', 'semester' => 6, 'phone' => '081234567890', 'status' => 'active']);
        $otherStudentUser->forceFill(['profile_completed' => true])->save();
        $otherAssignment = $this->makeAssignment($otherStudent);
        $otherAssignment->update(['field_supervisor_id' => null, 'status' => 'menunggu_pembimbing']);

        $this->actingAs($this->fieldUser)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/penilaian')
            ->assertOk()
            ->assertSee($this->mahasiswa->name)
            ->assertDontSee('Mahasiswa Nilai Lain');

        $this->actingAs($this->fieldUser)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->post('/pembimbing-lapangan/penilaian/'.$this->assignment->id.'/save', ['scores' => [['component_id' => $fieldComponent->id, 'score' => 80]]])
            ->assertRedirect();

        $this->actingAs($this->examinerUser)->withSession(['active_role' => 'penguji'])
            ->post('/penguji/penilaian/'.$this->exam->id.'/save', ['scores' => [['component_id' => $examinerComponent->id, 'score' => 85]]])
            ->assertRedirect();

        $this->assertDatabaseHas('kp_scores', ['assessor_type' => 'pembimbing_lapangan', 'score' => 80]);
        $this->assertDatabaseHas('kp_scores', ['assessor_type' => 'penguji', 'score' => 85]);

        $otherExaminer = $this->makeUser('other-examiner-score@test.local', ['penguji']);
        Lecturer::create(['user_id' => $otherExaminer->id, 'nidn_nip' => '881104', 'status' => 'active']);
        $this->actingAs($otherExaminer)->withSession(['active_role' => 'penguji'])
            ->post('/penguji/penilaian/'.$this->exam->id.'/save', ['scores' => [['component_id' => $examinerComponent->id, 'score' => 90]]])
            ->assertForbidden();
    }

    public function test_multiple_assigned_examiners_can_score_and_finalization_waits_for_each_examiner(): void
    {
        [, $field, $examiner] = $this->components();
        $secondExaminerUser = $this->makeUser('second-examiner-score@test.local', ['penguji']);
        $secondExaminer = Lecturer::create(['user_id' => $secondExaminerUser->id, 'nidn_nip' => '881105', 'status' => 'active']);
        $this->exam->examiners()->sync([
            $this->examiner->id => ['sort_order' => 1],
            $secondExaminer->id => ['sort_order' => 2],
        ]);

        $this->saveAndSubmit($this->supervisorUser, 'pembimbing-dalam', $this->assignment->id, $this->components()[0], 90);
        $this->saveAndSubmit($this->fieldUser, 'pembimbing-lapangan', $this->assignment->id, $field, 80);
        KpLogbook::create([
            'kp_assignment_id' => $this->assignment->id,
            'activity_date' => now()->toDateString(),
            'activity_title' => 'Kegiatan KP',
            'activity_description' => 'Kegiatan harian.',
            'status' => 'disetujui',
            'submitted_at' => now(),
            'validated_by' => $this->fieldUser->id,
            'validated_at' => now(),
        ]);

        $this->actingAs($this->examinerUser)->withSession(['active_role' => 'penguji'])
            ->post('/penguji/penilaian/'.$this->exam->id.'/save', ['scores' => [['component_id' => $examiner->id, 'score' => 80]]])
            ->assertRedirect();
        $this->actingAs($this->examinerUser)->withSession(['active_role' => 'penguji'])
            ->post('/penguji/penilaian/'.$this->exam->id.'/submit')
            ->assertRedirect();

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/scores/'.$this->assignment->id.'/finalize')
            ->assertSessionHasErrors('final_score');

        $this->actingAs($secondExaminerUser)->withSession(['active_role' => 'penguji'])
            ->post('/penguji/penilaian/'.$this->exam->id.'/save', ['scores' => [['component_id' => $examiner->id, 'score' => 100]]])
            ->assertRedirect();
        $this->actingAs($secondExaminerUser)->withSession(['active_role' => 'penguji'])
            ->post('/penguji/penilaian/'.$this->exam->id.'/submit')
            ->assertRedirect();

        $this->assertDatabaseHas('kp_scores', ['assessor_type' => 'penguji', 'assessor_user_id' => $this->examinerUser->id, 'score' => 80]);
        $this->assertDatabaseHas('kp_scores', ['assessor_type' => 'penguji', 'assessor_user_id' => $secondExaminerUser->id, 'score' => 100]);

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/scores/'.$this->assignment->id.'/finalize')
            ->assertRedirect();
    }

    public function test_final_score_requires_complete_submitted_scores_then_can_be_finalized_and_published(): void
    {
        [$internal, $field, $examiner] = $this->components();

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/scores/'.$this->assignment->id.'/finalize')
            ->assertSessionHasErrors('final_score');

        $this->saveAndSubmit($this->supervisorUser, 'pembimbing-dalam', $this->assignment->id, $internal, 90);
        $this->saveAndSubmit($this->fieldUser, 'pembimbing-lapangan', $this->assignment->id, $field, 80);
        KpLogbook::create([
            'kp_assignment_id' => $this->assignment->id,
            'activity_date' => now()->toDateString(),
            'activity_title' => 'Kegiatan KP',
            'activity_description' => 'Kegiatan harian.',
            'status' => 'disetujui',
            'submitted_at' => now(),
            'validated_by' => $this->fieldUser->id,
            'validated_at' => now(),
        ]);
        $this->actingAs($this->examinerUser)->withSession(['active_role' => 'penguji'])
            ->post('/penguji/penilaian/'.$this->exam->id.'/save', ['scores' => [['component_id' => $examiner->id, 'score' => 85]]])
            ->assertRedirect();
        $this->actingAs($this->examinerUser)->withSession(['active_role' => 'penguji'])
            ->post('/penguji/penilaian/'.$this->exam->id.'/submit')
            ->assertRedirect();

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/scores/'.$this->assignment->id.'/finalize', ['note' => 'Final.'])
            ->assertRedirect();

        $final = KpFinalScore::firstOrFail();
        $this->assertSame('locked', $final->status);
        $this->assertSame('A', $final->final_grade);
        $this->assertSame('86.50', (string) $final->final_score);

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/final-scores/'.$final->id.'/publish')
            ->assertRedirect();

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/nilai')
            ->assertOk()
            ->assertSee('Nilai Akhir KP')
            ->assertSee('A');
    }

    public function test_attendance_score_uses_assignment_work_period_and_workday_pattern(): void
    {
        $this->assignment->update([
            'started_at' => '2026-07-01',
            'ended_at' => '2026-07-07',
            'workday_pattern' => 'senin_sabtu',
        ]);

        foreach (['2026-07-01', '2026-07-02', '2026-07-04'] as $date) {
            KpLogbook::create([
                'kp_assignment_id' => $this->assignment->id,
                'activity_date' => $date,
                'activity_title' => 'Kegiatan KP '.$date,
                'activity_description' => 'Kegiatan harian.',
                'status' => 'disetujui',
                'submitted_at' => now(),
                'validated_by' => $this->fieldUser->id,
                'validated_at' => now(),
            ]);
        }

        $breakdown = app(KpScoreCalculator::class)->breakdown($this->assignment->fresh());

        $this->assertSame(6, $breakdown['sections']['kehadiran']['meta']['workdays']);
        $this->assertSame(3, $breakdown['sections']['kehadiran']['meta']['approved_logbook_days']);
        $this->assertSame(50.0, $breakdown['sections']['kehadiran']['score']);
    }

    public function test_published_score_cannot_be_changed_by_assessor_and_can_be_unlocked_by_management(): void
    {
        [$internal] = $this->components();
        KpFinalScore::create(['kp_assignment_id' => $this->assignment->id, 'final_score' => 90, 'final_grade' => 'A', 'status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->supervisorUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->post('/pembimbing-dalam/penilaian/'.$this->assignment->id.'/save', ['scores' => [['component_id' => $internal->id, 'score' => 95]]])
            ->assertSessionHasErrors('final_score');

        $final = KpFinalScore::first();
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post('/management/final-scores/'.$final->id.'/unlock', ['reason' => 'Koreksi nilai.'])
            ->assertRedirect();

        $this->assertSame('calculated', $final->fresh()->status);
        $this->assertDatabaseHas('kp_score_logs', ['action' => 'final_score_unlocked']);
    }

    public function test_management_can_override_scores_before_finalization(): void
    {
        [$internal, $field, $examiner] = $this->components();

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/scores/'.$this->assignment->id.'/override', [
                'attendance_score' => 100,
                'attendance_note' => 'Kehadiran lengkap.',
                'scores' => [
                    ['component_id' => $internal->id, 'score' => 90, 'note' => 'Override pembimbing dalam.'],
                    ['component_id' => $field->id, 'score' => 80, 'note' => 'Override lapangan.'],
                    ['component_id' => $examiner->id, 'score' => 85, 'note' => 'Override penguji.'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kp_final_scores', ['kp_assignment_id' => $this->assignment->id, 'attendance_score_override' => 100]);
        $this->assertDatabaseHas('kp_scores', ['kp_assignment_id' => $this->assignment->id, 'assessor_type' => 'pembimbing_dalam', 'score' => 90, 'status' => 'submitted']);
        $this->assertSame('86.50', (string) KpFinalScore::where('kp_assignment_id', $this->assignment->id)->firstOrFail()->final_score);
    }

    private function components(): array
    {
        return [
            KpAssessmentComponent::firstOrCreate(['kp_period_id' => $this->assignment->kp_period_id, 'assessor_type' => 'pembimbing_dalam', 'component_name' => 'Kualitas laporan'], ['weight' => 30, 'max_score' => 100, 'status' => 'aktif', 'is_required' => true]),
            KpAssessmentComponent::firstOrCreate(['kp_period_id' => $this->assignment->kp_period_id, 'assessor_type' => 'pembimbing_lapangan', 'component_name' => 'Kedisiplinan'], ['weight' => 30, 'max_score' => 100, 'status' => 'aktif', 'is_required' => true]),
            KpAssessmentComponent::firstOrCreate(['kp_period_id' => $this->assignment->kp_period_id, 'assessor_type' => 'penguji', 'component_name' => 'Presentasi'], ['weight' => 40, 'max_score' => 100, 'status' => 'aktif', 'is_required' => true]),
        ];
    }

    private function saveAndSubmit(User $user, string $prefix, int $assignmentId, KpAssessmentComponent $component, int $score): void
    {
        $role = $prefix === 'pembimbing-dalam' ? 'pembimbing_dalam' : 'pembimbing_lapangan';
        $this->actingAs($user)->withSession(['active_role' => $role])
            ->post("/{$prefix}/penilaian/{$assignmentId}/save", ['scores' => [['component_id' => $component->id, 'score' => $score]]])
            ->assertRedirect();
        $this->actingAs($user)->withSession(['active_role' => $role])
            ->post("/{$prefix}/penilaian/{$assignmentId}/submit")
            ->assertRedirect();
    }

    private function makeAssignment(Student $student): KpAssignment
    {
        $period = KpPeriod::create(['name' => 'KP Genap 2026', 'status' => 'dibuka']);
        $place = KpPlace::create(['name' => 'Apotek Sehat', 'type' => 'apotek', 'status' => 'aktif']);
        $registration = KpRegistration::create(['kp_period_id' => $period->id, 'student_id' => $student->id, 'status' => 'terverifikasi']);

        return KpAssignment::create(['kp_period_id' => $period->id, 'kp_registration_id' => $registration->id, 'student_id' => $student->id, 'kp_place_id' => $place->id, 'internal_supervisor_id' => $this->supervisor->id, 'field_supervisor_id' => $this->field->id, 'status' => 'aktif', 'assigned_by' => $this->admin->id, 'assigned_at' => now(), 'active_key' => $period->id.'-'.$student->id]);
    }

    private function makeUser(string $email, array $roles): User
    {
        $user = User::create(['name' => 'User Test', 'email' => $email, 'password' => Hash::make('password'), 'status' => 'active']);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));
        return $user;
    }
}
