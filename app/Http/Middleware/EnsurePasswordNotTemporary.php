<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordNotTemporary
{
    /**
     * Handle an incoming request.
     * Forces users with a temporary password (must_change_password = true)
     * to change their password before accessing other features.
     * Excludes password change routes and logout to prevent infinite redirect loops.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            // Exclude password change routes and logout route
            if (! $request->routeIs('password.change', 'password.update', 'logout')) {
                return redirect()->route('password.change')
                    ->with('warning', 'Anda menggunakan password sementara. Silakan ganti password terlebih dahulu.');
            }
        }

        return $next($request);
    }
}
