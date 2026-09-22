<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Show the change password form.
     */
    public function showChangeForm(): View
    {
        return view('auth.change-password');
    }

    /**
     * Update the user password.
     * Wrapped in a DB transaction with AuditService:
     * If audit fails, password and must_change_password flag roll back.
     * Session is regenerated upon success.
     */
    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $wasMustChange = $user->must_change_password;

        // 1. Atomically update password, must_change_password flag, session_version, and record audit in same transaction
        DB::transaction(function () use ($user, $request, $wasMustChange) {
            $user->password = Hash::make($request->validated('password'));
            $user->must_change_password = false;
            $user->session_version = ($user->session_version ?? 1) + 1;
            $user->remember_token = \Illuminate\Support\Str::random(60);
            $user->save();

            // Record audit without logging password/token
            $this->auditService->log(
                action: 'password_change',
                module: 'users',
                summary: "Pengguna {$user->name} memperbarui password akun",
                actor: $user,
                entityType: 'User',
                entityId: $user->id,
                entityLabel: $user->name,
                changes: [
                    'before' => ['must_change_password' => $wasMustChange],
                    'after' => ['must_change_password' => false],
                ]
            );
        });

        // 2. Regenerate session upon successful password change and update signature only after transaction commits
        $request->session()->regenerate();
        $request->session()->put('auth_session_hash_' . $user->id, $user->getSessionSignature());

        // Redirect based on role
        $redirectRoute = match ($user->role?->code) {
            'admin' => 'admin.dashboard',
            'owner' => 'owner.dashboard',
            'resident' => 'resident.portal',
            default => 'login',
        };

        return redirect()->route($redirectRoute)->with('status', 'Password Anda berhasil diperbarui.');
    }
}
