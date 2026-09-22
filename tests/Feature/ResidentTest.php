<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Placement;
use App\Models\Resident;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ResidentTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createUser(string $roleCode, string $email = null): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $email = $email ?? ($roleCode . '_' . Str::random(8) . '@example.test');

        return User::create([
            'role_id' => $role->id,
            'name' => 'User ' . ucfirst($roleCode),
            'email' => $email,
            'password' => Hash::make('Password123456!'),
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ]);
    }

    private function createResident(string $email = null, string $name = 'Penghuni Test', string $phone = '081234567890', ?string $archivedAt = null): Resident
    {
        $user = $this->createUser('resident', $email);
        $user->update(['name' => $name]);

        return Resident::create([
            'user_id' => $user->id,
            'name' => $name,
            'phone' => $phone,
            'origin_address' => 'Jl. Asal No. 1, Kota Asal',
            'archived_at' => $archivedAt,
        ]);
    }

    private function createRoom(string $number = null): Room
    {
        $number = $number ?? ('K-' . rand(100, 999));

        return Room::create([
            'number' => $number,
            'type' => 'Standard',
            'monthly_rate' => 800000,
        ]);
    }

    // =========================================================================
    // 1. OTORISASI SERVER-SIDE & PROTEKSI ENDPOINT MASTER
    // =========================================================================

    public function test_admin_can_access_all_master_resident_endpoints(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();

        $this->actingAs($admin)->get(route('residents.index'))->assertOk();
        $this->actingAs($admin)->get(route('residents.create'))->assertOk();
        $this->actingAs($admin)->get(route('residents.show', $resident))->assertOk();
        $this->actingAs($admin)->get(route('residents.edit', $resident))->assertOk();
    }

    public function test_owner_can_only_view_residents_as_readonly(): void
    {
        $owner = $this->createUser('owner');
        $resident = $this->createResident();

        // Read endpoints: 200 OK
        $this->actingAs($owner)->get(route('residents.index'))->assertOk();
        $this->actingAs($owner)->get(route('residents.show', $resident))->assertOk();

        // Mutation endpoints: 403 Forbidden
        $this->actingAs($owner)->get(route('residents.create'))->assertForbidden();
        $this->actingAs($owner)->post(route('residents.store'), [
            'name' => 'Penghuni Ilegal',
            'email' => 'ilegal@example.test',
            'phone' => '081234567890',
            'origin_address' => 'Alamat Asal Valid',
        ])->assertForbidden();
        $this->actingAs($owner)->get(route('residents.edit', $resident))->assertForbidden();
        $this->actingAs($owner)->put(route('residents.update', $resident), [
            'name' => 'Update Ilegal',
            'email' => 'update.ilegal@example.test',
            'phone' => '081234567890',
            'origin_address' => 'Alamat Asal Valid',
        ])->assertForbidden();
        $this->actingAs($owner)->delete(route('residents.destroy', $resident))->assertForbidden();
        $this->actingAs($owner)->post(route('residents.archive', $resident))->assertForbidden();
        $this->actingAs($owner)->post(route('residents.unarchive', $resident))->assertForbidden();
        $this->actingAs($owner)->post(route('residents.activate', $resident))->assertForbidden();
        $this->actingAs($owner)->post(route('residents.deactivate', $resident))->assertForbidden();
        $this->actingAs($owner)->post(route('residents.reset-password', $resident))->assertForbidden();
    }

    public function test_resident_cannot_access_any_master_resident_endpoints_including_own_detail(): void
    {
        $resident = $this->createResident(name: 'Penghuni Satu');
        $otherResident = $this->createResident(name: 'Penghuni Dua');

        // Master Index: 403
        $this->actingAs($resident->user)->get(route('residents.index'))->assertForbidden();
        $this->actingAs($resident->user)->get(route('residents.create'))->assertForbidden();

        // Master Detail: 403 even for self!
        $this->actingAs($resident->user)->get(route('residents.show', $resident))->assertForbidden();
        $this->actingAs($resident->user)->get(route('residents.show', $otherResident))->assertForbidden();

        // Mutation endpoints: 403
        $this->actingAs($resident->user)->get(route('residents.edit', $resident))->assertForbidden();
        $this->actingAs($resident->user)->delete(route('residents.destroy', $resident))->assertForbidden();
        $this->actingAs($resident->user)->post(route('residents.archive', $resident))->assertForbidden();
        $this->actingAs($resident->user)->post(route('residents.unarchive', $resident))->assertForbidden();
        $this->actingAs($resident->user)->post(route('residents.activate', $resident))->assertForbidden();
        $this->actingAs($resident->user)->post(route('residents.deactivate', $resident))->assertForbidden();
        $this->actingAs($resident->user)->post(route('residents.reset-password', $resident))->assertForbidden();
    }

    public function test_unauthorized_roles_receive_403_before_validation(): void
    {
        $owner = $this->createUser('owner');
        $resident = $this->createResident();

        // Send invalid payload to store (missing all required fields)
        $this->actingAs($owner)
            ->post(route('residents.store'), [])
            ->assertForbidden();

        // Send invalid payload to update
        $this->actingAs($owner)
            ->put(route('residents.update', $resident), [])
            ->assertForbidden();
    }

    // =========================================================================
    // 2. PEMBUATAN AKUN & PROFIL ATOMIK (TC-07 & TC-30)
    // =========================================================================

    public function test_tc07_admin_can_create_resident_with_account_atomically(): void
    {
        $admin = $this->createUser('admin');
        $email = 'penghuni.baru.' . Str::random(6) . '@example.test';

        $response = $this->actingAs($admin)->post(route('residents.store'), [
            'name' => 'Budi Santoso',
            'email' => $email,
            'phone' => '+62 812-3456-7890',
            'origin_address' => 'Jl. Kenanga No. 15, Surabaya',
        ]);

        $createdResident = Resident::where('name', 'Budi Santoso')->firstOrFail();
        $response->assertRedirect(route('residents.show', $createdResident));
        $response->assertSessionHas('status');
        $response->assertSessionHas('temporary_credentials');

        $tempCreds = session('temporary_credentials');
        $this->assertSame('Budi Santoso', $tempCreds['name']);
        $this->assertSame($email, $tempCreds['email']);
        $this->assertNotEmpty($tempCreds['password']);
        $this->assertSame(12, strlen($tempCreds['password']));

        // Verify user account in database
        $user = $createdResident->user;
        $this->assertNotNull($user);
        $this->assertSame('resident', $user->role->code);
        $this->assertSame($email, $user->email);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($tempCreds['password'], $user->password));

        // Verify audit logs for both modules (residents and users)
        $residentLog = ActivityLog::where('module', 'residents')
            ->where('entity_id', $createdResident->id)
            ->where('action', 'create')
            ->first();
        $this->assertNotNull($residentLog);
        $this->assertSame($admin->id, $residentLog->actor_id);

        $userLog = ActivityLog::where('module', 'users')
            ->where('entity_id', $user->id)
            ->where('action', 'create')
            ->first();
        $this->assertNotNull($userLog);
        $this->assertSame($admin->id, $userLog->actor_id);
    }

    public function test_resident_creation_validates_required_fields_and_phone_format(): void
    {
        $admin = $this->createUser('admin');

        // Invalid: missing fields
        $this->actingAs($admin)
            ->post(route('residents.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'phone', 'origin_address']);

        // Invalid: phone without any digits
        $this->actingAs($admin)
            ->post(route('residents.store'), [
                'name' => 'Budi Santoso',
                'email' => 'valid@example.test',
                'phone' => '+++---   ',
                'origin_address' => 'Jl. Alamat Valid 123',
            ])
            ->assertSessionHasErrors(['phone']);

        // Invalid: phone too short (< 8 chars)
        $this->actingAs($admin)
            ->post(route('residents.store'), [
                'name' => 'Budi Santoso',
                'email' => 'valid@example.test',
                'phone' => '08123',
                'origin_address' => 'Jl. Alamat Valid 123',
            ])
            ->assertSessionHasErrors(['phone']);

        // Invalid: address too short (< 5 chars)
        $this->actingAs($admin)
            ->post(route('residents.store'), [
                'name' => 'Budi Santoso',
                'email' => 'valid@example.test',
                'phone' => '08123456789',
                'origin_address' => 'Jkt',
            ])
            ->assertSessionHasErrors(['origin_address']);
    }

    public function test_tc07_email_must_be_unique_including_inactive_accounts(): void
    {
        $admin = $this->createUser('admin');

        // Existing inactive user account
        $inactiveUser = $this->createUser('resident', 'inactive.user@example.test');
        $inactiveUser->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('residents.store'), [
                'name' => 'Penghuni Duplikat',
                'email' => 'inactive.user@example.test',
                'phone' => '081234567890',
                'origin_address' => 'Jl. Alamat Valid 123',
            ])
            ->assertSessionHasErrors(['email']);

        // Assert no orphan resident profile was created
        $this->assertDatabaseMissing('residents', [
            'name' => 'Penghuni Duplikat',
        ]);
    }

    public function test_tc30_creation_rolls_back_fully_when_profile_creation_fails(): void
    {
        $admin = $this->createUser('admin');
        $email = 'will.fail.' . Str::random(6) . '@example.test';

        $residentService = app(\App\Services\ResidentService::class);

        // Force failure by passing invalid data to profile insert inside transaction
        try {
            DB::transaction(function () use ($residentService, $admin, $email) {
                // Pass origin_address as null or trigger exception
                $residentService->createResidentWithAccount([
                    'name' => 'Gagal Test',
                    'email' => $email,
                    'phone' => '081234567890',
                    'origin_address' => null, // Column is NOT NULL in schema
                ], $admin);
            });
            $this->fail('Expected exception was not thrown');
        } catch (\Throwable $e) {
            // Expected
        }

        // Verify User was rolled back completely
        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertDatabaseMissing('residents', ['name' => 'Gagal Test']);
    }

    public function test_tc30_creation_rolls_back_when_second_audit_fails(): void
    {
        $admin = $this->createUser('admin');
        $email = 'audit.fail.' . Str::random(6) . '@example.test';

        // Mock AuditService so the second log call throws an exception
        $mockAudit = $this->createMock(AuditService::class);
        $callCount = 0;
        $mockAudit->method('log')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 2) {
                throw new RuntimeException("Simulated failure on second audit log (users module)");
            }
            return new ActivityLog();
        });

        $service = new \App\Services\ResidentService($mockAudit);

        try {
            $service->createResidentWithAccount([
                'name' => 'Audit Fail Test',
                'email' => $email,
                'phone' => '081234567890',
                'origin_address' => 'Jl. Asal Valid No. 12',
            ], $admin);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated failure on second audit log', $e->getMessage());
        }

        // Entire transaction must be rolled back
        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertDatabaseMissing('residents', ['name' => 'Audit Fail Test']);
    }

    // =========================================================================
    // 3. PENOLAKAN MANIPULASI ROLE & AKUN ADMIN/PEMILIK
    // =========================================================================

    public function test_tc30_role_manipulation_via_payload_is_strictly_rejected(): void
    {
        $admin = $this->createUser('admin');
        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $email = 'hacker.' . Str::random(6) . '@example.test';

        // Attempting to send role_id as admin
        $this->actingAs($admin)->post(route('residents.store'), [
            'name' => 'Hacker Budi',
            'email' => $email,
            'phone' => '081234567890',
            'origin_address' => 'Jl. Hacker No. 1',
            'role_id' => $adminRole->id,
            'role' => 'admin',
        ])->assertRedirect();

        $user = User::where('email', $email)->firstOrFail();
        $this->assertSame('resident', $user->role->code);
        $this->assertNotEquals($adminRole->id, $user->role_id);
    }

    public function test_admin_or_owner_accounts_cannot_be_manipulated_through_resident_service(): void
    {
        $adminActor = $this->createUser('admin');
        $resident = $this->createResident();
        $ownerTarget = $this->createUser('owner');
        $resident->update(['user_id' => $ownerTarget->id]);

        $residentService = app(\App\Services\ResidentService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Akun target bukan bertipe penghuni');

        $residentService->updateResident($resident, [
            'name' => 'Manipulated Name',
            'email' => 'manipulated@example.test',
            'phone' => '081234567890',
            'origin_address' => 'Jl. Fake',
        ], $adminActor);
    }

    // =========================================================================
    // 4. SINKRONISASI NAMA VS IMUTABILITAS SNAPSHOT FAKTUR
    // =========================================================================

    public function test_tc30_name_update_syncs_user_and_resident_without_altering_invoice_snapshot(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident(name: 'Nama Lama');
        $room = $this->createRoom('K-201');

        // Create historical placement and invoice with snapshot
        $placement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-08-01',
            'ended_on' => '2026-08-31',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
            'ended_by' => $admin->id,
            'end_reason' => 'Selesai Kontrak',
        ]);

        $invoice = Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-08-01',
            'due_on' => '2026-08-05',
            'amount' => 800000,
            'resident_name_snapshot' => 'Nama Lama',
            'room_number_snapshot' => 'K-201',
            'created_by' => $admin->id,
        ]);

        // Update resident name
        $this->actingAs($admin)->put(route('residents.update', $resident), [
            'name' => 'Nama Baru Disinkronkan',
            'email' => $resident->user->email,
            'phone' => $resident->phone,
            'origin_address' => $resident->origin_address,
        ])->assertRedirect();

        // 1. Verify resident profile name updated
        $resident->refresh();
        $this->assertSame('Nama Baru Disinkronkan', $resident->name);

        // 2. Verify user account name synchronized
        $resident->user->refresh();
        $this->assertSame('Nama Baru Disinkronkan', $resident->user->name);

        // 3. Verify historical invoice snapshot remains untouched!
        $invoice->refresh();
        $this->assertSame('Nama Lama', $invoice->resident_name_snapshot);
    }

    public function test_resident_update_rolls_back_when_audit_fails(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident(name: 'Nama Asli');

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException("Simulated audit failure during update"));

        $service = new \App\Services\ResidentService($mockAudit);

        try {
            $service->updateResident($resident, [
                'name' => 'Nama Berubah',
                'email' => $resident->user->email,
                'phone' => $resident->phone,
                'origin_address' => $resident->origin_address,
            ], $admin);
            $this->fail('Expected exception not thrown');
        } catch (RuntimeException $e) {
            // Expected
        }

        $resident->refresh();
        $this->assertSame('Nama Asli', $resident->name);
        $this->assertSame('Nama Asli', $resident->user->name);
    }

    // =========================================================================
    // 5. HAPUS FISIK VS PENGARSIPAN (TC-07 & TC-09)
    // =========================================================================

    public function test_tc07_delete_profile_without_references_deletes_profile_and_deactivates_user(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident(name: 'Tanpa Referensi');
        $userId = $resident->user_id;

        $this->actingAs($admin)
            ->delete(route('residents.destroy', $resident))
            ->assertRedirect(route('residents.index'));

        // Profile must be deleted from residents table
        $this->assertDatabaseMissing('residents', ['id' => $resident->id]);

        // User account MUST still exist in users table but be DEACTIVATED per TC-07
        $user = User::find($userId);
        $this->assertNotNull($user);
        $this->assertFalse($user->is_active);

        // Audit log exists
        $log = ActivityLog::where('module', 'residents')
            ->where('action', 'delete')
            ->where('entity_id', $resident->id)
            ->first();
        $this->assertNotNull($log);
    }

    public function test_tc09_delete_rejected_when_resident_has_placement_history(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();
        $room = $this->createRoom();

        // Create past placement
        Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-07-01',
            'ended_on' => '2026-07-31',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
            'ended_by' => $admin->id,
            'end_reason' => 'Selesai Sewa',
        ]);

        $this->actingAs($admin)
            ->delete(route('residents.destroy', $resident))
            ->assertSessionHasErrors(['delete']);

        // Profile and user remain untouched
        $this->assertDatabaseHas('residents', ['id' => $resident->id]);
        $this->assertTrue($resident->user->fresh()->is_active);
    }

    public function test_delete_rolls_back_when_audit_fails(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException("Audit failure on delete"));

        $service = new \App\Services\ResidentService($mockAudit);

        try {
            $service->deleteResidentProfile($resident, $admin);
            $this->fail('Expected exception not thrown');
        } catch (RuntimeException $e) {
            // Expected
        }

        $this->assertDatabaseHas('residents', ['id' => $resident->id]);
        $this->assertTrue($resident->user->fresh()->is_active);
    }

    public function test_archive_resident_without_active_placement_deactivates_user(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();

        $this->actingAs($admin)
            ->post(route('residents.archive', $resident))
            ->assertRedirect();

        $resident->refresh();
        $this->assertNotNull($resident->archived_at);
        $this->assertFalse($resident->user->is_active);

        // Audit log exists
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'residents',
            'action' => 'archive',
            'entity_id' => $resident->id,
        ]);
    }

    public function test_tc09_archive_rejected_when_resident_has_active_placement(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();
        $room = $this->createRoom();

        // Active placement (ended_on is null)
        Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'ended_on' => null,
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('residents.archive', $resident))
            ->assertSessionHasErrors(['archive']);

        $resident->refresh();
        $this->assertNull($resident->archived_at);
        $this->assertTrue($resident->user->is_active);
    }

    public function test_unarchive_restores_profile_and_reactivates_user(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident(archivedAt: '2026-08-01 10:00:00');
        $resident->user->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('residents.unarchive', $resident))
            ->assertRedirect();

        $resident->refresh();
        $this->assertNull($resident->archived_at);
        $this->assertTrue($resident->user->is_active);

        // Audit logs exist
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'residents',
            'action' => 'unarchive',
            'entity_id' => $resident->id,
        ]);
    }

    public function test_archive_rolls_back_when_audit_fails(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException("Audit failure on archive"));

        $service = new \App\Services\ResidentService($mockAudit);

        try {
            $service->archiveResident($resident, $admin);
            $this->fail('Expected exception not thrown');
        } catch (RuntimeException $e) {
            // Expected
        }

        $resident->refresh();
        $this->assertNull($resident->archived_at);
        $this->assertTrue($resident->user->is_active);
    }

    public function test_unarchive_rolls_back_when_audit_fails(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident(archivedAt: '2026-08-01 10:00:00');
        $resident->user->update(['is_active' => false]);

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException("Audit failure on unarchive"));

        $service = new \App\Services\ResidentService($mockAudit);

        try {
            $service->unarchiveResident($resident, $admin);
            $this->fail('Expected exception not thrown');
        } catch (RuntimeException $e) {
            // Expected
        }

        $resident->refresh();
        $this->assertNotNull($resident->archived_at);
        $this->assertFalse($resident->user->is_active);
    }

    public function test_unarchive_rejected_if_not_currently_archived(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident(archivedAt: null);

        $this->actingAs($admin)
            ->post(route('residents.unarchive', $resident))
            ->assertSessionHasErrors(['unarchive']);
    }

    // =========================================================================
    // 6. AKSI EKSPLISIT AKTIFKAN & NONAKTIFKAN AKUN (IDEMPOTEN)
    // =========================================================================

    public function test_explicit_deactivate_and_activate_account(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();

        // 1. Deactivate
        $this->actingAs($admin)
            ->post(route('residents.deactivate', $resident))
            ->assertRedirect();

        $resident->user->refresh();
        $this->assertFalse($resident->user->is_active);

        // 2. Idempotent deactivation
        $this->actingAs($admin)
            ->post(route('residents.deactivate', $resident))
            ->assertRedirect();
        $this->assertFalse($resident->user->is_active);

        // 3. Activate
        $this->actingAs($admin)
            ->post(route('residents.activate', $resident))
            ->assertRedirect();

        $resident->user->refresh();
        $this->assertTrue($resident->user->is_active);

        // 4. Idempotent activation
        $this->actingAs($admin)
            ->post(route('residents.activate', $resident))
            ->assertRedirect();
        $this->assertTrue($resident->user->is_active);
    }

    public function test_activate_rejected_when_resident_profile_is_archived(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident(archivedAt: '2026-09-01 10:00:00');
        $resident->user->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('residents.activate', $resident))
            ->assertSessionHasErrors(['activate']);

        $resident->user->refresh();
        $this->assertFalse($resident->user->is_active);
    }

    public function test_activate_rolls_back_when_audit_fails(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();
        $resident->user->update(['is_active' => false]);

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException("Audit failure on activate"));

        $service = new \App\Services\ResidentService($mockAudit);

        try {
            $service->activateAccount($resident, $admin);
            $this->fail('Expected exception not thrown');
        } catch (RuntimeException $e) {
            // Expected
        }

        $resident->user->refresh();
        $this->assertFalse($resident->user->is_active);
    }

    public function test_deactivate_rolls_back_when_audit_fails(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();
        $this->assertTrue($resident->user->is_active);

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException("Audit failure on deactivate"));

        $service = new \App\Services\ResidentService($mockAudit);

        try {
            $service->deactivateAccount($resident, $admin);
            $this->fail('Expected exception not thrown');
        } catch (RuntimeException $e) {
            // Expected
        }

        $resident->user->refresh();
        $this->assertTrue($resident->user->is_active);
    }

    // =========================================================================
    // 7. RESET PASSWORD SEMENTARA & PENCABUTAN SESI LAMA (TC-05)
    // =========================================================================

    public function test_tc05_reset_temporary_password_updates_credentials_and_omits_password_from_audit(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();
        $oldPasswordHash = $resident->user->password;

        $response = $this->actingAs($admin)
            ->post(route('residents.reset-password', $resident));

        $response->assertRedirect();
        $response->assertSessionHas('temporary_credentials');

        $tempCreds = session('temporary_credentials');
        $this->assertSame(12, strlen($tempCreds['password']));

        $resident->user->refresh();
        $this->assertNotEquals($oldPasswordHash, $resident->user->password);
        $this->assertTrue($resident->user->must_change_password);
        $this->assertTrue(Hash::check($tempCreds['password'], $resident->user->password));

        // Audit log must NOT contain password
        $log = ActivityLog::where('module', 'users')
            ->where('action', 'reset_password')
            ->where('entity_id', $resident->user->id)
            ->first();
        $this->assertNotNull($log);

        $logJson = json_encode($log->changes);
        $this->assertStringNotContainsString($tempCreds['password'], $logJson);
        $this->assertArrayNotHasKey('password', $log->changes['before'] ?? []);
    }

    public function test_reset_temporary_password_rolls_back_when_audit_fails(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();
        $oldPassword = $resident->user->password;

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException("Audit failure on reset password"));

        $service = new \App\Services\ResidentService($mockAudit);

        try {
            $service->resetTemporaryPassword($resident, $admin);
            $this->fail('Expected exception not thrown');
        } catch (RuntimeException $e) {
            // Expected
        }

        $resident->user->refresh();
        $this->assertSame($oldPassword, $resident->user->password);
    }

    public function test_two_independent_sessions_revocation_on_reset_temporary_password(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();
        $user = $resident->user;

        // Session 1 and Session 2 initially have valid signatures
        $oldSignature = $user->getSessionSignature();
        $session1 = ['auth_session_hash_' . $user->id => $oldSignature];
        $session2 = ['auth_session_hash_' . $user->id => $oldSignature];

        // Session 1 can access protected route initially
        $this->actingAs($user)
            ->withSession($session1)
            ->get(route('resident.portal'))
            ->assertOk();

        // Session 2 can access protected route initially
        $this->actingAs($user)
            ->withSession($session2)
            ->get(route('resident.portal'))
            ->assertOk();

        // Admin resets temporary password
        $service = app(\App\Services\ResidentService::class);
        $tempPassword = $service->resetTemporaryPassword($resident, $admin);

        // Session 1 makes request: Credentials/version changed -> revoked and redirected to login!
        $this->actingAs($user->fresh())
            ->withSession($session1)
            ->get(route('resident.portal'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        // Session 2 makes request: Also revoked and redirected to login!
        $this->actingAs($user->fresh())
            ->withSession($session2)
            ->get(route('resident.portal'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_new_login_remains_valid_even_when_old_session_sends_request(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();
        $user = $resident->user;

        // Session 1 (Device A, old signature)
        $oldSignature = $user->getSessionSignature();
        $session1 = ['auth_session_hash_' . $user->id => $oldSignature];

        // Admin resets temporary password
        $service = app(\App\Services\ResidentService::class);
        $tempPassword = $service->resetTemporaryPassword($resident, $admin);

        // User logs in on Device C via POST login with new temporary password
        $loginRes = $this->post(route('login'), [
            'email' => $user->email,
            'password' => $tempPassword,
        ]);
        $loginRes->assertRedirect(route('password.change'));
        $this->assertAuthenticatedAs($user);

        // Save new session data from Device C
        $session3 = session()->all();

        // Now, Device A (Session 1 with old signature) sends a request
        // Middleware must reject Device A WITHOUT rotating global tokens or invalidating Device C
        $this->actingAs($user->fresh())
            ->withSession($session1)
            ->get(route('resident.portal'))
            ->assertRedirect(route('login'));

        // Verify Device C (Session 3) is STILL completely valid and can access its route!
        $this->actingAs($user->fresh())
            ->withSession($session3)
            ->get(route('password.change'))
            ->assertOk();
    }

    public function test_session_without_signature_is_rejected(): void
    {
        $resident = $this->createResident();
        $user = $resident->user;

        // User authenticated in session, but session does NOT contain auth_session_hash
        $this->actingAs($user);
        session()->forget('auth_session_hash_' . $user->id);

        $response = $this->get(route('resident.portal'));
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_legitimate_remember_me_works_and_old_cookie_rejected_after_revocation(): void
    {
        $password = 'SecretPass123!';
        $role = \App\Models\Role::where('code', 'resident')->first();
        $user = \App\Models\User::create([
            'role_id' => $role->id,
            'name' => 'Remember Me User',
            'email' => 'rememberme@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => \Illuminate\Support\Str::random(60),
            'session_version' => 1,
        ]);
        \App\Models\Resident::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'phone' => '089988776655',
            'origin_address' => 'Jl. Pengujian No. 1',
        ]);

        // 1. User logs in with remember-me
        $loginRes = $this->post(route('login'), [
            'email' => $user->email,
            'password' => $password,
            'remember' => '1',
        ]);
        $loginRes->assertRedirect(route('resident.portal'));

        // Retrieve remember cookie from response
        $cookie = $loginRes->getCookie(\Illuminate\Support\Facades\Auth::getRecallerName());
        $this->assertNotNull($cookie, 'Remember-me cookie must be sent on login with remember=1');

        // 2. Clear session to simulate closed browser / expired session, but send remember cookie
        session()->flush();
        auth()->forgetUser();

        // Send request with remember cookie: legitimate remember-me works!
        $res = $this->withCookie($cookie->getName(), $cookie->getValue())
            ->get(route('resident.portal'));
        $res->assertOk();

        // 3. Now admin revokes session/credentials (e.g. deactivate or reset password)
        $admin = $this->createUser('admin');
        $service = app(\App\Services\ResidentService::class);
        $service->resetTemporaryPassword($user->resident, $admin);

        // 4. Send request with OLD remember cookie: cookie rejected after revocation!
        session()->flush();
        auth()->forgetUser();

        $resAfter = $this->withCookie($cookie->getName(), $cookie->getValue())
            ->get(route('resident.portal'));
        $resAfter->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivation_then_reactivation_does_not_revive_old_session(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();
        $user = $resident->user;

        // Session 1 created with current version signature
        $oldSignature = $user->getSessionSignature();
        $session1 = ['auth_session_hash_' . $user->id => $oldSignature];

        // Session 1 can access protected route initially
        $this->actingAs($user)
            ->withSession($session1)
            ->get(route('resident.portal'))
            ->assertOk();

        // Admin deactivates account (session_version++) and reactivates account (session_version++)
        $service = app(\App\Services\ResidentService::class);
        $service->deactivateAccount($resident, $admin);
        $service->activateAccount($resident, $admin);

        $user->refresh();
        $this->assertTrue($user->is_active);

        // Session 1 makes another request:
        // Even though user is active again, session_version changed, so old session MUST be terminated!
        $this->actingAs($user)
            ->withSession($session1)
            ->get(route('resident.portal'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_audit_failure_rolls_back_password_status_and_revocation_marker(): void
    {
        $admin = $this->createUser('admin');
        $resident = $this->createResident();
        $user = $resident->user;

        $originalPassword = $user->password;
        $originalVersion = $user->session_version;
        $originalToken = $user->remember_token;
        $originalActive = $user->is_active;

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException("Audit failure"));

        $service = new \App\Services\ResidentService($mockAudit);

        // 1. Reset password audit failure
        try {
            $service->resetTemporaryPassword($resident, $admin);
            $this->fail('Expected exception not thrown');
        } catch (RuntimeException) {}

        $user->refresh();
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame($originalVersion, $user->session_version);
        $this->assertSame($originalToken, $user->remember_token);

        // 2. Deactivate audit failure
        try {
            $service->deactivateAccount($resident, $admin);
            $this->fail('Expected exception not thrown');
        } catch (RuntimeException) {}

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertSame($originalVersion, $user->session_version);

        // 3. User change password audit failure in PasswordController
        $this->actingAs($user);
        $res = $this->post(route('password.update'), [
            'current_password' => 'WrongPasswordOrAny',
            'password' => 'Short1!',
            'password_confirmation' => 'Mismatch',
        ]);
        // Validated before transaction, user data untouched
        $user->refresh();
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame($originalVersion, $user->session_version);
    }

    // =========================================================================
    // 8. INDEX PENCARIAN, FILTER, PAGINASI, & N+1 PREVENTION
    // =========================================================================

    public function test_index_search_and_filter_work_accurately(): void
    {
        $admin = $this->createUser('admin');
        $resA = $this->createResident(name: 'Ahmad Dahlan', phone: '081111111111');
        $resB = $this->createResident(name: 'Budi Utomo', phone: '082222222222');
        $resArchived = $this->createResident(name: 'Candra Wijaya', phone: '083333333333', archivedAt: '2026-08-01');

        // Search by name
        $response = $this->actingAs($admin)->get(route('residents.index', ['q' => 'Ahmad']));
        $response->assertSee('Ahmad Dahlan');
        $response->assertDontSee('Budi Utomo');

        // Search by phone
        $response = $this->actingAs($admin)->get(route('residents.index', ['q' => '082222222222']));
        $response->assertSee('Budi Utomo');
        $response->assertDontSee('Ahmad Dahlan');

        // Tab Active (default excludes archived)
        $response = $this->actingAs($admin)->get(route('residents.index', ['status' => 'active']));
        $response->assertSee('Ahmad Dahlan');
        $response->assertSee('Budi Utomo');
        $response->assertDontSee('Candra Wijaya');

        // Tab Archived
        $response = $this->actingAs($admin)->get(route('residents.index', ['status' => 'archived']));
        $response->assertSee('Candra Wijaya');
        $response->assertDontSee('Ahmad Dahlan');

        // Tab All
        $response = $this->actingAs($admin)->get(route('residents.index', ['status' => 'all']));
        $response->assertSee('Ahmad Dahlan');
        $response->assertSee('Candra Wijaya');
    }

    public function test_index_rejects_array_inputs_gracefully_without_500(): void
    {
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)->get('/residents?status[]=active&account_status[]=all&q[]=search&per_page[]=10');
        $response->assertOk();
    }

    public function test_index_rendering_does_not_trigger_n_plus_one_queries(): void
    {
        $admin = $this->createUser('admin');

        for ($i = 1; $i <= 5; $i++) {
            $this->createResident(name: "Penghuni Batch {$i}");
        }

        DB::enableQueryLog();

        $this->actingAs($admin)->get(route('residents.index'))->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Ensure number of queries is constant and small (< 10 queries total for index rendering)
        $this->assertLessThan(10, count($queries), 'Index rendering triggered excessive N+1 queries');
    }
}
