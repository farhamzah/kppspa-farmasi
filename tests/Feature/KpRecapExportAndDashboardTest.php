<?php

namespace Tests\Feature;

use App\Models\KpAssignment;
use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpRegistration;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KpRecapExportAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;
    private User $internal;
    private User $field;
    private User $examiner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('admin-recap@test.local', ['admin']);
        $this->koordinator = $this->makeUser('koor-recap@test.local', ['koordinator_kp']);
        $this->mahasiswa = $this->makeUser('student-recap@test.local', ['mahasiswa']);
        $this->internal = $this->makeUser('internal-recap@test.local', ['pembimbing_dalam']);
        $this->field = $this->makeUser('field-recap@test.local', ['pembimbing_lapangan']);
        $this->examiner = $this->makeUser('examiner-recap@test.local', ['penguji']);
    }

    public function test_admin_and_koordinator_can_open_recaps_and_other_roles_cannot(): void
    {
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/recaps')
            ->assertOk()
            ->assertSee('Rekap, Pemantauan, dan Ekspor KP')
            ->assertSee('Pratinjau')
            ->assertSee('Excel');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/recaps/students')
            ->assertOk()
            ->assertSee('Rekap Mahasiswa KP')
            ->assertSee('Pratinjau Cetak')
            ->assertSee('PDF');

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/recaps')
            ->assertForbidden();
    }

    public function test_exports_can_be_downloaded_by_management_only(): void
    {
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get('/management/exports/students')
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/exports/scores')
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/exports/scores')
            ->assertForbidden();
    }

    public function test_recap_reports_can_be_previewed_printed_and_downloaded_by_management_only(): void
    {
        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/recaps/students/preview')
            ->assertOk()
            ->assertSee('Rekap Mahasiswa KP');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/recaps/placements/download/word')
            ->assertOk()
            ->assertHeader('content-type', 'application/msword; charset=UTF-8');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/recaps/logbooks/download/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/recaps/scores/download/excel')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/recaps/scores/download/pdf')
            ->assertForbidden();
    }

    public function test_all_role_dashboards_can_be_opened(): void
    {
        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/dashboard')
            ->assertOk()
            ->assertSee('Dashboard Mahasiswa')
            ->assertSee('Pendaftaran')
            ->assertSee('Berkas')
            ->assertSee('Nilai')
            ->assertDontSee('Data transaksi tetap memakai ID legacy KP')
            ->assertDontSee('Segera');
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])->get('/admin/dashboard')->assertOk()->assertSee('Dashboard Admin')->assertDontSee('Segera');
        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])->get('/koordinator/dashboard')->assertOk()->assertSee('Dashboard Koordinator PKPA')->assertDontSee('Segera');
        $this->actingAs($this->internal)->withSession(['active_role' => 'pembimbing_dalam'])->get('/pembimbing-dalam/dashboard')->assertOk()->assertSee('Dashboard Pembimbing Dalam')->assertDontSee('Segera');
        $this->actingAs($this->field)->withSession(['active_role' => 'pembimbing_lapangan'])->get('/pembimbing-lapangan/dashboard')->assertOk()->assertSee('Dashboard Pembimbing Luar')->assertDontSee('Segera');
        $this->actingAs($this->examiner)->withSession(['active_role' => 'penguji'])->get('/penguji/dashboard')->assertOk()->assertSee('Dashboard Penguji')->assertDontSee('Segera');
    }

    public function test_field_supervisor_dashboard_does_not_count_unassigned_students_without_profile(): void
    {
        $this->createAssignmentWithNoFieldSupervisor();

        $this->actingAs($this->field)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/dashboard')
            ->assertOk()
            ->assertSee('Dashboard Pembimbing Luar')
            ->assertSee('Tidak ada antrian mendesak')
            ->assertDontSee('Nilai belum submit');
    }

    private function makeUser(string $email, array $roles): User
    {
        $user = User::create(['name' => 'User Test', 'email' => $email, 'password' => Hash::make('password'), 'status' => 'active']);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user;
    }

    private function createAssignmentWithNoFieldSupervisor(): KpAssignment
    {
        $studentUser = $this->makeUser('dashboard-student@test.local', ['mahasiswa']);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'nim' => '24416248201001',
            'study_program' => 'Farmasi',
            'semester' => 6,
            'status' => 'active',
        ]);
        $period = KpPeriod::create(['name' => 'KP TA 2026_2027', 'status' => 'dibuka']);
        $place = KpPlace::create(['name' => 'Apotek UAT', 'type' => 'apotek', 'status' => 'aktif']);
        $registration = KpRegistration::create([
            'kp_period_id' => $period->id,
            'student_id' => $student->id,
            'status' => 'terverifikasi',
        ]);

        return KpAssignment::create([
            'kp_period_id' => $period->id,
            'kp_registration_id' => $registration->id,
            'student_id' => $student->id,
            'kp_place_id' => $place->id,
            'internal_supervisor_id' => null,
            'field_supervisor_id' => null,
            'status' => 'aktif',
            'assigned_by' => $this->koordinator->id,
            'assigned_at' => now(),
            'active_key' => $period->id.'-'.$student->id,
        ]);
    }
}
