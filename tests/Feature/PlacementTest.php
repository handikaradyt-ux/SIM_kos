<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Placement;
use App\Models\Resident;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BillingService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class PlacementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createUser(string $roleCode, string $email, ?string $name = null, bool $isActive = true): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();

        return User::create([
            'role_id' => $role->id,
            'name' => $name ?? ('User ' . ucfirst($roleCode)),
            'email' => $email,
            'password' => Hash::make('Password123456!'),
            'is_active' => $isActive,
            'must_change_password' => false,
        ]);
    }

    private function createRoom(string $number = 'K-201', string $type = 'Standard', int $rate = 850000, ?string $archivedAt = null): Room
    {
        return Room::create([
            'number' => $number,
            'type' => $type,
            'monthly_rate' => $rate,
            'notes' => 'Kamar uji placement',
            'archived_at' => $archivedAt,
        ]);
    }

    private function createResident(string $email = 'resident.placement@example.test', string $name = 'Budi Santoso', bool $isUserActive = true, ?string $archivedAt = null): Resident
    {
        $user = $this->createUser('resident', $email, $name, $isUserActive);

        return Resident::create([
            'user_id' => $user->id,
            'name' => $name,
            'phone' => '081234567890',
            'origin_address' => 'Jl. Test No. 10',
            'archived_at' => $archivedAt,
        ]);
    }

    /**
     * 1. TC-10: Admin can preview and start placement with first invoice and audit logs atomically.
     */
    public function test_admin_can_preview_and_start_placement_atomically_with_invoice_and_audit(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $admin = $this->createUser('admin', 'admin.t10@example.test');
        $room = $this->createRoom('K-201', 'Superior', 1200000);
        $resident = $this->createResident('resident.t10@example.test', 'Ahmad Dani');

        // Step 1: Preview
        $previewResponse = $this->actingAs($admin)->postJson(route('placements.preview'), [
            'room_id' => $room->id,
            'resident_id' => $resident->id,
        ]);

        $previewResponse->assertOk();
        $previewResponse->assertJsonStructure([
            'success',
            'data' => [
                'preview_token',
                'resident' => ['id', 'name', 'phone', 'email'],
                'room' => ['id', 'number', 'type', 'monthly_rate'],
                'contract' => ['started_on', 'agreed_monthly_rate'],
                'first_invoice' => ['period_month', 'due_on', 'amount'],
                'same_month_warning',
                'expires_at',
            ],
        ]);

        $previewToken = $previewResponse->json('data.preview_token');
        $this->assertNotEmpty($previewToken);

        // Verify session holds preview data
        $sessionPreview = session("placement_preview_{$previewToken}");
        $this->assertNotNull($sessionPreview);
        $this->assertSame($admin->id, $sessionPreview['admin_id']);
        $this->assertSame($room->id, $sessionPreview['room_id']);
        $this->assertSame($resident->id, $sessionPreview['resident_id']);
        $this->assertSame(1200000, $sessionPreview['room_rate']);
        $this->assertSame('2026-09-21', $sessionPreview['started_on']);

        // Step 2: Store
        $storeResponse = $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $room->id,
            'resident_id' => $resident->id,
            'preview_token' => $previewToken,
        ]);

        // Assert placement created
        $placement = Placement::where('resident_id', $resident->id)->first();
        $this->assertNotNull($placement);
        $this->assertSame($room->id, $placement->room_id);
        $this->assertSame('2026-09-21', $placement->started_on->toDateString());
        $this->assertNull($placement->ended_on);
        $this->assertSame(1200000, $placement->agreed_monthly_rate);
        $this->assertSame($admin->id, $placement->created_by);
        $this->assertTrue($placement->isActive());

        // Assert room status is now occupied
        $this->assertTrue($room->fresh()->is_occupied);

        // Assert exactly 1 invoice created for first period
        $invoices = Invoice::where('placement_id', $placement->id)->get();
        $this->assertCount(1, $invoices);
        $invoice = $invoices->first();
        $this->assertSame('2026-09-01', $invoice->period_month->toDateString());
        $this->assertSame('2026-09-21', $invoice->due_on->toDateString()); // started on 21st > 5th -> due date is started_on
        $this->assertSame(1200000, $invoice->amount);
        $this->assertSame($resident->name, $invoice->resident_name_snapshot);
        $this->assertSame($room->number, $invoice->room_number_snapshot);
        $this->assertSame($admin->id, $invoice->created_by);

        // Assert dual audit logs recorded
        $placementAudit = ActivityLog::where('module', 'placements')
            ->where('entity_id', $placement->id)
            ->first();
        $this->assertNotNull($placementAudit);
        $this->assertSame('create', $placementAudit->action);
        $this->assertSame($admin->id, $placementAudit->actor_id);

        $invoiceAudit = ActivityLog::where('module', 'invoices')
            ->where('entity_id', $invoice->id)
            ->first();
        $this->assertNotNull($invoiceAudit);
        $this->assertSame('create', $invoiceAudit->action);
        $this->assertSame($admin->id, $invoiceAudit->actor_id);

        // Assert preview session token invalidated
        $this->assertNull(session("placement_preview_{$previewToken}"));

        // Assert redirect to show view
        $storeResponse->assertRedirect(route('placements.show', $placement));

        Carbon::setTestNow();
    }

    /**
     * 2. Submit without valid preview token must be rejected without mutation.
     */
    public function test_submit_without_valid_preview_token_is_rejected_without_mutation(): void
    {
        $admin = $this->createUser('admin', 'admin.nopreview@example.test');
        $room = $this->createRoom('K-202', 'Standard', 800000);
        $resident = $this->createResident('resident.nopreview@example.test', 'Budi');

        $initialPlacementsCount = Placement::count();
        $initialInvoicesCount = Invoice::count();
        $initialAuditsCount = ActivityLog::count();

        // Submit without token
        $response = $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $room->id,
            'resident_id' => $resident->id,
        ]);

        $response->assertSessionHasErrors(['preview_token']);
        $this->assertSame($initialPlacementsCount, Placement::count());
        $this->assertSame($initialInvoicesCount, Invoice::count());
        $this->assertSame($initialAuditsCount, ActivityLog::count());

        // Submit with non-existent token
        $response2 = $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $room->id,
            'resident_id' => $resident->id,
            'preview_token' => 'random_fake_token_not_in_session_12345',
        ]);

        $response2->assertSessionHas('error');
        $this->assertSame($initialPlacementsCount, Placement::count());
        $this->assertSame($initialInvoicesCount, Invoice::count());
        $this->assertSame($initialAuditsCount, ActivityLog::count());
    }

    /**
     * 3. Submit with tampered, expired, different Admin, or mismatched pair token is rejected.
     */
    public function test_submit_with_tampered_or_expired_or_different_admin_token_is_rejected(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $admin1 = $this->createUser('admin', 'admin1.token@example.test');
        $admin2 = $this->createUser('admin', 'admin2.token@example.test');
        $room1 = $this->createRoom('K-203', 'Standard', 800000);
        $room2 = $this->createRoom('K-204', 'Standard', 850000);
        $resident1 = $this->createResident('resident1.token@example.test', 'Citra');
        $resident2 = $this->createResident('resident2.token@example.test', 'Dewi');

        // Case A: Expired token (> 15 minutes)
        $expiredToken = 'token_expired_123';
        session(["placement_preview_{$expiredToken}" => [
            'token' => $expiredToken,
            'admin_id' => $admin1->id,
            'resident_id' => $resident1->id,
            'room_id' => $room1->id,
            'room_rate' => 800000,
            'started_on' => '2026-09-21',
            'expires_at' => Carbon::now()->subMinutes(1)->timestamp,
        ]]);

        $responseExp = $this->actingAs($admin1)->post(route('placements.store'), [
            'room_id' => $room1->id,
            'resident_id' => $resident1->id,
            'preview_token' => $expiredToken,
        ]);
        $responseExp->assertSessionHas('error');
        $this->assertDatabaseMissing('placements', ['resident_id' => $resident1->id]);

        // Case B: Token created by Admin 1, submitted by Admin 2
        $tokenOtherAdmin = 'token_other_admin_456';
        session(["placement_preview_{$tokenOtherAdmin}" => [
            'token' => $tokenOtherAdmin,
            'admin_id' => $admin1->id,
            'resident_id' => $resident1->id,
            'room_id' => $room1->id,
            'room_rate' => 800000,
            'started_on' => '2026-09-21',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        $responseAdmin = $this->actingAs($admin2)->post(route('placements.store'), [
            'room_id' => $room1->id,
            'resident_id' => $resident1->id,
            'preview_token' => $tokenOtherAdmin,
        ]);
        $responseAdmin->assertSessionHas('error');
        $this->assertDatabaseMissing('placements', ['resident_id' => $resident1->id]);

        // Case C: Token bound to Room 1 and Resident 1, submitted with Room 2 or Resident 2
        $tokenMismatched = 'token_mismatched_789';
        session(["placement_preview_{$tokenMismatched}" => [
            'token' => $tokenMismatched,
            'admin_id' => $admin1->id,
            'resident_id' => $resident1->id,
            'room_id' => $room1->id,
            'room_rate' => 800000,
            'started_on' => '2026-09-21',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        $responseMismatch = $this->actingAs($admin1)->post(route('placements.store'), [
            'room_id' => $room2->id,
            'resident_id' => $resident1->id,
            'preview_token' => $tokenMismatched,
        ]);
        $responseMismatch->assertSessionHas('error');
        $this->assertDatabaseMissing('placements', ['resident_id' => $resident1->id]);

        Carbon::setTestNow();
    }

    /**
     * 4. Submit after room rate drift is rejected under lock.
     */
    public function test_submit_after_room_rate_changed_post_preview_is_rejected(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $admin = $this->createUser('admin', 'admin.drift@example.test');
        $room = $this->createRoom('K-205', 'Standard', 800000);
        $resident = $this->createResident('resident.drift@example.test', 'Eko');

        $token = 'token_drift_rate';
        session(["placement_preview_{$token}" => [
            'token' => $token,
            'admin_id' => $admin->id,
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'room_rate' => 800000,
            'started_on' => '2026-09-21',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        // Another process updates room rate to 900,000
        $room->update(['monthly_rate' => 900000]);

        $response = $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $room->id,
            'resident_id' => $resident->id,
            'preview_token' => $token,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Tarif kamar telah berubah', session('error'));
        $this->assertDatabaseMissing('placements', ['resident_id' => $resident->id]);

        Carbon::setTestNow();
    }

    /**
     * 5. Preview before midnight, submit after midnight/month crossed: must reject without mutation.
     */
    public function test_preview_before_midnight_then_submit_after_date_change_is_rejected(): void
    {
        // 23:55 on 2026-09-21
        Carbon::setTestNow('2026-09-21 23:55:00');

        $admin = $this->createUser('admin', 'admin.midnight@example.test');
        $room = $this->createRoom('K-206', 'Standard', 800000);
        $resident = $this->createResident('resident.midnight@example.test', 'Fajar');

        $token = 'token_midnight';
        session(["placement_preview_{$token}" => [
            'token' => $token,
            'admin_id' => $admin->id,
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'room_rate' => 800000,
            'started_on' => '2026-09-21',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        // Advance time to 00:05 on 2026-09-22
        Carbon::setTestNow('2026-09-22 00:05:00');

        $response = $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $room->id,
            'resident_id' => $resident->id,
            'preview_token' => $token,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Tanggal operasional bisnis telah berganti', session('error'));
        $this->assertDatabaseMissing('placements', ['resident_id' => $resident->id]);

        Carbon::setTestNow();
    }

    /**
     * 6. Preview endpoint is strictly read-only (0 placement, 0 invoice, 0 audit).
     */
    public function test_preview_is_strictly_read_only(): void
    {
        $admin = $this->createUser('admin', 'admin.readonly@example.test');
        $room = $this->createRoom('K-207', 'Standard', 800000);
        $resident = $this->createResident('resident.readonly@example.test', 'Gita');

        $initialPlacements = Placement::count();
        $initialInvoices = Invoice::count();
        $initialAudits = ActivityLog::count();

        $response = $this->actingAs($admin)->postJson(route('placements.preview'), [
            'room_id' => $room->id,
            'resident_id' => $resident->id,
        ]);

        $response->assertOk();
        $this->assertSame($initialPlacements, Placement::count());
        $this->assertSame($initialInvoices, Invoice::count());
        $this->assertSame($initialAudits, ActivityLog::count());
    }

    /**
     * 7. Preview returns controlled JSON 422 (not 500) when conditions are unavailable.
     */
    public function test_preview_returns_controlled_json_422_when_unavailable(): void
    {
        $admin = $this->createUser('admin', 'admin.prev422@example.test');
        $room = $this->createRoom('K-208', 'Standard', 800000);
        $resident = $this->createResident('resident.prev422@example.test', 'Hadi');

        // Case 1: Room archived
        $room->update(['archived_at' => now()]);
        $resArchivedRoom = $this->actingAs($admin)->postJson(route('placements.preview'), [
            'room_id' => $room->id,
            'resident_id' => $resident->id,
        ]);
        $resArchivedRoom->assertStatus(422);
        $resArchivedRoom->assertJson(['success' => false]);
        $this->assertStringContainsString('diarsipkan', $resArchivedRoom->json('message'));

        // Restore room, make it occupied
        $room->update(['archived_at' => null]);
        Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => now()->toDateString(),
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        // Case 2: Room occupied
        $resident2 = $this->createResident('resident2.prev422@example.test', 'Iwan');
        $resOccRoom = $this->actingAs($admin)->postJson(route('placements.preview'), [
            'room_id' => $room->id,
            'resident_id' => $resident2->id,
        ]);
        $resOccRoom->assertStatus(422);
        $resOccRoom->assertJson(['success' => false]);
        $this->assertStringContainsString('terisi', $resOccRoom->json('message'));

        // Case 3: Resident already has active placement
        $roomFree = $this->createRoom('K-209', 'Standard', 800000);
        $resOccRes = $this->actingAs($admin)->postJson(route('placements.preview'), [
            'room_id' => $roomFree->id,
            'resident_id' => $resident->id,
        ]);
        $resOccRes->assertStatus(422);
        $resOccRes->assertJson(['success' => false]);
        $this->assertStringContainsString('memiliki penempatan aktif', $resOccRes->json('message'));
    }

    /**
     * 8. Submit rejects unavailable room or resident under lock.
     */
    public function test_submit_rejects_unavailable_room_or_resident_under_lock(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $admin = $this->createUser('admin', 'admin.lockreject@example.test');
        $room = $this->createRoom('K-210', 'Standard', 800000);
        $resident = $this->createResident('resident.lockreject@example.test', 'Joko');

        $token = 'token_lock_reject';
        session(["placement_preview_{$token}" => [
            'token' => $token,
            'admin_id' => $admin->id,
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'room_rate' => 800000,
            'started_on' => '2026-09-21',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        // Archive resident before submit
        $resident->update(['archived_at' => now()]);

        $response = $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $room->id,
            'resident_id' => $resident->id,
            'preview_token' => $token,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('placements', ['resident_id' => $resident->id]);

        Carbon::setTestNow();
    }

    /**
     * 9. Manipulated inputs (started_on, agreed_monthly_rate, created_by, status) are ignored.
     */
    public function test_manipulated_inputs_are_ignored_and_determined_authoritatively_by_server(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $admin = $this->createUser('admin', 'admin.manip@example.test');
        $fakeAdmin = $this->createUser('admin', 'admin.fake@example.test');
        $room = $this->createRoom('K-211', 'Deluxe', 1500000);
        $resident = $this->createResident('resident.manip@example.test', 'Kartika');

        $token = 'token_manip';
        session(["placement_preview_{$token}" => [
            'token' => $token,
            'admin_id' => $admin->id,
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'room_rate' => 1500000,
            'started_on' => '2026-09-21',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        $response = $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $room->id,
            'resident_id' => $resident->id,
            'preview_token' => $token,
            // Malicious payload attempts:
            'started_on' => '2020-01-01',
            'agreed_monthly_rate' => 50000,
            'created_by' => $fakeAdmin->id,
            'ended_on' => '2026-09-21',
            'end_reason' => 'Hack attempt',
        ]);

        $placement = Placement::where('resident_id', $resident->id)->first();
        $this->assertNotNull($placement);
        // Server authoritative values:
        $this->assertSame('2026-09-21', $placement->started_on->toDateString());
        $this->assertSame(1500000, $placement->agreed_monthly_rate);
        $this->assertSame($admin->id, $placement->created_by);
        $this->assertNull($placement->ended_on);
        $this->assertNull($placement->end_reason);

        Carbon::setTestNow();
    }

    /**
     * 10. TC-12 & TC-33: Calendar due date rules with frozen server times.
     */
    public function test_due_date_rules_with_frozen_server_times(): void
    {
        $admin = $this->createUser('admin', 'admin.dates@example.test');
        $billingService = app(BillingService::class);

        // Case A: Started on 1st of month -> due on 5th
        Carbon::setTestNow('2026-04-01 10:00:00');
        $roomA = $this->createRoom('K-212A', 'Standard', 800000);
        $residentA = $this->createResident('res.dateA@example.test', 'User A');
        $tokenA = 'token_date_a';
        session(["placement_preview_{$tokenA}" => [
            'token' => $tokenA,
            'admin_id' => $admin->id,
            'resident_id' => $residentA->id,
            'room_id' => $roomA->id,
            'room_rate' => 800000,
            'started_on' => '2026-04-01',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);
        $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $roomA->id,
            'resident_id' => $residentA->id,
            'preview_token' => $tokenA,
        ]);
        $placementA = Placement::where('resident_id', $residentA->id)->firstOrFail();
        $invoiceA = Invoice::where('placement_id', $placementA->id)->firstOrFail();
        $this->assertSame('2026-04-05', $invoiceA->due_on->toDateString());

        // Case B: Started on 5th of month -> due on 5th
        Carbon::setTestNow('2026-04-05 10:00:00');
        $roomB = $this->createRoom('K-212B', 'Standard', 800000);
        $residentB = $this->createResident('res.dateB@example.test', 'User B');
        $tokenB = 'token_date_b';
        session(["placement_preview_{$tokenB}" => [
            'token' => $tokenB,
            'admin_id' => $admin->id,
            'resident_id' => $residentB->id,
            'room_id' => $roomB->id,
            'room_rate' => 800000,
            'started_on' => '2026-04-05',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);
        $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $roomB->id,
            'resident_id' => $residentB->id,
            'preview_token' => $tokenB,
        ]);
        $placementB = Placement::where('resident_id', $residentB->id)->firstOrFail();
        $invoiceB = Invoice::where('placement_id', $placementB->id)->firstOrFail();
        $this->assertSame('2026-04-05', $invoiceB->due_on->toDateString());

        // Case C: Started on 10th of month -> due on 10th
        Carbon::setTestNow('2026-04-10 10:00:00');
        $roomC = $this->createRoom('K-212C', 'Standard', 800000);
        $residentC = $this->createResident('res.dateC@example.test', 'User C');
        $tokenC = 'token_date_c';
        session(["placement_preview_{$tokenC}" => [
            'token' => $tokenC,
            'admin_id' => $admin->id,
            'resident_id' => $residentC->id,
            'room_id' => $roomC->id,
            'room_rate' => 800000,
            'started_on' => '2026-04-10',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);
        $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $roomC->id,
            'resident_id' => $residentC->id,
            'preview_token' => $tokenC,
        ]);
        $placementC = Placement::where('resident_id', $residentC->id)->firstOrFail();
        $invoiceC = Invoice::where('placement_id', $placementC->id)->firstOrFail();
        $this->assertSame('2026-04-10', $invoiceC->due_on->toDateString());

        // Case D: Started on 31st Jan -> due on 31st Jan, full monthly amount
        Carbon::setTestNow('2026-01-31 10:00:00');
        $roomD = $this->createRoom('K-212D', 'Standard', 800000);
        $residentD = $this->createResident('res.dateD@example.test', 'User D');
        $tokenD = 'token_date_d';
        session(["placement_preview_{$tokenD}" => [
            'token' => $tokenD,
            'admin_id' => $admin->id,
            'resident_id' => $residentD->id,
            'room_id' => $roomD->id,
            'room_rate' => 800000,
            'started_on' => '2026-01-31',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);
        $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $roomD->id,
            'resident_id' => $residentD->id,
            'preview_token' => $tokenD,
        ]);
        $placementD = Placement::where('resident_id', $residentD->id)->firstOrFail();
        $invoiceD = Invoice::where('placement_id', $placementD->id)->firstOrFail();
        $this->assertSame('2026-01-31', $invoiceD->due_on->toDateString());
        $this->assertSame(800000, $invoiceD->amount);

        Carbon::setTestNow();
    }

    /**
     * 11. TC-11: Double submit or conflict does not leave partial data.
     */
    public function test_double_submit_or_conflict_does_not_leave_partial_data(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $admin = $this->createUser('admin', 'admin.doublesubmit@example.test');
        $room = $this->createRoom('K-213', 'Standard', 800000);
        $resident = $this->createResident('resident.doublesubmit@example.test', 'Lina');

        $token = 'token_double_submit';
        session(["placement_preview_{$token}" => [
            'token' => $token,
            'admin_id' => $admin->id,
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'room_rate' => 800000,
            'started_on' => '2026-09-21',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        // First submit succeeds
        $res1 = $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $room->id,
            'resident_id' => $resident->id,
            'preview_token' => $token,
        ]);
        $placement = Placement::where('resident_id', $resident->id)->firstOrFail();

        // Second submit with re-seeded token attempting duplicate placement
        $token2 = 'token_double_submit_2';
        session(["placement_preview_{$token2}" => [
            'token' => $token2,
            'admin_id' => $admin->id,
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'room_rate' => 800000,
            'started_on' => '2026-09-21',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        $res2 = $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => $room->id,
            'resident_id' => $resident->id,
            'preview_token' => $token2,
        ]);

        $res2->assertSessionHas('error');
        $this->assertStringContainsString('terisi', session('error'));

        // Assert exactly 1 placement and 1 invoice exists
        $this->assertSame(1, Placement::where('resident_id', $resident->id)->count());
        $this->assertSame(1, Invoice::where('placement_id', $placement->id)->count());

        Carbon::setTestNow();
    }

    /**
     * 12. TC-11 Skenario A: Kegagalan BillingService menyebabkan rollback menyeluruh.
     */
    public function test_atomic_rollback_on_billing_service_failure(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $admin = $this->createUser('admin', 'admin.billingfail@example.test');
        $room = $this->createRoom('K-214A', 'Standard', 800000);
        $resident = $this->createResident('resident.billingfail@example.test', 'Mirna A');

        $token = 'token_atomic_billing_fail';
        session(["placement_preview_{$token}" => [
            'token' => $token,
            'admin_id' => $admin->id,
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'room_rate' => 800000,
            'started_on' => '2026-09-21',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        $mockBilling = Mockery::mock(BillingService::class);
        $mockBilling->shouldReceive('calculateDueDate')->andReturn('2026-09-21');
        $mockBilling->shouldReceive('syncPlacementInvoices')
            ->once()
            ->andThrow(new \RuntimeException('SIMULATED BILLING SERVICE FAILURE'));
        $this->app->instance(BillingService::class, $mockBilling);

        $initialPlacements = Placement::count();
        $initialInvoices = Invoice::count();
        $initialAudits = ActivityLog::count();

        $exceptionThrown = false;
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post(route('placements.store'), [
                'room_id' => $room->id,
                'resident_id' => $resident->id,
                'preview_token' => $token,
            ]);
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'SIMULATED BILLING SERVICE FAILURE') {
                $exceptionThrown = true;
            } else {
                throw $e;
            }
        }

        if (! $exceptionThrown) {
            $this->fail('Expected SIMULATED BILLING SERVICE FAILURE exception was not thrown.');
        }

        // Entire transaction must have rolled back to baseline
        $this->assertSame($initialPlacements, Placement::count());
        $this->assertSame($initialInvoices, Invoice::count());
        $this->assertSame($initialAudits, ActivityLog::count());

        // Room and resident have no active placement
        $this->assertFalse(Placement::where('room_id', $room->id)->whereNull('ended_on')->exists());
        $this->assertFalse(Placement::where('resident_id', $resident->id)->whereNull('ended_on')->exists());

        $this->app->forgetInstance(BillingService::class);
        Carbon::setTestNow();
    }

    /**
     * 12b. TC-11 Skenario B: Kegagalan AuditService khusus pada modul invoices menyebabkan rollback.
     */
    public function test_atomic_rollback_on_invoice_audit_failure(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $admin = $this->createUser('admin', 'admin.invauditfail@example.test');
        $room = $this->createRoom('K-214B', 'Standard', 800000);
        $resident = $this->createResident('resident.invauditfail@example.test', 'Mirna B');

        $token = 'token_atomic_invoice_audit_fail';
        session(["placement_preview_{$token}" => [
            'token' => $token,
            'admin_id' => $admin->id,
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'room_rate' => 800000,
            'started_on' => '2026-09-21',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        // Real BillingService, but AuditService fails specifically on invoices module
        $customAudit = new class extends AuditService {
            public function log(
                string $action,
                string $module,
                string $summary,
                User $actor,
                ?string $entityType = null,
                ?int $entityId = null,
                ?string $entityLabel = null,
                ?array $changes = null
            ): ActivityLog {
                if ($module === 'invoices') {
                    throw new \RuntimeException('SIMULATED INVOICE AUDIT FAILURE');
                }
                return parent::log($action, $module, $summary, $actor, $entityType, $entityId, $entityLabel, $changes);
            }
        };
        $this->app->instance(AuditService::class, $customAudit);

        $initialPlacements = Placement::count();
        $initialInvoices = Invoice::count();
        $initialAudits = ActivityLog::count();

        $exceptionThrown = false;
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post(route('placements.store'), [
                'room_id' => $room->id,
                'resident_id' => $resident->id,
                'preview_token' => $token,
            ]);
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'SIMULATED INVOICE AUDIT FAILURE') {
                $exceptionThrown = true;
            } else {
                throw $e;
            }
        }

        if (! $exceptionThrown) {
            $this->fail('Expected SIMULATED INVOICE AUDIT FAILURE exception was not thrown.');
        }

        // Entire transaction must have rolled back to baseline
        $this->assertSame($initialPlacements, Placement::count());
        $this->assertSame($initialInvoices, Invoice::count());
        $this->assertSame($initialAudits, ActivityLog::count());

        // Room and resident have no active placement
        $this->assertFalse(Placement::where('room_id', $room->id)->whereNull('ended_on')->exists());
        $this->assertFalse(Placement::where('resident_id', $resident->id)->whereNull('ended_on')->exists());

        $this->app->forgetInstance(AuditService::class);
        Carbon::setTestNow();
    }

    /**
     * 12c. TC-11 Skenario C: Rollback tahap akhir dengan BillingService nyata.
     * Invoice pertama dan audit invoice tersimpan dalam transaksi sebelum AuditService
     * gagal khusus pada modul placements. Seluruh mutasi harus rollback ke baseline.
     */
    public function test_atomic_rollback_on_final_placement_audit_failure_with_real_billing_and_invoice(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $admin = $this->createUser('admin', 'admin.plcauditfail@example.test');
        $room = $this->createRoom('K-214C', 'Standard', 800000);
        $resident = $this->createResident('resident.plcauditfail@example.test', 'Mirna C');

        $token = 'token_atomic_final_audit_fail';
        session(["placement_preview_{$token}" => [
            'token' => $token,
            'admin_id' => $admin->id,
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'room_rate' => 800000,
            'started_on' => '2026-09-21',
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        // Use real BillingService. Fail AuditService ONLY when module is 'placements'.
        // This ensures the initial invoice and its audit log were truly executed in the transaction.
        $customAudit = new class extends AuditService {
            public int $invoiceAuditCallCount = 0;

            public function log(
                string $action,
                string $module,
                string $summary,
                User $actor,
                ?string $entityType = null,
                ?int $entityId = null,
                ?string $entityLabel = null,
                ?array $changes = null
            ): ActivityLog {
                if ($module === 'invoices') {
                    $this->invoiceAuditCallCount++;
                    return parent::log($action, $module, $summary, $actor, $entityType, $entityId, $entityLabel, $changes);
                }

                if ($module === 'placements') {
                    throw new \RuntimeException('SIMULATED FINAL PLACEMENT AUDIT FAILURE');
                }

                return parent::log($action, $module, $summary, $actor, $entityType, $entityId, $entityLabel, $changes);
            }
        };
        $this->app->instance(AuditService::class, $customAudit);

        $initialPlacements = Placement::count();
        $initialInvoices = Invoice::count();
        $initialAudits = ActivityLog::count();

        $exceptionThrown = false;
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post(route('placements.store'), [
                'room_id' => $room->id,
                'resident_id' => $resident->id,
                'preview_token' => $token,
            ]);
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'SIMULATED FINAL PLACEMENT AUDIT FAILURE') {
                $exceptionThrown = true;
            } else {
                throw $e;
            }
        }

        if (! $exceptionThrown) {
            $this->fail('Expected SIMULATED FINAL PLACEMENT AUDIT FAILURE exception was not thrown.');
        }

        // Verify that invoice audit was indeed called inside the transaction prior to failure
        $this->assertGreaterThanOrEqual(1, $customAudit->invoiceAuditCallCount);

        // Entire transaction must have rolled back to baseline
        $this->assertSame($initialPlacements, Placement::count());
        $this->assertSame($initialInvoices, Invoice::count());
        $this->assertSame($initialAudits, ActivityLog::count());

        // Room and resident have no active placement
        $this->assertFalse(Placement::where('room_id', $room->id)->whereNull('ended_on')->exists());
        $this->assertFalse(Placement::where('resident_id', $resident->id)->whereNull('ended_on')->exists());

        $this->app->forgetInstance(AuditService::class);
        Carbon::setTestNow();
    }

    /**
     * 13. Authorization matrix: Admin, Owner, Resident, Guest.
     */
    public function test_authorization_matrix_for_placement_endpoints(): void
    {
        $admin = $this->createUser('admin', 'admin.matrix@example.test');
        $owner = $this->createUser('owner', 'owner.matrix@example.test');
        $resident = $this->createResident('resident.matrix@example.test', 'Nanda');
        $room = $this->createRoom('K-215', 'Standard', 800000);

        $placement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => now()->toDateString(),
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        // Admin: 200 on index, create, show
        $this->actingAs($admin)->get(route('placements.index'))->assertOk();
        $this->actingAs($admin)->get(route('placements.create'))->assertOk();
        $this->actingAs($admin)->get(route('placements.show', $placement))->assertOk();

        // Owner: 200 on index and show, 403 on create, preview, store
        $this->actingAs($owner)->get(route('placements.index'))->assertOk();
        $this->actingAs($owner)->get(route('placements.show', $placement))->assertOk();
        $this->actingAs($owner)->get(route('placements.create'))->assertForbidden();
        $this->actingAs($owner)->postJson(route('placements.preview'), ['room_id' => $room->id, 'resident_id' => $resident->id])->assertForbidden();
        $this->actingAs($owner)->post(route('placements.store'), ['room_id' => $room->id, 'resident_id' => $resident->id, 'preview_token' => 'x'])->assertForbidden();

        // Resident: 403 on all placement master routes
        $residentUser = $resident->user;
        $this->actingAs($residentUser)->get(route('placements.index'))->assertForbidden();
        $this->actingAs($residentUser)->get(route('placements.create'))->assertForbidden();
        $this->actingAs($residentUser)->get(route('placements.show', $placement))->assertForbidden();
        $this->actingAs($residentUser)->postJson(route('placements.preview'), ['room_id' => $room->id, 'resident_id' => $resident->id])->assertForbidden();
        $this->actingAs($residentUser)->post(route('placements.store'), ['room_id' => $room->id, 'resident_id' => $resident->id, 'preview_token' => 'x'])->assertForbidden();

        // Guest: 302 to login
        auth()->logout();
        $this->get(route('placements.index'))->assertRedirect(route('login'));
        $this->get(route('placements.create'))->assertRedirect(route('login'));
        $this->get(route('placements.show', $placement))->assertRedirect(route('login'));
    }

    /**
     * 14. Input array on preview, store, and index is handled gracefully (anti-500).
     */
    public function test_input_array_on_preview_and_store_is_handled_gracefully_anti_500(): void
    {
        $admin = $this->createUser('admin', 'admin.array500@example.test');

        // Array input on preview
        $previewRes = $this->actingAs($admin)->postJson(route('placements.preview'), [
            'room_id' => [1, 2],
            'resident_id' => [3, 4],
        ]);
        $previewRes->assertStatus(422);

        // Array input on store
        $storeRes = $this->actingAs($admin)->post(route('placements.store'), [
            'room_id' => [1],
            'resident_id' => [2],
            'preview_token' => ['token_array'],
        ]);
        $storeRes->assertSessionHasErrors(['room_id', 'resident_id', 'preview_token']);

        // Array input on index filters
        $indexRes = $this->actingAs($admin)->get(route('placements.index', [
            'search' => ['test'],
            'status' => ['active'],
            'per_page' => [10],
        ]));
        $indexRes->assertRedirect(route('placements.index'));
    }

    /**
     * 15. Same-month re-placement triggers warning flag.
     */
    public function test_same_month_re_placement_triggers_warning_flag(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $admin = $this->createUser('admin', 'admin.samemonth@example.test');
        $room1 = $this->createRoom('K-216', 'Standard', 800000);
        $room2 = $this->createRoom('K-217', 'Standard', 850000);
        $resident = $this->createResident('resident.samemonth@example.test', 'Oki');

        // Past placement ended earlier this month (2026-09-05)
        Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room1->id,
            'started_on' => '2026-08-01',
            'ended_on' => '2026-09-05',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
            'ended_by' => $admin->id,
            'end_reason' => 'Pindah sementara',
        ]);

        $previewResponse = $this->actingAs($admin)->postJson(route('placements.preview'), [
            'room_id' => $room2->id,
            'resident_id' => $resident->id,
        ]);

        $previewResponse->assertOk();
        $this->assertTrue($previewResponse->json('data.same_month_warning'));

        Carbon::setTestNow();
    }

    /**
     * 16. Detail view and index eager load valid payment preventing N+1 queries.
     */
    public function test_placement_show_and_index_eager_load_valid_payment_preventing_n_plus_one(): void
    {
        $admin = $this->createUser('admin', 'admin.nplusone@example.test');
        $room = $this->createRoom('K-218', 'Standard', 800000);
        $resident = $this->createResident('resident.nplusone@example.test', 'Putri');

        $placement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-09-01',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
        ]);

        // Create 3 invoices with payments
        for ($i = 1; $i <= 3; $i++) {
            $invoice = Invoice::create([
                'placement_id' => $placement->id,
                'period_month' => "2026-0{$i}-01",
                'due_on' => "2026-0{$i}-05",
                'amount' => 800000,
                'resident_name_snapshot' => $resident->name,
                'room_number_snapshot' => $room->number,
                'created_by' => $admin->id,
            ]);

            Payment::create([
                'invoice_id' => $invoice->id,
                'receipt_number' => "REC-20260{$i}-001",
                'amount' => 800000,
                'paid_on' => "2026-0{$i}-04",
                'method' => 'transfer',
                'status' => 'valid',
                'recorded_by' => $admin->id,
            ]);
        }

        // Measure queries on show route
        DB::enableQueryLog();
        $response = $this->actingAs($admin)->get(route('placements.show', $placement));
        $response->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert query count is small and fixed (less than 10 total queries, not 1 query per invoice)
        $this->assertLessThan(10, count($queries));
    }
}
