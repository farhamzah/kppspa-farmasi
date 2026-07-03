<?php

namespace Tests\Feature;

use App\Models\FieldSupervisor;
use App\Models\KpAssignment;
use App\Models\KpFinalReport;
use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpRegistration;
use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KpFinalReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;
    private Student $student;
    private User $lecturerUser;
    private Lecturer $lecturer;
    private User $fieldUser;
    private FieldSupervisor $field;
    private KpAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');

        $this->admin = $this->makeUser('admin-final@test.local', ['admin']);
        $this->koordinator = $this->makeUser('koordinator-final@test.local', ['koordinator_kp']);
        $this->mahasiswa = $this->makeUser('mahasiswa-final@test.local', ['mahasiswa']);
        $this->student = $this->makeStudent($this->mahasiswa, '2210631230201');
        $this->lecturerUser = $this->makeUser('dosen-final@test.local', ['pembimbing_dalam']);
        $this->lecturer = Lecturer::create(['user_id' => $this->lecturerUser->id, 'nidn_nip' => '552211', 'status' => 'active']);
        $this->fieldUser = $this->makeUser('field-final@test.local', ['pembimbing_lapangan']);
        $this->field = FieldSupervisor::create(['user_id' => $this->fieldUser->id, 'institution_name' => 'Apotek Sehat', 'position' => 'Supervisor', 'status' => 'active']);
        $this->assignment = $this->makeAssignment($this->student, $this->lecturer, $this->field);
    }

    public function test_student_with_active_assignment_can_open_final_report_and_without_assignment_sees_empty_state(): void
    {
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/laporan-akhir')
            ->assertOk()
            ->assertSee('Laporan Akhir');

        $other = $this->makeUser('no-final-assignment@test.local', ['mahasiswa']);
        $this->makeStudent($other, '2210631230202');

        $this->actingAs($other)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/laporan-akhir')
            ->assertOk()
            ->assertSee('Anda belum memiliki penempatan KP aktif.');
    }

    public function test_student_can_upload_submit_revision_and_version_increments(): void
    {
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/laporan-akhir/upload', [
                'report_file' => UploadedFile::fake()->create('laporan.pdf', 256, 'application/pdf'),
                'note' => 'Upload awal',
            ])->assertRedirect();

        $report = KpFinalReport::first();
        $this->assertSame(1, $report->current_version);
        $this->assertDatabaseHas('kp_final_report_logs', ['action' => 'uploaded']);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/laporan-akhir/submit')
            ->assertRedirect();
        $this->assertSame('menunggu_review', $report->fresh()->status);

        $this->actingAs($this->lecturerUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->post('/pembimbing-dalam/laporan-akhir/'.$report->id.'/revision', ['review_note' => 'Perbaiki format.'])
            ->assertRedirect();

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/laporan-akhir/upload', [
                'report_file' => UploadedFile::fake()->create('laporan-revisi.docx', 256, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ])->assertRedirect();

        $report->refresh();
        $this->assertSame(2, $report->current_version);
        $this->assertSame('draft', $report->status);
        $this->assertDatabaseHas('kp_final_report_logs', ['kp_final_report_id' => $report->id, 'action' => 'revision_uploaded']);
    }

    public function test_student_cannot_submit_without_file_or_upload_after_approved_and_invalid_file_is_rejected(): void
    {
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/laporan-akhir/submit')
            ->assertSessionHasErrors('file');

        $report = KpFinalReport::first();
        $report->update(['status' => 'disetujui']);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/laporan-akhir/upload', ['report_file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('report');

        $report->update(['status' => 'draft']);
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/laporan-akhir/upload', ['report_file' => UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream')])
            ->assertSessionHasErrors('report_file');
    }

    public function test_internal_supervisor_can_review_only_own_student_report(): void
    {
        $report = $this->submittedReport();
        $otherLecturerUser = $this->makeUser('other-final-lecturer@test.local', ['pembimbing_dalam']);
        Lecturer::create(['user_id' => $otherLecturerUser->id, 'nidn_nip' => '778811', 'status' => 'active']);

        $this->actingAs($otherLecturerUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/laporan-akhir/'.$report->id)
            ->assertForbidden();

        $this->actingAs($this->lecturerUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->post('/pembimbing-dalam/laporan-akhir/'.$report->id.'/approve', ['review_note' => 'Layak.'])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame('menunggu_review', $report->status);
        $this->assertSame('disetujui', $report->internal_review_status);
        $this->assertNull($report->approved_at);

        $this->actingAs($this->fieldUser)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->post('/pembimbing-lapangan/laporan-akhir/'.$report->id.'/approve', ['review_note' => 'Sesuai lapangan.'])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame('disetujui', $report->status);
        $this->assertSame('disetujui', $report->field_review_status);
        $this->assertNotNull($report->approved_at);
        $this->assertDatabaseHas('kp_final_report_logs', ['kp_final_report_id' => $report->id, 'action' => 'internal_approved']);
        $this->assertDatabaseHas('kp_final_report_logs', ['kp_final_report_id' => $report->id, 'action' => 'field_approved']);
    }

    public function test_student_can_save_final_link_and_internal_supervisor_validates_guidance_logs(): void
    {
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/laporan-akhir/link-final', [
                'final_document_url' => 'https://docs.google.com/document/d/final-report',
                'final_document_label' => 'Laporan Final KP',
            ])->assertRedirect();

        $report = KpFinalReport::first();
        $this->assertSame('https://docs.google.com/document/d/final-report', $report->final_document_url);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/laporan-akhir/bimbingan', [
                'guidance_date' => now()->toDateString(),
                'topic' => 'Review bab hasil',
                'student_note' => 'Sudah revisi pembahasan.',
            ])->assertRedirect();

        $guidance = $this->assignment->reportGuidanceLogs()->first();
        $this->assertSame('menunggu_validasi', $guidance->status);

        $this->actingAs($this->lecturerUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->post('/pembimbing-dalam/laporan-akhir/'.$report->id.'/bimbingan/'.$guidance->id.'/approve', ['review_note' => 'OK.'])
            ->assertRedirect();

        $this->assertSame('disetujui', $guidance->fresh()->status);
    }

    public function test_internal_supervisor_can_reject_with_note_and_admin_koordinator_can_monitor(): void
    {
        $report = $this->submittedReport();

        $this->actingAs($this->lecturerUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->post('/pembimbing-dalam/laporan-akhir/'.$report->id.'/reject', ['review_note' => 'Tidak sesuai.'])
            ->assertRedirect();

        $this->assertSame('ditolak', $report->fresh()->status);

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])->get('/management/final-reports')->assertOk()->assertSee('Monitoring Laporan');
        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])->get('/management/final-report-logs')->assertOk();
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])->get('/management/final-reports')->assertForbidden();
    }

    public function test_download_file_is_protected_by_ownership(): void
    {
        $report = $this->submittedReport();
        $file = $report->files()->first();
        Storage::disk('local')->put($file->file_path, 'dummy');

        $other = $this->makeUser('other-student-final@test.local', ['mahasiswa']);
        $this->makeStudent($other, '2210631230203');

        $this->actingAs($other)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/laporan-akhir/files/'.$file->id.'/download')
            ->assertForbidden();

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/laporan-akhir/files/'.$file->id.'/download')
            ->assertOk();
    }

    private function submittedReport(): KpFinalReport
    {
        $report = KpFinalReport::create(['kp_assignment_id' => $this->assignment->id, 'current_version' => 1, 'status' => 'menunggu_review', 'submitted_at' => now()]);
        $report->files()->create([
            'version' => 1,
            'original_filename' => 'laporan.pdf',
            'file_path' => 'kp-final-reports/laporan.pdf',
            'file_disk' => 'local',
            'file_mime' => 'application/pdf',
            'file_size' => 100,
            'uploaded_by' => $this->mahasiswa->id,
            'uploaded_at' => now(),
        ]);

        return $report;
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
