<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Complaint;
use App\Models\Facility;
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

class FacilityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createUser(string $roleCode, string $email, ?string $name = null): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();

        return User::create([
            'role_id' => $role->id,
            'name' => $name ?? ('User ' . ucfirst($roleCode)),
            'email' => $email,
            'password' => Hash::make('Password123456!'),
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function createRoom(string $number = 'K-101', string $type = 'Standard', int $rate = 800000, ?string $archivedAt = null): Room
    {
        return Room::create([
            'number' => $number,
            'type' => $type,
            'monthly_rate' => $rate,
            'archived_at' => $archivedAt,
        ]);
    }

    private function createResident(string $email = 'resident.facility@example.test', string $name = 'Penghuni Test'): Resident
    {
        $user = $this->createUser('resident', $email, $name);

        return Resident::create([
            'user_id' => $user->id,
            'name' => $name,
            'phone' => '081234567890',
            'origin_address' => 'Jl. Test No. 10',
        ]);
    }

    private function createFacility(
        string $code = 'FAC-001',
        string $name = 'AC Daikin 1/2 PK',
        string $locationType = 'room',
        ?int $roomId = null,
        ?string $areaName = null,
        string $condition = 'good',
        ?string $notes = null,
        ?string $archivedAt = null
    ): Facility {
        if ($locationType === 'room' && ! $roomId) {
            $room = $this->createRoom('RM-' . uniqid());
            $roomId = $room->id;
        }

        return Facility::create([
            'code' => $code,
            'name' => $name,
            'location_type' => $locationType,
            'room_id' => $roomId,
            'area_name' => $areaName,
            'condition' => $condition,
            'notes' => $notes,
            'archived_at' => $archivedAt,
        ]);
    }

    private function createComplaint(
        Facility $facility,
        string $status = 'open',
        ?Placement $placement = null,
        ?User $submitter = null
    ): Complaint {
        if (! $placement) {
            $admin = User::whereHas('role', fn ($q) => $q->where('code', 'admin'))->first();
            if (! $admin) {
                $admin = $this->createUser('admin', 'admin.complaint.fixture@example.test');
            }
            $room = $this->createRoom('RM-C-' . uniqid());
            $resident = $this->createResident('res.complaint.' . uniqid() . '@example.test');
            $placement = Placement::create([
                'resident_id' => $resident->id,
                'room_id' => $room->id,
                'started_on' => '2026-01-01',
                'agreed_monthly_rate' => 800000,
                'created_by' => $admin->id,
            ]);
        }

        if (! $submitter) {
            $submitter = $placement->resident->user;
        }

        return Complaint::create([
            'placement_id' => $placement->id,
            'facility_id' => $facility->id,
            'subject' => 'Keluhan Fasilitas ' . $facility->name,
            'description' => 'Kerusakan fisik atau fungsi tidak normal.',
            'status' => $status,
            'submitted_by' => $submitter->id,
            'closed_at' => in_array($status, ['resolved', 'closed_without_action'], true) ? now('UTC') : null,
        ]);
    }

    // =========================================================================
    // 1. Role Authorization & Access Control (TC-08 / TC-03)
    // =========================================================================

    public function test_admin_can_access_all_facility_endpoints(): void
    {
        $admin = $this->createUser('admin', 'admin.fac.auth@example.test');
        $room = $this->createRoom('A-101');
        $facility = $this->createFacility('AC-A101', 'AC Daikin', 'room', $room->id);

        // Index
        $this->actingAs($admin)->get(route('facilities.index'))->assertStatus(200)->assertSee('AC-A101');

        // Create
        $this->actingAs($admin)->get(route('facilities.create'))->assertStatus(200);

        // Show
        $this->actingAs($admin)->get(route('facilities.show', $facility))->assertStatus(200)->assertSee('AC-A101');

        // Edit
        $this->actingAs($admin)->get(route('facilities.edit', $facility))->assertStatus(200);

        // Store
        $storeResponse = $this->actingAs($admin)->post(route('facilities.store'), [
            'code' => 'WTR-01',
            'name' => 'Dispenser Air',
            'condition' => 'good',
            'location_type' => 'shared',
            'area_name' => 'Dapur Lantai 1',
        ]);
        $storeResponse->assertRedirect(route('facilities.index'));

        // Archive & Unarchive
        $this->actingAs($admin)->post(route('facilities.archive', $facility))->assertRedirect(route('facilities.index'));
        $this->actingAs($admin)->post(route('facilities.unarchive', $facility))->assertRedirect(route('facilities.index'));

        // Destroy
        $this->actingAs($admin)->delete(route('facilities.destroy', $facility))->assertRedirect(route('facilities.index'));
    }

    public function test_owner_can_access_facility_index_and_show_only_without_mutation_actions(): void
    {
        $owner = $this->createUser('owner', 'owner.fac.auth@example.test');
        $room = $this->createRoom('B-201');
        $facility = $this->createFacility('KSR-201', 'Kasur Springbed', 'room', $room->id);

        // Index: 200 OK, see facility, do NOT see "Tambah Fasilitas" button
        $indexResponse = $this->actingAs($owner)->get(route('facilities.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('KSR-201');
        $indexResponse->assertDontSee('Tambah Fasilitas');

        // Show: 200 OK, do NOT see "Ubah Fasilitas", "Hapus", or "Arsip" action buttons
        $showResponse = $this->actingAs($owner)->get(route('facilities.show', $facility));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('KSR-201');
        $showResponse->assertDontSee('Ubah Fasilitas');
        $showResponse->assertDontSee('Arsipkan Fasilitas Ini');
        $showResponse->assertDontSee('Hapus Permanen Fasilitas Ini');
    }

    public function test_owner_gets_403_on_facility_mutation_endpoints(): void
    {
        $owner = $this->createUser('owner', 'owner.fac.mut@example.test');
        $room = $this->createRoom('B-202');
        $facility = $this->createFacility('KSR-202', 'Kasur Springbed', 'room', $room->id);

        $this->actingAs($owner)->get(route('facilities.create'))->assertStatus(403);
        $this->actingAs($owner)->post(route('facilities.store'), ['code' => 'TEST'])->assertStatus(403);
        $this->actingAs($owner)->get(route('facilities.edit', $facility))->assertStatus(403);
        $this->actingAs($owner)->put(route('facilities.update', $facility), ['code' => 'TEST'])->assertStatus(403);
        $this->actingAs($owner)->delete(route('facilities.destroy', $facility))->assertStatus(403);
        $this->actingAs($owner)->post(route('facilities.archive', $facility))->assertStatus(403);
        $this->actingAs($owner)->post(route('facilities.unarchive', $facility))->assertStatus(403);
    }

    public function test_resident_gets_403_on_all_master_facility_endpoints(): void
    {
        $residentUser = $this->createUser('resident', 'resident.fac.auth@example.test');
        $room = $this->createRoom('C-301');
        $facility = $this->createFacility('AC-301', 'AC Daikin', 'room', $room->id);

        $this->actingAs($residentUser)->get(route('facilities.index'))->assertStatus(403);
        $this->actingAs($residentUser)->get(route('facilities.create'))->assertStatus(403);
        $this->actingAs($residentUser)->post(route('facilities.store'), ['code' => 'TEST'])->assertStatus(403);
        $this->actingAs($residentUser)->get(route('facilities.show', $facility))->assertStatus(403);
        $this->actingAs($residentUser)->get(route('facilities.edit', $facility))->assertStatus(403);
        $this->actingAs($residentUser)->put(route('facilities.update', $facility), ['code' => 'TEST'])->assertStatus(403);
        $this->actingAs($residentUser)->delete(route('facilities.destroy', $facility))->assertStatus(403);
        $this->actingAs($residentUser)->post(route('facilities.archive', $facility))->assertStatus(403);
        $this->actingAs($residentUser)->post(route('facilities.unarchive', $facility))->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login_for_facility_endpoints(): void
    {
        $room = $this->createRoom('G-101');
        $facility = $this->createFacility('G-FAC', 'Barang Guest', 'room', $room->id);

        $this->get(route('facilities.index'))->assertRedirect(route('login'));
        $this->get(route('facilities.show', $facility))->assertRedirect(route('login'));
        $this->post(route('facilities.store'), [])->assertRedirect(route('login'));
    }

    public function test_non_admin_submitting_invalid_payload_gets_403_before_validation(): void
    {
        $owner = $this->createUser('owner', 'owner.invalid.fac@example.test');
        $resident = $this->createUser('resident', 'resident.invalid.fac@example.test');
        $room = $this->createRoom('D-401');
        $facility = $this->createFacility('FAC-INV', 'Fasilitas Inv', 'room', $room->id);

        // Owner sends completely invalid payload -> must receive 403, NOT 422
        $this->actingAs($owner)->post(route('facilities.store'), ['bad_key' => 'garbage'])->assertStatus(403);
        $this->actingAs($owner)->put(route('facilities.update', $facility), ['bad_key' => 'garbage'])->assertStatus(403);

        // Resident sends completely invalid payload -> must receive 403, NOT 422
        $this->actingAs($resident)->post(route('facilities.store'), ['bad_key' => 'garbage'])->assertStatus(403);
        $this->actingAs($resident)->put(route('facilities.update', $facility), ['bad_key' => 'garbage'])->assertStatus(403);
    }

    // =========================================================================
    // 2. Server Validation & Database Constraints (TC-08)
    // =========================================================================

    public function test_facility_code_is_required_and_unique_including_archived_facilities(): void
    {
        $admin = $this->createUser('admin', 'admin.code.val@example.test');
        $room = $this->createRoom('V-101');

        // Create an archived facility with code 'CODE-ARCH'
        $this->createFacility('CODE-ARCH', 'Barang Arsip', 'room', $room->id, null, 'good', null, now('UTC')->toDateTimeString());

        // Attempt to create new facility with duplicate code of archived facility
        $response = $this->actingAs($admin)->from(route('facilities.create'))->post(route('facilities.store'), [
            'code' => 'CODE-ARCH',
            'name' => 'Barang Baru Kode Sama',
            'condition' => 'good',
            'location_type' => 'shared',
            'area_name' => 'Area Depan',
        ]);

        $response->assertRedirect(route('facilities.create'));
        $response->assertSessionHasErrors(['code']);
    }

    public function test_facility_code_is_unique_case_insensitively_including_archived_facilities(): void
    {
        $admin = $this->createUser('admin', 'admin.case.val@example.test');
        $room = $this->createRoom('V-102');

        // Create facility with uppercase 'FAC-UPPER'
        $this->createFacility('FAC-UPPER', 'Barang Upper', 'room', $room->id);

        // Attempt to create new facility with lowercase 'fac-upper'
        $response = $this->actingAs($admin)->from(route('facilities.create'))->post(route('facilities.store'), [
            'code' => 'fac-upper',
            'name' => 'Barang Lower',
            'condition' => 'good',
            'location_type' => 'shared',
            'area_name' => 'Area Belakang',
        ]);

        $response->assertRedirect(route('facilities.create'));
        $response->assertSessionHasErrors(['code']);
    }

    public function test_facility_name_and_condition_validation(): void
    {
        $admin = $this->createUser('admin', 'admin.namecond.val@example.test');

        // Name too short (min 2), condition invalid
        $response = $this->actingAs($admin)->from(route('facilities.create'))->post(route('facilities.store'), [
            'code' => 'VAL-01',
            'name' => 'A', // too short
            'condition' => 'invalid_cond', // not in good, broken, repairing
            'location_type' => 'shared',
            'area_name' => 'Ruang Santai',
        ]);

        $response->assertRedirect(route('facilities.create'));
        $response->assertSessionHasErrors(['name', 'condition']);
    }

    public function test_room_facility_requires_valid_room_id_and_null_area_name(): void
    {
        $admin = $this->createUser('admin', 'admin.roomrule.val@example.test');

        // Location is 'room', but room_id is omitted
        $response1 = $this->actingAs($admin)->from(route('facilities.create'))->post(route('facilities.store'), [
            'code' => 'ROOM-FAIL-1',
            'name' => 'Meja Belajar',
            'condition' => 'good',
            'location_type' => 'room',
        ]);
        $response1->assertRedirect(route('facilities.create'));
        $response1->assertSessionHasErrors(['room_id']);

        // Location is 'room', but non-null area_name is also sent (violates chk_facilities_location_rule)
        $room = $this->createRoom('R-501');
        $response2 = $this->actingAs($admin)->from(route('facilities.create'))->post(route('facilities.store'), [
            'code' => 'ROOM-FAIL-2',
            'name' => 'Kursi Kerja',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => $room->id,
            'area_name' => 'Area Terlarang',
        ]);
        $response2->assertRedirect(route('facilities.create'));
        $response2->assertSessionHasErrors(['area_name']);
    }

    public function test_shared_facility_requires_area_name_and_null_room_id(): void
    {
        $admin = $this->createUser('admin', 'admin.sharedrule.val@example.test');
        $room = $this->createRoom('R-502');

        // Location is 'shared', but area_name omitted
        $response1 = $this->actingAs($admin)->from(route('facilities.create'))->post(route('facilities.store'), [
            'code' => 'SHARED-FAIL-1',
            'name' => 'Kulkas Umum',
            'condition' => 'good',
            'location_type' => 'shared',
        ]);
        $response1->assertRedirect(route('facilities.create'));
        $response1->assertSessionHasErrors(['area_name']);

        // Location is 'shared', but room_id is also sent
        $response2 = $this->actingAs($admin)->from(route('facilities.create'))->post(route('facilities.store'), [
            'code' => 'SHARED-FAIL-2',
            'name' => 'Microwave Umum',
            'condition' => 'good',
            'location_type' => 'shared',
            'area_name' => 'Dapur Lantai 2',
            'room_id' => $room->id,
        ]);
        $response2->assertRedirect(route('facilities.create'));
        $response2->assertSessionHasErrors(['room_id']);
    }

    public function test_archived_at_cannot_be_injected_via_create_or_update_payload(): void
    {
        $admin = $this->createUser('admin', 'admin.archinject.val@example.test');
        $room = $this->createRoom('R-503');

        // Attempt inject archived_at on create
        $this->actingAs($admin)->post(route('facilities.store'), [
            'code' => 'NO-INJECT-1',
            'name' => 'Fasilitas No Inject',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => $room->id,
            'archived_at' => '2020-01-01 00:00:00',
        ]);

        $facility = Facility::where('code', 'NO-INJECT-1')->firstOrFail();
        $this->assertNull($facility->archived_at, 'archived_at must not be set through create payload');

        // Attempt inject archived_at on update
        $this->actingAs($admin)->put(route('facilities.update', $facility), [
            'code' => 'NO-INJECT-1',
            'name' => 'Fasilitas Updated',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => $room->id,
            'archived_at' => '2020-01-01 00:00:00',
        ]);

        $facility->refresh();
        $this->assertNull($facility->archived_at, 'archived_at must not be altered through update payload');
    }

    public function test_query_filters_handle_array_inputs_gracefully_without_500_error(): void
    {
        $admin = $this->createUser('admin', 'admin.queryval.val@example.test');

        // Array injection on search, tab, condition, per_page
        $response = $this->actingAs($admin)->get(route('facilities.index', [
            'search' => ['bad' => 'array'],
            'tab' => ['bad' => 'array'],
            'condition' => ['bad' => 'array'],
            'location_type' => ['bad' => 'array'],
            'per_page' => ['bad' => 'array'],
        ]));

        $response->assertRedirect(route('facilities.index'));
        $response->assertSessionHasErrors(['search', 'tab', 'condition', 'location_type', 'per_page']);
    }

    // =========================================================================
    // 3. Location Business Rules & Archived Room Handling
    // =========================================================================

    public function test_cannot_assign_facility_to_archived_room_on_create(): void
    {
        $admin = $this->createUser('admin', 'admin.archroom.create@example.test');
        $archivedRoom = $this->createRoom('ARCH-101', 'Standard', 800000, now('UTC')->toDateTimeString());

        $response = $this->actingAs($admin)->from(route('facilities.create'))->post(route('facilities.store'), [
            'code' => 'FAC-TO-ARCH',
            'name' => 'AC Kamar Arsip',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => $archivedRoom->id,
        ]);

        $response->assertRedirect(route('facilities.create'));
        $response->assertSessionHasErrors(['room_id']);
        $this->assertDatabaseMissing('facilities', ['code' => 'FAC-TO-ARCH']);
    }

    public function test_cannot_relocate_facility_to_archived_room_on_update(): void
    {
        $admin = $this->createUser('admin', 'admin.archroom.reloc@example.test');
        $activeRoom = $this->createRoom('ACTIVE-101');
        $archivedRoom = $this->createRoom('ARCH-102', 'Standard', 800000, now('UTC')->toDateTimeString());

        $facility = $this->createFacility('FAC-RELOC', 'Meja Rias', 'room', $activeRoom->id);

        $response = $this->actingAs($admin)->from(route('facilities.edit', $facility))->put(route('facilities.update', $facility), [
            'code' => 'FAC-RELOC',
            'name' => 'Meja Rias',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => $archivedRoom->id,
        ]);

        $response->assertRedirect(route('facilities.edit', $facility));
        $response->assertSessionHasErrors(['room_id']);

        $facility->refresh();
        $this->assertEquals($activeRoom->id, $facility->room_id, 'Location must remain in the active room');
    }

    public function test_facility_in_archived_room_can_update_non_location_info_without_relocating(): void
    {
        $admin = $this->createUser('admin', 'admin.archroom.retain@example.test');
        $room = $this->createRoom('ROOM-OLD', 'Standard', 800000);
        $facility = $this->createFacility('FAC-RETAIN', 'Kipas Angin Lama', 'room', $room->id);

        // Room is later archived
        $room->update(['archived_at' => now('UTC')]);
        $this->assertTrue($room->fresh()->isArchived());

        // Admin updates name, condition, notes while keeping room_id the same
        $response = $this->actingAs($admin)->put(route('facilities.update', $facility), [
            'code' => 'FAC-RETAIN',
            'name' => 'Kipas Angin Diperbaiki',
            'condition' => 'broken',
            'notes' => 'Perlu servis baling-baling.',
            'location_type' => 'room',
            'room_id' => $room->id, // Same room ID
        ]);

        $response->assertRedirect(route('facilities.index'));

        $facility->refresh();
        $this->assertEquals('Kipas Angin Diperbaiki', $facility->name);
        $this->assertEquals('broken', $facility->condition);
        $this->assertEquals($room->id, $facility->room_id);
    }

    public function test_facility_with_complaints_cannot_change_location_type_or_location_details(): void
    {
        $admin = $this->createUser('admin', 'admin.complaint.lock@example.test');
        $room1 = $this->createRoom('R-LOCK-1');
        $room2 = $this->createRoom('R-LOCK-2');

        $facilityRoom = $this->createFacility('AC-LOCK', 'AC Utama', 'room', $room1->id);
        $facilityShared = $this->createFacility('WTR-LOCK', 'Dispenser', 'shared', null, 'Dapur Lantai 1');

        // Attach complaints to both facilities
        $this->createComplaint($facilityRoom, 'resolved');
        $this->createComplaint($facilityShared, 'closed_without_action');

        // 1. Attempt room -> room relocation on facilityRoom
        $resp1 = $this->actingAs($admin)->put(route('facilities.update', $facilityRoom), [
            'code' => 'AC-LOCK',
            'name' => 'AC Utama',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => $room2->id,
        ]);
        $resp1->assertSessionHasErrors();
        $this->assertEquals($room1->id, $facilityRoom->fresh()->room_id);

        // 2. Attempt room -> shared relocation on facilityRoom
        $resp2 = $this->actingAs($admin)->put(route('facilities.update', $facilityRoom), [
            'code' => 'AC-LOCK',
            'name' => 'AC Utama',
            'condition' => 'good',
            'location_type' => 'shared',
            'area_name' => 'Area Lobby',
        ]);
        $resp2->assertSessionHasErrors();
        $this->assertEquals('room', $facilityRoom->fresh()->location_type);

        // 3. Attempt shared -> room relocation on facilityShared
        $resp3 = $this->actingAs($admin)->put(route('facilities.update', $facilityShared), [
            'code' => 'WTR-LOCK',
            'name' => 'Dispenser',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => $room1->id,
        ]);
        $resp3->assertSessionHasErrors();
        $this->assertEquals('shared', $facilityShared->fresh()->location_type);

        // 4. Attempt changing area_name on facilityShared
        $resp4 = $this->actingAs($admin)->put(route('facilities.update', $facilityShared), [
            'code' => 'WTR-LOCK',
            'name' => 'Dispenser',
            'condition' => 'good',
            'location_type' => 'shared',
            'area_name' => 'Dapur Lantai 2', // changed
        ]);
        $resp4->assertSessionHasErrors();
        $this->assertEquals('Dapur Lantai 1', $facilityShared->fresh()->area_name);
    }

    public function test_facility_with_complaints_can_update_non_location_fields(): void
    {
        $admin = $this->createUser('admin', 'admin.complaint.editnonloc@example.test');
        $room = $this->createRoom('R-NL-01');
        $facility = $this->createFacility('BED-01', 'Kasur Busa', 'room', $room->id, null, 'good');

        $this->createComplaint($facility, 'resolved');

        // Form submits non-location updates. In HTML forms, disabled inputs are not submitted.
        // We simulate submitting without location fields, or with matching hidden inputs.
        $response = $this->actingAs($admin)->put(route('facilities.update', $facility), [
            'code' => 'BED-01-REV',
            'name' => 'Kasur Busa Tebal',
            'condition' => 'repairing',
            'notes' => 'Sedang diganti sprei dan dibersihkan.',
        ]);

        $response->assertRedirect(route('facilities.index'));

        $facility->refresh();
        $this->assertEquals('BED-01-REV', $facility->code);
        $this->assertEquals('Kasur Busa Tebal', $facility->name);
        $this->assertEquals('repairing', $facility->condition);
        $this->assertEquals('room', $facility->location_type);
        $this->assertEquals($room->id, $facility->room_id);
    }

    public function test_facility_without_complaints_can_relocate_freely(): void
    {
        $admin = $this->createUser('admin', 'admin.freereloc@example.test');
        $room1 = $this->createRoom('R-FR-1');
        $room2 = $this->createRoom('R-FR-2');

        $facility = $this->createFacility('TV-01', 'Smart TV', 'room', $room1->id);

        // 1. Move room1 -> room2
        $this->actingAs($admin)->put(route('facilities.update', $facility), [
            'code' => 'TV-01',
            'name' => 'Smart TV',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => $room2->id,
        ])->assertRedirect(route('facilities.index'));
        $this->assertEquals($room2->id, $facility->fresh()->room_id);

        // 2. Move room2 -> shared
        $this->actingAs($admin)->put(route('facilities.update', $facility), [
            'code' => 'TV-01',
            'name' => 'Smart TV',
            'condition' => 'good',
            'location_type' => 'shared',
            'area_name' => 'Ruang Tamu',
        ])->assertRedirect(route('facilities.index'));
        $facility->refresh();
        $this->assertEquals('shared', $facility->location_type);
        $this->assertNull($facility->room_id);
        $this->assertEquals('Ruang Tamu', $facility->area_name);

        // 3. Change area_name
        $this->actingAs($admin)->put(route('facilities.update', $facility), [
            'code' => 'TV-01',
            'name' => 'Smart TV',
            'condition' => 'good',
            'location_type' => 'shared',
            'area_name' => 'Lobby Lantai 1',
        ])->assertRedirect(route('facilities.index'));
        $this->assertEquals('Lobby Lantai 1', $facility->fresh()->area_name);

        // 4. Move shared -> room1
        $this->actingAs($admin)->put(route('facilities.update', $facility), [
            'code' => 'TV-01',
            'name' => 'Smart TV',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => $room1->id,
        ])->assertRedirect(route('facilities.index'));
        $facility->refresh();
        $this->assertEquals('room', $facility->location_type);
        $this->assertEquals($room1->id, $facility->room_id);
        $this->assertNull($facility->area_name);
    }

    public function test_facility_placement_does_not_alter_room_occupancy_status(): void
    {
        $admin = $this->createUser('admin', 'admin.occupancy.check@example.test');
        $room = $this->createRoom('R-OCC-01');

        $this->assertFalse($room->is_occupied, 'Empty room must be vacant initially');

        // Place a facility in the room
        $this->actingAs($admin)->post(route('facilities.store'), [
            'code' => 'OCC-FAC-1',
            'name' => 'Lemari Pakaian',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => $room->id,
        ]);

        $room->refresh();
        $this->assertFalse($room->is_occupied, 'Placing facility must NOT alter room occupancy status');
    }

    // =========================================================================
    // 4. Referential Integrity & Delete vs Archive (TC-09)
    // =========================================================================

    public function test_facility_without_complaints_can_be_physically_deleted(): void
    {
        $admin = $this->createUser('admin', 'admin.del.clean@example.test');
        $room = $this->createRoom('R-DEL-01');
        $facility = $this->createFacility('DEL-01', 'Barang Bersih', 'room', $room->id);

        $response = $this->actingAs($admin)->delete(route('facilities.destroy', $facility));

        $response->assertRedirect(route('facilities.index'));
        $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);

        $log = ActivityLog::where('module', 'facilities')->where('action', 'delete')->first();
        $this->assertNotNull($log);
        $this->assertEquals('DEL-01', $log->entity_label);
    }

    public function test_facility_with_complaint_history_cannot_be_deleted(): void
    {
        $admin = $this->createUser('admin', 'admin.del.hist@example.test');
        $room = $this->createRoom('R-DEL-02');
        $facility = $this->createFacility('DEL-HIST', 'Barang Berhistori', 'room', $room->id);

        $this->createComplaint($facility, 'resolved');

        $response = $this->actingAs($admin)->delete(route('facilities.destroy', $facility));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('facilities', ['id' => $facility->id]);
    }

    public function test_facility_with_open_or_in_progress_complaints_cannot_be_archived(): void
    {
        $admin = $this->createUser('admin', 'admin.arch.open@example.test');
        $room = $this->createRoom('R-ARCH-01');
        $facilityOpen = $this->createFacility('ARCH-OPEN', 'Barang Open', 'room', $room->id);
        $facilityProgress = $this->createFacility('ARCH-PROG', 'Barang In Progress', 'room', $room->id);

        $this->createComplaint($facilityOpen, 'open');
        $this->createComplaint($facilityProgress, 'in_progress');

        // Attempt archive facility with 'open' complaint
        $resp1 = $this->actingAs($admin)->post(route('facilities.archive', $facilityOpen));
        $resp1->assertSessionHas('error');
        $this->assertNull($facilityOpen->fresh()->archived_at);

        // Attempt archive facility with 'in_progress' complaint
        $resp2 = $this->actingAs($admin)->post(route('facilities.archive', $facilityProgress));
        $resp2->assertSessionHas('error');
        $this->assertNull($facilityProgress->fresh()->archived_at);
    }

    public function test_facility_with_resolved_or_closed_complaints_can_be_archived(): void
    {
        $admin = $this->createUser('admin', 'admin.arch.closed@example.test');
        $room = $this->createRoom('R-ARCH-02');
        $facility = $this->createFacility('ARCH-OK', 'Barang Selesai Keluhan', 'room', $room->id);

        $this->createComplaint($facility, 'resolved');

        $response = $this->actingAs($admin)->post(route('facilities.archive', $facility));

        $response->assertRedirect(route('facilities.index'));
        $this->assertNotNull($facility->fresh()->archived_at);

        $log = ActivityLog::where('module', 'facilities')->where('action', 'archive')->first();
        $this->assertNotNull($log);
        $this->assertEquals('ARCH-OK', $log->entity_label);
    }

    public function test_facility_can_be_unarchived(): void
    {
        $admin = $this->createUser('admin', 'admin.unarch@example.test');
        $room = $this->createRoom('R-UNARCH-01');
        $facility = $this->createFacility('UNARCH-01', 'Barang Buka Arsip', 'room', $room->id, null, 'good', null, now('UTC')->toDateTimeString());

        $this->assertTrue($facility->isArchived());

        $response = $this->actingAs($admin)->post(route('facilities.unarchive', $facility));

        $response->assertRedirect(route('facilities.index'));
        $this->assertNull($facility->fresh()->archived_at);

        $log = ActivityLog::where('module', 'facilities')->where('action', 'unarchive')->first();
        $this->assertNotNull($log);
        $this->assertEquals('UNARCH-01', $log->entity_label);
    }

    public function test_repeated_archive_and_unarchive_actions_are_idempotent_without_duplicate_audits(): void
    {
        $admin = $this->createUser('admin', 'admin.idemp.arch@example.test');
        $room = $this->createRoom('R-IDEMP-01');
        $facility = $this->createFacility('IDEMP-01', 'Barang Idempoten', 'room', $room->id);

        // First archive
        $this->actingAs($admin)->post(route('facilities.archive', $facility));
        $this->assertNotNull($facility->fresh()->archived_at);
        $archiveLogsCount = ActivityLog::where('module', 'facilities')->where('entity_id', $facility->id)->where('action', 'archive')->count();
        $this->assertEquals(1, $archiveLogsCount);

        // Second archive (already archived)
        $this->actingAs($admin)->post(route('facilities.archive', $facility));
        $this->assertEquals(1, ActivityLog::where('module', 'facilities')->where('entity_id', $facility->id)->where('action', 'archive')->count());

        // First unarchive
        $this->actingAs($admin)->post(route('facilities.unarchive', $facility));
        $this->assertNull($facility->fresh()->archived_at);
        $unarchiveLogsCount = ActivityLog::where('module', 'facilities')->where('entity_id', $facility->id)->where('action', 'unarchive')->count();
        $this->assertEquals(1, $unarchiveLogsCount);

        // Second unarchive (already active)
        $this->actingAs($admin)->post(route('facilities.unarchive', $facility));
        $this->assertEquals(1, ActivityLog::where('module', 'facilities')->where('entity_id', $facility->id)->where('action', 'unarchive')->count());
    }

    public function test_rejected_mutations_do_not_persist_data_or_produce_success_audit_logs(): void
    {
        $admin = $this->createUser('admin', 'admin.noaudit.fail@example.test');
        $room = $this->createRoom('R-FAIL-01');
        $facility = $this->createFacility('FAIL-01', 'Barang Tolak', 'room', $room->id);
        $this->createComplaint($facility, 'open');

        $initialLogsCount = ActivityLog::count();

        // Attempt delete (rejected because complaints exist)
        $this->actingAs($admin)->delete(route('facilities.destroy', $facility));
        $this->assertEquals($initialLogsCount, ActivityLog::count());

        // Attempt archive (rejected because open complaint exists)
        $this->actingAs($admin)->post(route('facilities.archive', $facility));
        $this->assertEquals($initialLogsCount, ActivityLog::count());
    }

    // =========================================================================
    // 5. Transactions & Audit Trail Rollback (TC-32)
    // =========================================================================

    public function test_audit_failure_rolls_back_facility_creation(): void
    {
        $admin = $this->createUser('admin', 'admin.rb.create@example.test');
        $room = $this->createRoom('R-RB-01');

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException('Audit DB failure simulation'));
        $this->app->instance(AuditService::class, $mockAudit);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->post(route('facilities.store'), [
                'code' => 'RB-CREATE-01',
                'name' => 'Barang Gagal Audit',
                'condition' => 'good',
                'location_type' => 'room',
                'room_id' => $room->id,
            ]);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertEquals('Audit DB failure simulation', $e->getMessage());
        }

        $this->assertDatabaseMissing('facilities', ['code' => 'RB-CREATE-01']);
    }

    public function test_audit_failure_rolls_back_facility_update(): void
    {
        $admin = $this->createUser('admin', 'admin.rb.update@example.test');
        $room = $this->createRoom('R-RB-02');
        $facility = $this->createFacility('RB-UPD-01', 'Nama Asli', 'room', $room->id, null, 'good');

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException('Audit DB failure simulation'));
        $this->app->instance(AuditService::class, $mockAudit);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->put(route('facilities.update', $facility), [
                'code' => 'RB-UPD-01',
                'name' => 'Nama Baru Gagal',
                'condition' => 'broken',
                'location_type' => 'room',
                'room_id' => $room->id,
            ]);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertEquals('Audit DB failure simulation', $e->getMessage());
        }

        $facility->refresh();
        $this->assertEquals('Nama Asli', $facility->name);
        $this->assertEquals('good', $facility->condition);
    }

    public function test_audit_failure_rolls_back_facility_deletion(): void
    {
        $admin = $this->createUser('admin', 'admin.rb.del@example.test');
        $room = $this->createRoom('R-RB-03');
        $facility = $this->createFacility('RB-DEL-01', 'Barang Batal Hapus', 'room', $room->id);

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException('Audit DB failure simulation'));
        $this->app->instance(AuditService::class, $mockAudit);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->delete(route('facilities.destroy', $facility));
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertEquals('Audit DB failure simulation', $e->getMessage());
        }

        $this->assertDatabaseHas('facilities', ['id' => $facility->id]);
    }

    public function test_audit_failure_rolls_back_facility_archive(): void
    {
        $admin = $this->createUser('admin', 'admin.rb.arch@example.test');
        $room = $this->createRoom('R-RB-04');
        $facility = $this->createFacility('RB-ARCH-01', 'Barang Batal Arsip', 'room', $room->id);

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException('Audit DB failure simulation'));
        $this->app->instance(AuditService::class, $mockAudit);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->post(route('facilities.archive', $facility));
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertEquals('Audit DB failure simulation', $e->getMessage());
        }

        $this->assertNull($facility->fresh()->archived_at);
    }

    public function test_audit_failure_rolls_back_facility_unarchive(): void
    {
        $admin = $this->createUser('admin', 'admin.rb.unarch@example.test');
        $room = $this->createRoom('R-RB-05');
        $facility = $this->createFacility('RB-UNARCH-01', 'Barang Batal Unarchive', 'room', $room->id, null, 'good', null, now('UTC')->toDateTimeString());

        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')->willThrowException(new RuntimeException('Audit DB failure simulation'));
        $this->app->instance(AuditService::class, $mockAudit);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->post(route('facilities.unarchive', $facility));
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertEquals('Audit DB failure simulation', $e->getMessage());
        }

        $this->assertNotNull($facility->fresh()->archived_at);
    }

    // =========================================================================
    // 6. Search, Filters, Detail View, & N+1 Query Elimination
    // =========================================================================

    public function test_facility_search_by_code_and_name_with_grouped_conditions(): void
    {
        $admin = $this->createUser('admin', 'admin.search.fac@example.test');
        $room = $this->createRoom('SEARCH-101');

        $this->createFacility('AC-SRCH', 'AC Panasonic', 'room', $room->id);
        $this->createFacility('KSR-SRCH', 'Kasur Busa Inoac', 'room', $room->id);

        // Search by code
        $resp1 = $this->actingAs($admin)->get(route('facilities.index', ['search' => 'AC-SRCH']));
        $resp1->assertStatus(200);
        $resp1->assertSee('AC Panasonic');
        $resp1->assertDontSee('Kasur Busa Inoac');

        // Search by name
        $resp2 = $this->actingAs($admin)->get(route('facilities.index', ['search' => 'Inoac']));
        $resp2->assertStatus(200);
        $resp2->assertSee('Kasur Busa Inoac');
        $resp2->assertDontSee('AC Panasonic');
    }

    public function test_facility_filters_by_condition_and_location_type(): void
    {
        $admin = $this->createUser('admin', 'admin.filter.fac@example.test');
        $room = $this->createRoom('FLTR-101');

        $this->createFacility('F-GOOD-ROOM', 'Barang Baik Kamar', 'room', $room->id, null, 'good');
        $this->createFacility('F-BROK-SHARED', 'Barang Rusak Shared', 'shared', null, 'Dapur', 'broken');

        // Filter: condition=broken
        $resp1 = $this->actingAs($admin)->get(route('facilities.index', ['condition' => 'broken']));
        $resp1->assertStatus(200);
        $resp1->assertSee('Barang Rusak Shared');
        $resp1->assertDontSee('Barang Baik Kamar');

        // Filter: location_type=room
        $resp2 = $this->actingAs($admin)->get(route('facilities.index', ['location_type' => 'room']));
        $resp2->assertStatus(200);
        $resp2->assertSee('Barang Baik Kamar');
        $resp2->assertDontSee('Barang Rusak Shared');
    }

    public function test_facility_pagination_preserves_query_parameters(): void
    {
        $admin = $this->createUser('admin', 'admin.paginate.fac@example.test');
        $room = $this->createRoom('PG-101');

        for ($i = 1; $i <= 15; $i++) {
            $this->createFacility("PG-FAC-{$i}", "Barang Paginasi {$i}", 'room', $room->id);
        }

        $response = $this->actingAs($admin)->get(route('facilities.index', [
            'search' => 'Barang Paginasi',
            'per_page' => 10,
        ]));

        $response->assertStatus(200);
        $response->assertSee('per_page=10');
        $this->assertTrue(
            str_contains($response->getContent(), 'search=Barang%20Paginasi') ||
            str_contains($response->getContent(), 'search=Barang+Paginasi')
        );
    }

    public function test_facility_show_displays_complaint_history_with_submitter_and_placement(): void
    {
        $admin = $this->createUser('admin', 'admin.show.detail@example.test');
        $room = $this->createRoom('SHOW-101');
        $facility = $this->createFacility('SHOW-FAC-01', 'Water Heater Listrik', 'room', $room->id);

        $resident = $this->createResident('res.show@example.test', 'Ahmad Resident');
        $placement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-01-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        $complaint = $this->createComplaint($facility, 'in_progress', $placement, $resident->user);

        $response = $this->actingAs($admin)->get(route('facilities.show', $facility));

        $response->assertStatus(200);
        $response->assertSee('SHOW-FAC-01');
        $response->assertSee('Water Heater Listrik');
        $response->assertSee('Ahmad Resident');
        $response->assertSee('Diproses');
        $response->assertSee('#' . $complaint->id);
    }

    public function test_index_rendering_does_not_trigger_n_plus_one_queries(): void
    {
        $admin = $this->createUser('admin', 'admin.nplusone.fac@example.test');
        $room = $this->createRoom('N1-FAC-ROOM');

        // Create 8 facilities with various combinations of rooms and complaints
        for ($i = 1; $i <= 8; $i++) {
            $fac = $this->createFacility("N1-FAC-0{$i}", "Fasilitas N1 {$i}", 'room', $room->id);

            if ($i % 2 === 0) {
                $this->createComplaint($fac, 'resolved');
            }
            if ($i % 3 === 0) {
                $this->createComplaint($fac, 'open');
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get(route('facilities.index'));
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Ensure no individual queries were run per facility row
        foreach ($queries as $q) {
            $sql = strtolower($q['query']);
            $this->assertFalse(
                str_contains($sql, 'select * from `complaints` where `complaints`.`facility_id` =') ||
                str_contains($sql, 'select * from `rooms` where `rooms`.`id` ='),
                "Found N+1 query: {$q['query']}"
            );
        }

        $this->assertLessThan(12, count($queries), 'Index rendering triggered excessive N+1 queries');
    }

    public function test_create_facility_rejects_array_inputs_and_prevents_500_without_audit_or_data_mutation(): void
    {
        $admin = $this->createUser('admin', 'admin.array.create@example.test');
        $room = $this->createRoom('ARR-CREATE-101');

        $initialFacCount = Facility::count();
        $initialAuditCount = ActivityLog::where('module', 'facilities')->count();

        // 1. Array code
        $resp1 = $this->actingAs($admin)->post(route('facilities.store'), [
            'code' => ['ARR-CODE-01'],
            'name' => 'Fasilitas Valid',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => $room->id,
        ]);
        $resp1->assertSessionHasErrors(['code']);
        $this->assertEquals($initialFacCount, Facility::count());
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());

        // 2. Array room_id
        $resp2 = $this->actingAs($admin)->post(route('facilities.store'), [
            'code' => 'FAC-VALID-01',
            'name' => 'Fasilitas Valid',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => [$room->id],
        ]);
        $resp2->assertSessionHasErrors(['room_id']);
        $this->assertEquals($initialFacCount, Facility::count());
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());

        // 3. Array location_type
        $resp3 = $this->actingAs($admin)->post(route('facilities.store'), [
            'code' => 'FAC-VALID-02',
            'name' => 'Fasilitas Valid',
            'condition' => 'good',
            'location_type' => ['room'],
            'room_id' => $room->id,
        ]);
        $resp3->assertSessionHasErrors(['location_type']);
        $this->assertEquals($initialFacCount, Facility::count());
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());

        // 4. Array area_name (shared)
        $resp4 = $this->actingAs($admin)->post(route('facilities.store'), [
            'code' => 'FAC-VALID-03',
            'name' => 'Fasilitas Valid',
            'condition' => 'good',
            'location_type' => 'shared',
            'area_name' => ['Lobi Utama'],
        ]);
        $resp4->assertSessionHasErrors(['area_name']);
        $this->assertEquals($initialFacCount, Facility::count());
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());

        // 5. JSON request returns 422, not 500
        $resp5 = $this->actingAs($admin)->postJson(route('facilities.store'), [
            'code' => ['FAC-JSON-ARR'],
            'name' => ['Nama Array'],
            'condition' => ['good'],
            'location_type' => ['room'],
            'room_id' => [$room->id],
        ]);
        $resp5->assertStatus(422);
        $resp5->assertJsonValidationErrors(['code', 'name', 'condition', 'location_type']);
        $this->assertEquals($initialFacCount, Facility::count());
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());
    }

    public function test_update_facility_without_complaints_rejects_array_inputs_without_audit_or_data_mutation(): void
    {
        $admin = $this->createUser('admin', 'admin.array.updatenocomplaint@example.test');
        $room1 = $this->createRoom('ARR-UP1-101');
        $room2 = $this->createRoom('ARR-UP1-102');
        $facility = $this->createFacility('FAC-ARR-UP1', 'Fasilitas Awal', 'room', $room1->id, null, 'good', 'Catatan Awal');

        $initialAuditCount = ActivityLog::where('module', 'facilities')->count();

        // 1. Array code
        $resp1 = $this->actingAs($admin)->put(route('facilities.update', $facility), [
            'code' => ['FAC-ARR-REV'],
            'name' => 'Nama Baru',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => $room2->id,
        ]);
        $resp1->assertSessionHasErrors(['code']);
        $facility->refresh();
        $this->assertEquals('FAC-ARR-UP1', $facility->code);
        $this->assertEquals('Fasilitas Awal', $facility->name);
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());

        // 2. Array room_id
        $resp2 = $this->actingAs($admin)->put(route('facilities.update', $facility), [
            'code' => 'FAC-ARR-UP1',
            'name' => 'Nama Baru',
            'condition' => 'good',
            'location_type' => 'room',
            'room_id' => [$room2->id],
        ]);
        $resp2->assertSessionHasErrors(['room_id']);
        $facility->refresh();
        $this->assertEquals($room1->id, $facility->room_id);
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());

        // 3. Array location_type
        $resp3 = $this->actingAs($admin)->put(route('facilities.update', $facility), [
            'code' => 'FAC-ARR-UP1',
            'name' => 'Nama Baru',
            'condition' => 'good',
            'location_type' => ['shared'],
            'area_name' => 'Lobi Baru',
        ]);
        $resp3->assertSessionHasErrors(['location_type']);
        $facility->refresh();
        $this->assertEquals('room', $facility->location_type);
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());

        // 4. Array area_name on switch to shared
        $resp4 = $this->actingAs($admin)->put(route('facilities.update', $facility), [
            'code' => 'FAC-ARR-UP1',
            'name' => 'Nama Baru',
            'condition' => 'good',
            'location_type' => 'shared',
            'area_name' => ['Lobi Baru'],
        ]);
        $resp4->assertSessionHasErrors(['area_name']);
        $facility->refresh();
        $this->assertEquals('room', $facility->location_type);
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());

        // 5. JSON request returns 422, not 500
        $resp5 = $this->actingAs($admin)->putJson(route('facilities.update', $facility), [
            'code' => ['FAC-ARR-UP1'],
            'name' => ['Nama Array'],
            'condition' => ['good'],
            'location_type' => ['room'],
            'room_id' => [$room2->id],
        ]);
        $resp5->assertStatus(422);
        $resp5->assertJsonValidationErrors(['code', 'name', 'condition', 'location_type']);
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());
    }

    public function test_update_facility_with_complaints_rejects_array_inputs_and_does_not_convert_to_canonical(): void
    {
        $admin = $this->createUser('admin', 'admin.array.complaintlock@example.test');
        $room = $this->createRoom('ARR-COMP-101');
        $facilityRoom = $this->createFacility('FAC-COMP-ARR', 'Kulkas Kamar', 'room', $room->id, null, 'good', 'Catatan');
        $facilityShared = $this->createFacility('FAC-COMP-SH', 'Dispenser Bersama', 'shared', null, 'Dapur Lantai 1', 'good');

        // Attach complaints to both
        $this->createComplaint($facilityRoom, 'resolved');
        $this->createComplaint($facilityShared, 'in_progress');

        $initialAuditCount = ActivityLog::where('module', 'facilities')->count();

        // 1. Send room_id as array matching current room ID [$room->id]
        // This must be REJECTED, NOT cast to integer $room->id and reported as success!
        $resp1 = $this->actingAs($admin)->put(route('facilities.update', $facilityRoom), [
            'code' => 'FAC-COMP-ARR',
            'name' => 'Kulkas Kamar Diperbarui',
            'condition' => 'repairing',
            'room_id' => [$room->id],
        ]);
        $resp1->assertSessionHasErrors(['room_id']);
        $facilityRoom->refresh();
        $this->assertEquals('Kulkas Kamar', $facilityRoom->name, 'Data must NOT be updated when room_id is an array');
        $this->assertEquals('good', $facilityRoom->condition);
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count(), 'No audit log when rejected');

        // 2. Send location_type as array ['room']
        $resp2 = $this->actingAs($admin)->put(route('facilities.update', $facilityRoom), [
            'code' => 'FAC-COMP-ARR',
            'name' => 'Kulkas Kamar Diperbarui',
            'condition' => 'repairing',
            'location_type' => ['room'],
        ]);
        $resp2->assertSessionHasErrors(['location_type']);
        $facilityRoom->refresh();
        $this->assertEquals('Kulkas Kamar', $facilityRoom->name);
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());

        // 3. Send area_name as array on shared facility with complaints ['Dapur Lantai 1']
        // Must be REJECTED, NOT accepted or converted
        $resp3 = $this->actingAs($admin)->put(route('facilities.update', $facilityShared), [
            'code' => 'FAC-COMP-SH',
            'name' => 'Dispenser Baru',
            'condition' => 'repairing',
            'area_name' => ['Dapur Lantai 1'],
        ]);
        $resp3->assertSessionHasErrors(['area_name']);
        $facilityShared->refresh();
        $this->assertEquals('Dispenser Bersama', $facilityShared->name);
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());

        // 4. Send code as array on facility with complaints
        $resp4 = $this->actingAs($admin)->put(route('facilities.update', $facilityRoom), [
            'code' => ['FAC-COMP-REV'],
            'name' => 'Kulkas Kamar',
            'condition' => 'good',
        ]);
        $resp4->assertSessionHasErrors(['code']);
        $facilityRoom->refresh();
        $this->assertEquals('FAC-COMP-ARR', $facilityRoom->code);
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());

        // 5. JSON request returns 422, not 500
        $resp5 = $this->actingAs($admin)->putJson(route('facilities.update', $facilityRoom), [
            'code' => ['FAC-COMP-ARR'],
            'name' => ['Nama Array'],
            'condition' => ['good'],
            'room_id' => [$room->id],
        ]);
        $resp5->assertStatus(422);
        $resp5->assertJsonValidationErrors(['code', 'name', 'condition', 'room_id']);
        $this->assertEquals($initialAuditCount, ActivityLog::where('module', 'facilities')->count());
    }
}
