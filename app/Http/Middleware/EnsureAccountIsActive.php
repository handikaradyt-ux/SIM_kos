<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     * Ensures an authenticated user account is still active.
     * If the account has been deactivated, immediately terminates the session
     * and redirects to login with a notice.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if (! $user->is_active) {
                Auth::guard()->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.',
                ]);
            }

            // Verify session signature against current user's credentials and session version
            $sessionKey = 'auth_session_hash_' . $user->id;
            $sessionHash = $request->session()->get($sessionKey);
            $currentHash = $user->getSessionSignature();

            // Legitimate remember-me restoration: If authenticated via remember-me cookie in this request, initialize signature
            if (! $sessionHash && Auth::viaRemember()) {
                $request->session()->put($sessionKey, $currentHash);
                $sessionHash = $currentHash;
            }

            // Reject old sessions without signature or with outdated/mismatched signature
            // Terminate only this device/session without rotating global tokens
            if (! $sessionHash || ! hash_equals($sessionHash, $currentHash)) {
                Auth::guard()->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Sesi Anda telah berakhir atau kredensial telah diperbarui. Silakan login kembali.',
                ]);
            }
        }

        return $next($request);
    }
}
