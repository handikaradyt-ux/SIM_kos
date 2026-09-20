<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\RoomController;
use App\Models\Resident;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// Authenticated Routes (Requires active account)
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Password change routes (Excluded from EnsurePasswordNotTemporary to allow changing password)
    Route::get('/password/change', [PasswordController::class, 'showChangeForm'])->name('password.change');
    Route::post('/password/change', [PasswordController::class, 'update'])->name('password.update');

    // Protected application routes (Requires password not temporary)
    Route::middleware('password.not_temp')->group(function () {
        Route::get('/dashboard', function () {
            $user = Auth::user();
            return match ($user->role?->code) {
                'admin' => redirect()->route('admin.dashboard'),
                'owner' => redirect()->route('owner.dashboard'),
                'resident' => redirect()->route('resident.portal'),
                default => redirect()->route('login'),
            };
        })->name('dashboard');

        // Admin Dashboard
        Route::get('/admin/dashboard', function () {
            return view('admin.dashboard');
        })->middleware('role:admin')->name('admin.dashboard');

        // Owner Dashboard
        Route::get('/owner/dashboard', function () {
            return view('owner.dashboard');
        })->middleware('role:owner')->name('owner.dashboard');

        // Resident Portal
        Route::get('/portal', function () {
            return view('resident.portal');
        })->middleware('role:resident')->name('resident.portal');

        // Master Kamar (Room Management)
        Route::resource('rooms', RoomController::class);
        Route::post('/rooms/{room}/archive', [RoomController::class, 'archive'])->name('rooms.archive');
        Route::post('/rooms/{room}/unarchive', [RoomController::class, 'unarchive'])->name('rooms.unarchive');
    });
});

// Test dummy routes (ONLY registered in testing environment for TC-03 and TC-04)
if (app()->environment('testing')) {
    Route::middleware(['auth', 'active'])->group(function () {
        // Protected admin mutation route to test 403 on non-admin
        Route::post('/_test/admin-mutation', function () {
            return response()->json(['status' => 'mutation_executed']);
        })->middleware('role:admin');

        // Resident profile view route to test data ownership policy
        Route::get('/_test/resident-profile/{resident}', function (Resident $resident) {
            Gate::authorize('view', $resident);
            return response()->json(['resident' => $resident->name]);
        });
    });
}
