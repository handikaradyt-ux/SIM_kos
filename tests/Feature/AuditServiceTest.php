<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->auditService = new AuditService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset frozen time
        parent::tearDown();
    }

    private function createAdminUser(string $email = 'admin.audit@example.test', string $name = 'Admin Audit'): User
    {
        $adminRole = Role::where('code', 'admin')->firstOrFail();

        return User::create([
            'role_id' => $adminRole->id,
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('secret123456'),
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    // =========================================================================
    // 1. Deterministic Time & UTC Storage
    // =========================================================================

    public function test_occurred_at_is_stored_in_utc_and_converted_to_jakarta_only_for_display(): void
    {
        // Freeze time deterministically to 2026-09-19 12:00:00 UTC
        $frozenUtc = Carbon::parse('2026-09-19 12:00:00', 'UTC');
        Carbon::setTestNow($frozenUtc);

        $admin = $this->createAdminUser('admin.utc@example.test');

        $log = $this->auditService->log(
            action: 'create',
            module: 'rooms',
            summary: 'Tambah kamar uji waktu',
            actor: $admin,
            entityType: 'Room',
            entityId: 101,
            entityLabel: 'Kamar K-TIME'
        );

        $freshLog = ActivityLog::findOrFail($log->id);

        // Verify stored in UTC
        $this->assertEquals('2026-09-19 12:00:00', $freshLog->occurred_at->setTimezone('UTC')->format('Y-m-d H:i:s'));

        // Verify display conversion to Asia/Jakarta (UTC+7 -> 19:00:00)
        $this->assertEquals('2026-09-19 19:00:00', $freshLog->occurredAtJakarta()->format('Y-m-d H:i:s'));
        $this->assertEquals('Asia/Jakarta', $freshLog->occurredAtJakarta()->getTimezone()->getName());
    }

    // =========================================================================
    // 2. Changes Contract & Allowlist / Sensitive Field Filtering
    // =========================================================================

    public function test_changes_adheres_to_contract_and_strictly_filters_sensitive_or_unknown_fields(): void
    {
        $admin = $this->createAdminUser('admin.changes@example.test');

        $dirtyChanges = [
            'before' => [
                'number' => 'K100',
                'monthly_rate' => 700000,
                'password' => 'secret_plain_text',
                'password_confirmation' => 'secret_plain_text',
                'remember_token' => 'leak_token_123',
                'token' => 'auth_bearer_token',
                'notes' => ['nested_leak' => 'forbidden_array_on_scalar'], // Nested array on scalar
                'unregistered_field' => 'should_be_dropped',
            ],
            'after' => [
                'number' => 'K101',
                'monthly_rate' => 800000,
                'password' => 'new_secret_leak',
                'notes' => 'Catatan kamar valid', // Valid scalar
                'cookie' => 'session_cookie_leak',
                'unregistered_field' => 'should_be_dropped',
            ],
        ];

        $log = $this->auditService->log(
            action: 'update',
            module: 'rooms',
            summary: 'Pembaruan kamar K101',
            actor: $admin,
            entityType: 'Room',
            entityId: 101,
            entityLabel: 'Kamar K101',
            changes: $dirtyChanges
        );

        $freshLog = ActivityLog::findOrFail($log->id);
        $storedChanges = $freshLog->changes;

        // Verify structure adheres to {"before": {...}, "after": {...}}
        $this->assertIsArray($storedChanges);
        $this->assertArrayHasKey('before', $storedChanges);
        $this->assertArrayHasKey('after', $storedChanges);

        // Before assertions
        $this->assertSame('K100', $storedChanges['before']['number']);
        $this->assertSame(700000, $storedChanges['before']['monthly_rate']);
        $this->assertArrayNotHasKey('password', $storedChanges['before']);
        $this->assertArrayNotHasKey('password_confirmation', $storedChanges['before']);
        $this->assertArrayNotHasKey('remember_token', $storedChanges['before']);
        $this->assertArrayNotHasKey('token', $storedChanges['before']);
        $this->assertArrayNotHasKey('notes', $storedChanges['before'], 'Nested array on scalar field must be dropped');
        $this->assertArrayNotHasKey('unregistered_field', $storedChanges['before']);

        // After assertions
        $this->assertSame('K101', $storedChanges['after']['number']);
        $this->assertSame(800000, $storedChanges['after']['monthly_rate']);
        $this->assertSame('Catatan kamar valid', $storedChanges['after']['notes']);
        $this->assertArrayNotHasKey('password', $storedChanges['after']);
        $this->assertArrayNotHasKey('cookie', $storedChanges['after']);
        $this->assertArrayNotHasKey('unregistered_field', $storedChanges['after']);
    }

    public function test_flat_changes_array_is_converted_to_after_contract(): void
    {
        $admin = $this->createAdminUser('admin.flat@example.test');

        $flatPayload = [
            'number' => 'K201',
            'monthly_rate' => 900000,
            'password' => 'secret_leak',
        ];

        $log = $this->auditService->log(
            action: 'create',
            module: 'rooms',
            summary: 'Tambah kamar flat payload',
            actor: $admin,
            entityType: 'Room',
            entityId: 201,
            entityLabel: 'Kamar K201',
            changes: $flatPayload
        );

        $freshLog = ActivityLog::findOrFail($log->id);
        $this->assertEquals([
            'after' => [
                'number' => 'K201',
                'monthly_rate' => 900000,
            ],
        ], $freshLog->changes);
    }

    // =========================================================================
    // 3. Actor Identity, Snapshot Immutability, and System Actor (TC-24 Foundation)
    // =========================================================================

    public function test_user_actor_snapshot_remains_intact_even_after_user_is_renamed(): void
    {
        $admin = $this->createAdminUser('admin.orig@example.test', 'Nama Asli Admin');

        $log = $this->auditService->log(
            action: 'create',
            module: 'rooms',
            summary: 'Tambah kamar',
            actor: $admin,
            entityType: 'Room',
            entityId: 1,
            entityLabel: 'Kamar K-SNAP'
        );

        // Rename the user in database
        $admin->update(['name' => 'Nama Baru Admin Yang Berubah']);

        $freshLog = ActivityLog::findOrFail($log->id);

        // Snapshot MUST NOT change
        $this->assertSame('Nama Asli Admin', $freshLog->actor_name);
        $this->assertSame($admin->id, $freshLog->actor_id);

        // BelongsTo relation reflects the current updated record
        $this->assertSame('Nama Baru Admin Yang Berubah', $freshLog->actor->name);
    }

    public function test_system_actor_is_explicitly_recorded_with_null_actor_id_and_sistem_label(): void
    {
        $log = $this->auditService->logSystem(
            action: 'archive',
            module: 'rooms',
            summary: 'Arsip kamar otomatis oleh sistem',
            entityType: 'Room',
            entityId: 5,
            entityLabel: 'Kamar K-SYS',
            changes: [
                'before' => ['archived_at' => null],
                'after' => ['archived_at' => '2026-09-19 00:00:00'],
            ]
        );

        $freshLog = ActivityLog::findOrFail($log->id);
        $this->assertNull($freshLog->actor_id);
        $this->assertSame('Sistem', $freshLog->actor_name);
        $this->assertSame('archive', $freshLog->action);
        $this->assertSame('rooms', $freshLog->module);
        $this->assertEquals(['archived_at' => null], $freshLog->changes['before']);
        $this->assertEquals(['archived_at' => '2026-09-19 00:00:00'], $freshLog->changes['after']);
    }

    public function test_system_actor_accepts_custom_system_component_name(): void
    {
        $log = $this->auditService->logSystem(
            action: 'sync',
            module: 'invoices',
            summary: 'Sinkronisasi tagihan bulanan otomatis',
            systemName: 'Sistem: Billing Scheduler'
        );

        $freshLog = ActivityLog::findOrFail($log->id);
        $this->assertNull($freshLog->actor_id);
        $this->assertSame('Sistem: Billing Scheduler', $freshLog->actor_name);
    }

    // =========================================================================
    // 4. Transactional Integration & Rollback (TC-32 Foundation)
    // =========================================================================

    public function test_business_mutation_and_audit_are_both_committed_in_successful_transaction(): void
    {
        $admin = $this->createAdminUser('admin.tx.ok@example.test');

        $room = DB::transaction(function () use ($admin) {
            $createdRoom = Room::create([
                'number' => 'R-TX-SUCCESS',
                'type' => 'Standard',
                'monthly_rate' => 850000,
                'notes' => 'Kamar sukses transaksi',
            ]);

            $this->auditService->log(
                action: 'create',
                module: 'rooms',
                summary: "Menambahkan kamar {$createdRoom->number}",
                actor: $admin,
                entityType: 'Room',
                entityId: $createdRoom->id,
                entityLabel: "Kamar {$createdRoom->number}",
                changes: [
                    'after' => [
                        'number' => $createdRoom->number,
                        'monthly_rate' => $createdRoom->monthly_rate,
                    ],
                ]
            );

            return $createdRoom;
        });

        // Both mutation and audit exist in database
        $this->assertDatabaseHas('rooms', ['number' => 'R-TX-SUCCESS']);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'rooms',
            'action' => 'create',
            'entity_id' => $room->id,
            'entity_label' => 'Kamar R-TX-SUCCESS',
            'actor_name' => $admin->name,
        ]);
    }

    public function test_business_mutation_rolls_back_when_real_database_audit_insert_fails(): void
    {
        $admin = $this->createAdminUser('admin.tx.fail@example.test');
        $roomNumber = 'R-TX-FAIL-AUDIT';

        $exceptionCaught = false;

        try {
            DB::transaction(function () use ($admin, $roomNumber) {
                // 1. Business mutation executes successfully in transaction
                $createdRoom = Room::create([
                    'number' => $roomNumber,
                    'type' => 'Deluxe',
                    'monthly_rate' => 1200000,
                ]);

                // 2. Audit insert genuinely FAILS at the MySQL database level:
                // Column 'action' is VARCHAR(50). Passing 70 characters triggers real MySQL error 1406 (Data too long)
                $this->auditService->log(
                    action: str_repeat('ILLEGAL_ACTION_LENGTH_', 4), // 88 characters > 50
                    module: 'rooms',
                    summary: "Menambahkan kamar {$createdRoom->number}",
                    actor: $admin,
                    entityType: 'Room',
                    entityId: $createdRoom->id,
                    entityLabel: "Kamar {$createdRoom->number}"
                );
            });
        } catch (QueryException $e) {
            $exceptionCaught = true;
            // Verify it was a real database error, not a mock
            $this->assertStringContainsString('Data too long', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught, 'Expected real MySQL QueryException to be thrown');

        // CRITICAL: Verify the business mutation was completely ROLLED BACK
        $this->assertDatabaseMissing('rooms', ['number' => $roomNumber]);
        $this->assertDatabaseMissing('activity_logs', ['entity_label' => "Kamar {$roomNumber}"]);
    }

    public function test_both_mutation_and_audit_roll_back_when_subsequent_operation_in_transaction_fails(): void
    {
        $admin = $this->createAdminUser('admin.tx.after@example.test');
        $roomNumber = 'R-TX-SUBSEQUENT-FAIL';

        $exceptionCaught = false;

        try {
            DB::transaction(function () use ($admin, $roomNumber) {
                // 1. Business mutation succeeds
                $createdRoom = Room::create([
                    'number' => $roomNumber,
                    'type' => 'Standard',
                    'monthly_rate' => 900000,
                ]);

                // 2. Audit log succeeds
                $this->auditService->log(
                    action: 'create',
                    module: 'rooms',
                    summary: "Menambahkan kamar {$createdRoom->number}",
                    actor: $admin,
                    entityType: 'Room',
                    entityId: $createdRoom->id,
                    entityLabel: "Kamar {$createdRoom->number}"
                );

                // 3. Subsequent operation in the same transaction fails
                throw new \RuntimeException('Simulasi kegagalan operasi lanjutan dalam transaksi');
            });
        } catch (\RuntimeException $e) {
            $exceptionCaught = true;
            $this->assertSame('Simulasi kegagalan operasi lanjutan dalam transaksi', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught, 'Expected RuntimeException to be caught');

        // Both mutation and audit must be rolled back
        $this->assertDatabaseMissing('rooms', ['number' => $roomNumber]);
        $this->assertDatabaseMissing('activity_logs', ['entity_label' => "Kamar {$roomNumber}"]);
    }

    public function test_no_audit_log_created_when_business_mutation_fails_before_audit(): void
    {
        $admin = $this->createAdminUser('admin.tx.pre@example.test');
        $initialLogCount = ActivityLog::count();

        $exceptionCaught = false;

        try {
            DB::transaction(function () use ($admin) {
                // Illegal business mutation: rate <= 0 triggers chk_rooms_monthly_rate
                Room::create([
                    'number' => 'R-TX-PRE-FAIL',
                    'type' => 'Standard',
                    'monthly_rate' => -50000, // ILLEGAL
                ]);

                // This audit call is never reached
                $this->auditService->log(
                    action: 'create',
                    module: 'rooms',
                    summary: 'Tambah kamar',
                    actor: $admin
                );
            });
        } catch (QueryException $e) {
            $exceptionCaught = true;
            $this->assertSame(3819, $e->errorInfo[1] ?? null);
        }

        $this->assertTrue($exceptionCaught, 'Expected CHECK constraint violation');

        // 1. Verify business mutation is NOT saved
        $this->assertDatabaseMissing('rooms', ['number' => 'R-TX-PRE-FAIL']);

        // 2. Verify total activity_logs count remains strictly identical before and after
        $this->assertSame($initialLogCount, ActivityLog::count(), 'Activity log count must remain unchanged when mutation fails');
    }
}
