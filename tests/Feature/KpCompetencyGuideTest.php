<?php

namespace Tests\Feature;

use App\Models\FieldSupervisor;
use App\Models\KpAssignment;
use App\Models\KpCompetency;
use App\Models\KpDocument;
use App\Models\KpDocumentRequirement;
use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpPlaceQuota;
use App\Models\KpPlaceSelection;
use App\Models\KpRegistration;
use App\Models\Lecturer;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KpCompetencyGuideTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;
    private User $lecturerUser;
    private Lecturer $lecturer;
    private User $fieldUser;
    private FieldSupervisor $fieldSupervisor;
    private KpAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('admin-competency@test.local', ['admin']);
        $this->koordinator = $this->makeUser('koordinator-competency@test.local', ['koordinator_kp']);
        $this->mahasiswa = $this->makeUser('student-competency@test.local', ['mahasiswa']);
        $this->lecturerUser = $this->makeUser('internal-competency@test.local', ['pembimbing_dalam']);
        $this->lecturer = Lecturer::create(['user_id' => $this->lecturerUser->id, 'nidn_nip' => '009988', 'status' => 'active']);
        $this->fieldUser = $this->makeUser('field-competency@test.local', ['pembimbing_lapangan']);
        $this->fieldSupervisor = FieldSupervisor::create(['user_id' => $this->fieldUser->id, 'institution_name' => 'Apotek Sehat', 'position' => 'Supervisor', 'status' => 'active']);

        $student = $this->makeStudent($this->mahasiswa, '2210631250001');
        $this->assignment = $this->assignment($student, $this->lecturer, $this->fieldSupervisor);
    }

    public function test_management_can_build_competencies_and_monitor_assignments(): void
    {
        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/competencies')
            ->assertOk()
            ->assertSee('Panduan Kompetensi KP')
            ->assertSee($this->assignment->student->user->name);

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/competencies', [
                'kp_period_id' => $this->assignment->kp_period_id,
                'place_type' => 'apotek',
                'title' => 'Pelayanan resep',
                'description' => 'Mahasiswa mampu memahami alur pelayanan resep.',
                'sort_order' => 1,
                'status' => 'aktif',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kp_competencies', [
            'kp_period_id' => $this->assignment->kp_period_id,
            'place_type' => 'apotek',
            'title' => 'Pelayanan resep',
            'status' => 'aktif',
        ]);

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->post('/management/competencies', [
                'kp_period_id' => $this->assignment->kp_period_id,
                'place_types' => ['apotek', 'rumah_sakit'],
                'title' => 'Komunikasi profesional lintas fasilitas',
                'description' => 'Mahasiswa mampu berkomunikasi dengan tenaga kesehatan.',
                'sort_order' => 2,
                'status' => 'aktif',
            ])
            ->assertRedirect();

        $multiTypeCompetency = KpCompetency::where('title', 'Komunikasi profesional lintas fasilitas')->firstOrFail();
        $this->assertNull($multiTypeCompetency->place_type);
        $this->assertSame(['apotek', 'rumah_sakit'], $multiTypeCompetency->place_types);
        $this->assertSame('Apotek, Rumah Sakit', $multiTypeCompetency->placeTypeLabel());

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/competencies')
            ->assertForbidden();
    }

    public function test_field_supervisor_can_check_only_own_student_competencies(): void
    {
        $competency = KpCompetency::create([
            'kp_period_id' => $this->assignment->kp_period_id,
            'title' => 'Komunikasi pasien',
            'status' => 'aktif',
        ]);
        $unassignedUser = $this->makeUser('unassigned-competency@test.local', ['mahasiswa']);
        $unassignedUser->update(['name' => 'Mahasiswa Kompetensi Tanpa Pembimbing Lapangan']);
        $unassignedStudent = $this->makeStudent($unassignedUser, '2210631250777');
        $unassignedAssignment = $this->assignment($unassignedStudent, $this->lecturer, $this->fieldSupervisor);
        $unassignedAssignment->update(['field_supervisor_id' => null, 'status' => 'menunggu_pembimbing']);

        $this->actingAs($this->fieldUser)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/kompetensi')
            ->assertOk()
            ->assertSee($this->assignment->student->user->name)
            ->assertDontSee($unassignedStudent->user->name);

        $this->actingAs($this->fieldUser)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/kompetensi/'.$this->assignment->id)
            ->assertOk()
            ->assertSee('Komunikasi pasien')
            ->assertSee('Simpan Checklist');

        $this->actingAs($this->fieldUser)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->put('/pembimbing-lapangan/kompetensi/'.$this->assignment->id, [
                'competencies' => [$competency->id],
                'notes' => [$competency->id => 'Sudah mandiri.'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kp_competency_achievements', [
            'kp_assignment_id' => $this->assignment->id,
            'kp_competency_id' => $competency->id,
            'checked_by' => $this->fieldUser->id,
            'note' => 'Sudah mandiri.',
        ]);

        $otherFieldUser = $this->makeUser('other-field-competency@test.local', ['pembimbing_lapangan']);
        FieldSupervisor::create(['user_id' => $otherFieldUser->id, 'institution_name' => 'RS Lain', 'position' => 'Supervisor', 'status' => 'active']);

        $this->actingAs($otherFieldUser)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->put('/pembimbing-lapangan/kompetensi/'.$this->assignment->id, ['competencies' => []])
            ->assertForbidden();
    }

    public function test_competencies_are_filtered_by_assignment_place_type(): void
    {
        $general = KpCompetency::create([
            'title' => 'Etika kerja umum',
            'status' => 'aktif',
        ]);
        $apotek = KpCompetency::create([
            'place_type' => 'apotek',
            'title' => 'Pelayanan resep apotek',
            'status' => 'aktif',
        ]);
        $hospital = KpCompetency::create([
            'place_type' => 'rumah_sakit',
            'title' => 'Rekonsiliasi obat rumah sakit',
            'status' => 'aktif',
        ]);
        $multiType = KpCompetency::create([
            'place_types' => ['apotek', 'rumah_sakit'],
            'title' => 'Komunikasi profesional',
            'status' => 'aktif',
        ]);
        $industry = KpCompetency::create([
            'place_types' => ['industri', 'distributor'],
            'title' => 'Dokumentasi produksi dan distribusi',
            'status' => 'aktif',
        ]);

        $this->actingAs($this->fieldUser)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/kompetensi/'.$this->assignment->id)
            ->assertOk()
            ->assertSee($general->title)
            ->assertSee($apotek->title)
            ->assertSee($multiType->title)
            ->assertDontSee($hospital->title)
            ->assertDontSee($industry->title);

        $this->actingAs($this->fieldUser)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->put('/pembimbing-lapangan/kompetensi/'.$this->assignment->id, [
                'competencies' => [$hospital->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('kp_competency_achievements', [
            'kp_assignment_id' => $this->assignment->id,
            'kp_competency_id' => $hospital->id,
        ]);

        $hospitalStudent = $this->makeStudent($this->makeUser('hospital-student-competency@test.local', ['mahasiswa']), '2210631250002');
        $hospitalAssignment = $this->assignment($hospitalStudent, $this->lecturer, $this->fieldSupervisor, 'rumah_sakit', 'RS Sehat');

        $this->actingAs($this->fieldUser)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/pembimbing-lapangan/kompetensi/'.$hospitalAssignment->id)
            ->assertOk()
            ->assertSee($general->title)
            ->assertSee($hospital->title)
            ->assertSee($multiType->title)
            ->assertDontSee($apotek->title)
            ->assertDontSee($industry->title);
    }

    public function test_internal_supervisor_can_view_but_not_update_competencies(): void
    {
        KpCompetency::create([
            'kp_period_id' => $this->assignment->kp_period_id,
            'title' => 'Etika profesi',
            'status' => 'aktif',
        ]);

        $this->actingAs($this->lecturerUser)
            ->withSession(['active_role' => 'pembimbing_dalam'])
            ->get('/pembimbing-dalam/kompetensi/'.$this->assignment->id)
            ->assertOk()
            ->assertSee('Read-only')
            ->assertSee('Etika profesi')
            ->assertDontSee('Simpan Checklist');

        $this->actingAs($this->lecturerUser)
            ->withSession(['active_role' => 'pembimbing_dalam'])
            ->put('/pembimbing-lapangan/kompetensi/'.$this->assignment->id, ['competencies' => []])
            ->assertForbidden();
    }

    private function assignment(Student $student, Lecturer $lecturer, FieldSupervisor $fieldSupervisor, string $placeType = 'apotek', string $placeName = 'Apotek Sehat'): KpAssignment
    {
        $period = KpPeriod::create(['name' => 'KP Genap 2026', 'status' => 'dibuka']);
        $place = KpPlace::create(['name' => $placeName, 'type' => $placeType, 'status' => 'aktif']);
        $quota = KpPlaceQuota::create(['kp_period_id' => $period->id, 'kp_place_id' => $place->id, 'quota' => 5, 'is_open' => true]);
        $requirement = KpDocumentRequirement::create(['kp_period_id' => $period->id, 'name' => 'KRS', 'is_required' => true, 'status' => 'aktif']);
        $registration = KpRegistration::create(['kp_period_id' => $period->id, 'student_id' => $student->id, 'status' => 'terverifikasi']);
        KpDocument::create(['kp_registration_id' => $registration->id, 'kp_document_requirement_id' => $requirement->id, 'file_path' => 'x.pdf', 'status' => 'disetujui']);
        $selection = KpPlaceSelection::create([
            'kp_period_id' => $period->id,
            'kp_registration_id' => $registration->id,
            'student_id' => $student->id,
            'kp_place_id' => $place->id,
            'kp_place_quota_id' => $quota->id,
            'selected_at' => now(),
            'selected_by' => $student->user_id,
            'status' => 'aktif',
            'active_key' => $period->id.'-'.$student->id,
        ]);

        return KpAssignment::create([
            'kp_period_id' => $period->id,
            'kp_registration_id' => $registration->id,
            'kp_place_selection_id' => $selection->id,
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
