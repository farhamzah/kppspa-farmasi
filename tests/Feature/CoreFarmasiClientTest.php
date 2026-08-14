<?php

namespace Tests\Feature;

use App\Services\CoreFarmasiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoreFarmasiClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_client_returns_empty_results_and_does_not_call_http(): void
    {
        config(['core_farmasi.enabled' => false]);
        Http::fake();

        $client = app(CoreFarmasiClient::class);

        $this->assertFalse($client->enabled());
        $this->assertNull($client->getUser(10));
        $this->assertSame(['data' => [], 'meta' => null], $client->searchStudents(['q' => 'A']));
        $this->assertNull($client->getCurrentLeadership(['position_type' => 'dekan']));
        $this->assertSame([
            'has_access' => false,
            'app_code' => 'kp-farmasi',
            'user_id' => 10,
            'roles' => [],
        ], $client->checkUserAppAccess(10));

        Http::assertNothingSent();
    }

    public function test_enabled_client_sends_required_headers_without_secret_in_url(): void
    {
        $this->enableClient();
        Http::fake([
            'https://core.test/api/v1/internal/directory/users/42' => Http::response([
                'data' => ['id' => 42, 'name' => 'Core User'],
            ]),
        ]);

        $result = app(CoreFarmasiClient::class)->getUser(42);

        $this->assertSame('Core User', $result['name']);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://core.test/api/v1/internal/directory/users/42'
                && $request->hasHeader('X-Core-App-Code', 'kp-farmasi')
                && $request->hasHeader('X-Core-Client-Id', 'client-id-test')
                && $request->hasHeader('X-Core-Client-Secret', 'secret-test')
                && ! str_contains($request->url(), 'secret-test')
                && ! str_contains($request->url(), 'client-id-test');
        });
    }

    public function test_profile_urls_are_safe_browser_links_without_query_token(): void
    {
        config([
            'core_farmasi.base_url' => 'https://core.test',
            'core_farmasi.profile_url' => null,
        ]);

        $client = app(CoreFarmasiClient::class);

        $this->assertSame('https://core.test/profile', $client->profileUrl());
        $this->assertSame('https://core.test/profile/edit', $client->profileEditUrl());

        config(['core_farmasi.profile_url' => 'https://core.example.test/profile?token=secret-test&user=5']);

        $this->assertSame('https://core.example.test/profile', $client->profileUrl());
        $this->assertSame('https://core.example.test/profile/edit', $client->profileEditUrl());

        config(['core_farmasi.profile_url' => 'javascript:alert(1)']);

        $this->assertNull($client->profileUrl());
        $this->assertNull($client->profileEditUrl());
    }

    public function test_public_core_storage_photo_paths_are_safe_browser_links(): void
    {
        config(['core_farmasi.base_url' => 'https://core.test']);

        $client = app(CoreFarmasiClient::class);

        $this->assertSame('https://core.test/storage/profile-photos/farah.jpg', $client->publicUrl('profile-photos/farah.jpg'));
        $this->assertSame('https://core.test/storage/profile-photos/farah.jpg', $client->publicUrl('storage/profile-photos/farah.jpg'));
        $this->assertSame('https://core.test/storage/profile-photos/farah.jpg', $client->publicUrl('/storage/profile-photos/farah.jpg'));
        $this->assertSame('https://cdn.test/farah.jpg', $client->publicUrl('https://cdn.test/farah.jpg?secret=1'));
        $this->assertNull($client->publicUrl('../.env'));
        $this->assertNull($client->publicUrl('javascript:alert(1)'));
    }

    public function test_get_user_and_search_students_map_to_core_endpoints(): void
    {
        $this->enableClient();
        Http::fake([
            'https://core.test/api/v1/internal/directory/users/7' => Http::response([
                'data' => ['id' => 7, 'username' => 'admin-kp'],
            ]),
            'https://core.test/api/v1/internal/directory/students*' => Http::response([
                'data' => [['id' => 3, 'nim' => '230001']],
                'meta' => ['limit' => 25],
            ]),
        ]);

        $this->assertSame('admin-kp', app(CoreFarmasiClient::class)->getUser(7)['username']);
        $students = app(CoreFarmasiClient::class)->searchStudents(['q' => '230', 'limit' => 25]);

        $this->assertSame('230001', $students['data'][0]['nim']);
        $this->assertSame(25, $students['meta']['limit']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://core.test/api/v1/internal/directory/users/7');
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://core.test/api/v1/internal/directory/students'));
    }

    public function test_lecturer_404_unauthorized_forbidden_rate_limited_and_server_errors_are_safe(): void
    {
        $this->enableClient();
        Http::fake([
            'https://core.test/api/v1/internal/directory/lecturers/404' => Http::response(['message' => 'Not Found'], 404),
            'https://core.test/api/v1/internal/directory/lecturers/401' => Http::response(['message' => 'Unauthorized'], 401),
            'https://core.test/api/v1/internal/directory/lecturers/403' => Http::response(['message' => 'Forbidden'], 403),
            'https://core.test/api/v1/internal/directory/lecturers/429' => Http::response(['message' => 'Too Many Requests'], 429),
            'https://core.test/api/v1/internal/directory/lecturers/500' => Http::response(['message' => 'Server Error'], 500),
        ]);

        $client = app(CoreFarmasiClient::class);

        $this->assertNull($client->getLecturer(404));
        $this->assertNull($client->getLecturer(401));
        $this->assertNull($client->getLecturer(403));
        $this->assertNull($client->getLecturer(429));
        $this->assertNull($client->getLecturer(500));
    }

    public function test_study_program_leadership_and_app_access_methods_map_to_expected_endpoints(): void
    {
        $this->enableClient();
        Http::fake([
            'https://core.test/api/v1/internal/directory/study-programs/1' => Http::response([
                'data' => ['id' => 1, 'code' => 'S1-FAR'],
            ]),
            'https://core.test/api/v1/internal/directory/study-programs*' => Http::response([
                'data' => [['id' => 1, 'code' => 'S1-FAR']],
                'meta' => ['total' => 1],
            ]),
            'https://core.test/api/v1/internal/leadership/current*' => Http::response([
                'found' => true,
                'leadership' => ['position_type' => 'dekan', 'person_name' => 'Dekan'],
            ]),
            'https://core.test/api/v1/internal/apps/kp-farmasi/users/9/access' => Http::response([
                'has_access' => true,
                'app_code' => 'kp-farmasi',
                'user_id' => 9,
                'roles' => [['slug' => 'admin-kp']],
            ]),
        ]);

        $client = app(CoreFarmasiClient::class);

        $this->assertSame('S1-FAR', $client->listStudyPrograms(['q' => 'Farmasi'])['data'][0]['code']);
        $this->assertSame('S1-FAR', $client->getStudyProgram(1)['code']);
        $this->assertSame('Dekan', $client->getCurrentLeadership(['position_type' => 'dekan', 'unit_type' => 'faculty'])['person_name']);
        $this->assertTrue($client->checkUserAppAccess(9)['has_access']);

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://core.test/api/v1/internal/leadership/current'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://core.test/api/v1/internal/apps/kp-farmasi/users/9/access');
    }

    public function test_core_unavailable_is_safe_when_fail_silently_enabled(): void
    {
        $this->enableClient();
        Http::fake([
            '*' => fn () => throw new \RuntimeException('connection refused secret-test'),
        ]);

        $this->assertNull(app(CoreFarmasiClient::class)->getUser(1));
    }

    public function test_authenticate_normalizes_core_profile_photo_payload(): void
    {
        $this->enableClient();
        Http::fake([
            'https://core.test/api/v1/auth/login' => Http::response([
                'token' => 'token-test',
                'user' => [
                    'id' => 2,
                    'name' => 'Farhamzah',
                    'email' => 'farhamzah@ubpkarawang.ac.id',
                    'active' => true,
                    'profile_photo_path' => 'profile-photos/farhamzah.jpg',
                ],
            ]),
        ]);

        $result = app(CoreFarmasiClient::class)->authenticate('farhamzah@ubpkarawang.ac.id', 'secret');

        $this->assertTrue($result['authenticated']);
        $this->assertSame('https://core.test/storage/profile-photos/farhamzah.jpg', $result['user']['profile_photo_url']);
        $this->assertSame('https://core.test/storage/profile-photos/farhamzah.jpg', $result['user']['avatar_url']);
    }

    public function test_smoke_command_disabled_mode_does_not_call_http(): void
    {
        config(['core_farmasi.enabled' => false]);
        Http::fake();

        $this->artisan('kp:core-smoke-test')
            ->expectsOutputToContain('Core HTTP adapter is disabled')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_smoke_command_success_uses_read_only_endpoints(): void
    {
        $this->enableClient();
        Http::fake([
            'https://core.test/api/v1/internal/directory/study-programs*' => Http::response([
                'data' => [['id' => 1, 'code' => 'S1-FAR']],
            ]),
            'https://core.test/api/v1/internal/directory/students*' => Http::response([
                'data' => [['id' => 2, 'nim' => '230001']],
            ]),
            'https://core.test/api/v1/internal/directory/lecturers*' => Http::response([
                'data' => [['id' => 3, 'nidn' => '0012345601']],
            ]),
            'https://core.test/api/v1/internal/leadership/current*' => Http::response([
                'found' => true,
                'leadership' => ['position_type' => 'dekan', 'person_name' => 'Dekan'],
            ]),
            'https://core.test/api/v1/internal/apps/kp-farmasi/users/9/access' => Http::response([
                'has_access' => true,
                'app_code' => 'kp-farmasi',
                'user_id' => 9,
                'roles' => [['slug' => 'admin-kp']],
            ]),
        ]);

        $this->artisan('kp:core-smoke-test', ['--user-id' => 9])
            ->expectsOutputToContain('Core HTTP smoke test completed.')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://core.test/api/v1/internal/apps/kp-farmasi/users/9/access');
        Http::assertSentCount(5);
    }

    public function test_smoke_command_failure_is_safe_and_does_not_print_secret(): void
    {
        $this->enableClient();
        config(['core_farmasi.fail_silently' => false]);
        Http::fake([
            '*' => Http::response(['message' => 'Server Error'], 500),
        ]);

        $exitCode = Artisan::call('kp:core-smoke-test');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Core HTTP smoke test failed.', $output);
        $this->assertStringNotContainsString('secret-test', $output);
    }

    private function enableClient(): void
    {
        config([
            'core_farmasi.enabled' => true,
            'core_farmasi.base_url' => 'https://core.test',
            'core_farmasi.app_code' => 'kp-farmasi',
            'core_farmasi.client_id' => 'client-id-test',
            'core_farmasi.client_secret' => 'secret-test',
            'core_farmasi.timeout' => 5,
            'core_farmasi.connect_timeout' => 3,
            'core_farmasi.verify_ssl' => true,
            'core_farmasi.read_mode' => 'legacy',
            'core_farmasi.fail_silently' => true,
        ]);
    }
}
