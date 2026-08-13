<?php

namespace Tests\Feature;

use App\Models\KpDocument;
use App\Models\KpDocumentRequirement;
use App\Models\KpPeriod;
use App\Models\KpRegistration;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KpRegistrationAndDocumentVerificationTest extends TestCase
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
        $this->student = Student::create([
            'user_id' => $this->mahasiswa->id,
            'nim' => '2210631230001',
            'study_program' => 'Farmasi',
            'semester' => 6,
            'phone' => '081234567890',
            'status' => 'active',
        ]);
        $this->mahasiswa->forceFill(['profile_completed' => true])->save();
    }

    public function test_mahasiswa_can_open_registration_page_and_management_is_forbidden(): void
    {
        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/pendaftaran-kp')
            ->assertOk()
            ->assertSee('Pendaftaran KP');

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/management/kp-registrations')
            ->assertForbidden();
    }

    public function test_mahasiswa_can_open_kp_documents_page_and_empty_state_without_registration(): void
    {
        $response = $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/berkas-kp');

        $response->assertOk()
            ->assertSee('Anda belum memiliki pendaftaran KP')
            ->assertSee('Berkas KP');
        $this->assertSame(1, substr_count($response->getContent(), 'bg-cyan-700 text-white'));
    }

    public function test_mahasiswa_with_registration_can_view_kp_documents_and_upload_rules_still_apply(): void
    {
        Storage::fake('local');
        [$registration, $requirement] = $this->registrationWithRequirement();

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/berkas-kp')
            ->assertOk()
            ->assertSee('KRS')
            ->assertSee('Belum Unggah');

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post("/mahasiswa/pendaftaran-kp/{$registration->id}/documents/{$requirement->id}", [
                'document' => UploadedFile::fake()->create('krs.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = KpDocument::where('kp_registration_id', $registration->id)->firstOrFail();
        $document->update(['status' => 'disetujui']);
        $registration->update(['status' => 'terverifikasi']);

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post("/mahasiswa/pendaftaran-kp/{$registration->id}/documents/{$requirement->id}", [
                'document' => UploadedFile::fake()->create('krs-baru.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('document');
    }

    public function test_mahasiswa_cannot_register_when_profile_is_incomplete(): void
    {
        $this->mahasiswa->forceFill(['profile_completed' => false])->save();
        $this->student->update(['phone' => null]);
        $period = $this->openPeriod();

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/pendaftaran-kp', ['kp_period_id' => $period->id])
            ->assertSessionHasErrors('profile');
    }

    public function test_mahasiswa_can_create_registration_and_duplicate_is_rejected(): void
    {
        $period = $this->openPeriod();
        $this->requirement($period);

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/pendaftaran-kp', ['kp_period_id' => $period->id])
            ->assertRedirect();

        $this->assertDatabaseHas('kp_registrations', [
            'kp_period_id' => $period->id,
            'student_id' => $this->student->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/mahasiswa/pendaftaran-kp', ['kp_period_id' => $period->id])
            ->assertSessionHasErrors('kp_period_id');
    }

    public function test_mahasiswa_can_upload_valid_document_and_invalid_file_is_rejected(): void
    {
        Storage::fake('local');
        [$registration, $requirement] = $this->registrationWithRequirement();

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post("/mahasiswa/pendaftaran-kp/{$registration->id}/documents/{$requirement->id}", [
                'document' => UploadedFile::fake()->create('krs.exe', 10, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('document');

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post("/mahasiswa/pendaftaran-kp/{$registration->id}/documents/{$requirement->id}", [
                'document' => UploadedFile::fake()->create('krs.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = KpDocument::where('kp_registration_id', $registration->id)->firstOrFail();
        $this->assertSame('menunggu', $document->status);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_mahasiswa_can_submit_after_required_document_uploaded(): void
    {
        [$registration, $requirement] = $this->registrationWithRequirement();
        KpDocument::where('kp_registration_id', $registration->id)
            ->where('kp_document_requirement_id', $requirement->id)
            ->update([
                'original_filename' => 'krs.pdf',
                'file_path' => 'kp-documents/test/krs.pdf',
                'file_disk' => 'local',
                'status' => 'menunggu',
                'uploaded_at' => now(),
            ]);

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post("/mahasiswa/pendaftaran-kp/{$registration->id}/submit")
            ->assertRedirect();

        $registration->refresh();
        $this->assertSame('menunggu_verifikasi', $registration->status);
        $this->assertNotNull($registration->registration_number);
    }

    public function test_document_page_prompts_submit_when_required_files_are_complete(): void
    {
        [$registration, $requirement] = $this->registrationWithRequirement();
        KpDocument::where('kp_registration_id', $registration->id)
            ->where('kp_document_requirement_id', $requirement->id)
            ->update([
                'original_filename' => 'krs.pdf',
                'file_path' => 'kp-documents/test/krs.pdf',
                'file_disk' => 'local',
                'status' => 'disetujui',
                'uploaded_at' => now(),
            ]);

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/mahasiswa/berkas-kp')
            ->assertOk()
            ->assertSee('Berkas sudah lengkap, kirim pendaftaran Anda.')
            ->assertSee('Kirim Pendaftaran');
    }

    public function test_admin_and_koordinator_can_open_review_page(): void
    {
        $this->registrationWithRequirement();

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->get('/management/kp-registrations')
            ->assertOk()
            ->assertSee('Verifikasi Pendaftaran KP');

        $this->actingAs($this->koordinator)
            ->withSession(['active_role' => 'koordinator_kp'])
            ->get('/management/kp-registrations')
            ->assertOk();
    }

    public function test_review_link_preserves_current_queue_page_and_filters(): void
    {
        $period = $this->openPeriod();
        $this->requirement($period);

        for ($i = 1; $i <= 11; $i++) {
            $user = $this->makeUser("queue-{$i}@test.local", ['mahasiswa']);
            $user->forceFill(['name' => "Queue Test {$i}", 'profile_completed' => true])->save();
            $student = Student::create([
                'user_id' => $user->id,
                'nim' => sprintf('221063124%04d', $i),
                'study_program' => 'Farmasi',
                'semester' => 6,
                'phone' => '081234567890',
                'status' => 'active',
            ]);
            KpRegistration::create([
                'kp_period_id' => $period->id,
                'student_id' => $student->id,
                'registration_number' => sprintf('KP-2026-%04d', $i),
                'status' => 'menunggu_verifikasi',
                'submitted_at' => now(),
            ]);
        }

        $queueUrl = "/management/kp-registrations?page=2&q=Queue%20Test&period={$period->id}&status=menunggu_verifikasi";
        $indexResponse = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->get($queueUrl);

        $indexResponse->assertOk()
            ->assertSee('return_url=', false)
            ->assertSee('page%3D2', false)
            ->assertSee('q%3DQueue%2520Test', false)
            ->assertSee('status%3Dmenunggu_verifikasi', false);

        $registration = KpRegistration::where('status', 'menunggu_verifikasi')->latest()->skip(10)->firstOrFail();
        $detailResponse = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->get(route('management.kp-registrations.show', [
                'registration' => $registration,
                'return_url' => url($queueUrl),
            ]));

        $detailResponse->assertOk()
            ->assertSee('Kembali ke Antrian')
            ->assertSee('href="http://localhost/management/kp-registrations?page=2&amp;q=Queue%20Test&amp;period='.$period->id.'&amp;status=menunggu_verifikasi"', false);
    }

    public function test_admin_can_preview_uploaded_registration_document_inline(): void
    {
        Storage::fake('local');
        [$registration, $requirement] = $this->registrationWithRequirement();
        Storage::disk('local')->put('kp-documents/test/krs.pdf', 'PDF preview content');

        $document = KpDocument::where('kp_registration_id', $registration->id)
            ->where('kp_document_requirement_id', $requirement->id)
            ->firstOrFail();
        $document->update([
            'original_filename' => 'krs.pdf',
            'file_path' => 'kp-documents/test/krs.pdf',
            'file_disk' => 'local',
            'file_mime' => 'application/pdf',
            'status' => 'menunggu',
        ]);

        $showResponse = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->get("/management/kp-registrations/{$registration->id}");

        $showResponse->assertOk()
            ->assertSee('Kembali ke Antrian')
            ->assertSee('Pratinjau')
            ->assertSee('Unduh');

        $previewResponse = $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->get("/management/kp-registrations/{$registration->id}/documents/{$document->id}/preview");

        $previewResponse->assertOk();
        $this->assertStringContainsString(
            'inline',
            strtolower($previewResponse->headers->get('content-disposition') ?? '')
        );

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get("/management/kp-registrations/{$registration->id}/documents/{$document->id}/preview")
            ->assertForbidden();
    }

    public function test_admin_can_approve_revision_and_verify_registration_rules(): void
    {
        [$registration, $requirement] = $this->registrationWithRequirement();
        $document = KpDocument::where('kp_registration_id', $registration->id)
            ->where('kp_document_requirement_id', $requirement->id)
            ->firstOrFail();
        $document->update([
            'original_filename' => 'krs.pdf',
            'file_path' => 'kp-documents/test/krs.pdf',
            'file_disk' => 'local',
            'status' => 'menunggu',
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post("/management/kp-registrations/{$registration->id}/verify")
            ->assertSessionHasErrors('registration');

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post("/management/kp-registrations/{$registration->id}/documents/{$document->id}/revision", [
                'review_note' => 'File kurang jelas.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kp_documents', ['id' => $document->id, 'status' => 'revisi']);
        $this->assertDatabaseHas('kp_registration_logs', ['kp_registration_id' => $registration->id, 'action' => 'document_revision']);

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post("/management/kp-registrations/{$registration->id}/documents/{$document->id}/approve")
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post("/management/kp-registrations/{$registration->id}/verify", [
                'verification_note' => 'Lengkap.',
            ])
            ->assertSessionHasErrors('registration');

        $this->actingAs($this->mahasiswa)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post("/mahasiswa/pendaftaran-kp/{$registration->id}/submit")
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->withSession(['active_role' => 'admin'])
            ->post("/management/kp-registrations/{$registration->id}/verify", [
                'verification_note' => 'Lengkap.',
            ])
            ->assertRedirect();

        $registration->refresh()->load(['period.documentRequirements', 'documents']);
        $this->assertSame('terverifikasi', $registration->status);
        $this->assertTrue($registration->isEligibleForPlaceSelection());
    }

    private function registrationWithRequirement(): array
    {
        $period = $this->openPeriod();
        $requirement = $this->requirement($period);
        $registration = KpRegistration::create([
            'kp_period_id' => $period->id,
            'student_id' => $this->student->id,
            'status' => 'draft',
        ]);
        KpDocument::create([
            'kp_registration_id' => $registration->id,
            'kp_document_requirement_id' => $requirement->id,
            'status' => 'belum_upload',
        ]);

        return [$registration, $requirement];
    }

    private function openPeriod(): KpPeriod
    {
        return KpPeriod::create([
            'name' => 'KP Genap 2026',
            'academic_year' => '2025/2026',
            'semester' => 'genap',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDays(7),
            'status' => 'dibuka',
        ]);
    }

    private function requirement(KpPeriod $period): KpDocumentRequirement
    {
        return KpDocumentRequirement::create([
            'kp_period_id' => $period->id,
            'name' => 'KRS',
            'is_required' => true,
            'allowed_file_types' => 'pdf,jpg,jpeg,png',
            'max_file_size_mb' => 5,
            'status' => 'aktif',
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
