<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Complaint;
use App\Models\ComplaintUpdate;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Placement;
use App\Models\Resident;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConstraintTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles so valid tests can run reliably
        $this->seed(RoleSeeder::class);
    }

    /**
     * Helper to assert a QueryException is specifically a CHECK constraint violation (MySQL 3819).
     * Strictly verifies MySQL error code 3819 and the exact constraint name.
     * Never accepts generic 'CONSTRAINT' keyword as an alternative.
     */
    protected function assertCheckConstraintFails(callable $callback, string $expectedConstraintName): void
    {
        if (strtoupper($expectedConstraintName) === 'CONSTRAINT') {
            throw new \InvalidArgumentException("Generic 'CONSTRAINT' name is not permitted. Specify the exact constraint name.");
        }

        try {
            $callback();
            $this->fail("Expected CHECK constraint '{$expectedConstraintName}' violation, but operation succeeded.");
        } catch (QueryException $e) {
            $errorCode = $e->errorInfo[1] ?? null;
            $message = $e->getMessage();

            // 1. Verify MySQL error code is specifically 3819 (CHECK constraint violation)
            $this->assertSame(
                3819,
                $errorCode,
                "Expected CHECK constraint violation (MySQL error 3819), got [{$errorCode}]: {$message}"
            );

            // 2. Verify exact constraint name is present in MySQL error message
            $this->assertStringContainsString(
                $expectedConstraintName,
                $message,
                "Expected exact constraint name '{$expectedConstraintName}' in MySQL error message, got: {$message}"
            );
        }
    }

    /**
     * Helper to assert a QueryException is specifically a FOREIGN KEY insert/update failure (MySQL 1452).
     */
    protected function assertForeignKeyFails(callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected FOREIGN KEY constraint violation, but operation succeeded.");
        } catch (QueryException $e) {
            $errorCode = $e->errorInfo[1] ?? null;
            $message = $e->getMessage();
            $this->assertSame(
                1452,
                $errorCode,
                "Expected foreign key constraint failure (MySQL error 1452), got [{$errorCode}]: {$message}"
            );
        }
    }

    /**
     * Helper to assert a QueryException is specifically a FOREIGN KEY RESTRICT deletion failure (MySQL 1451).
     */
    protected function assertForeignKeyDeleteRestricted(callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected FOREIGN KEY delete RESTRICT violation, but operation succeeded.");
        } catch (QueryException $e) {
            $errorCode = $e->errorInfo[1] ?? null;
            $message = $e->getMessage();
            $this->assertSame(
                1451,
                $errorCode,
                "Expected foreign key RESTRICT failure on parent delete (MySQL error 1451), got [{$errorCode}]: {$message}"
            );
        }
    }

    /**
     * Helper to assert a QueryException is specifically a UNIQUE constraint violation (MySQL 1062).
     */
    protected function assertUniqueConstraintFails(callable $callback, ?string $expectedKeyName = null): void
    {
        try {
            $callback();
            $this->fail("Expected UNIQUE constraint violation, but operation succeeded.");
        } catch (QueryException $e) {
            $errorCode = $e->errorInfo[1] ?? null;
            $message = $e->getMessage();
            $this->assertSame(
                1062,
                $errorCode,
                "Expected duplicate entry failure (MySQL error 1062), got [{$errorCode}]: {$message}"
            );
            if ($expectedKeyName) {
                $this->assertStringContainsString(
                    $expectedKeyName,
                    $message,
                    "Expected key '{$expectedKeyName}' in duplicate error message."
                );
            }
        }
    }

    private function createAdminUser(string $email = 'admin.test@example.test'): User
    {
        $adminRole = Role::where('code', 'admin')->firstOrFail();

        return User::create([
            'role_id' => $adminRole->id,
            'name' => 'Admin Test',
            'email' => $email,
            'password' => bcrypt('secret123456'),
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function createResidentUserAndProfile(string $email = 'resident.test@example.test', string $phone = '081234567890'): array
    {
        $residentRole = Role::where('code', 'resident')->firstOrFail();
        $user = User::create([
            'role_id' => $residentRole->id,
            'name' => 'Resident Test',
            'email' => $email,
            'password' => bcrypt('secret123456'),
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $resident = Resident::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'phone' => $phone,
            'origin_address' => 'Jl. Asal No. 123',
        ]);

        return [$user, $resident];
    }

    private function createRoom(string $number = 'K101', int $monthlyRate = 800000): Room
    {
        return Room::create([
            'number' => $number,
            'type' => 'Standard',
            'monthly_rate' => $monthlyRate,
            'notes' => 'Kamar uji',
        ]);
    }

    // =========================================================================
    // 1. Connection and Safety Verification
    // =========================================================================

    public function test_01_database_guard_and_actual_connection_is_sim_kos_test(): void
    {
        $result = DB::select('SELECT DATABASE() as db');
        $this->assertNotEmpty($result);
        $this->assertEquals('sim_kos_test', $result[0]->db);
        $this->assertEquals('mysql', config('database.default'));
    }

    // =========================================================================
    // 2. Roles, CHECK constraint, and Idempotent Seeder
    // =========================================================================

    public function test_02_role_code_check_constraint_rejects_invalid_role(): void
    {
        $this->assertCheckConstraintFails(function () {
            DB::table('roles')->insert([
                'code' => 'superadmin',
                'name' => 'Super Admin Ilegal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_roles_code');
    }

    /**
     * Requirement 1: Penolakan duplikasi roles.code
     */
    public function test_03_roles_code_unique_constraint_rejects_duplicate_code(): void
    {
        // Role 'admin' already seeded by RoleSeeder
        $this->assertDatabaseHas('roles', ['code' => 'admin']);

        // Attempt duplicate roles.code
        $this->assertUniqueConstraintFails(function () {
            DB::table('roles')->insert([
                'code' => 'admin',
                'name' => 'Administrator Duplikat',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'roles_code_unique');
    }

    public function test_04_role_seeder_is_idempotent_and_creates_exactly_three_roles(): void
    {
        // Run seeder twice
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $roles = Role::orderBy('id')->get();
        $this->assertCount(3, $roles);
        $this->assertEquals(['admin', 'owner', 'resident'], $roles->pluck('code')->all());
    }

    // =========================================================================
    // 3. Resident user_id uniqueness
    // =========================================================================

    /**
     * Requirement 2: Penolakan duplikasi residents.user_id
     */
    public function test_05_residents_user_id_unique_constraint_rejects_duplicate_user(): void
    {
        [$user, $resident] = $this->createResidentUserAndProfile('res.dup.user@example.test', '081211112222');
        $this->assertDatabaseHas('residents', ['user_id' => $user->id]);

        // Attempt duplicate residents.user_id with a second resident record
        $this->assertUniqueConstraintFails(function () use ($user) {
            Resident::create([
                'user_id' => $user->id,
                'name' => 'Resident Duplikat User ID',
                'phone' => '081233334444',
                'origin_address' => 'Alamat Berbeda',
            ]);
        }, 'residents_user_id_unique');
    }

    // =========================================================================
    // 4. Payment receipt_number uniqueness across DIFFERENT invoices
    // =========================================================================

    /**
     * Requirement 3: Penolakan duplikasi payments.receipt_number.
     * Gunakan invoice berbeda agar kegagalan benar-benar berasal dari nomor bukti yang duplikat
     * (bukan collision generated column valid_invoice_id).
     */
    public function test_06_payments_receipt_number_unique_constraint_rejects_duplicate_across_different_invoices(): void
    {
        $admin = $this->createAdminUser('admin.rec.dup@example.test');

        // Invoice 1: Resident 1 in Room 1
        [$user1, $res1] = $this->createResidentUserAndProfile('res1.rec@example.test', '081255551111');
        $room1 = $this->createRoom('ROOM-REC-1');
        $placement1 = Placement::create([
            'resident_id' => $res1->id,
            'room_id' => $room1->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);
        $invoice1 = Invoice::create([
            'placement_id' => $placement1->id,
            'period_month' => '2026-09-01',
            'due_on' => '2026-09-05',
            'amount' => 800000,
            'resident_name_snapshot' => $res1->name,
            'room_number_snapshot' => $room1->number,
            'created_by' => $admin->id,
        ]);

        // Invoice 2: Resident 2 in Room 2 (DIFFERENT invoice)
        [$user2, $res2] = $this->createResidentUserAndProfile('res2.rec@example.test', '081255552222');
        $room2 = $this->createRoom('ROOM-REC-2');
        $placement2 = Placement::create([
            'resident_id' => $res2->id,
            'room_id' => $room2->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 900000,
            'created_by' => $admin->id,
        ]);
        $invoice2 = Invoice::create([
            'placement_id' => $placement2->id,
            'period_month' => '2026-09-01',
            'due_on' => '2026-09-05',
            'amount' => 900000,
            'resident_name_snapshot' => $res2->name,
            'room_number_snapshot' => $room2->number,
            'created_by' => $admin->id,
        ]);

        // Payment 1 for Invoice 1
        Payment::create([
            'invoice_id' => $invoice1->id,
            'receipt_number' => 'RECEIPT-ISOLATED-DUP-001',
            'amount' => 800000,
            'paid_on' => '2026-09-02',
            'method' => 'cash',
            'status' => 'valid',
            'recorded_by' => $admin->id,
        ]);

        // Payment 2 for Invoice 2 with DUPLICATE receipt_number
        // Because invoice_id is different ($invoice2->id != $invoice1->id),
        // payments_valid_invoice_id_unique is NOT violated.
        // Failure is purely and strictly caused by payments_receipt_number_unique.
        $this->assertUniqueConstraintFails(function () use ($invoice2, $admin) {
            Payment::create([
                'invoice_id' => $invoice2->id,
                'receipt_number' => 'RECEIPT-ISOLATED-DUP-001',
                'amount' => 900000,
                'paid_on' => '2026-09-03',
                'method' => 'transfer',
                'status' => 'valid',
                'recorded_by' => $admin->id,
            ]);
        }, 'payments_receipt_number_unique');
    }

    // =========================================================================
    // 5. Payment invalid foreign key (non-existent invoice_id)
    // =========================================================================

    /**
     * Requirement 4: Penolakan payments.invoice_id yang tidak ada, dengan field lain valid.
     */
    public function test_07_payments_foreign_key_rejects_nonexistent_invoice_id_with_other_fields_valid(): void
    {
        $admin = $this->createAdminUser('admin.pay.fk@example.test');

        // Non-existent invoice_id with all other fields strictly valid
        $this->assertForeignKeyFails(function () use ($admin) {
            Payment::create([
                'invoice_id' => 999999, // Non-existent invoice
                'receipt_number' => 'PAY-REC-VALID-FIELDS-001', // Valid & unique receipt
                'amount' => 850000, // Valid positive amount
                'paid_on' => '2026-09-02', // Valid date
                'method' => 'cash', // Valid method
                'status' => 'valid', // Valid status
                'recorded_by' => $admin->id, // Valid existing user
            ]);
        });
    }

    // =========================================================================
    // 6. Foreign Key RESTRICT on Referenced Parents
    // =========================================================================

    /**
     * Requirement 5: Penolakan penghapusan parent yang masih direferensikan untuk
     * membuktikan RESTRICT menjaga histori.
     */
    public function test_08_foreign_key_restrict_prevents_deleting_referenced_parents_preserving_history(): void
    {
        $admin = $this->createAdminUser('admin.hist.restrict@example.test');
        [$user, $resident] = $this->createResidentUserAndProfile('res.hist.restrict@example.test', '08129999988');
        $room = $this->createRoom('ROOM-HIST-RESTRICT');
        $placement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);
        $invoice = Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-09-01',
            'due_on' => '2026-09-05',
            'amount' => 800000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'PAY-HIST-RESTRICT-001',
            'amount' => 800000,
            'paid_on' => '2026-09-02',
            'method' => 'cash',
            'status' => 'valid',
            'recorded_by' => $admin->id,
        ]);

        // 1. Cannot delete Room when referenced by Placement (history preserved)
        $this->assertForeignKeyDeleteRestricted(function () use ($room) {
            DB::table('rooms')->where('id', $room->id)->delete();
        });

        // 2. Cannot delete Resident when referenced by Placement (history preserved)
        $this->assertForeignKeyDeleteRestricted(function () use ($resident) {
            DB::table('residents')->where('id', $resident->id)->delete();
        });

        // 3. Cannot delete User when referenced by Resident or recorded_by (history preserved)
        $this->assertForeignKeyDeleteRestricted(function () use ($user) {
            DB::table('users')->where('id', $user->id)->delete();
        });
        $this->assertForeignKeyDeleteRestricted(function () use ($admin) {
            DB::table('users')->where('id', $admin->id)->delete();
        });

        // 4. Cannot delete Placement when referenced by Invoice (history preserved)
        $this->assertForeignKeyDeleteRestricted(function () use ($placement) {
            DB::table('placements')->where('id', $placement->id)->delete();
        });

        // 5. Cannot delete Invoice when referenced by Payment (history preserved)
        $this->assertForeignKeyDeleteRestricted(function () use ($invoice) {
            DB::table('invoices')->where('id', $invoice->id)->delete();
        });

        // 6. Cannot delete Role when referenced by User (history preserved)
        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $this->assertForeignKeyDeleteRestricted(function () use ($adminRole) {
            DB::table('roles')->where('id', $adminRole->id)->delete();
        });
    }

    // =========================================================================
    // 7. General Foreign Key Constraints
    // =========================================================================

    public function test_09_foreign_key_constraints_reject_invalid_insert_references(): void
    {
        // User with non-existent role_id
        $this->assertForeignKeyFails(function () {
            User::create([
                'role_id' => 99999,
                'name' => 'Invalid User',
                'email' => 'invalid.user@example.test',
                'password' => bcrypt('password'),
            ]);
        });

        // Resident with non-existent user_id
        $this->assertForeignKeyFails(function () {
            Resident::create([
                'user_id' => 99999,
                'name' => 'Invalid Resident',
                'phone' => '081234567890',
                'origin_address' => 'Alamat',
            ]);
        });

        // Facility with non-existent room_id
        $this->assertForeignKeyFails(function () {
            Facility::create([
                'code' => 'FAC-INV-FK',
                'name' => 'AC Rusak',
                'location_type' => 'room',
                'room_id' => 99999,
                'condition' => 'good',
            ]);
        });

        // Placement with non-existent resident_id
        $admin = $this->createAdminUser('admin.fk.gen@example.test');
        $room = $this->createRoom('R-FK-GEN', 900000);
        $this->assertForeignKeyFails(function () use ($admin, $room) {
            Placement::create([
                'resident_id' => 99999,
                'room_id' => $room->id,
                'started_on' => '2026-09-01',
                'agreed_monthly_rate' => 900000,
                'created_by' => $admin->id,
            ]);
        });
    }

    // =========================================================================
    // 8. General Unique Constraints
    // =========================================================================

    public function test_10_unique_constraints_reject_duplicate_user_room_facility_and_invoice_period(): void
    {
        // Duplicate user email
        $this->createAdminUser('duplicate.email@example.test');
        $this->assertUniqueConstraintFails(function () {
            $this->createAdminUser('duplicate.email@example.test');
        }, 'users_email_unique');

        // Duplicate room number
        $this->createRoom('ROOM-DUP-1');
        $this->assertUniqueConstraintFails(function () {
            $this->createRoom('ROOM-DUP-1');
        }, 'rooms_number_unique');

        // Duplicate facility code
        Facility::create([
            'code' => 'FAC-DUP-1',
            'name' => 'Dapur Bersama',
            'location_type' => 'shared',
            'area_name' => 'Lantai 1',
            'condition' => 'good',
        ]);
        $this->assertUniqueConstraintFails(function () {
            Facility::create([
                'code' => 'FAC-DUP-1',
                'name' => 'Dapur Duplikat',
                'location_type' => 'shared',
                'area_name' => 'Lantai 2',
                'condition' => 'good',
            ]);
        }, 'facilities_code_unique');

        // Duplicate composite (placement_id, period_month) on invoices
        $admin = $this->createAdminUser('admin.inv.dup@example.test');
        [$user, $resident] = $this->createResidentUserAndProfile('res.inv.dup@example.test', '08129999991');
        $room = $this->createRoom('ROOM-INV-DUP');
        $placement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-09-01',
            'due_on' => '2026-09-05',
            'amount' => 800000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        $this->assertUniqueConstraintFails(function () use ($placement, $resident, $room, $admin) {
            Invoice::create([
                'placement_id' => $placement->id,
                'period_month' => '2026-09-01',
                'due_on' => '2026-09-05',
                'amount' => 800000,
                'resident_name_snapshot' => $resident->name,
                'room_number_snapshot' => $room->number,
                'created_by' => $admin->id,
            ]);
        }, 'invoices_placement_id_period_month_unique');
    }

    // =========================================================================
    // 9. CHECK Constraints (MySQL error code 3819 and exact constraint names)
    // =========================================================================

    public function test_11_check_constraints_reject_invalid_business_data_with_mysql_3819(): void
    {
        // 1. Room monthly_rate <= 0 -> chk_rooms_monthly_rate
        $this->assertCheckConstraintFails(function () {
            DB::table('rooms')->insert([
                'number' => 'R-NEG-RATE',
                'type' => 'Standard',
                'monthly_rate' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_rooms_monthly_rate');

        // 2. Facility invalid location_type violates chk_facilities_location_rule
        $this->assertCheckConstraintFails(function () {
            DB::table('facilities')->insert([
                'code' => 'FAC-INV-TYPE',
                'name' => 'Fasilitas',
                'location_type' => 'corridor',
                'area_name' => 'Lantai 1',
                'condition' => 'good',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_facilities_location_rule');

        // 3. Facility condition invalid -> chk_facilities_condition
        $this->assertCheckConstraintFails(function () {
            DB::table('facilities')->insert([
                'code' => 'FAC-INV-COND',
                'name' => 'Fasilitas',
                'location_type' => 'shared',
                'area_name' => 'Area Depan',
                'condition' => 'ancient',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_facilities_condition');

        // 4. Facility location rule violation (location_type='room' but room_id IS NULL) -> chk_facilities_location_rule
        $this->assertCheckConstraintFails(function () {
            DB::table('facilities')->insert([
                'code' => 'FAC-ROOM-NO-ID',
                'name' => 'Kasur Tanpa Kamar',
                'location_type' => 'room',
                'room_id' => null,
                'area_name' => null,
                'condition' => 'good',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_facilities_location_rule');

        // 5. Placement agreed_monthly_rate <= 0 -> chk_placements_agreed_rate
        $admin = $this->createAdminUser('admin.chk.biz@example.test');
        [$user, $resident] = $this->createResidentUserAndProfile('res.chk.biz@example.test', '08129999922');
        $room = $this->createRoom('ROOM-CHK-BIZ');

        $this->assertCheckConstraintFails(function () use ($admin, $resident, $room) {
            DB::table('placements')->insert([
                'resident_id' => $resident->id,
                'room_id' => $room->id,
                'started_on' => '2026-09-01',
                'agreed_monthly_rate' => -5000,
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_placements_agreed_rate');

        // 6. Placement ended_on < started_on -> chk_placements_date_range
        $this->assertCheckConstraintFails(function () use ($admin, $resident, $room) {
            DB::table('placements')->insert([
                'resident_id' => $resident->id,
                'room_id' => $room->id,
                'started_on' => '2026-09-10',
                'ended_on' => '2026-09-05',
                'agreed_monthly_rate' => 800000,
                'created_by' => $admin->id,
                'ended_by' => $admin->id,
                'end_reason' => 'Selesai',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_placements_date_range');

        // 7. Placement end metadata incomplete (ended_on filled, but end_reason null) -> chk_placements_end_metadata
        $this->assertCheckConstraintFails(function () use ($admin, $resident, $room) {
            DB::table('placements')->insert([
                'resident_id' => $resident->id,
                'room_id' => $room->id,
                'started_on' => '2026-09-01',
                'ended_on' => '2026-09-10',
                'agreed_monthly_rate' => 800000,
                'created_by' => $admin->id,
                'ended_by' => $admin->id,
                'end_reason' => null, // INCOMPLETE
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_placements_end_metadata');

        // 8. Invoice amount <= 0 -> chk_invoices_amount
        $validPlacement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        $this->assertCheckConstraintFails(function () use ($validPlacement, $resident, $room, $admin) {
            DB::table('invoices')->insert([
                'placement_id' => $validPlacement->id,
                'period_month' => '2026-09-01',
                'due_on' => '2026-09-05',
                'amount' => 0, // MUST BE > 0
                'resident_name_snapshot' => $resident->name,
                'room_number_snapshot' => $room->number,
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_invoices_amount');

        // 9. Invoice period_month not 1st of month -> chk_invoices_period_month
        $this->assertCheckConstraintFails(function () use ($validPlacement, $resident, $room, $admin) {
            DB::table('invoices')->insert([
                'placement_id' => $validPlacement->id,
                'period_month' => '2026-09-15', // NOT DAY 1
                'due_on' => '2026-09-20',
                'amount' => 800000,
                'resident_name_snapshot' => $resident->name,
                'room_number_snapshot' => $room->number,
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_invoices_period_month');

        // 10. Payment amount <= 0 -> chk_payments_amount
        $invoice = Invoice::create([
            'placement_id' => $validPlacement->id,
            'period_month' => '2026-09-01',
            'due_on' => '2026-09-05',
            'amount' => 800000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        $this->assertCheckConstraintFails(function () use ($invoice, $admin) {
            DB::table('payments')->insert([
                'invoice_id' => $invoice->id,
                'receipt_number' => 'PAY-CHK-ZERO-AMOUNT',
                'amount' => 0, // MUST BE > 0
                'paid_on' => '2026-09-03',
                'method' => 'cash',
                'status' => 'valid',
                'recorded_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_payments_amount');

        // 11. Payment method invalid -> chk_payments_method
        $this->assertCheckConstraintFails(function () use ($invoice, $admin) {
            DB::table('payments')->insert([
                'invoice_id' => $invoice->id,
                'receipt_number' => 'PAY-CHK-BAD-METHOD',
                'amount' => 800000,
                'paid_on' => '2026-09-03',
                'method' => 'crypto', // ILLEGAL METHOD
                'status' => 'valid',
                'recorded_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_payments_method');

        // 12. Payment invalid status -> chk_payments_status
        $this->assertCheckConstraintFails(function () use ($invoice, $admin) {
            DB::table('payments')->insert([
                'invoice_id' => $invoice->id,
                'receipt_number' => 'PAY-INV-STATUS',
                'amount' => 800000,
                'paid_on' => '2026-09-03',
                'method' => 'cash',
                'status' => 'pending', // ILLEGAL STATUS
                'recorded_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_payments_status');

        // 13. Payment void metadata incomplete (status='void' but void_reason is null) -> chk_payments_void_metadata
        $this->assertCheckConstraintFails(function () use ($invoice, $admin) {
            DB::table('payments')->insert([
                'invoice_id' => $invoice->id,
                'receipt_number' => 'PAY-INV-VOID-META',
                'amount' => 800000,
                'paid_on' => '2026-09-03',
                'method' => 'cash',
                'status' => 'void',
                'recorded_by' => $admin->id,
                'voided_by' => $admin->id,
                'voided_at' => now(),
                'void_reason' => null, // INCOMPLETE
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_payments_void_metadata');

        // 14. Complaint status invalid (violates chk_complaints_closed_metadata)
        $this->assertCheckConstraintFails(function () use ($validPlacement, $admin) {
            DB::table('complaints')->insert([
                'placement_id' => $validPlacement->id,
                'subject' => 'Lampu Mati',
                'description' => 'Lampu kamar mandi mati total',
                'status' => 'draft', // ILLEGAL STATUS
                'submitted_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_complaints_closed_metadata');

        // 15. Complaint closed metadata violation (status='resolved' but closed_at is null) -> chk_complaints_closed_metadata
        $this->assertCheckConstraintFails(function () use ($validPlacement, $admin) {
            DB::table('complaints')->insert([
                'placement_id' => $validPlacement->id,
                'subject' => 'Lampu Mati',
                'description' => 'Lampu kamar mandi mati total',
                'status' => 'resolved',
                'submitted_by' => $admin->id,
                'closed_at' => null, // MUST BE NOT NULL FOR RESOLVED
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_complaints_closed_metadata');

        // 16. ComplaintUpdate to_status invalid -> chk_complaint_updates_to_status
        $complaint = Complaint::create([
            'placement_id' => $validPlacement->id,
            'subject' => 'AC Bocor',
            'description' => 'AC menetes air terus menerus',
            'status' => 'open',
            'submitted_by' => $admin->id,
        ]);

        $this->assertCheckConstraintFails(function () use ($complaint, $admin) {
            DB::table('complaint_updates')->insert([
                'complaint_id' => $complaint->id,
                'actor_id' => $admin->id,
                'from_status' => 'open',
                'to_status' => 'unknown_status', // ILLEGAL
                'note' => 'Status tidak dikenal',
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'chk_complaint_updates_to_status');
    }

    // =========================================================================
    // 10. Generated Unique Column: Placements (Active Room / Active Resident)
    // =========================================================================

    public function test_12_generated_unique_column_prevents_duplicate_active_placement_per_room_and_per_resident(): void
    {
        $admin = $this->createAdminUser('admin.act.room@example.test');
        [$user1, $resident1] = $this->createResidentUserAndProfile('res1.act@example.test', '08129999993');
        [$user2, $resident2] = $this->createResidentUserAndProfile('res2.act@example.test', '08129999994');
        $room1 = $this->createRoom('ROOM-ACT-1');
        $room2 = $this->createRoom('ROOM-ACT-2');

        // Active placement 1: resident1 in room1
        Placement::create([
            'resident_id' => $resident1->id,
            'room_id' => $room1->id,
            'started_on' => '2026-09-01',
            'ended_on' => null,
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        // Attempt second active placement on SAME room (room1) with resident2 -> Must FAIL
        $this->assertUniqueConstraintFails(function () use ($resident2, $room1, $admin) {
            Placement::create([
                'resident_id' => $resident2->id,
                'room_id' => $room1->id,
                'started_on' => '2026-09-10',
                'ended_on' => null,
                'agreed_monthly_rate' => 800000,
                'created_by' => $admin->id,
            ]);
        }, 'placements_active_room_id_unique');

        // Attempt second active placement for SAME resident (resident1) in room2 -> Must FAIL
        $this->assertUniqueConstraintFails(function () use ($resident1, $room2, $admin) {
            Placement::create([
                'resident_id' => $resident1->id,
                'room_id' => $room2->id,
                'started_on' => '2026-09-10',
                'ended_on' => null,
                'agreed_monthly_rate' => 900000,
                'created_by' => $admin->id,
            ]);
        }, 'placements_active_resident_id_unique');
    }

    public function test_13_generated_unique_column_allows_multiple_ended_placements(): void
    {
        $admin = $this->createAdminUser('admin.ended@example.test');
        [$user1, $resident1] = $this->createResidentUserAndProfile('res1.ended@example.test', '08129999995');
        [$user2, $resident2] = $this->createResidentUserAndProfile('res2.ended@example.test', '08129999996');
        $room = $this->createRoom('ROOM-HIST-1');

        // First ended placement: resident1 in room (July)
        $p1 = Placement::create([
            'resident_id' => $resident1->id,
            'room_id' => $room->id,
            'started_on' => '2026-07-01',
            'ended_on' => '2026-07-31',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
            'ended_by' => $admin->id,
            'end_reason' => 'Kontrak habis',
        ]);

        // Second ended placement: resident2 in SAME room (August)
        $p2 = Placement::create([
            'resident_id' => $resident2->id,
            'room_id' => $room->id,
            'started_on' => '2026-08-01',
            'ended_on' => '2026-08-31',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
            'ended_by' => $admin->id,
            'end_reason' => 'Pindah dinas',
        ]);

        // Third: Active placement in SAME room (September)
        $p3 = Placement::create([
            'resident_id' => $resident1->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'ended_on' => null,
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('placements', ['id' => $p1->id, 'ended_on' => '2026-07-31']);
        $this->assertDatabaseHas('placements', ['id' => $p2->id, 'ended_on' => '2026-08-31']);
        $this->assertDatabaseHas('placements', ['id' => $p3->id, 'ended_on' => null]);
    }

    public function test_14_generated_unique_column_update_prevents_reactivating_to_colliding_active_placement(): void
    {
        $admin = $this->createAdminUser('admin.upd.act@example.test');
        [$user1, $resident1] = $this->createResidentUserAndProfile('res1.upd@example.test', '08129999997');
        [$user2, $resident2] = $this->createResidentUserAndProfile('res2.upd@example.test', '08129999998');
        $room = $this->createRoom('ROOM-UPD-ACT');

        // Past ended placement
        $pastPlacement = Placement::create([
            'resident_id' => $resident1->id,
            'room_id' => $room->id,
            'started_on' => '2026-08-01',
            'ended_on' => '2026-08-31',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
            'ended_by' => $admin->id,
            'end_reason' => 'Selesai',
        ]);

        // Current active placement
        $currentPlacement = Placement::create([
            'resident_id' => $resident2->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'ended_on' => null,
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        // Try to UPDATE past placement back to active (ended_on = NULL) -> Must FAIL with unique collision!
        $this->assertUniqueConstraintFails(function () use ($pastPlacement) {
            DB::table('placements')->where('id', $pastPlacement->id)->update([
                'ended_on' => null,
                'ended_by' => null,
                'end_reason' => null,
            ]);
        }, 'placements_active_room_id_unique');
    }

    // =========================================================================
    // 11. Generated Unique Column: Payments (Valid Invoice ID)
    // =========================================================================

    public function test_15_generated_unique_column_prevents_duplicate_valid_payment_per_invoice(): void
    {
        $admin = $this->createAdminUser('admin.pay.dup@example.test');
        [$user, $resident] = $this->createResidentUserAndProfile('res.pay.dup@example.test', '08129999999');
        $room = $this->createRoom('ROOM-PAY-DUP');
        $placement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);
        $invoice = Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-09-01',
            'due_on' => '2026-09-05',
            'amount' => 800000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        // Valid Payment 1
        Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'PAY-VALID-001',
            'amount' => 800000,
            'paid_on' => '2026-09-03',
            'method' => 'cash',
            'status' => 'valid',
            'recorded_by' => $admin->id,
        ]);

        // Attempt second valid payment on same invoice -> Must FAIL
        $this->assertUniqueConstraintFails(function () use ($invoice, $admin) {
            Payment::create([
                'invoice_id' => $invoice->id,
                'receipt_number' => 'PAY-VALID-002',
                'amount' => 800000,
                'paid_on' => '2026-09-03',
                'method' => 'transfer',
                'status' => 'valid',
                'recorded_by' => $admin->id,
            ]);
        }, 'payments_valid_invoice_id_unique');
    }

    public function test_16_generated_unique_column_allows_multiple_void_payments(): void
    {
        $admin = $this->createAdminUser('admin.pay.void@example.test');
        [$user, $resident] = $this->createResidentUserAndProfile('res.pay.void@example.test', '08129999900');
        $room = $this->createRoom('ROOM-PAY-VOID');
        $placement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);
        $invoice = Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-09-01',
            'due_on' => '2026-09-05',
            'amount' => 800000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        // First void payment
        $pay1 = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'PAY-VOID-001',
            'amount' => 800000,
            'paid_on' => '2026-09-02',
            'method' => 'transfer',
            'status' => 'void',
            'recorded_by' => $admin->id,
            'voided_by' => $admin->id,
            'voided_at' => now(),
            'void_reason' => 'Salah nominal transfer',
        ]);

        // Second void payment on same invoice
        $pay2 = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'PAY-VOID-002',
            'amount' => 800000,
            'paid_on' => '2026-09-03',
            'method' => 'transfer',
            'status' => 'void',
            'recorded_by' => $admin->id,
            'voided_by' => $admin->id,
            'voided_at' => now(),
            'void_reason' => 'Bukti transfer tidak terbaca',
        ]);

        // Third payment is valid on the same invoice
        $pay3 = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'PAY-VALID-003',
            'amount' => 800000,
            'paid_on' => '2026-09-04',
            'method' => 'cash',
            'status' => 'valid',
            'recorded_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('payments', ['id' => $pay1->id, 'status' => 'void']);
        $this->assertDatabaseHas('payments', ['id' => $pay2->id, 'status' => 'void']);
        $this->assertDatabaseHas('payments', ['id' => $pay3->id, 'status' => 'valid']);
    }

    public function test_17_generated_unique_column_update_prevents_unvoiding_to_colliding_valid_payment(): void
    {
        $admin = $this->createAdminUser('admin.unvoid@example.test');
        [$user, $resident] = $this->createResidentUserAndProfile('res.unvoid@example.test', '08129999901');
        $room = $this->createRoom('ROOM-UNVOID');
        $placement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);
        $invoice = Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-09-01',
            'due_on' => '2026-09-05',
            'amount' => 800000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        // Payment 1 is void
        $voidPayment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'PAY-VOID-UNV',
            'amount' => 800000,
            'paid_on' => '2026-09-02',
            'method' => 'transfer',
            'status' => 'void',
            'recorded_by' => $admin->id,
            'voided_by' => $admin->id,
            'voided_at' => now(),
            'void_reason' => 'Koreksi admin',
        ]);

        // Payment 2 is valid
        $validPayment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'PAY-VAL-UNV',
            'amount' => 800000,
            'paid_on' => '2026-09-03',
            'method' => 'cash',
            'status' => 'valid',
            'recorded_by' => $admin->id,
        ]);

        // Attempt to UPDATE Payment 1 from 'void' to 'valid' -> Must FAIL with unique collision!
        $this->assertUniqueConstraintFails(function () use ($voidPayment) {
            DB::table('payments')->where('id', $voidPayment->id)->update([
                'status' => 'valid',
                'voided_by' => null,
                'voided_at' => null,
                'void_reason' => null,
            ]);
        }, 'payments_valid_invoice_id_unique');
    }

    // =========================================================================
    // 12. Generated Columns Protected From Mass Assignment
    // =========================================================================

    public function test_18_generated_columns_are_protected_from_mass_assignment(): void
    {
        $placement = new Placement();
        $this->assertFalse(in_array('active_room_id', $placement->getFillable()));
        $this->assertFalse(in_array('active_resident_id', $placement->getFillable()));

        $payment = new Payment();
        $this->assertFalse(in_array('valid_invoice_id', $payment->getFillable()));
    }
}
