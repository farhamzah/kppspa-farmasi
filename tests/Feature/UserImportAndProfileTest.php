<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\KpPlace;
use App\Services\UserImportService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserImportAndProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.core', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('core');

        $this->seed(RoleSeeder::class);
    }

    public function test_import_validation_rejects_duplicate_email(): void
    {
        User::create([
            'name' => 'Existing',
            'email' => 'existing@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $service = app(UserImportService::class);
        $preview = $service->preview('mahasiswa', [
            ['nim' => '2211', 'name' => 'A', 'email' => 'existing@test.local'],
            ['nim' => '2212', 'name' => 'B', 'email' => 'new@test.local'],
            ['nim' => '2213', 'name' => 'C', 'email' => 'new@test.local'],
        ]);

        $this->assertFalse($preview[0]['valid']);
        $this->assertTrue($preview[1]['valid']);
        $this->assertFalse($preview[2]['valid']);
    }

    public function test_user_can_open_and_update_own_profile(): void
    {
        config(['core_farmasi.profile_url' => 'https://core.test/profile?token=secret']);

        $user = User::create([
            'name' => 'Mahasiswa',
            'email' => 'mhs@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
            'profile_completed' => false,
        ]);
        $user->roles()->sync([Role::where('name', 'mahasiswa')->first()->id]);
        $user->student()->create(['nim' => '221010001']);

        $this->actingAs($user)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/profil-saya')
            ->assertOk()
            ->assertSee('Profil Saya')
            ->assertSee('Profil utama dikelola di Core Farmasi')
            ->assertSee('https://core.test/profile/edit', false)
            ->assertDontSee('token=secret', false);

        $response = $this->actingAs($user)
            ->withSession(['active_role' => 'mahasiswa'])
            ->put('/profile', [
                'phone' => '081234567890',
                'study_program' => 'Farmasi',
                'semester' => 6,
                'class_name' => 'A',
                'address' => 'Karawang',
            ]);

        $response->assertRedirect('/profil-saya');
        $this->assertTrue($user->fresh()->profile_completed);
        $this->assertDatabaseHas('students', ['nim' => '221010001', 'phone' => '081234567890']);
    }

    public function test_user_can_upload_view_and_delete_valid_avatar(): void
    {
        Storage::fake('local');

        $user = User::create([
            'name' => 'Mahasiswa Avatar',
            'email' => 'avatar@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->roles()->sync([Role::where('name', 'mahasiswa')->first()->id]);

        $file = UploadedFile::fake()->image('avatar.jpg', 300, 300)->size(512);

        $this->actingAs($user)
            ->withSession(['active_role' => 'mahasiswa'])
            ->post('/profile/avatar', ['avatar' => $file])
            ->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('local')->assertExists($user->avatar_path);

        $this->actingAs($user)
            ->withSession(['active_role' => 'mahasiswa'])
            ->get('/profile/avatar')
            ->assertOk();

        $oldPath = $user->avatar_path;

        $this->actingAs($user)
            ->withSession(['active_role' => 'mahasiswa'])
            ->delete('/profile/avatar')
            ->assertRedirect();

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_multi_role_user_avatar_renders_before_role_selection(): void
    {
        Storage::fake('local');

        $user = User::create([
            'name' => 'Avatar Role',
            'email' => 'avatar-role@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
            'avatar_path' => 'avatars/role/avatar.jpg',
            'avatar_disk' => 'local',
            'avatar_original_filename' => 'avatar.jpg',
            'avatar_mime' => 'image/jpeg',
            'avatar_size' => 128,
        ]);
        $user->roles()->sync(Role::whereIn('name', ['admin', 'penguji'])->pluck('id'));
        Storage::disk('local')->put('avatars/role/avatar.jpg', UploadedFile::fake()->image('avatar.jpg')->getContent());

        $this->actingAs($user)
            ->get('/pilih-role')
            ->assertOk()
            ->assertSee(route('profile.avatar.show'), false);

        $this->actingAs($user)
            ->get('/profile/avatar')
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_remote_core_avatar_renders_without_local_avatar_route(): void
    {
        $user = User::create([
            'name' => 'Core Avatar',
            'email' => 'core-avatar@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
            'avatar_path' => 'https://core.test/storage/profile-photos/core-avatar.jpg',
            'avatar_disk' => 'remote',
            'avatar_original_filename' => 'core-avatar.jpg',
        ]);
        $user->roles()->sync(Role::whereIn('name', ['admin', 'penguji'])->pluck('id'));

        $this->assertTrue($user->hasAvatar());
        $this->assertFalse($user->hasLocalAvatar());
        $this->assertSame('https://core.test/storage/profile-photos/core-avatar.jpg', $user->avatarUrl());

        $this->actingAs($user)
            ->get('/pilih-role')
            ->assertOk()
            ->assertSee('https://core.test/storage/profile-photos/core-avatar.jpg', false)
            ->assertDontSee(route('profile.avatar.show'), false);

        $this->actingAs($user)
            ->get('/profile/avatar')
            ->assertNotFound();
    }

    public function test_invalid_avatar_upload_is_rejected_and_initials_are_available(): void
    {
        Storage::fake('local');

        $user = User::create([
            'name' => 'Dr. Rina Kartika',
            'email' => 'rina@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->roles()->sync([Role::where('name', 'pembimbing_dalam')->first()->id]);

        $this->assertSame('RK', $user->initials());

        $this->actingAs($user)
            ->withSession(['active_role' => 'pembimbing_dalam'])
            ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('avatar.svg', 12, 'image/svg+xml')])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_multi_role_user_can_open_polished_role_selection_page(): void
    {
        $user = User::create([
            'name' => 'Dosen Multi Role',
            'email' => 'multi-role@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->roles()->sync(Role::whereIn('name', ['koordinator_kp', 'pembimbing_dalam'])->pluck('id'));

        $this->actingAs($user)
            ->get('/pilih-role')
            ->assertOk()
            ->assertSee('Pilih akses untuk melanjutkan')
            ->assertSee('Koordinator KP')
            ->assertSee('Pembimbing Dalam')
            ->assertSee('Kelola periode, kuota, pembimbing, sidang, dan nilai KP.')
            ->assertSee('Pantau mahasiswa bimbingan, laporan, sidang, dan penilaian.');
    }

    public function test_admin_can_open_import_page(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $admin->roles()->sync([Role::where('name', 'admin')->first()->id]);

        $this->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->get('/admin/import-users')
            ->assertOk()
            ->assertSee('Impor Pengguna');
    }

    public function test_multi_role_lecturer_can_manage_field_partner_places_from_profile(): void
    {
        $user = User::create([
            'name' => 'Dosen Mitra',
            'email' => 'dosen-mitra@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
            'profile_completed' => true,
        ]);
        $user->roles()->sync(Role::whereIn('name', ['pembimbing_dalam', 'pembimbing_lapangan'])->pluck('id'));
        $user->lecturer()->create(['nidn_nip' => '0429000001', 'study_program' => 'Farmasi S1']);
        $fieldSupervisor = $user->fieldSupervisor()->create([
            'institution_name' => 'Apotek Dosen Mitra',
            'position' => 'Apoteker Penanggung Jawab',
            'phone' => '081200001111',
            'status' => 'active',
        ]);
        $apotek = KpPlace::create(['name' => 'Apotek Mitra A', 'type' => 'apotek', 'city' => 'Karawang', 'status' => 'aktif']);
        $klinik = KpPlace::create(['name' => 'Klinik Mitra B', 'type' => 'klinik', 'city' => 'Bekasi', 'status' => 'aktif']);
        KpPlace::create(['name' => 'Industri Nonaktif', 'type' => 'industri', 'status' => 'nonaktif']);

        $this->actingAs($user)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/profile/edit')
            ->assertOk()
            ->assertSee('Tempat KP / Mitra Terkait')
            ->assertSee('Apotek Mitra A')
            ->assertSee('Klinik Mitra B')
            ->assertDontSee('Industri Nonaktif');

        $this->actingAs($user)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->put('/profile', [
                'institution_name' => 'Apotek Dosen Mitra',
                'position' => 'Apoteker Penanggung Jawab',
                'phone' => '081200001111',
                'address' => 'Karawang',
                'place_ids' => [$apotek->id, $klinik->id],
            ])
            ->assertRedirect('/profil-saya');

        $this->assertDatabaseHas('kp_place_field_supervisors', [
            'kp_place_id' => $apotek->id,
            'field_supervisor_id' => $fieldSupervisor->id,
            'status' => 'aktif',
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('kp_place_field_supervisors', [
            'kp_place_id' => $klinik->id,
            'field_supervisor_id' => $fieldSupervisor->id,
            'status' => 'aktif',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_role' => 'pembimbing_lapangan'])
            ->get('/profil-saya')
            ->assertOk()
            ->assertSee('Tempat KP Terkait')
            ->assertSee('Apotek Mitra A')
            ->assertSee('Klinik Mitra B');
    }
}
