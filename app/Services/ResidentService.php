<?php

namespace App\Services;

use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResidentService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Generate a cryptographically secure 12-character alphanumeric temporary password.
     */
    public function generateTemporaryPassword(): string
    {
        return Str::password(12, letters: true, numbers: true, symbols: false, spaces: false);
    }

    /**
     * Atomically create a user account with 'resident' role and a resident profile.
     * Both mutations and their audit logs execute in a single DB transaction.
     *
     * @param array{name: string, email: string, phone: string, origin_address: string} $data
     * @return array{resident: Resident, user: User, temporary_password: string}
     */
    public function createResidentWithAccount(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $residentRole = Role::where('code', 'resident')->firstOrFail();
            $plainPassword = $this->generateTemporaryPassword();

            // 1. Create User account with resident role and temporary password
            $user = User::create([
                'role_id' => $residentRole->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($plainPassword),
                'is_active' => true,
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ]);

            // 2. Create Resident profile
            $resident = Resident::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'origin_address' => $data['origin_address'],
            ]);

            // 3. Record audit for resident profile creation
            $this->auditService->log(
                action: 'create',
                module: 'residents',
                summary: "Admin {$actor->name} menambahkan data master penghuni {$resident->name}",
                actor: $actor,
                entityType: 'Resident',
                entityId: $resident->id,
                entityLabel: $resident->name,
                changes: [
                    'after' => [
                        'name' => $resident->name,
                        'phone' => $resident->phone,
                        'origin_address' => $resident->origin_address,
                    ],
                ]
            );

            // 4. Record audit for user account creation (password strictly omitted)
            $this->auditService->log(
                action: 'create',
                module: 'users',
                summary: "Admin {$actor->name} membuat akun penghuni untuk {$user->name} ({$user->email})",
                actor: $actor,
                entityType: 'User',
                entityId: $user->id,
                entityLabel: $user->name,
                changes: [
                    'after' => [
                        'role_id' => $user->role_id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'is_active' => true,
                        'must_change_password' => true,
                    ],
                ]
            );

            return [
                'resident' => $resident,
                'user' => $user,
                'temporary_password' => $plainPassword,
            ];
        });
    }

    /**
     * Atomically update a resident profile and synchronize user account name and email.
     * Historical invoice resident_name_snapshot is strictly NOT touched.
     *
     * @param array{name: string, email: string, phone: string, origin_address: string} $data
     */
    public function updateResident(int|Resident $resident, array $data, User $actor): Resident
    {
        return DB::transaction(function () use ($resident, $data, $actor) {
            $residentId = $resident instanceof Resident ? $resident->id : (int) $resident;

            // Consistent lock order: Resident first, then User
            /** @var Resident $lockedResident */
            $lockedResident = Resident::where('id', $residentId)->lockForUpdate()->firstOrFail();
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $lockedResident->user_id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->role?->code !== 'resident') {
                throw new DomainException("Operasi ditolak: Akun target bukan bertipe penghuni.");
            }

            $residentBefore = [
                'name' => $lockedResident->name,
                'phone' => $lockedResident->phone,
                'origin_address' => $lockedResident->origin_address,
            ];
            $userBefore = [
                'name' => $lockedUser->name,
                'email' => $lockedUser->email,
            ];

            // 1. Update resident profile
            $lockedResident->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'origin_address' => $data['origin_address'],
            ]);

            // 2. Synchronize user account name and email
            $lockedUser->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            // 3. Record audit log for resident profile update
            $this->auditService->log(
                action: 'update',
                module: 'residents',
                summary: "Admin {$actor->name} memperbarui profil penghuni {$lockedResident->name}",
                actor: $actor,
                entityType: 'Resident',
                entityId: $lockedResident->id,
                entityLabel: $lockedResident->name,
                changes: [
                    'before' => $residentBefore,
                    'after' => [
                        'name' => $lockedResident->name,
                        'phone' => $lockedResident->phone,
                        'origin_address' => $lockedResident->origin_address,
                    ],
                ]
            );

            // 4. Record audit log for user account update if modified
            if ($userBefore['name'] !== $lockedUser->name || $userBefore['email'] !== $lockedUser->email) {
                $this->auditService->log(
                    action: 'update',
                    module: 'users',
                    summary: "Admin {$actor->name} menyinkronkan data akun pengguna {$lockedUser->name}",
                    actor: $actor,
                    entityType: 'User',
                    entityId: $lockedUser->id,
                    entityLabel: $lockedUser->name,
                    changes: [
                        'before' => $userBefore,
                        'after' => [
                            'name' => $lockedUser->name,
                            'email' => $lockedUser->email,
                        ],
                    ]
                );
            }

            return $lockedResident;
        });
    }

    /**
     * Atomically delete resident profile if no business references exist,
     * and deactivate associated user account per TC-07.
     */
    public function deleteResidentProfile(int|Resident $resident, User $actor): void
    {
        DB::transaction(function () use ($resident, $actor) {
            $residentId = $resident instanceof Resident ? $resident->id : (int) $resident;

            // Consistent lock order: Resident first, then User
            /** @var Resident $lockedResident */
            $lockedResident = Resident::where('id', $residentId)->lockForUpdate()->firstOrFail();
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $lockedResident->user_id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->role?->code !== 'resident') {
                throw new DomainException("Operasi ditolak: Akun target bukan bertipe penghuni.");
            }

            // Check business references: placements or complaints
            if ($lockedResident->placements()->exists() || $lockedUser->complaints()->exists()) {
                throw ValidationException::withMessages([
                    'delete' => 'Penghuni tidak dapat dihapus permanen karena memiliki riwayat referensi bisnis (penempatan atau keluhan). Silakan gunakan fitur arsip.',
                ]);
            }

            $snapshot = [
                'id' => $lockedResident->id,
                'user_id' => $lockedResident->user_id,
                'name' => $lockedResident->name,
                'phone' => $lockedResident->phone,
                'origin_address' => $lockedResident->origin_address,
            ];

            // 1. Delete resident profile physically
            $lockedResident->delete();

            // 2. Deactivate user account per TC-07 and rotate remember token
            $userWasActive = $lockedUser->is_active;
            $lockedUser->is_active = false;
            $lockedUser->session_version = ($lockedUser->session_version ?? 1) + 1;
            $lockedUser->remember_token = Str::random(60);
            $lockedUser->save();

            // 3. Record audit log for resident profile deletion
            $this->auditService->log(
                action: 'delete',
                module: 'residents',
                summary: "Admin {$actor->name} menghapus profil penghuni {$snapshot['name']} tanpa riwayat bisnis dan menonaktifkan akun terkait",
                actor: $actor,
                entityType: 'Resident',
                entityId: $snapshot['id'],
                entityLabel: $snapshot['name'],
                changes: [
                    'before' => $snapshot,
                    'after' => null,
                ]
            );

            // 4. Record audit log for user deactivation
            if ($userWasActive) {
                $this->auditService->log(
                    action: 'deactivate',
                    module: 'users',
                    summary: "Sistem menonaktifkan akun login {$lockedUser->name} karena profil penghuni dihapus",
                    actor: $actor,
                    entityType: 'User',
                    entityId: $lockedUser->id,
                    entityLabel: $lockedUser->name,
                    changes: [
                        'before' => ['is_active' => true],
                        'after' => ['is_active' => false],
                    ]
                );
            }
        });
    }

    /**
     * Atomically archive a resident profile and deactivate associated user account.
     * Active placement blocks archiving.
     */
    public function archiveResident(int|Resident $resident, User $actor): Resident
    {
        return DB::transaction(function () use ($resident, $actor) {
            $residentId = $resident instanceof Resident ? $resident->id : (int) $resident;

            // Consistent lock order: Resident first, then User
            /** @var Resident $lockedResident */
            $lockedResident = Resident::where('id', $residentId)->lockForUpdate()->firstOrFail();
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $lockedResident->user_id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->role?->code !== 'resident') {
                throw new DomainException("Operasi ditolak: Akun target bukan bertipe penghuni.");
            }

            if ($lockedResident->archived_at !== null) {
                return $lockedResident;
            }

            // Active placement check
            if ($lockedResident->activePlacement()->exists()) {
                throw ValidationException::withMessages([
                    'archive' => 'Penghuni tidak dapat diarsipkan karena sedang memiliki penempatan aktif. Akhiri penempatan terlebih dahulu.',
                ]);
            }

            // 1. Archive resident
            $lockedResident->archived_at = now();
            $lockedResident->save();

            // 2. Deactivate user account and rotate remember token
            $userWasActive = $lockedUser->is_active;
            $lockedUser->is_active = false;
            $lockedUser->session_version = ($lockedUser->session_version ?? 1) + 1;
            $lockedUser->remember_token = Str::random(60);
            $lockedUser->save();

            // 3. Record audit log for resident archiving
            $this->auditService->log(
                action: 'archive',
                module: 'residents',
                summary: "Admin {$actor->name} mengarsipkan data penghuni {$lockedResident->name} dan menonaktifkan akun login terkait",
                actor: $actor,
                entityType: 'Resident',
                entityId: $lockedResident->id,
                entityLabel: $lockedResident->name,
                changes: [
                    'before' => ['archived_at' => null],
                    'after' => ['archived_at' => $lockedResident->archived_at?->toIso8601String()],
                ]
            );

            // 4. Record audit log for user account deactivation
            if ($userWasActive) {
                $this->auditService->log(
                    action: 'deactivate',
                    module: 'users',
                    summary: "Sistem menonaktifkan akun login {$lockedUser->name} karena profil penghuni diarsipkan",
                    actor: $actor,
                    entityType: 'User',
                    entityId: $lockedUser->id,
                    entityLabel: $lockedUser->name,
                    changes: [
                        'before' => ['is_active' => true],
                        'after' => ['is_active' => false],
                    ]
                );
            }

            return $lockedResident;
        });
    }

    /**
     * Atomically unarchive a resident profile and reactivate associated user account.
     * Profile must currently be archived.
     */
    public function unarchiveResident(int|Resident $resident, User $actor): Resident
    {
        return DB::transaction(function () use ($resident, $actor) {
            $residentId = $resident instanceof Resident ? $resident->id : (int) $resident;

            // Consistent lock order: Resident first, then User
            /** @var Resident $lockedResident */
            $lockedResident = Resident::where('id', $residentId)->lockForUpdate()->firstOrFail();
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $lockedResident->user_id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->role?->code !== 'resident') {
                throw new DomainException("Operasi ditolak: Akun target bukan bertipe penghuni.");
            }

            if ($lockedResident->archived_at === null) {
                throw ValidationException::withMessages([
                    'unarchive' => 'Penghuni tidak sedang dalam status diarsipkan.',
                ]);
            }

            $beforeArchivedAt = $lockedResident->archived_at?->toIso8601String();

            // 1. Unarchive resident profile
            $lockedResident->archived_at = null;
            $lockedResident->save();

            // 2. Reactivate user account and revoke pre-archive sessions
            $userWasActive = $lockedUser->is_active;
            $lockedUser->is_active = true;
            $lockedUser->session_version = ($lockedUser->session_version ?? 1) + 1;
            $lockedUser->remember_token = Str::random(60);
            $lockedUser->save();

            // 3. Record audit log for resident unarchive
            $this->auditService->log(
                action: 'unarchive',
                module: 'residents',
                summary: "Admin {$actor->name} membuka arsip penghuni {$lockedResident->name} dan mengaktifkan kembali akun login terkait",
                actor: $actor,
                entityType: 'Resident',
                entityId: $lockedResident->id,
                entityLabel: $lockedResident->name,
                changes: [
                    'before' => ['archived_at' => $beforeArchivedAt],
                    'after' => ['archived_at' => null],
                ]
            );

            // 4. Record audit log for user account reactivation
            if (! $userWasActive) {
                $this->auditService->log(
                    action: 'activate',
                    module: 'users',
                    summary: "Sistem mengaktifkan akun login {$lockedUser->name} karena profil penghuni dibuka dari arsip",
                    actor: $actor,
                    entityType: 'User',
                    entityId: $lockedUser->id,
                    entityLabel: $lockedUser->name,
                    changes: [
                        'before' => ['is_active' => false],
                        'after' => ['is_active' => true],
                    ]
                );
            }

            return $lockedResident;
        });
    }

    /**
     * Explicitly and idempotently activate a user account.
     * Fails if resident profile is currently archived.
     */
    public function activateAccount(int|Resident $resident, User $actor): bool
    {
        return DB::transaction(function () use ($resident, $actor) {
            $residentId = $resident instanceof Resident ? $resident->id : (int) $resident;

            // Consistent lock order: Resident first, then User
            /** @var Resident $lockedResident */
            $lockedResident = Resident::where('id', $residentId)->lockForUpdate()->firstOrFail();
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $lockedResident->user_id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->role?->code !== 'resident') {
                throw new DomainException("Operasi ditolak: Akun target bukan bertipe penghuni.");
            }

            if ($lockedResident->archived_at !== null) {
                throw ValidationException::withMessages([
                    'activate' => 'Akun dengan profil yang diarsipkan tidak dapat diaktifkan langsung. Buka arsip terlebih dahulu.',
                ]);
            }

            if ($lockedUser->is_active) {
                return true; // Idempotent
            }

            $lockedUser->is_active = true;
            $lockedUser->session_version = ($lockedUser->session_version ?? 1) + 1;
            $lockedUser->remember_token = Str::random(60);
            $lockedUser->save();

            $this->auditService->log(
                action: 'activate',
                module: 'users',
                summary: "Admin {$actor->name} mengaktifkan akun pengguna {$lockedUser->name}",
                actor: $actor,
                entityType: 'User',
                entityId: $lockedUser->id,
                entityLabel: $lockedUser->name,
                changes: [
                    'before' => ['is_active' => false],
                    'after' => ['is_active' => true],
                ]
            );

            return true;
        });
    }

    /**
     * Explicitly and idempotently deactivate a user account and rotate remember token to revoke old sessions.
     */
    public function deactivateAccount(int|Resident $resident, User $actor): bool
    {
        return DB::transaction(function () use ($resident, $actor) {
            $residentId = $resident instanceof Resident ? $resident->id : (int) $resident;

            // Consistent lock order: Resident first, then User
            /** @var Resident $lockedResident */
            $lockedResident = Resident::where('id', $residentId)->lockForUpdate()->firstOrFail();
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $lockedResident->user_id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->role?->code !== 'resident') {
                throw new DomainException("Operasi ditolak: Akun target bukan bertipe penghuni.");
            }

            if (! $lockedUser->is_active) {
                return true; // Idempotent
            }

            $lockedUser->is_active = false;
            $lockedUser->session_version = ($lockedUser->session_version ?? 1) + 1;
            $lockedUser->remember_token = Str::random(60);
            $lockedUser->save();

            $this->auditService->log(
                action: 'deactivate',
                module: 'users',
                summary: "Admin {$actor->name} menonaktifkan akun pengguna {$lockedUser->name}",
                actor: $actor,
                entityType: 'User',
                entityId: $lockedUser->id,
                entityLabel: $lockedUser->name,
                changes: [
                    'before' => ['is_active' => true],
                    'after' => ['is_active' => false],
                ]
            );

            return true;
        });
    }

    /**
     * Atomically reset temporary password, set must_change_password flag, and rotate remember token.
     * Password plaintext is returned for one-time display to Admin and strictly NEVER logged.
     */
    public function resetTemporaryPassword(int|Resident $resident, User $actor): string
    {
        return DB::transaction(function () use ($resident, $actor) {
            $residentId = $resident instanceof Resident ? $resident->id : (int) $resident;

            // Consistent lock order: Resident first, then User
            /** @var Resident $lockedResident */
            $lockedResident = Resident::where('id', $residentId)->lockForUpdate()->firstOrFail();
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $lockedResident->user_id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->role?->code !== 'resident') {
                throw new DomainException("Operasi ditolak: Akun target bukan bertipe penghuni.");
            }

            $plainPassword = $this->generateTemporaryPassword();
            $wasMustChange = $lockedUser->must_change_password;

            $lockedUser->password = Hash::make($plainPassword);
            $lockedUser->must_change_password = true;
            $lockedUser->session_version = ($lockedUser->session_version ?? 1) + 1;
            $lockedUser->remember_token = Str::random(60);
            $lockedUser->save();

            // Record audit log without credential
            $this->auditService->log(
                action: 'reset_password',
                module: 'users',
                summary: "Admin {$actor->name} mereset password sementara akun {$lockedUser->name}",
                actor: $actor,
                entityType: 'User',
                entityId: $lockedUser->id,
                entityLabel: $lockedUser->name,
                changes: [
                    'before' => ['must_change_password' => $wasMustChange],
                    'after' => ['must_change_password' => true],
                ]
            );

            return $plainPassword;
        });
    }
}
