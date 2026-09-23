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

        // Master Penghuni & Akun (Resident Management)
        Route::resource('residents', \App\Http\Controllers\ResidentController::class);
        Route::post('/residents/{resident}/archive', [\App\Http\Controllers\ResidentController::class, 'archive'])->name('residents.archive');
        Route::post('/residents/{resident}/unarchive', [\App\Http\Controllers\ResidentController::class, 'unarchive'])->name('residents.unarchive');
        Route::post('/residents/{resident}/activate', [\App\Http\Controllers\ResidentController::class, 'activate'])->name('residents.activate');
        Route::post('/residents/{resident}/deactivate', [\App\Http\Controllers\ResidentController::class, 'deactivate'])->name('residents.deactivate');
        Route::post('/residents/{resident}/reset-password', [\App\Http\Controllers\ResidentController::class, 'resetPassword'])->name('residents.reset-password');

        // Master Fasilitas (Facility Management)
        Route::resource('facilities', \App\Http\Controllers\FacilityController::class);
        Route::post('/facilities/{facility}/archive', [\App\Http\Controllers\FacilityController::class, 'archive'])->name('facilities.archive');
        Route::post('/facilities/{facility}/unarchive', [\App\Http\Controllers\FacilityController::class, 'unarchive'])->name('facilities.unarchive');

        // Operasional Penempatan (Placement Management - T10 & T11)
        Route::get('/placements', [\App\Http\Controllers\PlacementController::class, 'index'])->name('placements.index');
        Route::get('/placements/create', [\App\Http\Controllers\PlacementController::class, 'create'])->name('placements.create');
        Route::post('/placements/preview', [\App\Http\Controllers\PlacementController::class, 'preview'])->name('placements.preview');
        Route::post('/placements', [\App\Http\Controllers\PlacementController::class, 'store'])->name('placements.store');
        Route::get('/placements/{placement}', [\App\Http\Controllers\PlacementController::class, 'show'])->name('placements.show');
        Route::post('/placements/{placement}/end-preview', [\App\Http\Controllers\PlacementController::class, 'endPreview'])->name('placements.end-preview');
        Route::post('/placements/{placement}/end', [\App\Http\Controllers\PlacementController::class, 'end'])->name('placements.end');
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
