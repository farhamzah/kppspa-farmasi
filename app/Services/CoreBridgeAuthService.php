<?php

namespace App\Services;

use App\Models\Core\CoreUser;
use App\Models\Role;
use App\Models\User;
use App\Support\CoreRoleTranslator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CoreBridgeAuthService
{
    private ?string $failureReason = null;

    public function __construct(
        private readonly KpCoreBridgeProvisioningService $provisioningService,
        private readonly CoreFarmasiClient $coreClient,
    ) {
    }

    public function attempt(string $email, string $password, bool $remember = false): array
    {
        $this->failureReason = null;
        $mode = config('kp_auth.mode', 'legacy');

        if ($mode === 'legacy') {
            return $this->fallbackLegacyAttempt($email, $password, $remember);
        }

        $coreResult = $mode === 'core_http'
            ? $this->attemptCoreHttp($email, $password, $remember)
            : $this->attemptCoreBridge($email, $password, $remember);
        if ($coreResult['ok']) {
            return $coreResult;
        }

        if ($mode === 'core_bridge_with_legacy_fallback' && in_array($coreResult['reason'], ['core_unavailable', 'invalid_credentials'], true)) {
            Log::warning('KP auth legacy fallback attempted after Core bridge failure.', [
                'email' => $this->normalize($email),
                'reason' => $coreResult['reason'],
            ]);

            return $this->fallbackLegacyAttempt($email, $password, $remember, 'legacy_fallback');
        }

        return $coreResult;
    }

    public function validateCoreUser(string $email, string $password): ?CoreUser
    {
        try {
            $coreUser = CoreUser::query()
                ->whereRaw('LOWER(TRIM(email)) = ?', [$this->normalize($email)])
                ->first();
        } catch (Throwable $exception) {
            $this->failureReason = 'core_unavailable';
            Log::warning('KP auth Core lookup failed.', [
                'email' => $this->normalize($email),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $coreUser) {
            $this->failureReason = 'core_user_missing';
            Log::warning('KP auth Core user missing.', ['email' => $this->normalize($email)]);

            return null;
        }

        if (! $coreUser->active) {
            $this->failureReason = 'core_user_inactive';
            Log::warning('KP auth Core user inactive.', ['email' => $this->normalize($email)]);

            return null;
        }

        if ($coreUser->must_change_password) {
            $this->failureReason = 'core_password_must_change';
            Log::warning('KP auth Core user must change password before KP login.', ['email' => $this->normalize($email)]);

            return null;
        }

        if (! Hash::check($password, $coreUser->password)) {
            $this->failureReason = 'invalid_credentials';

            return null;
        }

        return $coreUser;
    }

    public function validateKpAppAccess(int $coreUserId): bool
    {
        $allowedRoles = config('kp_auth.core_bridge_allowed_roles', []);
        $coreUser = CoreUser::query()
            ->with(['roles', 'appAccesses'])
            ->find($coreUserId);
        $appCode = (string) config('core_farmasi.app_code', 'kppspa-farmasi');
        $accesses = $coreUser?->appAccesses
            ->where('app_code', $appCode)
            ->where('is_active', true) ?? collect();
        $coreRoles = $coreUser?->roles->pluck('name')->all() ?? [];
        $roleCandidates = $accesses
            ->pluck('role_slug')
            ->merge($coreRoles)
            ->filter()
            ->values()
            ->all();

        if ($accesses->isEmpty()) {
            $this->failureReason = 'core_app_access_denied';
            Log::warning('KP auth Core app access denied.', ['core_user_id' => $coreUserId]);

            return false;
        }

        if (in_array('admin-core', $roleCandidates, true) && collect($roleCandidates)->intersect($allowedRoles)->isEmpty()) {
            $this->failureReason = 'core_app_access_denied';
            Log::warning('KP auth denied admin-core-only Core app access.', ['core_user_id' => $coreUserId]);

            return false;
        }

        if (CoreRoleTranslator::coreRolesToKp($roleCandidates) === []) {
            $this->failureReason = 'core_app_access_denied';
            Log::warning('KP auth Core app access denied.', ['core_user_id' => $coreUserId]);

            return false;
        }

        return true;
    }

    public function resolveLegacyKpUser(int $coreUserId): ?User
    {
        $legacyUser = User::query()->where('core_user_id', $coreUserId)->first();

        if (! $legacyUser) {
            $this->failureReason = 'legacy_bridge_user_missing';
            Log::warning('KP auth legacy bridge user missing.', ['core_user_id' => $coreUserId]);

            return null;
        }

        if (method_exists($legacyUser, 'isActive') && ! $legacyUser->isActive()) {
            $this->failureReason = 'legacy_user_inactive';
            Log::warning('KP auth legacy bridge user inactive.', ['core_user_id' => $coreUserId]);

            return null;
        }

        return $legacyUser;
    }

    public function fallbackLegacyAttempt(string $email, string $password, bool $remember = false, string $via = 'legacy'): array
    {
        $ok = Auth::attempt(['email' => $email, 'password' => $password], $remember);
        if (! $ok) {
            $this->failureReason = 'invalid_credentials';

            return $this->result(false, null, $this->failureReason, $via);
        }

        Log::info('KP auth legacy login success.', [
            'email' => $this->normalize($email),
            'via' => $via,
        ]);

        return $this->result(true, Auth::user(), null, $via);
    }

    public function explainFailureReason(): ?string
    {
        return $this->failureReason;
    }

    private function attemptCoreBridge(string $email, string $password, bool $remember): array
    {
        $coreUser = $this->validateCoreUser($email, $password);
        if (! $coreUser) {
            return $this->result(false, null, $this->failureReason, 'core_bridge');
        }

        if (! $this->validateKpAppAccess($coreUser->id)) {
            return $this->result(false, null, $this->failureReason, 'core_bridge');
        }

        $legacyUser = $this->resolveLegacyKpUser($coreUser->id);
        if (! $legacyUser) {
            $legacyUser = $this->autoProvisionLegacyKpUser($coreUser);
        }

        if (! $legacyUser) {
            return $this->result(false, null, $this->failureReason, 'core_bridge_auto_provision');
        }

        $this->syncLegacyRolesFromCore($legacyUser, $coreUser);

        Auth::login($legacyUser, $remember);
        Log::info('KP auth Core bridge login success.', [
            'email' => $this->normalize($email),
            'core_user_id' => $coreUser->id,
            'legacy_user_id' => $legacyUser->id,
        ]);

        return $this->result(true, $legacyUser, null, 'core_bridge');
    }

    private function attemptCoreHttp(string $email, string $password, bool $remember): array
    {
        $auth = $this->coreClient->authenticate($email, $password);

        if (! is_array($auth)) {
            $this->failureReason = 'core_unavailable';

            return $this->result(false, null, $this->failureReason, 'core_http');
        }

        if (($auth['authenticated'] ?? false) !== true || ! is_array($auth['user'] ?? null)) {
            $this->failureReason = $auth['reason'] ?? 'invalid_credentials';

            return $this->result(false, null, $this->failureReason, 'core_http');
        }

        $coreUser = $auth['user'];
        if (($coreUser['active'] ?? true) !== true) {
            $this->failureReason = 'core_user_inactive';

            return $this->result(false, null, $this->failureReason, 'core_http');
        }

        $access = $this->coreClient->checkUserAppAccess($coreUser['id']);
        if (($access['has_access'] ?? false) !== true) {
            $this->failureReason = 'core_app_access_denied';

            return $this->result(false, null, $this->failureReason, 'core_http');
        }

        $kpRoles = CoreRoleTranslator::coreRolesToKp($access['roles'] ?? $coreUser['roles'] ?? []);
        if ($kpRoles === []) {
            $this->failureReason = 'core_app_access_denied';

            return $this->result(false, null, $this->failureReason, 'core_http');
        }

        $legacyUser = $this->resolveOrCreateLocalProjection($coreUser, $kpRoles);
        if (! $legacyUser) {
            return $this->result(false, null, $this->failureReason, 'core_http');
        }

        Auth::login($legacyUser, $remember);
        Log::info('MY PSPA Core HTTP login success.', [
            'email' => $this->normalize($email),
            'core_user_id' => $coreUser['id'] ?? null,
            'legacy_user_id' => $legacyUser->id,
        ]);

        return $this->result(true, $legacyUser, null, 'core_http');
    }

    private function result(bool $ok, ?User $legacyUser, ?string $reason, string $via): array
    {
        return [
            'ok' => $ok,
            'legacy_user' => $legacyUser,
            'reason' => $reason,
            'via' => $via,
        ];
    }

    private function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    private function syncLegacyRolesFromCore(User $legacyUser, CoreUser $coreUser): void
    {
        $coreRoles = $coreUser->roles->pluck('name');
        $appAccessRoles = $coreUser->appAccesses
            ->where('app_code', (string) config('core_farmasi.app_code', 'kppspa-farmasi'))
            ->where('is_active', true)
            ->pluck('role_slug');
        $kpRoles = CoreRoleTranslator::coreRolesToKp($appAccessRoles->merge($coreRoles));

        if ($kpRoles === []) {
            return;
        }

        $roleIds = Role::query()
            ->whereIn('name', $kpRoles)
            ->pluck('id')
            ->all();

        $legacyUser->roles()->sync($roleIds);
        $legacyUser->load('roles');
    }

    private function autoProvisionLegacyKpUser(CoreUser $coreUser): ?User
    {
        $report = $this->provisioningService->execute((string) $coreUser->email);

        if ($report['blockers'] !== [] || ! $report['legacy_user_id']) {
            $this->failureReason = 'legacy_bridge_user_missing';
            Log::warning('KP auth Core bridge auto-provision failed.', [
                'core_user_id' => $coreUser->id,
                'email' => $this->normalize((string) $coreUser->email),
                'blockers' => $report['blockers'],
            ]);

            return null;
        }

        Log::info('KP auth Core bridge auto-provisioned legacy user.', [
            'core_user_id' => $coreUser->id,
            'legacy_user_id' => $report['legacy_user_id'],
        ]);

        return User::query()->find($report['legacy_user_id']);
    }

    private function resolveOrCreateLocalProjection(array $coreUser, array $kpRoles): ?User
    {
        $coreUserId = (int) ($coreUser['id'] ?? 0);
        $email = (string) ($coreUser['email'] ?? '');
        $name = (string) ($coreUser['name'] ?? $email);

        if ($coreUserId <= 0 || blank($email)) {
            $this->failureReason = 'core_contract_incomplete';

            return null;
        }

        $legacyUser = User::query()
            ->where('core_user_id', $coreUserId)
            ->orWhereRaw('LOWER(TRIM(email)) = ?', [$this->normalize($email)])
            ->first();

        if ($legacyUser && filled($legacyUser->core_user_id) && (int) $legacyUser->core_user_id !== $coreUserId) {
            $this->failureReason = 'legacy_bridge_email_conflict';
            Log::warning('KP auth Core HTTP projection email conflict.', [
                'core_user_id' => $coreUserId,
                'legacy_user_id' => $legacyUser->id,
                'legacy_core_user_id' => $legacyUser->core_user_id,
                'email' => $this->normalize($email),
            ]);

            return null;
        }

        $legacyUser ??= new User();
        $legacyUser->forceFill([
            'core_user_id' => $coreUserId,
            'name' => $name,
            'email' => $email,
            'status' => 'active',
            'must_change_password' => false,
            'profile_completed' => $legacyUser->profile_completed ?? false,
            'core_synced_at' => now(),
            'core_sync_status' => 'synced',
            'core_sync_note' => 'Synced from Core Farmasi HTTP auth.',
        ]);

        if (! $legacyUser->exists) {
            $legacyUser->setAttribute('password', Hash::make(Str::random(48)));
        }

        $legacyUser->save();

        $roleIds = Role::query()
            ->whereIn('name', $kpRoles)
            ->pluck('id')
            ->all();

        if ($roleIds === []) {
            $this->failureReason = 'core_app_access_denied';

            return null;
        }

        $legacyUser->roles()->sync($roleIds);

        return $legacyUser->load('roles');
    }
}
