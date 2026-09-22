<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Show the application login form.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectForUser(Auth::user());
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $throttleKey = Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());

        // 1. Check rate limiter (5 attempts per minute per email + IP)
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
                ]);
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        // 2. Attempt authentication
        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => 'Email atau password yang Anda masukkan salah.',
                ]);
        }

        $user = Auth::user();

        // 3. Inactive account verification (use generic error message to prevent probing)
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => 'Email atau password yang Anda masukkan salah.',
                ]);
        }

        // Clear throttle attempts upon successful authentication
        RateLimiter::clear($throttleKey);

        // 4. Regenerate session to prevent session fixation
        $request->session()->regenerate();
        if (empty($user->remember_token)) {
            $user->remember_token = \Illuminate\Support\Str::random(60);
            $user->saveQuietly();
        }
        $request->session()->put('auth_session_hash_' . $user->id, $user->getSessionSignature());

        // 5. Audit login - if audit fails, login MUST be rolled back entirely
        try {
            $this->auditService->log(
                action: 'login',
                module: 'auth',
                summary: "Pengguna {$user->name} berhasil login",
                actor: $user,
                entityType: 'User',
                entityId: $user->id,
                entityLabel: $user->name,
                changes: [
                    'after' => [
                        'email' => $user->email,
                        'ip_address' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 100),
                    ],
                ]
            );
        } catch (\Throwable $e) {
            // Asymmetric rule: When login audit fails, DO NOT leave an active session!
            Auth::guard()->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Technical logging with safe context only (no raw SQL or query bindings)
            Log::error('Technical error during login audit logging', [
                'operation' => 'login_audit',
                'user_id' => $user->id,
                'exception_class' => get_class($e),
                'error_code' => $e->getCode(),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Terjadi kesalahan sistem saat memproses login. Silakan coba lagi.',
                ]);
        }

        // 6. Enforce temporary password change
        if ($user->must_change_password) {
            return redirect()->route('password.change')
                ->with('warning', 'Anda menggunakan password sementara. Silakan ganti password terlebih dahulu.');
        }

        return $this->redirectForUser($user);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        try {
            if ($user) {
                $this->auditService->log(
                    action: 'logout',
                    module: 'auth',
                    summary: "Pengguna {$user->name} keluar dari sistem",
                    actor: $user,
                    entityType: 'User',
                    entityId: $user->id,
                    entityLabel: $user->name
                );
            }
        } catch (\Throwable $e) {
            // Technical logging with safe context only (no credentials or raw SQL bindings).
            // Do not claim audit succeeded.
            try {
                Log::error('Technical error during logout audit logging', [
                    'operation' => 'logout_audit',
                    'user_id' => $user?->id,
                    'exception_class' => get_class($e),
                    'error_code' => $e->getCode(),
                ]);
            } catch (\Throwable) {
                // Ensure logger failure never prevents session cleanup in finally
            }
        } finally {
            // Session termination on this device, session invalidation, and CSRF token regeneration
            // ALWAYS execute in finally, even if audit or technical logging fails.
            Auth::guard()->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('status', 'Anda telah berhasil keluar.');
    }

    /**
     * Redirect user to their respective role dashboard.
     */
    protected function redirectForUser($user): RedirectResponse
    {
        return match ($user->role?->code) {
            'admin' => redirect()->intended(route('admin.dashboard')),
            'owner' => redirect()->intended(route('owner.dashboard')),
            'resident' => redirect()->intended(route('resident.portal')),
            default => redirect()->intended('/'),
        };
    }
}
