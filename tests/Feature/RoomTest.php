<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Facility;
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
use RuntimeException;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createUser(string $roleCode, string $email): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();

        return User::create([
            'role_id' => $role->id,
            'name' => 'User ' . ucfirst($roleCode),
            'email' => $email,
            'password' => Hash::make('Password123456!'),
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function createRoom(string $number = 'K-101', string $type = 'Standard', int $rate = 800000, ?string $notes = null, ?string $archivedAt = null): Room
    {
        return Room::create([
            'number' => $number,
            'type' => $type,
            'monthly_rate' => $rate,
            'notes' => $notes,
            'archived_at' => $archivedAt,
        ]);
    }

    private function createResident(string $email = 'resident.test@example.test', string $name = 'Penghuni Test'): Resident
    {
        $user = $this->createUser('resident', $email);

        return Resident::create([
            'user_id' => $user->id,
            'name' => $name,
            'phone' => '081234567890',
            'origin_address' => 'Jl. Test No. 1',
        ]);
    }

    // =========================================================================
    // 1. Role Authorization & Early FormRequest Rejection
    // =========================================================================

    public function test_admin_can_view_rooms_list_with_search_and_pagination(): void
    {
        $admin = $this->createUser('admin', 'admin.rooms@example.test');
        $this->createRoom('A-01', 'Standard Single', 800000);
        $this->createRoom('B-02', 'Deluxe AC', 1200000);

        $response = $this->actingAs($admin)->get(route('rooms.index', ['search' => 'Deluxe']));

        $response->assertStatus(200);
        $response->assertSee('B-02');
        $response->assertSee('Deluxe AC');
        $response->assertDontSee('A-01');
        $response->assertSee('Tambah Kamar');
    }

    public function test_owner_can_view_rooms_list_and_details_as_readonly(): void
    {
        $owner = $this->createUser('owner', 'owner.rooms@example.test');
        $room = $this->createRoom('A-01', 'Standard Single', 800000);

        // Owner can access index
        $indexResponse = $this->actingAs($owner)->get(route('rooms.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('A-01');
        $indexResponse->assertDontSee('Tambah Kamar'); // Mutation button hidden

        // Owner can access show
        $showResponse = $this->actingAs($owner)->get(route('rooms.show', $room));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Detail Kamar A-01');
        $showResponse->assertDontSee('Ubah Kamar'); // Mutation button hidden
    }

    public function test_owner_cannot_access_mutation_endpoints_and_receives_403(): void
    {
        $owner = $this->createUser('owner', 'owner.deny@example.test');
        $room = $this->createRoom('M-01', 'Standard', 750000);

        $this->actingAs($owner);

        $this->get(route('rooms.create'))->assertStatus(403);
        $this->post(route('rooms.store'), ['number' => 'X-99', 'type' => 'VIP', 'monthly_rate' => 1000000])->assertStatus(403);
        $this->get(route('rooms.edit', $room))->assertStatus(403);
        $this->put(route('rooms.update', $room), ['number' => 'M-01', 'type' => 'Standard Updated', 'monthly_rate' => 800000])->assertStatus(403);
        $this->delete(route('rooms.destroy', $room))->assertStatus(403);
        $this->post(route('rooms.archive', $room))->assertStatus(403);
        $this->post(route('rooms.unarchive', $room))->assertStatus(403);
    }

    public function test_resident_cannot_access_any_rooms_endpoints_and_receives_403(): void
    {
        $residentUser = $this->createUser('resident', 'resident.deny@example.test');
        $room = $this->createRoom('R-01', 'Standard', 750000);

        $this->actingAs($residentUser);

        $this->get(route('rooms.index'))->assertStatus(403);
        $this->get(route('rooms.show', $room))->assertStatus(403);
        $this->get(route('rooms.create'))->assertStatus(403);
        $this->post(route('rooms.store'), ['number' => 'R-99', 'type' => 'VIP', 'monthly_rate' => 1000000])->assertStatus(403);
        $this->get(route('rooms.edit', $room))->assertStatus(403);
        $this->put(route('rooms.update', $room), ['number' => 'R-01', 'type' => 'VIP', 'monthly_rate' => 1000000])->assertStatus(403);
        $this->delete(route('rooms.destroy', $room))->assertStatus(403);
        $this->post(route('rooms.archive', $room))->assertStatus(403);
        $this->post(route('rooms.unarchive', $room))->assertStatus(403);
    }

    public function test_unauthorized_roles_receive_403_even_with_invalid_payload(): void
    {
        $owner = $this->createUser('owner', 'owner.invalidpayload@example.test');
        $resident = $this->createUser('resident', 'resident.invalidpayload@example.test');
        $room = $this->createRoom('INV-01', 'Standard', 700000);

        // Completely invalid payload (empty, wrong types, negative rate)
        $invalidPayload = [
            'number' => '',
            'type' => '',
            'monthly_rate' => -5000,
        ];

        // Owner Store & Update -> MUST be 403 Forbidden (NOT 422 Unprocessable)
        $this->actingAs($owner)->post(route('rooms.store'), $invalidPayload)->assertStatus(403);
        $this->actingAs($owner)->put(route('rooms.update', $room), $invalidPayload)->assertStatus(403);

        // Resident Store & Update -> MUST be 403 Forbidden (NOT 422)
        $this->actingAs($resident)->post(route('rooms.store'), $invalidPayload)->assertStatus(403);
        $this->actingAs($resident)->put(route('rooms.update', $room), $invalidPayload)->assertStatus(403);
    }

    // =========================================================================
    // 2. Room Creation, Validation & Audit Rollback
    // =========================================================================

    public function test_admin_can_create_room_with_valid_data_and_records_audit_log(): void
    {
        $admin = $this->createUser('admin', 'admin.create@example.test');

        $response = $this->actingAs($admin)->post(route('rooms.store'), [
            'number' => 'K-201',
            'type' => 'Deluxe AC Double',
            'monthly_rate' => 1500000,
            'notes' => 'Lantai 2 menghadap taman',
        ]);

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionHas('status', 'Kamar K-201 berhasil ditambahkan.');

        $this->assertDatabaseHas('rooms', [
            'number' => 'K-201',
            'type' => 'Deluxe AC Double',
            'monthly_rate' => 1500000,
            'notes' => 'Lantai 2 menghadap taman',
            'archived_at' => null,
        ]);

        // Verify Audit Trail Log
        $log = ActivityLog::where('module', 'rooms')
            ->where('action', 'create')
            ->where('entity_label', 'K-201')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame($admin->name, $log->actor_name);
        $this->assertSame('K-201', $log->changes['after']['number']);
        $this->assertSame(1500000, $log->changes['after']['monthly_rate']);
    }

    public function test_room_creation_validates_required_unique_positive_rate_and_bounds(): void
    {
        $admin = $this->createUser('admin', 'admin.val@example.test');
        $this->createRoom('EXIST-01', 'Standard', 800000);
        $this->createRoom('ARCH-01', 'Standard', 800000, null, now()->toIso8601String());

        // 1. Missing fields
        $res1 = $this->actingAs($admin)->from(route('rooms.create'))->post(route('rooms.store'), [
            'number' => '',
            'type' => '',
            'monthly_rate' => '',
        ]);
        $res1->assertSessionHasErrors(['number', 'type', 'monthly_rate']);

        // 2. Duplicate number against active room
        $res2 = $this->actingAs($admin)->from(route('rooms.create'))->post(route('rooms.store'), [
            'number' => 'EXIST-01',
            'type' => 'Standard',
            'monthly_rate' => 850000,
        ]);
        $res2->assertSessionHasErrors(['number']);

        // 3. Duplicate number against archived room (unique includes archived rooms)
        $res3 = $this->actingAs($admin)->from(route('rooms.create'))->post(route('rooms.store'), [
            'number' => 'ARCH-01',
            'type' => 'Standard',
            'monthly_rate' => 850000,
        ]);
        $res3->assertSessionHasErrors(['number']);

        // 4. Rate <= 0 or non-integer
        $res4 = $this->actingAs($admin)->from(route('rooms.create'))->post(route('rooms.store'), [
            'number' => 'NEW-99',
            'type' => 'Standard',
            'monthly_rate' => 0,
        ]);
        $res4->assertSessionHasErrors(['monthly_rate']);

        $res5 = $this->actingAs($admin)->from(route('rooms.create'))->post(route('rooms.store'), [
            'number' => 'NEW-99',
            'type' => 'Standard',
            'monthly_rate' => -50000,
        ]);
        $res5->assertSessionHasErrors(['monthly_rate']);

        $res6 = $this->actingAs($admin)->from(route('rooms.create'))->post(route('rooms.store'), [
            'number' => 'NEW-99',
            'type' => 'Standard',
            'monthly_rate' => 1000000000, // Exceeds 999.999.999
        ]);
        $res6->assertSessionHasErrors(['monthly_rate']);
    }

    public function test_create_and_update_cannot_manipulate_archived_at_or_occupancy_fields(): void
    {
        $admin = $this->createUser('admin', 'admin.tamper@example.test');

        // Attempt injecting archived_at and occupancy status during store
        $this->actingAs($admin)->post(route('rooms.store'), [
            'number' => 'TAMPER-01',
            'type' => 'Standard',
            'monthly_rate' => 900000,
            'archived_at' => now()->toIso8601String(),
            'is_occupied' => true,
            'active_room_id' => 999,
        ]);

        $room = Room::where('number', 'TAMPER-01')->firstOrFail();
        $this->assertNull($room->archived_at);
        $this->assertFalse($room->is_occupied);

        // Attempt injecting archived_at during update
        $this->actingAs($admin)->put(route('rooms.update', $room), [
            'number' => 'TAMPER-01',
            'type' => 'Standard Updated',
            'monthly_rate' => 950000,
            'archived_at' => now()->toIso8601String(),
            'is_occupied' => true,
        ]);

        $room->refresh();
        $this->assertNull($room->archived_at);
        $this->assertFalse($room->is_occupied);
    }

    public function test_room_creation_rolls_back_when_audit_fails(): void
    {
        $admin = $this->createUser('admin', 'admin.createrollback@example.test');

        // Mock AuditService to throw an exception
        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')
            ->willThrowException(new RuntimeException('Simulated database error during room create audit'));
        $this->app->instance(AuditService::class, $mockAudit);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->post(route('rooms.store'), [
                'number' => 'RB-STORE-01',
                'type' => 'Standard',
                'monthly_rate' => 850000,
            ]);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated database error during room create audit', $e->getMessage());
        }

        // Room must NOT exist in database
        $this->assertDatabaseMissing('rooms', ['number' => 'RB-STORE-01']);
    }

    // =========================================================================
    // 3. Room Update, Self-Ignore Validation & Audit Rollback
    // =========================================================================

    public function test_admin_can_update_room_and_records_audit_log(): void
    {
        $admin = $this->createUser('admin', 'admin.update@example.test');
        $room = $this->createRoom('UPD-01', 'Standard', 800000, 'Catatan lama');

        $response = $this->actingAs($admin)->put(route('rooms.update', $room), [
            'number' => 'UPD-01-REV',
            'type' => 'VIP Superior',
            'monthly_rate' => 1100000,
            'notes' => 'Catatan baru telah direnovasi',
        ]);

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionHas('status', 'Data kamar UPD-01-REV berhasil diperbarui.');

        $room->refresh();
        $this->assertSame('UPD-01-REV', $room->number);
        $this->assertSame('VIP Superior', $room->type);
        $this->assertSame(1100000, $room->monthly_rate);
        $this->assertSame('Catatan baru telah direnovasi', $room->notes);

        // Verify Audit Log before vs after
        $log = ActivityLog::where('module', 'rooms')
            ->where('action', 'update')
            ->where('entity_id', $room->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('UPD-01', $log->changes['before']['number']);
        $this->assertSame(800000, $log->changes['before']['monthly_rate']);
        $this->assertSame('UPD-01-REV', $log->changes['after']['number']);
        $this->assertSame(1100000, $log->changes['after']['monthly_rate']);
    }

    public function test_room_update_validation_and_unique_number_ignoring_self(): void
    {
        $admin = $this->createUser('admin', 'admin.updval@example.test');
        $room1 = $this->createRoom('SELF-01', 'Standard', 800000);
        $room2 = $this->createRoom('OTHER-02', 'Standard', 800000);

        // 1. Keeping own number must pass
        $res1 = $this->actingAs($admin)->put(route('rooms.update', $room1), [
            'number' => 'SELF-01',
            'type' => 'Updated Type',
            'monthly_rate' => 850000,
        ]);
        $res1->assertSessionHasNoErrors();
        $res1->assertRedirect(route('rooms.index'));

        // 2. Taking another room's number must fail
        $res2 = $this->actingAs($admin)->from(route('rooms.edit', $room1))->put(route('rooms.update', $room1), [
            'number' => 'OTHER-02',
            'type' => 'Updated Type',
            'monthly_rate' => 850000,
        ]);
        $res2->assertSessionHasErrors(['number']);
    }

    public function test_room_update_rolls_back_when_audit_fails(): void
    {
        $admin = $this->createUser('admin', 'admin.updaterollback@example.test');
        $room = $this->createRoom('RB-UPD-01', 'Standard Initial', 700000);

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')
            ->willThrowException(new RuntimeException('Simulated database error during room update audit'));
        $this->app->instance(AuditService::class, $mockAudit);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->put(route('rooms.update', $room), [
                'number' => 'RB-UPD-01-CHANGED',
                'type' => 'Standard Changed',
                'monthly_rate' => 999000,
            ]);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated database error during room update audit', $e->getMessage());
        }

        // Room in DB must retain old attributes
        $room->refresh();
        $this->assertSame('RB-UPD-01', $room->number);
        $this->assertSame('Standard Initial', $room->type);
        $this->assertSame(700000, $room->monthly_rate);
    }

    // =========================================================================
    // 4. Invariant: Room Rate Update Does NOT Alter Existing Contracts (TC-06)
    // =========================================================================

    public function test_room_rate_update_does_not_change_existing_placement_agreed_rate_or_invoice_amount(): void
    {
        $admin = $this->createUser('admin', 'admin.rateinv@example.test');
        $resident = $this->createResident('resident.rateinv@example.test');
        $room = $this->createRoom('RATE-01', 'Standard', 800000);

        // Create active placement with agreed rate 800,000
        $placement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        // Create invoice with amount 800,000
        $invoice = Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-09-01',
            'due_on' => '2026-09-05',
            'amount' => 800000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        // Admin updates room monthly rate to 1,200,000
        $response = $this->actingAs($admin)->put(route('rooms.update', $room), [
            'number' => 'RATE-01',
            'type' => 'Standard',
            'monthly_rate' => 1200000,
        ]);

        $response->assertRedirect(route('rooms.index'));

        // Verify Room updated
        $this->assertSame(1200000, $room->fresh()->monthly_rate);

        // CRITICAL INVARIANT: Placement agreed rate and Invoice amount MUST REMAIN 800,000
        $this->assertSame(800000, $placement->fresh()->agreed_monthly_rate);
        $this->assertSame(800000, $invoice->fresh()->amount);
    }

    // =========================================================================
    // 5. Dynamic Occupancy Status Calculation (Requirement 3)
    // =========================================================================

    public function test_occupancy_status_is_derived_dynamically_from_active_placement(): void
    {
        $admin = $this->createUser('admin', 'admin.occ@example.test');
        $resident = $this->createResident('resident.occ@example.test');
        $room = $this->createRoom('OCC-01', 'Standard', 800000);

        // Initially vacant
        $this->assertFalse($room->is_occupied);

        // Placement starts -> Room becomes occupied
        $placement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        $this->assertTrue($room->fresh()->is_occupied);

        // Placement ends -> Room becomes vacant again
        $placement->update([
            'ended_on' => '2026-09-20',
            'ended_by' => $admin->id,
            'end_reason' => 'Selesai masa sewa',
        ]);

        $this->assertFalse($room->fresh()->is_occupied);
    }

    // =========================================================================
    // 6. Deletion, Reference Integrity & TC-09 Protection
    // =========================================================================

    public function test_admin_can_delete_room_with_no_references_and_records_audit(): void
    {
        $admin = $this->createUser('admin', 'admin.del@example.test');
        $room = $this->createRoom('DEL-01', 'Standard', 800000);

        $response = $this->actingAs($admin)->delete(route('rooms.destroy', $room));

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionHas('status', 'Kamar DEL-01 berhasil dihapus permanen.');

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);

        // Verify delete audit log
        $log = ActivityLog::where('module', 'rooms')
            ->where('action', 'delete')
            ->where('entity_label', 'DEL-01')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('DEL-01', $log->changes['before']['number']);
        $this->assertArrayNotHasKey('after', $log->changes);
    }

    public function test_room_physical_deletion_rolls_back_when_audit_fails(): void
    {
        $admin = $this->createUser('admin', 'admin.delrollback@example.test');
        $room = $this->createRoom('RB-DEL-01', 'Standard', 800000);

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')
            ->willThrowException(new RuntimeException('Simulated database error during room delete audit'));
        $this->app->instance(AuditService::class, $mockAudit);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->delete(route('rooms.destroy', $room));
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated database error during room delete audit', $e->getMessage());
        }

        // Room MUST still exist in database
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_room_physical_deletion_is_rejected_when_placement_history_exists(): void
    {
        $admin = $this->createUser('admin', 'admin.rejectdelplace@example.test');
        $resident = $this->createResident('resident.delplace@example.test');
        $room = $this->createRoom('HIST-01', 'Standard', 800000);

        // Historical ended placement
        Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-01-01',
            'ended_on' => '2026-06-30',
            'ended_by' => $admin->id,
            'end_reason' => 'Pindah kerja',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('rooms.destroy', $room));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('memiliki riwayat penempatan', session('error'));

        // Room must remain intact
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_room_physical_deletion_is_rejected_when_facility_reference_exists_including_archived_facility(): void
    {
        $admin = $this->createUser('admin', 'admin.rejectdelfac@example.test');
        $room = $this->createRoom('FACREF-01', 'Standard', 800000);

        // Archived facility located in room
        Facility::create([
            'code' => 'FAC-TEST-99',
            'name' => 'Meja Belajar',
            'location_type' => 'room',
            'room_id' => $room->id,
            'condition' => 'good',
            'archived_at' => now()->toIso8601String(),
        ]);

        $response = $this->actingAs($admin)->delete(route('rooms.destroy', $room));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('fasilitas terkait', session('error'));

        // Room must remain intact
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    // =========================================================================
    // 7. Archiving, Unarchiving & Active Placement Rejection
    // =========================================================================

    public function test_admin_can_archive_vacant_room_with_history_and_records_audit(): void
    {
        $admin = $this->createUser('admin', 'admin.arch@example.test');
        $resident = $this->createResident('resident.arch@example.test');
        $room = $this->createRoom('ARCH-VAC-01', 'Standard', 800000);

        // Ended placement (vacant)
        Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-01-01',
            'ended_on' => '2026-05-31',
            'ended_by' => $admin->id,
            'end_reason' => 'Habis kontrak',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('rooms.archive', $room));

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionHas('status', 'Kamar ARCH-VAC-01 berhasil diarsipkan.');

        $this->assertNotNull($room->fresh()->archived_at);

        // Verify archive audit log
        $log = ActivityLog::where('module', 'rooms')
            ->where('action', 'archive')
            ->where('entity_id', $room->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->changes['before']['archived_at']);
        $this->assertNotNull($log->changes['after']['archived_at']);
    }

    public function test_room_archiving_rolls_back_when_audit_fails(): void
    {
        $admin = $this->createUser('admin', 'admin.archrollback@example.test');
        $room = $this->createRoom('RB-ARCH-01', 'Standard', 800000);

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')
            ->willThrowException(new RuntimeException('Simulated database error during room archive audit'));
        $this->app->instance(AuditService::class, $mockAudit);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->post(route('rooms.archive', $room));
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated database error during room archive audit', $e->getMessage());
        }

        // Room must NOT be archived
        $this->assertNull($room->fresh()->archived_at);
    }

    public function test_room_archiving_is_rejected_when_active_placement_exists(): void
    {
        $admin = $this->createUser('admin', 'admin.rejectarchactive@example.test');
        $resident = $this->createResident('resident.archactive@example.test');
        $room = $this->createRoom('OCC-ARCH-01', 'Standard', 800000);

        // Active placement
        Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('rooms.archive', $room));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('sedang dihuni oleh penempatan aktif', session('error'));

        // Room must remain unarchived
        $this->assertNull($room->fresh()->archived_at);
    }

    public function test_admin_can_unarchive_archived_room_and_records_audit(): void
    {
        $admin = $this->createUser('admin', 'admin.unarch@example.test');
        $room = $this->createRoom('UNARCH-01', 'Standard', 800000, null, now()->toIso8601String());

        $response = $this->actingAs($admin)->post(route('rooms.unarchive', $room));

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionHas('status', 'Arsip kamar UNARCH-01 berhasil dibuka kembali (diaktifkan).');

        $this->assertNull($room->fresh()->archived_at);

        // Verify unarchive audit log
        $log = ActivityLog::where('module', 'rooms')
            ->where('action', 'unarchive')
            ->where('entity_id', $room->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->changes['before']['archived_at']);
        $this->assertNull($log->changes['after']['archived_at']);
    }

    public function test_room_unarchive_rolls_back_when_audit_fails(): void
    {
        $admin = $this->createUser('admin', 'admin.unarchrollback@example.test');
        $room = $this->createRoom('RB-UNARCH-01', 'Standard', 800000, null, now()->toIso8601String());

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')
            ->willThrowException(new RuntimeException('Simulated database error during room unarchive audit'));
        $this->app->instance(AuditService::class, $mockAudit);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->post(route('rooms.unarchive', $room));
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated database error during room unarchive audit', $e->getMessage());
        }

        // Room must still remain archived
        $this->assertNotNull($room->fresh()->archived_at);
    }

    public function test_rejected_actions_do_not_create_audit_logs(): void
    {
        $admin = $this->createUser('admin', 'admin.noaudit@example.test');
        $resident = $this->createResident('resident.noaudit@example.test');
        $room = $this->createRoom('NOAUDIT-01', 'Standard', 800000);

        // Active placement preventing archive
        Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        $auditCountBefore = ActivityLog::where('module', 'rooms')->count();

        // 1. Rejected archive
        $this->actingAs($admin)->post(route('rooms.archive', $room));

        // 2. Rejected delete
        $this->actingAs($admin)->delete(route('rooms.destroy', $room));

        // 3. Validation failure store
        $this->actingAs($admin)->post(route('rooms.store'), ['number' => '']);

        $auditCountAfter = ActivityLog::where('module', 'rooms')->count();

        // Count must not increase
        $this->assertSame($auditCountBefore, $auditCountAfter);
    }

    // =========================================================================
    // 8. Room Details (Show) with Active & Past Placements (Points 1 & 2)
    // =========================================================================

    public function test_admin_can_view_room_detail_with_active_and_ordered_past_placements(): void
    {
        $admin = $this->createUser('admin', 'admin.detail@example.test');
        $room = $this->createRoom('DET-101', 'Deluxe AC', 1200000, 'Kamar lantai 1 hadap taman');

        $res1 = $this->createResident('res1.det@example.test', 'Andi Permana');
        $res2 = $this->createResident('res2.det@example.test', 'Budi Santoso');
        $res3 = $this->createResident('res3.det@example.test', 'Citra Lestari');

        // Past placement 1 (oldest: 2024)
        Placement::create([
            'resident_id' => $res1->id,
            'room_id' => $room->id,
            'started_on' => '2024-01-01',
            'ended_on' => '2024-06-30',
            'ended_by' => $admin->id,
            'end_reason' => 'Selesai masa sewa',
            'agreed_monthly_rate' => 1000000,
            'created_by' => $admin->id,
        ]);

        // Past placement 2 (intermediate: 2025)
        Placement::create([
            'resident_id' => $res2->id,
            'room_id' => $room->id,
            'started_on' => '2025-01-01',
            'ended_on' => '2025-12-31',
            'ended_by' => $admin->id,
            'end_reason' => 'Pindah tugas kerja',
            'agreed_monthly_rate' => 1100000,
            'created_by' => $admin->id,
        ]);

        // Active placement (newest: 2026, active)
        Placement::create([
            'resident_id' => $res3->id,
            'room_id' => $room->id,
            'started_on' => '2026-01-01',
            'ended_on' => null,
            'agreed_monthly_rate' => 1200000,
            'created_by' => $admin->id,
        ]);

        // Add a facility
        Facility::create([
            'code' => 'FAC-DET-01',
            'name' => 'Lemari Pakaian 2 Pintu',
            'location_type' => 'room',
            'room_id' => $room->id,
            'condition' => 'good',
        ]);

        $response = $this->actingAs($admin)->get(route('rooms.show', $room));

        $response->assertStatus(200);
        $response->assertSee('Detail Kamar DET-101');
        $response->assertSee('Deluxe AC');
        $response->assertSee('Terisi');

        // Active placement card shows active resident
        $response->assertSee('Penempatan Aktif');
        $response->assertSee('Citra Lestari');

        // Facility table shows room facility
        $response->assertSee('FAC-DET-01');
        $response->assertSee('Lemari Pakaian 2 Pintu');

        // Placement history table: resident names must appear in exact descending order of started_on
        $response->assertSeeInOrder(['Citra Lestari', 'Budi Santoso', 'Andi Permana']);
        $response->assertSeeInOrder(['01/01/2026', '01/01/2025', '01/01/2024']);

        // Admin action buttons visible
        $response->assertSee('Ubah Kamar');
        $response->assertSee('Kamar terisi tidak dapat diarsipkan');
        $response->assertSee('Penghapusan fisik dinonaktifkan (memiliki referensi data)');
    }

    public function test_owner_can_view_room_detail_as_readonly_with_placements_and_no_mutation_actions(): void
    {
        $owner = $this->createUser('owner', 'owner.detail@example.test');
        $admin = $this->createUser('admin', 'admin.setup@example.test');
        $room = $this->createRoom('DET-OWNER-01', 'Standard Single', 850000);

        $res1 = $this->createResident('res1.own@example.test', 'Ahmad Fauzi');
        $res2 = $this->createResident('res2.own@example.test', 'Dewi Lestari');

        // Past placement
        Placement::create([
            'resident_id' => $res1->id,
            'room_id' => $room->id,
            'started_on' => '2025-06-01',
            'ended_on' => '2025-11-30',
            'ended_by' => $admin->id,
            'end_reason' => 'Habis kontrak',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        // Active placement
        Placement::create([
            'resident_id' => $res2->id,
            'room_id' => $room->id,
            'started_on' => '2026-02-01',
            'ended_on' => null,
            'agreed_monthly_rate' => 850000,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($owner)->get(route('rooms.show', $room));

        $response->assertStatus(200);
        $response->assertSee('Detail Kamar DET-OWNER-01');
        $response->assertSee('Dewi Lestari');
        $response->assertSee('Ahmad Fauzi');

        // History in descending order
        $response->assertSeeInOrder(['Dewi Lestari', 'Ahmad Fauzi']);
        $response->assertSeeInOrder(['01/02/2026', '01/06/2025']);

        // Owner MUST NOT see mutation buttons
        $response->assertDontSee('Ubah Kamar');
        $response->assertDontSee('Arsipkan Kamar Ini');
        $response->assertDontSee('Hapus Permanen');
        $response->assertSee('Daftar Kamar');
    }

    // =========================================================================
    // 9. Query Parameter Validation & Array Input Rejection (Point 4)
    // =========================================================================

    public function test_index_rejects_array_inputs_gracefully_without_500_error(): void
    {
        $admin = $this->createUser('admin', 'admin.arrval@example.test');

        // 1. Array search parameter
        $res1 = $this->actingAs($admin)->get(route('rooms.index', ['search' => ['injected_array']]));
        $res1->assertRedirect(route('rooms.index'));
        $res1->assertSessionHasErrors(['search']);

        // 2. Array tab parameter
        $res2 = $this->actingAs($admin)->get(route('rooms.index', ['tab' => ['active', 'archived']]));
        $res2->assertRedirect(route('rooms.index'));
        $res2->assertSessionHasErrors(['tab']);

        // 3. Array occupancy parameter
        $res3 = $this->actingAs($admin)->get(route('rooms.index', ['occupancy' => ['occupied']]));
        $res3->assertRedirect(route('rooms.index'));
        $res3->assertSessionHasErrors(['occupancy']);

        // 4. Array per_page parameter
        $res4 = $this->actingAs($admin)->get(route('rooms.index', ['per_page' => [10, 25]]));
        $res4->assertRedirect(route('rooms.index'));
        $res4->assertSessionHasErrors(['per_page']);
    }

    public function test_index_rejects_invalid_filter_options(): void
    {
        $admin = $this->createUser('admin', 'admin.filtval@example.test');

        // 1. Invalid tab
        $res1 = $this->actingAs($admin)->get(route('rooms.index', ['tab' => 'unsupported_tab']));
        $res1->assertRedirect(route('rooms.index'));
        $res1->assertSessionHasErrors(['tab']);

        // 2. Invalid occupancy
        $res2 = $this->actingAs($admin)->get(route('rooms.index', ['occupancy' => 'random_status']));
        $res2->assertRedirect(route('rooms.index'));
        $res2->assertSessionHasErrors(['occupancy']);

        // 3. Invalid per_page limit
        $res3 = $this->actingAs($admin)->get(route('rooms.index', ['per_page' => 999]));
        $res3->assertRedirect(route('rooms.index'));
        $res3->assertSessionHasErrors(['per_page']);
    }

    public function test_index_accepts_valid_filters(): void
    {
        $admin = $this->createUser('admin', 'admin.validfilt@example.test');
        $this->createRoom('VF-101', 'Deluxe Standard', 900000);

        $response = $this->actingAs($admin)->get(route('rooms.index', [
            'search' => 'VF',
            'tab' => 'active',
            'occupancy' => 'vacant',
            'per_page' => 25,
        ]));

        $response->assertStatus(200);
        $response->assertSessionHasNoErrors();
        $response->assertSee('VF-101');
    }

    // =========================================================================
    // 10. Verification of N+1 Query Elimination on Index (Point 3)
    // =========================================================================

    public function test_index_rendering_does_not_trigger_n_plus_one_queries_for_historical_references(): void
    {
        $admin = $this->createUser('admin', 'admin.nplusone@example.test');
        $resident = $this->createResident('resident.n1@example.test');

        // Create 6 rooms with varied relations
        for ($i = 1; $i <= 6; $i++) {
            $room = $this->createRoom("N1-ROOM-0{$i}", 'Standard', 800000);

            if ($i % 2 === 0) {
                // Add placement
                Placement::create([
                    'resident_id' => $resident->id,
                    'room_id' => $room->id,
                    'started_on' => '2026-01-01',
                    'ended_on' => '2026-06-30',
                    'ended_by' => $admin->id,
                    'end_reason' => 'Selesai',
                    'agreed_monthly_rate' => 800000,
                    'created_by' => $admin->id,
                ]);
            }

            if ($i % 3 === 0) {
                // Add facility
                Facility::create([
                    'code' => "FAC-N1-0{$i}",
                    'name' => "Fasilitas {$i}",
                    'location_type' => 'room',
                    'room_id' => $room->id,
                    'condition' => 'good',
                ]);
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get(route('rooms.index'));
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Ensure no individual queries were run per room row for placements/facilities existence
        foreach ($queries as $q) {
            $sql = strtolower($q['query']);
            $this->assertFalse(
                str_contains($sql, 'select * from `placements` where `placements`.`room_id` =') ||
                str_contains($sql, 'select * from `facilities` where `facilities`.`room_id` ='),
                "Found N+1 query: {$q['query']}"
            );
        }
    }
}
