<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CoreBridgeAuthService;
use App\Support\RoleDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class LoginController extends Controller
{
    public function create(): Response
    {
        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    public function store(Request $request, CoreBridgeAuthService $authBridge): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()
                ->withErrors(['email' => 'Terlalu banyak percobaan login. Silakan coba beberapa saat lagi.'])
                ->onlyInput('email');
        }

        try {
            $authResult = $authBridge->attempt(
                $credentials['email'],
                $credentials['password'],
                $request->boolean('remember')
            );

            if (! $authResult['ok']) {
                RateLimiter::hit($key);

                return back()
                    ->withErrors(['email' => $this->loginFailureMessage($authResult['reason'] ?? null)])
                    ->onlyInput('email');
            }

            RateLimiter::clear($key);
            $request->session()->regenerate();

            return $this->redirectAuthenticatedUser($request);
        } catch (Throwable $exception) {
            RateLimiter::hit($key);
            Auth::logout();
            $request->session()->forget('active_role');
            $request->session()->regenerateToken();

            Log::error('MY PSPA Core login failed with unhandled exception.', [
                'email' => Str::lower(trim((string) $credentials['email'])),
                'auth_mode' => config('kp_auth.mode'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()
                ->withErrors(['email' => 'Login belum dapat diproses. Silakan coba lagi atau hubungi Admin KPPSPA.'])
                ->onlyInput('email');
        }
    }

    private function redirectAuthenticatedUser(Request $request): RedirectResponse
    {
        $user = $request->user()->load('roles');

        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Akun Anda tidak aktif. Silakan hubungi Admin.']);
        }

        if ($user->roles->isEmpty()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Akun belum memiliki role. Silakan hubungi Admin.']);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->roles->count() === 1) {
            $role = $user->roles->first()->name;
            $request->session()->put('active_role', $role);

            return redirect()->route(RoleDashboard::routeFor($role));
        }

        return redirect()->route('role.select');
    }

    private function loginFailureMessage(?string $reason): string
    {
        return match ($reason) {
            'core_app_access_denied' => 'Akun Core Anda belum memiliki akses aplikasi MY PSPA / KPPSPA.',
            'core_user_inactive' => 'Akun Core Anda tidak aktif. Silakan hubungi Admin.',
            'legacy_bridge_user_missing' => 'Akun Core valid, tetapi belum terhubung ke akun MY PSPA lokal.',
            'legacy_user_inactive' => 'Akun MY PSPA Anda tidak aktif. Silakan hubungi Admin.',
            'core_unavailable' => 'Koneksi Core belum tersedia. Silakan coba lagi atau hubungi Admin.',
            default => 'Email atau password tidak sesuai.',
        };
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->forget('active_role');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda berhasil logout.');
    }
}
