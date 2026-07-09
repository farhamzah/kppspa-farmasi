<?php

namespace Tests\Feature;

use App\Models\FieldSupervisor;
use App\Models\KpAssignment;
use App\Models\KpLogbook;
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

class KpLogbookTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;
    private Student $student;
    private User $lecturerUser;
    private Lecturer $lecturer;
    private User $fieldUser;
    private FieldSupervisor $fieldSupervisor;
    private KpAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');

        $this->admin = $this->makeUser('admin-logbook@test.local', ['admin']);
        $this->koordinator = $this->makeUser('koordinator-logbook@test.local', ['koordinator_kp']);
        $this->mahasiswa = $this->makeUser('mahasiswa-logbook@test.local', ['mahasiswa']);
        $this->student = $this->makeStudent($this->mahasiswa, '2210631230099');
        $this->lecturerUser = $this->makeUser('dosen-logbook@test.local', ['pembimbing_dalam']);
        $this->lecturer = Lecturer::create(['user_id' => $this->lecturerUser->id, 'nidn_nip' => '991122', 'status' => 'active']);
        $this->fieldUser = $this->makeUser('lapangan-logbook@test.local', ['pembimbing_lapangan']);
        $this->fieldSupervisor = FieldSupervisor::create(['user_id' => $this->fieldUser->id, 'institution_name' => 'Apotek Sehat', 'position' => 'Supervisor', 'status' => 'active']);
        $this->assignment = $this->makeAssignment($this->student, $this->lecturer, $this->fieldSupervisor);
    }

    public function test_student_with_active_assignment_can_open_logbook_and_student_without_assignment_sees_empty_state(): void
    {
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/logbook')
            ->assertOk()
            ->assertSee('Logbook KP')
            ->assertSee('Tambah Logbook');

        $other = $this->makeUser('no-assignment@test.local', ['mahasiswa']);
        $this->makeStudent($other, '2210631230100');

        $this->actingAs($other)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/logbook')
            ->assertOk()
            ->assertSee('Anda belum memiliki penempatan KP aktif.');
    }

    public function test_student_can_create_draft_submit_and_duplicate_date_is_rejected(): void
    {
        $payload = $this->logbookPayload(['evidence' => UploadedFile::fake()->create('bukti.pdf', 128, 'application/pdf')]);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/logbook', $payload)
            ->assertRedirect();

        $logbook = KpLogbook::first();
        $this->assertSame('draft', $logbook->status);
        Storage::disk('local')->assertExists($logbook->evidence_path);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/logbook/'.$logbook->id.'/submit')
            ->assertRedirect();

        $this->assertSame('menunggu_validasi', $logbook->fresh()->status);
        $this->assertDatabaseHas('kp_logbook_logs', ['kp_logbook_id' => $logbook->id, 'action' => 'submitted']);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/logbook', $this->logbookPayload())
            ->assertSessionHasErrors('activity_date');
    }

    public function test_student_can_set_kp_work_period_from_logbook_page(): void
    {
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/logbook/periode-kerja', [
                'started_at' => '2026-07-01',
                'ended_at' => '2026-07-07',
                'workday_pattern' => 'senin_sabtu',
            ])
            ->assertRedirect();

        $this->assignment->refresh();

        $this->assertSame('2026-07-01', $this->assignment->started_at->toDateString());
        $this->assertSame('2026-07-07', $this->assignment->ended_at->toDateString());
        $this->assertSame('senin_sabtu', $this->assignment->workday_pattern);
        $this->assertSame(6, $this->assignment->expectedWorkdaysCount());

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/logbook')
            ->assertOk()
            ->assertSee('Dasar Perhitungan Absen')
            ->assertSee('6 hari')
            ->assertSee('Senin - Sabtu');
    }

    public function test_student_can_upload_mobile_photo_evidence_for_logbook(): void
    {
        $payload = $this->logbookPayload([
            'activity_date' => now()->addDays(2)->toDateString(),
            'evidence' => UploadedFile::fake()->create('foto-kegiatan.webp', 512, 'image/webp'),
        ]);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/logbook', $payload)
            ->assertRedirect();

        $logbook = KpLogbook::latest('id')->first();

        $this->assertSame('foto-kegiatan.webp', $logbook->evidence_original_filename);
        $this->assertSame('image/webp', $logbook->evidence_mime);
        Storage::disk('local')->assertExists($logbook->evidence_path);
    }

    public function test_student_cannot_edit_approved_logbook_and_invalid_upload_is_rejected(): void
    {
        $approved = KpLogbook::create($this->logbookAttributes(['status' => 'disetujui']));

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/logbook/'.$approved->id.'/edit')
            ->assertForbidden();

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/logbook', $this->logbookPayload([
                'activity_date' => now()->addDay()->toDateString(),
                'evidence' => UploadedFile::fake()->create('bukti.exe', 10, 'application/octet-stream'),
            ]))
            ->assertSessionHasErrors('evidence');
    }

    public function test_field_supervisor_can_review_only_assigned_logbook(): void
    {
        $logbook = KpLogbook::create($this->logbookAttributes(['status' => 'menunggu_validasi']));
        $otherFieldUser = $this->makeUser('other-field@test.local', ['pembimbing_lapangan']);
        FieldSupervisor::create(['user_id' => $otherFieldUser->id, 'institution_name' => 'RS Lain', 'position' => 'Supervisor', 'status' => 'active']);

        $this->actingAs($otherFieldUser)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/logbook/'.$logbook->id)
            ->assertForbidden();

        $this->actingAs($this->fieldUser)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->post('/pembimbing-lapangan/logbook/'.$logbook->id.'/approve', ['validation_note' => 'Baik.'])
            ->assertRedirect();

        $this->assertSame('disetujui', $logbook->fresh()->status);
        $this->assertDatabaseHas('kp_logbook_logs', ['kp_logbook_id' => $logbook->id, 'action' => 'approved']);
    }

    public function test_field_supervisor_can_request_revision_and_reject_with_note(): void
    {
        $revision = KpLogbook::create($this->logbookAttributes(['status' => 'menunggu_validasi']));
        $rejected = KpLogbook::create($this->logbookAttributes(['activity_date' => now()->subDay()->toDateString(), 'status' => 'menunggu_validasi']));

        $this->actingAs($this->fieldUser)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->post('/pembimbing-lapangan/logbook/'.$revision->id.'/revision', ['validation_note' => 'Lengkapi uraian.'])
            ->assertRedirect();

        $this->actingAs($this->fieldUser)->withSession(['active_role' => 'pembimbing_lapangan'])
            ->post('/pembimbing-lapangan/logbook/'.$rejected->id.'/reject', ['validation_note' => 'Tidak sesuai.'])
            ->assertRedirect();

        $this->assertSame('revisi', $revision->fresh()->status);
        $this->assertSame('ditolak', $rejected->fresh()->status);
    }

    public function test_field_supervisor_logbook_index_groups_by_student_and_opens_assignment_detail(): void
    {
        $otherFieldUser = $this->makeUser('other-index-field@test.local', ['pembimbing_lapangan']);
        $otherField = FieldSupervisor::create(['user_id' => $otherFieldUser->id, 'institution_name' => 'RS Lain', 'position' => 'Supervisor', 'status' => 'active']);
        $otherStudentUser = $this->makeUser('other-logbook-student@test.local', ['mahasiswa']);
        $otherStudentUser->update(['name' => 'Mahasiswa Logbook Lain']);
        $otherAssignment = $this->makeAssignment($this->makeStudent($otherStudentUser, '2210631230199'), $this->lecturer, $otherField);
        KpLogbook::create([
            'kp_assignment_id' => $otherAssignment->id,
            'student_id' => $otherAssignment->student_id,
            'activity_date' => now()->toDateString(),
            'activity_title' => 'Logbook mahasiswa lain',
            'activity_description' => 'Kegiatan bukan bimbingan user aktif.',
            'status' => 'menunggu_validasi',
            'submitted_at' => now(),
        ]);
        KpLogbook::create($this->logbookAttributes([
            'activity_title' => 'Kegiatan pelayanan resep',
            'status' => 'menunggu_validasi',
            'submitted_at' => now(),
        ]));
        KpLogbook::create($this->logbookAttributes([
            'activity_date' => now()->subDay()->toDateString(),
            'activity_title' => 'Kegiatan stok opname',
            'status' => 'disetujui',
            'submitted_at' => now()->subDay(),
            'validated_at' => now(),
        ]));

        $this->actingAs($this->fieldUser)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/logbook')
            ->assertOk()
            ->assertSee('Ringkasan per Mahasiswa')
            ->assertSee($this->mahasiswa->name)
            ->assertSee('2 total')
            ->assertSee('1 menunggu')
            ->assertSee('Lihat Logbook')
            ->assertDontSee('Mahasiswa Logbook Lain')
            ->assertDontSee('Kegiatan pelayanan resep');

        $this->actingAs($this->fieldUser)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/logbook?assignment='.$this->assignment->id)
            ->assertOk()
            ->assertSee('Rincian Logbook Mahasiswa')
            ->assertSee('Kegiatan pelayanan resep')
            ->assertSee('Kegiatan stok opname')
            ->assertSee('Validasi');
    }

    public function test_internal_supervisor_can_view_assigned_logbook_and_add_comment_only_for_own_student(): void
    {
        $logbook = KpLogbook::create($this->logbookAttributes());
        $otherLecturerUser = $this->makeUser('other-internal@test.local', ['pembimbing_dalam']);
        Lecturer::create(['user_id' => $otherLecturerUser->id, 'nidn_nip' => '778899', 'status' => 'active']);

        $this->actingAs($otherLecturerUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/logbook/'.$logbook->id)
            ->assertForbidden();

        $this->actingAs($this->lecturerUser)->withSession(['active_role' => 'pembimbing_dalam'])
            ->post('/pembimbing-dalam/logbook/'.$logbook->id.'/comments', [
                'comment' => 'Aktivitas sudah sesuai.',
                'visibility' => 'visible_to_student',
            ])->assertRedirect();

        $this->assertDatabaseHas('kp_logbook_comments', ['kp_logbook_id' => $logbook->id, 'comment' => 'Aktivitas sudah sesuai.']);
        $this->assertDatabaseHas('kp_logbook_logs', ['kp_logbook_id' => $logbook->id, 'action' => 'comment_added']);
    }

    public function test_internal_supervisor_logbook_index_groups_by_student_and_opens_assignment_detail(): void
    {
        KpLogbook::create($this->logbookAttributes([
            'activity_title' => 'Observasi pelayanan',
            'status' => 'revisi',
            'submitted_at' => now(),
        ]));

        $this->actingAs($this->lecturerUser)
            ->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/logbook')
            ->assertOk()
            ->assertSee('Ringkasan per Mahasiswa')
            ->assertSee($this->mahasiswa->name)
            ->assertSee('1 total')
            ->assertSee('1 revisi/ditolak')
            ->assertDontSee('Observasi pelayanan');

        $this->actingAs($this->lecturerUser)
            ->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/logbook?assignment='.$this->assignment->id)
            ->assertOk()
            ->assertSee('Rincian Logbook Mahasiswa')
            ->assertSee('Observasi pelayanan')
            ->assertSeeText('Komentar');
    }

    public function test_admin_and_koordinator_can_monitor_logbooks_while_student_cannot(): void
    {
        $logbook = KpLogbook::create($this->logbookAttributes());

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/logbooks')
            ->assertOk()
            ->assertSee($logbook->activity_title);

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/logbook-logs')
            ->assertOk();

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/logbooks')
            ->assertForbidden();
    }

    private function logbookPayload(array $overrides = []): array
    {
        return array_merge([
            'activity_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '12:00',
            'activity_title' => 'Pelayanan resep',
            'activity_description' => 'Membantu pelayanan resep dan edukasi pasien.',
            'learning_outcome' => 'Memahami alur pelayanan.',
        ], $overrides);
    }

    private function logbookAttributes(array $overrides = []): array
    {
        return array_merge($this->logbookPayload(), [
            'kp_assignment_id' => $this->assignment->id,
            'status' => 'draft',
        ], $overrides);
    }

    private function makeAssignment(Student $student, Lecturer $lecturer, FieldSupervisor $fieldSupervisor): KpAssignment
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
            'field_supervisor_id' => $fieldSupervisor->id,
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
