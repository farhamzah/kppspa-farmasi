<?php

namespace Tests\Feature;

use App\Models\KpAssignment;
use App\Models\KpExternalDocumentReference;
use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\KpRegistration;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalDocumentReferenceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $koordinator;
    private User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('admin-reference@test.local', ['admin']);
        $this->koordinator = $this->makeUser('koor-reference@test.local', ['koordinator_kp']);
        $this->mahasiswa = $this->makeUser('student-reference@test.local', ['mahasiswa']);
    }

    public function test_admin_and_koordinator_can_open_reference_management_but_student_cannot(): void
    {
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->get(route('management.integration.external-document-references.index'))
            ->assertOk()
            ->assertSee('Referensi Dokumen Eksternal TU')
            ->assertSee('Buat Draf Referensi Lokal');

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->get(route('management.integration.external-document-references.index'))
            ->assertOk()
            ->assertSee('Referensi Draf TU');

        $this->actingAs($this->mahasiswa)->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/integration/external-document-references')
            ->assertForbidden();
    }

    public function test_admin_can_create_local_draft_references_from_tu_preview_without_external_request(): void
    {
        Http::fake();
        $assignment = $this->createAssignment('KP-REF-MGMT-001');

        $before = [
            'assignments' => DB::table('kp_assignments')->count(),
            'users' => DB::table('users')->count(),
        ];

        $this->actingAs($this->admin)->withSession(['active_role' => 'admin'])
            ->post(route('management.integration.external-document-references.store-drafts'), [
                'assignment_id' => $assignment->id,
                'limit' => 1,
            ])
            ->assertRedirect(route('management.integration.external-document-references.index', [
                'assignment_id' => $assignment->id,
                'limit' => 1,
            ]));

        Http::assertNothingSent();
        $this->assertSame(7, KpExternalDocumentReference::count());
        $this->assertSame($before['assignments'], DB::table('kp_assignments')->count());
        $this->assertSame($before['users'], DB::table('users')->count());

        $stored = json_encode(KpExternalDocumentReference::query()->pluck('last_payload_snapshot')->all());
        $this->assertStringNotContainsString('storage/app', $stored);
        $this->assertStringNotContainsString('signed_url', $stored);
        $this->assertStringNotContainsString('password', strtolower($stored));
        $this->assertStringNotContainsString('secret', strtolower($stored));
        $this->assertStringNotContainsString('token', strtolower($stored));

        $this->assertDatabaseHas('kp_external_document_references', [
            'document_type' => 'placement_letter',
            'service_code' => 'KP_PLACEMENT_LETTER',
            'source_reference_type' => 'kp_assignment',
            'source_reference_id' => (string) $assignment->id,
            'external_status' => 'draft',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_koordinator_can_create_drafts_idempotently_without_duplicates(): void
    {
        Http::fake();
        $assignment = $this->createAssignment('KP-REF-MGMT-002');

        $payload = [
            'assignment_id' => $assignment->id,
            'limit' => 1,
        ];

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post(route('management.integration.external-document-references.store-drafts'), $payload)
            ->assertRedirect();

        $this->assertSame(7, KpExternalDocumentReference::count());

        $this->actingAs($this->koordinator)->withSession(['active_role' => 'koordinator_kp'])
            ->post(route('management.integration.external-document-references.store-drafts'), $payload)
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertSame(7, KpExternalDocumentReference::count());
        $this->assertTrue(KpExternalDocumentReference::query()->get()->every->isSafeReferenceUrl());
    }

    private function makeUser(string $email, array $roles): User
    {
        $user = User::create([
            'name' => 'Reference User',
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user;
    }

    private function createAssignment(string $registrationNumber): KpAssignment
    {
        $studentUser = User::create([
            'name' => 'Alya Farmasi',
            'email' => strtolower($registrationNumber).'@test.local',
            'password' => 'hash',
            'status' => 'active',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'nim' => substr(md5($registrationNumber), 0, 12),
            'study_program' => 'Farmasi',
            'semester' => 7,
            'class_name' => 'A',
            'status' => 'active',
        ]);

        $period = KpPeriod::create([
            'name' => 'KP Farmasi Reference',
            'academic_year' => '2025/2026',
            'semester' => 'genap',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'selection_start_at' => now()->subDay(),
            'selection_end_at' => now()->addDay(),
            'kp_start_date' => now()->toDateString(),
            'kp_end_date' => now()->addMonth()->toDateString(),
            'status' => 'dibuka',
        ]);

        $place = KpPlace::create([
            'name' => 'Apotek Reference',
            'type' => 'apotek',
            'city' => 'Karawang',
            'province' => 'Jawa Barat',
            'status' => 'aktif',
        ]);

        $registration = KpRegistration::create([
            'kp_period_id' => $period->id,
            'student_id' => $student->id,
            'registration_number' => $registrationNumber,
            'status' => 'terverifikasi',
        ]);

        return KpAssignment::create([
            'kp_period_id' => $period->id,
            'kp_registration_id' => $registration->id,
            'student_id' => $student->id,
            'kp_place_id' => $place->id,
            'status' => 'aktif',
            'active_key' => $period->id.'-'.$student->id,
        ]);
    }
}
