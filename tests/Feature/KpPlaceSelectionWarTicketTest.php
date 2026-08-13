<?php

namespace Tests\Feature;

use App\Models\KpDocument;
use App\Models\KpDocumentRequirement;
use App\Models\KpAssignment;
use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpPlaceQuota;
use App\Models\KpPlaceSelection;
use App\Models\KpRegistration;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KpPlaceSelectionWarTicketTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->admin = $this->makeUser('admin@test.local', ['admin']);
        $this->koordinator = $this->makeUser('koordinator@test.local', ['koordinator_kp']);
        $this->mahasiswa = $this->makeUser('mahasiswa@test.local', ['mahasiswa']);
        $this->student = $this->makeStudent($this->mahasiswa, '2210631230001');
    }

    public function test_verified_student_can_open_place_selection_page(): void
    {
        [$registration] = $this->verifiedRegistration($this->student);

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/pemilihan-tempat/'.$registration->kp_period_id)
            ->assertOk()
            ->assertSee('Daftar Tempat KP');
    }

    public function test_unverified_student_cannot_select_place(): void
    {
        [$registration, $quota] = $this->draftRegistrationWithQuota($this->student);

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/pemilihan-tempat/'.$quota->id.'/pilih')
            ->assertSessionHasErrors('selection');

        $this->assertDatabaseMissing('kp_place_selections', ['kp_registration_id' => $registration->id]);
    }

    public function test_student_can_select_place_when_open_and_quota_available(): void
    {
        [$registration, $quota] = $this->verifiedRegistration($this->student);

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/pemilihan-tempat/'.$quota->id.'/pilih')
            ->assertRedirect();

        $this->assertDatabaseHas('kp_place_selections', [
            'kp_registration_id' => $registration->id,
            'kp_place_quota_id' => $quota->id,
            'status' => 'aktif',
        ]);
        $this->assertSame(1, $quota->fresh()->filledCount());
        $this->assertDatabaseHas('kp_selection_logs', ['action' => 'selection_success', 'status' => 'success']);
    }

    public function test_place_selection_respects_june_30_selection_schedule(): void
    {
        [$registration, $quota] = $this->verifiedRegistration($this->student);
        $registration->period->update([
            'selection_start_at' => Carbon::parse('2026-06-30 08:00:00', config('app.timezone')),
            'selection_end_at' => Carbon::parse('2026-06-30 23:59:00', config('app.timezone')),
        ]);

        $this->travelTo(Carbon::parse('2026-06-30 07:59:59', config('app.timezone')));
        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/pemilihan-tempat/'.$quota->id.'/pilih')
            ->assertSessionHasErrors('selection');
        $this->assertDatabaseMissing('kp_place_selections', ['kp_registration_id' => $registration->id, 'status' => 'aktif']);

        $this->travelTo(Carbon::parse('2026-06-30 08:00:00', config('app.timezone')));
        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/pemilihan-tempat/'.$quota->id.'/pilih')
            ->assertRedirect();
        $this->assertDatabaseHas('kp_place_selections', ['kp_registration_id' => $registration->id, 'status' => 'aktif']);

        $other = $this->makeUser('after@student.test', ['mahasiswa']);
        $otherStudent = $this->makeStudent($other, '2210631230005');
        $otherRegistration = $this->verifiedRegistrationForPeriod($otherStudent, $registration->period, $quota->place);

        $this->travelTo(Carbon::parse('2026-07-01 00:00:00', config('app.timezone')));
        $this->actingAs($other)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/pemilihan-tempat/'.$quota->id.'/pilih')
            ->assertSessionHasErrors('selection');
        $this->assertDatabaseMissing('kp_place_selections', ['kp_registration_id' => $otherRegistration->id, 'status' => 'aktif']);

        $this->travelBack();
    }

    public function test_open_period_registration_upload_review_and_place_selection_flow(): void
    {
        Storage::fake('local');

        $period = KpPeriod::create([
            'name' => 'KP TA 2026_2027',
            'academic_year' => '2026/2027',
            'semester' => 'ganjil',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDays(7),
            'selection_start_at' => now()->subHour(),
            'selection_end_at' => now()->addDays(7),
            'status' => 'dibuka',
        ]);
        $requirement = KpDocumentRequirement::create([
            'kp_period_id' => $period->id,
            'name' => 'Bukti pembayaran UKT C10',
            'is_required' => true,
            'allowed_file_types' => 'pdf,jpg,jpeg,png',
            'max_file_size_mb' => 5,
            'status' => 'aktif',
        ]);
        $place = KpPlace::create(['name' => 'RSUD Karawang', 'type' => 'rumah_sakit', 'city' => 'KABUPATEN KARAWANG', 'status' => 'aktif']);
        $quota = KpPlaceQuota::create(['kp_period_id' => $period->id, 'kp_place_id' => $place->id, 'quota' => 10, 'is_open' => true]);

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/pendaftaran-kp', ['kp_period_id' => $period->id])
            ->assertRedirect();

        $registration = KpRegistration::where('kp_period_id', $period->id)->where('student_id', $this->student->id)->firstOrFail();

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post("/mahasiswa/pendaftaran-kp/{$registration->id}/documents/{$requirement->id}", [
                'document' => UploadedFile::fake()->create('bukti-ukt.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/berkas-kp')
            ->assertOk()
            ->assertSee('Kirim Pendaftaran');

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post("/mahasiswa/pendaftaran-kp/{$registration->id}/submit")
            ->assertRedirect();

        $document = KpDocument::where('kp_registration_id', $registration->id)->firstOrFail();

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/kp-registrations/{$registration->id}/documents/{$document->id}/approve")
            ->assertRedirect();

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->post("/management/kp-registrations/{$registration->id}/verify", ['verification_note' => 'Lengkap.'])
            ->assertRedirect();

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/pemilihan-tempat/'.$period->id)
            ->assertOk()
            ->assertSee('RSUD Karawang');

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/pemilihan-tempat/'.$quota->id.'/pilih')
            ->assertRedirect();

        $this->assertDatabaseHas('kp_registrations', ['id' => $registration->id, 'status' => 'terverifikasi']);
        $this->assertDatabaseHas('kp_place_selections', [
            'kp_registration_id' => $registration->id,
            'kp_place_quota_id' => $quota->id,
            'status' => 'aktif',
        ]);
    }

    public function test_student_cannot_select_twice_or_outside_schedule_or_closed_quota(): void
    {
        [$registration, $quota] = $this->verifiedRegistration($this->student);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])->post('/mahasiswa/pemilihan-tempat/'.$quota->id.'/pilih')->assertRedirect();
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])->post('/mahasiswa/pemilihan-tempat/'.$quota->id.'/pilih')->assertSessionHasErrors('selection');

        $other = $this->makeUser('other@student.test', ['mahasiswa']);
        $otherStudent = $this->makeStudent($other, '2210631230002');
        [$closedRegistration, $closedQuota] = $this->verifiedRegistration($otherStudent);
        $closedQuota->update(['is_open' => false]);
        $this->actingAs($other)->withSession(['active_role' => 'mahasiswa'])->post('/mahasiswa/pemilihan-tempat/'.$closedQuota->id.'/pilih')->assertSessionHasErrors('selection');

        $lateUser = $this->makeUser('late@student.test', ['mahasiswa']);
        $lateStudent = $this->makeStudent($lateUser, '2210631230003');
        [$lateRegistration, $lateQuota] = $this->verifiedRegistration($lateStudent);
        $lateRegistration->period->update(['selection_start_at' => now()->addDay(), 'selection_end_at' => now()->addDays(2)]);
        $this->actingAs($lateUser)->withSession(['active_role' => 'mahasiswa'])->post('/mahasiswa/pemilihan-tempat/'.$lateQuota->id.'/pilih')->assertSessionHasErrors('selection');

        $this->assertDatabaseHas('kp_selection_logs', ['action' => 'selection_failed_already_selected']);
        $this->assertDatabaseHas('kp_selection_logs', ['action' => 'selection_failed_quota_closed']);
        $this->assertDatabaseHas('kp_selection_logs', ['action' => 'selection_failed_not_open']);
    }

    public function test_full_quota_rejects_second_student_and_waiting_list_can_be_joined(): void
    {
        [$registration, $quota] = $this->verifiedRegistration($this->student, quota: 1);
        $secondUser = $this->makeUser('second@student.test', ['mahasiswa']);
        $secondStudent = $this->makeStudent($secondUser, '2210631230004');
        $secondRegistration = $this->verifiedRegistrationForPeriod($secondStudent, $registration->period, $quota->place);

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])->post('/mahasiswa/pemilihan-tempat/'.$quota->id.'/pilih')->assertRedirect();
        $this->actingAs($secondUser)->withSession(['active_role' => 'mahasiswa'])->post('/mahasiswa/pemilihan-tempat/'.$quota->id.'/pilih')->assertSessionHasErrors('selection');

        $this->assertSame(1, KpPlaceSelection::where('kp_place_quota_id', $quota->id)->where('status', 'aktif')->count());
        $this->assertDatabaseHas('kp_waiting_lists', ['kp_registration_id' => $secondRegistration->id, 'status' => 'menunggu']);
        $this->assertDatabaseHas('kp_selection_logs', ['action' => 'selection_failed_quota_full']);
    }

    public function test_admin_and_koordinator_can_monitor_and_student_cannot(): void
    {
        $this->verifiedRegistration($this->student);

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])->get('/management/place-selections')->assertOk()->assertSee('Pemantauan Pemilihan')->assertSee('Pratinjau Cetak')->assertSee('Excel');
        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])->get('/management/place-selections')->assertOk();
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])->get('/management/place-selections')->assertForbidden();
    }

    public function test_management_can_preview_print_and_download_place_selection_report(): void
    {
        [$registration, $quota] = $this->verifiedRegistration($this->student);
        KpPlaceSelection::create([
            'kp_period_id' => $registration->kp_period_id,
            'kp_registration_id' => $registration->id,
            'student_id' => $this->student->id,
            'kp_place_id' => $quota->kp_place_id,
            'kp_place_quota_id' => $quota->id,
            'selected_at' => now(),
            'selected_by' => $this->mahasiswa->id,
            'status' => 'aktif',
            'active_key' => $registration->kp_period_id.'-'.$this->student->id,
        ]);

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/place-selections/report/preview?status=aktif')
            ->assertOk()
            ->assertSee('Pemantauan Pemilihan Tempat KP')
            ->assertSee($this->student->nim)
            ->assertSee('Apotek Sehat');

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/place-selections/report/download/word?status=aktif')
            ->assertOk()
            ->assertHeader('content-type', 'application/msword; charset=UTF-8');

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/place-selections/report/download/pdf?status=aktif')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/place-selections/report/download/excel?status=aktif')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_admin_can_cancel_and_move_selection(): void
    {
        [$registration, $quota] = $this->verifiedRegistration($this->student);
        $selection = KpPlaceSelection::create([
            'kp_period_id' => $registration->kp_period_id,
            'kp_registration_id' => $registration->id,
            'student_id' => $this->student->id,
            'kp_place_id' => $quota->kp_place_id,
            'kp_place_quota_id' => $quota->id,
            'selected_at' => now(),
            'selected_by' => $this->mahasiswa->id,
            'status' => 'aktif',
            'active_key' => $registration->kp_period_id.'-'.$this->student->id,
        ]);

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])->post('/management/place-selections/'.$selection->id.'/cancel', ['reason' => 'Data perlu diperbaiki.'])->assertRedirect();
        $this->assertDatabaseHas('kp_place_selections', ['id' => $selection->id, 'status' => 'dibatalkan', 'active_key' => null]);
        $this->assertSame(0, $quota->fresh()->filledCount());

        $newPlace = KpPlace::create(['name' => 'RS Baru', 'type' => 'rumah_sakit', 'status' => 'aktif']);
        $newQuota = KpPlaceQuota::create(['kp_period_id' => $registration->kp_period_id, 'kp_place_id' => $newPlace->id, 'quota' => 5, 'is_open' => true]);
        $selection2 = KpPlaceSelection::create([
            'kp_period_id' => $registration->kp_period_id,
            'kp_registration_id' => $registration->id,
            'student_id' => $this->student->id,
            'kp_place_id' => $quota->kp_place_id,
            'kp_place_quota_id' => $quota->id,
            'selected_at' => now(),
            'selected_by' => $this->mahasiswa->id,
            'status' => 'aktif',
            'active_key' => $registration->kp_period_id.'-'.$this->student->id,
        ]);

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])->post('/management/place-selections/'.$selection2->id.'/move', [
            'kp_place_quota_id' => $newQuota->id,
            'reason' => 'Pindah manual.',
        ])->assertRedirect();

        $this->assertDatabaseHas('kp_place_selections', ['id' => $selection2->id, 'status' => 'dipindahkan']);
        $this->assertDatabaseHas('kp_place_selections', ['kp_place_quota_id' => $newQuota->id, 'status' => 'aktif']);
        $this->assertDatabaseHas('kp_selection_logs', ['action' => 'selection_moved_by_admin']);
    }

    public function test_selection_with_active_assignment_must_be_cancelled_from_assignment_flow(): void
    {
        [$registration, $quota] = $this->verifiedRegistration($this->student);
        $selection = KpPlaceSelection::create([
            'kp_period_id' => $registration->kp_period_id,
            'kp_registration_id' => $registration->id,
            'student_id' => $this->student->id,
            'kp_place_id' => $quota->kp_place_id,
            'kp_place_quota_id' => $quota->id,
            'selected_at' => now(),
            'selected_by' => $this->mahasiswa->id,
            'status' => 'aktif',
            'active_key' => $registration->kp_period_id.'-'.$this->student->id,
        ]);
        KpAssignment::create([
            'kp_period_id' => $selection->kp_period_id,
            'kp_registration_id' => $selection->kp_registration_id,
            'kp_place_selection_id' => $selection->id,
            'student_id' => $selection->student_id,
            'kp_place_id' => $selection->kp_place_id,
            'status' => 'menunggu_pembimbing',
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'active_key' => $selection->kp_period_id.'-'.$selection->student_id,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post('/management/place-selections/'.$selection->id.'/cancel', ['reason' => 'Salah pilih.'])
            ->assertSessionHasErrors('selection');

        $selection->refresh();
        $this->assertSame('aktif', $selection->status);
        $this->assertNotNull($selection->active_key);
        $this->assertSame(1, $quota->fresh()->filledCount());
    }

    public function test_koordinator_can_manually_select_place_for_non_war_ticket_student(): void
    {
        [$registration, $quota] = $this->verifiedRegistration($this->student, quota: 2);
        $registration->period->update([
            'selection_start_at' => now()->addDays(5),
            'selection_end_at' => now()->addDays(6),
        ]);

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/place-selections/manual')
            ->assertOk()
            ->assertSee('Pilihkan Tempat KP')
            ->assertSee($this->student->nim)
            ->assertSee($quota->place->name);

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/place-selections/manual', [
                'kp_registration_id' => $registration->id,
                'kp_place_quota_id' => $quota->id,
                'reason' => 'Ditunjuk langsung oleh koordinator.',
            ])
            ->assertRedirect();

        $selection = KpPlaceSelection::where('kp_registration_id', $registration->id)->firstOrFail();

        $this->assertSame('aktif', $selection->status);
        $this->assertSame($this->koordinator->id, $selection->selected_by);
        $this->assertStringContainsString('Ditunjuk langsung', (string) $selection->note);
        $this->assertSame(1, $quota->fresh()->filledCount());
        $this->assertDatabaseHas('kp_selection_logs', [
            'kp_registration_id' => $registration->id,
            'action' => 'selection_manual_by_koordinator',
            'status' => 'success',
        ]);

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/place-selections/'.$selection->id.'/create-assignment')
            ->assertRedirect();

        $this->assertDatabaseHas('kp_assignments', [
            'kp_place_selection_id' => $selection->id,
            'student_id' => $this->student->id,
            'kp_place_id' => $quota->kp_place_id,
            'status' => 'menunggu_pembimbing',
        ]);
    }

    public function test_manual_selection_rejects_duplicate_or_unverified_student(): void
    {
        [$registration, $quota] = $this->verifiedRegistration($this->student, quota: 1);

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/place-selections/manual', [
                'kp_registration_id' => $registration->id,
                'kp_place_quota_id' => $quota->id,
                'reason' => 'Penempatan pertama.',
            ])
            ->assertRedirect();

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/place-selections/manual', [
                'kp_registration_id' => $registration->id,
                'kp_place_quota_id' => $quota->id,
                'reason' => 'Duplikat.',
            ])
            ->assertSessionHasErrors('kp_registration_id');

        $other = $this->makeUser('manual-unverified@student.test', ['mahasiswa']);
        $otherStudent = $this->makeStudent($other, '2210631230099');
        [$draftRegistration] = $this->draftRegistrationWithQuota($otherStudent);

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/place-selections/manual', [
                'kp_registration_id' => $draftRegistration->id,
                'kp_place_quota_id' => $quota->id,
                'reason' => 'Belum valid.',
            ])
            ->assertSessionHasErrors('kp_registration_id');
    }

    private function verifiedRegistration(Student $student, int $quota = 5): array
    {
        $period = KpPeriod::create([
            'name' => 'KP Genap 2026',
            'registration_start_at' => now()->subDays(10),
            'registration_end_at' => now()->subDays(2),
            'selection_start_at' => now()->subHour(),
            'selection_end_at' => now()->addDay(),
            'status' => 'dibuka',
        ]);
        $place = KpPlace::create(['name' => 'Apotek Sehat', 'type' => 'apotek', 'status' => 'aktif']);
        $quotaModel = KpPlaceQuota::create(['kp_period_id' => $period->id, 'kp_place_id' => $place->id, 'quota' => $quota, 'is_open' => true]);
        $registration = $this->verifiedRegistrationForPeriod($student, $period, $place);

        return [$registration, $quotaModel];
    }

    private function verifiedRegistrationForPeriod(Student $student, KpPeriod $period, KpPlace $place): KpRegistration
    {
        $requirement = KpDocumentRequirement::firstOrCreate(['kp_period_id' => $period->id, 'name' => 'KRS'], ['is_required' => true, 'status' => 'aktif']);
        $registration = KpRegistration::create(['kp_period_id' => $period->id, 'student_id' => $student->id, 'status' => 'terverifikasi']);
        KpDocument::create([
            'kp_registration_id' => $registration->id,
            'kp_document_requirement_id' => $requirement->id,
            'original_filename' => 'krs.pdf',
            'file_path' => 'kp-documents/test/krs.pdf',
            'status' => 'disetujui',
        ]);

        return $registration;
    }

    private function draftRegistrationWithQuota(Student $student): array
    {
        [$registration, $quota] = $this->verifiedRegistration($student);
        $registration->update(['status' => 'draft']);

        return [$registration, $quota];
    }

    private function makeStudent(User $user, string $nim): Student
    {
        $user->forceFill(['profile_completed' => true])->save();

        return Student::create([
            'user_id' => $user->id,
            'nim' => $nim,
            'study_program' => 'Farmasi',
            'semester' => 6,
            'phone' => '081234567890',
            'status' => 'active',
        ]);
    }

    private function makeUser(string $email, array $roles): User
    {
        $user = User::create([
            'name' => 'User Test',
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user;
    }
}
