<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CoreFarmasiClient
{
    public function enabled(): bool
    {
        return (bool) config('core_farmasi.enabled', false)
            && filled(config('core_farmasi.base_url'))
            && filled(config('core_farmasi.client_id'))
            && filled(config('core_farmasi.client_secret'));
    }

    public function profileUrl(): ?string
    {
        $url = config('core_farmasi.profile_url')
            ?: (filled(config('core_farmasi.base_url')) ? rtrim((string) config('core_farmasi.base_url'), '/') . '/profile' : null);

        return $this->safeBrowserUrl($url);
    }

    public function profileEditUrl(): ?string
    {
        $url = $this->profileUrl();

        if (! $url) {
            return null;
        }

        if (str_ends_with($url, '/profile/edit')) {
            return $url;
        }

        if (str_ends_with($url, '/profile')) {
            return $url . '/edit';
        }

        return $url;
    }

    public function health(): bool
    {
        if (! filled(config('core_farmasi.base_url'))) {
            return false;
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('core_farmasi.timeout', 10))
                ->connectTimeout((int) config('core_farmasi.connect_timeout', 3))
                ->get($this->path('health'));

            return $response->successful() && ($response->json('status') === 'ok');
        } catch (Throwable $exception) {
            Log::warning('Core Farmasi health check unavailable.', [
                'app_code' => config('core_farmasi.app_code', 'kppspa-farmasi'),
                'error_class' => $exception::class,
            ]);

            return false;
        }
    }

    public function authenticate(string $email, string $password): ?array
    {
        if (! filled(config('core_farmasi.base_url'))) {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('core_farmasi.timeout', 10))
                ->connectTimeout((int) config('core_farmasi.connect_timeout', 3))
                ->post($this->path('auth/login'), [
                    'email' => $email,
                    'password' => $password,
                ]);

            if ($response->status() === 401) {
                return ['authenticated' => false, 'reason' => 'invalid_credentials'];
            }

            if (! $response->successful()) {
                return $this->handleFailedResponse($response->status());
            }

            $payload = $response->json();

            if (! is_array($payload) || blank($payload['token'] ?? null) || ! is_array($payload['user'] ?? null)) {
                return null;
            }

            return [
                'authenticated' => true,
                'token' => $payload['token'],
                'user' => $this->normalizeUserPayload($payload['user']),
            ];
        } catch (RequestException $exception) {
            return $this->handleThrowable($exception);
        } catch (Throwable $exception) {
            return $this->handleThrowable($exception);
        }
    }

    public function validateToken(string $token): ?array
    {
        if (! filled(config('core_farmasi.base_url')) || blank($token)) {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('core_farmasi.timeout', 10))
                ->connectTimeout((int) config('core_farmasi.connect_timeout', 3))
                ->withToken($token)
                ->get($this->path('auth/validate-token'));

            if (! $response->successful()) {
                return $this->handleFailedResponse($response->status());
            }

            return $response->json();
        } catch (RequestException $exception) {
            return $this->handleThrowable($exception);
        } catch (Throwable $exception) {
            return $this->handleThrowable($exception);
        }
    }

    public function getUser(int|string $id): ?array
    {
        return $this->data($this->get("internal/directory/users/{$id}"));
    }

    public function searchUsers(array $params = []): array
    {
        return $this->collection($this->get('internal/directory/users', $params));
    }

    public function getStudent(int|string $id): ?array
    {
        return $this->data($this->get("internal/directory/students/{$id}"));
    }

    public function searchStudents(array $params = []): array
    {
        return $this->collection($this->get('internal/directory/students', $params));
    }

    public function getLecturer(int|string $id): ?array
    {
        return $this->data($this->get("internal/directory/lecturers/{$id}"));
    }

    public function searchLecturers(array $params = []): array
    {
        return $this->collection($this->get('internal/directory/lecturers', $params));
    }

    public function getStudyProgram(int|string $id): ?array
    {
        return $this->data($this->get("internal/directory/study-programs/{$id}"));
    }

    public function listStudyPrograms(array $params = []): array
    {
        return $this->collection($this->get('internal/directory/study-programs', $params));
    }

    public function getCurrentLeadership(array $params): ?array
    {
        $payload = $this->get('internal/leadership/current', $params);

        if (! is_array($payload) || ($payload['found'] ?? false) !== true) {
            return null;
        }

        return is_array($payload['leadership'] ?? null) ? $payload['leadership'] : null;
    }

    public function checkUserAppAccess(int|string $userId): array
    {
        $appCode = (string) config('core_farmasi.app_code', 'kppspa-farmasi');

        return $this->get("internal/apps/{$appCode}/users/{$userId}/access") ?? [
            'has_access' => false,
            'app_code' => $appCode,
            'user_id' => (int) $userId,
            'roles' => [],
        ];
    }

    public function listAppAccessUsers(array $params = []): array
    {
        $appCode = (string) config('core_farmasi.app_code', 'kppspa-farmasi');

        return $this->collection($this->get("internal/apps/{$appCode}/users", $params));
    }

    public function getAppAccessUser(int|string $userId): ?array
    {
        $users = $this->listAppAccessUsers([
            'user_id' => $userId,
            'limit' => 1,
        ]);

        return $users['data'][0] ?? null;
    }

    protected function get(string $path, array $params = []): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = $this->request()->get($this->path($path), $params);

            if ($response->status() === 404) {
                return null;
            }

            if (! $response->successful()) {
                return $this->handleFailedResponse($response->status());
            }

            return $response->json();
        } catch (RequestException $exception) {
            return $this->handleThrowable($exception);
        } catch (Throwable $exception) {
            return $this->handleThrowable($exception);
        }
    }

    protected function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout((int) config('core_farmasi.timeout', 5))
            ->connectTimeout((int) config('core_farmasi.connect_timeout', 3))
            ->withHeaders([
                'X-Core-App-Code' => (string) config('core_farmasi.app_code', 'kppspa-farmasi'),
                'X-Core-Client-Id' => (string) config('core_farmasi.client_id'),
                'X-Core-Client-Secret' => (string) config('core_farmasi.client_secret'),
            ]);

        if (! (bool) config('core_farmasi.verify_ssl', true)) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }

    protected function path(string $path): string
    {
        return rtrim((string) config('core_farmasi.base_url'), '/') . '/api/v1/' . ltrim($path, '/');
    }

    protected function safeBrowserUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $parts = parse_url((string) $url);

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || blank($parts['host'] ?? null)) {
            return null;
        }

        $path = $parts['path'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port . '/' . ltrim($path, '/');
    }

    public function publicUrl(?string $pathOrUrl): ?string
    {
        if (blank($pathOrUrl)) {
            return null;
        }

        $value = trim(str_replace('\\', '/', (string) $pathOrUrl));

        if ($value === '' || str_contains($value, '..')) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $this->safeBrowserUrl($value);
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $value) === 1) {
            return null;
        }

        $baseUrl = $this->coreBaseUrl();

        if (! $baseUrl) {
            return null;
        }

        if (str_starts_with($value, '/storage/')) {
            return $this->safeBrowserUrl($baseUrl.$value);
        }

        if (str_starts_with($value, 'storage/')) {
            return $this->safeBrowserUrl($baseUrl.'/'.ltrim($value, '/'));
        }

        return $this->safeBrowserUrl($baseUrl.'/storage/'.ltrim($value, '/'));
    }

    protected function coreBaseUrl(): ?string
    {
        if (filled(config('core_farmasi.base_url'))) {
            return rtrim((string) config('core_farmasi.base_url'), '/');
        }

        if (blank(config('core_farmasi.profile_url'))) {
            return null;
        }

        $parts = parse_url((string) config('core_farmasi.profile_url'));

        if (! is_array($parts) || blank($parts['scheme'] ?? null) || blank($parts['host'] ?? null)) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port;
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    protected function normalizeUserPayload(array $user): array
    {
        $photo = $user['profile_photo_url']
            ?? $user['avatar_url']
            ?? $user['photo_url']
            ?? $user['profile_photo_path']
            ?? null;

        $photoUrl = is_string($photo) ? $this->publicUrl($photo) : null;

        if ($photoUrl) {
            $user['profile_photo_url'] = $photoUrl;
            $user['avatar_url'] ??= $photoUrl;
        }

        return $user;
    }

    protected function collection(?array $payload): array
    {
        if (! is_array($payload)) {
            return ['data' => [], 'meta' => null];
        }

        return [
            'data' => is_array($payload['data'] ?? null) ? $payload['data'] : [],
            'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : null,
        ];
    }

    protected function data(?array $payload): ?array
    {
        return is_array($payload['data'] ?? null) ? $payload['data'] : null;
    }

    protected function handleFailedResponse(int $status): ?array
    {
        if (! (bool) config('core_farmasi.fail_silently', true)) {
            throw new \RuntimeException("Core Farmasi request failed with status {$status}.");
        }

        Log::warning('Core Farmasi read-only request failed.', [
            'status' => $status,
            'app_code' => config('core_farmasi.app_code', 'kppspa-farmasi'),
        ]);

        return null;
    }

    protected function handleThrowable(Throwable $exception): ?array
    {
        if (! (bool) config('core_farmasi.fail_silently', true)) {
            throw new \RuntimeException('Core Farmasi request failed.', previous: $exception);
        }

        Log::warning('Core Farmasi read-only request unavailable.', [
            'app_code' => config('core_farmasi.app_code', 'kppspa-farmasi'),
            'error_class' => $exception::class,
        ]);

        return null;
    }
}
