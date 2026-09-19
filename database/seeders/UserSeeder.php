<?php

namespace Database\Seeders;

use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Seed initial demo users idempotently.
     * Passwords for new accounts must strictly come from local configuration.
     * If config is empty/invalid for accounts that need to be created, seeding halts with a clear error without creating partial accounts.
     * Existing accounts are NOT modified.
     */
    public function run(): void
    {
        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $residentRole = Role::where('code', 'resident')->firstOrFail();

        $accountsToSeed = [
            'admin' => [
                'role' => $adminRole,
                'email' => 'admin@example.test',
                'name' => 'Admin Kos',
                'config_key' => 'auth.demo_passwords.admin',
                'env_var' => 'DEMO_ADMIN_PASSWORD',
                'must_change_password' => false,
            ],
            'owner' => [
                'role' => $ownerRole,
                'email' => 'owner@example.test',
                'name' => 'Pemilik Kos',
                'config_key' => 'auth.demo_passwords.owner',
                'env_var' => 'DEMO_OWNER_PASSWORD',
                'must_change_password' => false,
            ],
            'resident' => [
                'role' => $residentRole,
                'email' => 'resident@example.test',
                'name' => 'Penghuni Demo',
                'config_key' => 'auth.demo_passwords.resident',
                'env_var' => 'DEMO_RESIDENT_PASSWORD',
                'must_change_password' => true,
            ],
        ];

        // 1. Validate configuration for all accounts that need to be created before making any changes
        $pendingAccounts = [];
        foreach ($accountsToSeed as $key => $account) {
            if (! User::where('email', $account['email'])->exists()) {
                $password = config($account['config_key']);
                if (empty($password) || ! is_string($password) || trim($password) === '') {
                    throw new RuntimeException(
                        "Konfigurasi password demo untuk {$key} belum diatur atau kosong ({$account['env_var']}). Seeding dihentikan tanpa membuat akun."
                    );
                }
                $pendingAccounts[$key] = [
                    'account' => $account,
                    'password' => $password,
                ];
            }
        }

        // If no accounts need to be created, do nothing (idempotent, existing accounts untouched)
        if (empty($pendingAccounts)) {
            return;
        }

        // 2. Create pending accounts inside a single atomic database transaction
        DB::transaction(function () use ($pendingAccounts) {
            foreach ($pendingAccounts as $key => $data) {
                $account = $data['account'];
                $password = $data['password'];

                $user = User::create([
                    'role_id' => $account['role']->id,
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'must_change_password' => $account['must_change_password'],
                ]);

                if ($key === 'resident') {
                    Resident::create([
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'phone' => '081234567890',
                        'origin_address' => 'Jl. Asal No. 1, Kota Asal',
                    ]);
                }
            }
        });
    }
}
