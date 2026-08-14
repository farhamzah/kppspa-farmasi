<?php

namespace Tests\Feature;

use App\Models\Lecturer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoreHttpAppUserSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        config()->set('core_farmasi.enabled', true);
        config()->set('core_farmasi.base_url', 'https://core.test');
        config()->set('core_farmasi.app_code', 'kppspa-farmasi');
        config()->set('core_farmasi.client_id', 'client-id');
        config()->set('core_farmasi.client_secret', 'client-secret');
    }

    public function test_bulk_core_http_app_user_sync_projects_lecturer_before_first_login(): void
    {
        Http::fake([
            'https://core.test/api/v1/internal/apps/kppspa-farmasi/users*' => Http::response([
                'data' => [[
                    'user_id' => 77,
                    'app_code' => 'kppspa-farmasi',
                    'roles' => [
                        ['slug' => 'pembimbing-dalam', 'name' => 'Pembimbing Dalam'],
                        ['slug' => 'penguji', 'name' => 'Penguji'],
                    ],
                    'user' => [
                        'id' => 77,
                        'name' => 'Dosen Belum Login',
                        'email' => 'dosen-belum-login@example.test',
                        'active' => true,
                    ],
                    'profiles' => [
                        'student' => null,
                        'lecturer' => [
                            'id' => 707,
                            'user_id' => 77,
                            'lecturer_number' => 'DOS707',
                            'nidn' => '0011223344',
                            'nip' => '198001012010011001',
                            'name' => 'Dosen Belum Login',
                            'email' => 'dosen-belum-login@example.test',
                            'phone' => '08123456789',
                            'active' => true,
                            'department' => ['name' => 'Farmasi Klinik'],
                            'study_program' => ['name' => 'Profesi Apoteker'],
                        ],
                        'external_person' => null,
                    ],
                ]],
                'meta' => ['page' => 1, 'limit' => 100, 'total' => 1, 'has_more' => false],
            ]),
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'dosen-belum-login@example.test']);

        $this->artisan('kp:sync-core-http-app-users --execute --confirm-execute')
            ->expectsOutputToContain('dosen-belum-login@example.test: synced')
            ->assertSuccessful();

        $user = User::where('email', 'dosen-belum-login@example.test')->firstOrFail();

        $this->assertSame(77, (int) $user->core_user_id);
        $this->assertEqualsCanonicalizing(
            ['pembimbing_dalam', 'penguji'],
            $user->roles()->pluck('name')->all()
        );
        $this->assertDatabaseHas('lecturers', [
            'user_id' => $user->id,
            'core_lecturer_id' => 707,
            'nidn_nip' => '0011223344',
            'employee_number' => '198001012010011001',
            'study_program' => 'Profesi Apoteker',
            'department' => 'Farmasi Klinik',
        ]);
        $this->assertSame(1, Lecturer::count());
    }

    public function test_bulk_sync_warns_when_required_core_profile_is_missing(): void
    {
        Http::fake([
            'https://core.test/api/v1/internal/apps/kppspa-farmasi/users*' => Http::response([
                'data' => [[
                    'user_id' => 78,
                    'app_code' => 'kppspa-farmasi',
                    'roles' => [['slug' => 'pembimbing-dalam', 'name' => 'Pembimbing Dalam']],
                    'user' => [
                        'id' => 78,
                        'name' => 'Dosen Tanpa Profil',
                        'email' => 'dosen-tanpa-profil@example.test',
                        'active' => true,
                    ],
                    'profiles' => [
                        'student' => null,
                        'lecturer' => null,
                        'external_person' => null,
                    ],
                ]],
                'meta' => ['page' => 1, 'limit' => 100, 'total' => 1, 'has_more' => false],
            ]),
        ]);

        $this->artisan('kp:sync-core-http-app-users --execute --confirm-execute')
            ->expectsOutputToContain('synced with warning')
            ->assertSuccessful();

        $user = User::where('email', 'dosen-tanpa-profil@example.test')->firstOrFail();

        $this->assertFalse((bool) $user->profile_completed);
        $this->assertStringContainsString('Profil dosen belum tersedia di Core', (string) $user->core_sync_note);
        $this->assertEqualsCanonicalizing(
            ['pembimbing_dalam'],
            $user->roles()->pluck('name')->all()
        );
        $this->assertDatabaseMissing('lecturers', ['user_id' => $user->id]);
    }
}
