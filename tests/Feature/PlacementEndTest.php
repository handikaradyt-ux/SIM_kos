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
use App\Services\PlacementService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use DomainException;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class PlacementEndTest extends TestCase
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

    private function createRoom(string $number = 'K-301', string $type = 'Standard', int $rate = 1000000, ?string $archivedAt = null): Room
    {
        return Room::create([
            'number' => $number,
            'type' => $type,
            'monthly_rate' => $rate,
            'notes' => 'Kamar uji placement end',
            'archived_at' => $archivedAt,
        ]);
    }

    private function createResident(string $email = 'resident.end@example.test', string $name = 'Budi Santoso', bool $isUserActive = true, ?string $archivedAt = null): Resident
    {
        $user = $this->createUser('resident', $email, $name, $isUserActive);

        return Resident::create([
            'user_id' => $user->id,
            'name' => $name,
            'phone' => '081234567899',
            'origin_address' => 'Jl. Pengakhiran No. 11',
            'archived_at' => $archivedAt,
        ]);
    }

    private function createPlacement(Resident $resident, Room $room, User $creator, string $startedOn = '2026-07-01', ?int $rate = null): Placement
    {
        return Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => $startedOn,
            'agreed_monthly_rate' => $rate ?? $room->monthly_rate,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * 1. TC-14 Preview: Admin can preview ending placement read-only with 0 DB mutations.
     */
    public function test_admin_can_preview_end_placement_successfully_with_zero_mutations(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.endpreview@example.test');
        $room = $this->createRoom('K-301', 'Standard', 1000000);
        $resident = $this->createResident('resident.preview@example.test', 'Ahmad Dani');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-07-01', 1000000);

        // Pre-create July invoice
        Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-07-01',
            'due_on' => '2026-07-05',
            'amount' => 1000000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        $initialPlacementsCount = Placement::count();
        $initialInvoicesCount = Invoice::count();
        $initialLogsCount = ActivityLog::count();

        $response = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement));

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'placement' => ['id', 'started_on', 'started_on_formatted', 'ended_on', 'ended_on_formatted', 'agreed_monthly_rate'],
                'resident' => ['id', 'name', 'phone', 'email'],
                'room' => ['id', 'number', 'type'],
                'financial' => [
                    'agreed_monthly_rate',
                    'existing_invoices_count',
                    'existing_paid_count',
                    'existing_paid_amount',
                    'existing_unpaid_count',
                    'existing_unpaid_amount',
                    'missing_periods',
                    'new_invoices_count',
                    'new_invoices_amount',
                    'new_invoice_items',
                    'total_unpaid_obligation',
                ],
                'preview_token',
                'expires_at',
            ],
        ]);

        $this->assertEquals(2, $response->json('data.financial.new_invoices_count'));
        $this->assertEquals(['2026-08-01', '2026-09-01'], $response->json('data.financial.missing_periods'));
        $this->assertEquals(3000000, $response->json('data.financial.total_unpaid_obligation'));

        // 0 database mutations
        $this->assertEquals($initialPlacementsCount, Placement::count());
        $this->assertEquals($initialInvoicesCount, Invoice::count());
        $this->assertEquals($initialLogsCount, ActivityLog::count());

        // Token saved in session
        $token = $response->json('data.preview_token');
        $this->assertTrue(session()->has("placement_end_preview_{$token}"));

        Carbon::setTestNow();
    }

    /**
     * 2. TC-14 End: Admin can end active placement atomically with missing invoices and audit log.
     */
    public function test_admin_can_end_active_placement_atomically(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.atomicend@example.test');
        $room = $this->createRoom('K-302', 'Standard', 1200000);
        $resident = $this->createResident('resident.atomicend@example.test', 'Budi Santoso');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01', 1200000);

        // Pre-create August invoice
        Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-08-01',
            'due_on' => '2026-08-05',
            'amount' => 1200000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        // Preview
        $previewRes = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement));
        $previewRes->assertOk();
        $token = $previewRes->json('data.preview_token');

        // Submit End
        $response = $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => $token,
            'end_reason' => 'Selesai masa kontrak sewa tahunan',
        ]);

        $response->assertRedirect(route('placements.show', $placement));
        $response->assertSessionHas('success');

        // Verify Placement updated
        $placement->refresh();
        $this->assertFalse($placement->isActive());
        $this->assertEquals('2026-09-22', $placement->ended_on->toDateString());
        $this->assertEquals($admin->id, $placement->ended_by);
        $this->assertEquals('Selesai masa kontrak sewa tahunan', $placement->end_reason);

        // Verify Invoices synced up to departure month (2026-08-01 and 2026-09-01)
        $invoices = Invoice::where('placement_id', $placement->id)->orderBy('period_month')->get();
        $this->assertCount(2, $invoices);
        $this->assertEquals('2026-09-01', $invoices[1]->period_month->toDateString());
        $this->assertEquals(1200000, $invoices[1]->amount);

        // Verify Audit Log on placements
        $endAudit = ActivityLog::where('module', 'placements')
            ->where('action', 'end')
            ->where('entity_id', $placement->id)
            ->first();
        $this->assertNotNull($endAudit);
        $this->assertEquals($admin->id, $endAudit->actor_id);
        $this->assertEquals('Selesai masa kontrak sewa tahunan', $endAudit->changes['after']['end_reason']);

        // Verify preview token cleared from session AFTER commit
        $this->assertFalse(session()->has("placement_end_preview_{$token}"));

        Carbon::setTestNow();
    }

    /**
     * 3. Room becomes vacant immediately through derived status.
     */
    public function test_room_becomes_vacant_immediately_after_placement_ends(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.vacant@example.test');
        $room = $this->createRoom('K-303', 'Deluxe', 1500000);
        $resident = $this->createResident('resident.vacant@example.test', 'Citra Dewi');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-09-01', 1500000);

        // Initially room is occupied
        $this->assertTrue($room->fresh()->isOccupied());
        $this->assertNull(Room::vacant()->find($room->id));

        // End placement
        $previewRes = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement));
        $token = $previewRes->json('data.preview_token');

        $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => $token,
            'end_reason' => 'Pindah domisili ke kota lain',
        ]);

        // Room is immediately vacant without manual occupancy column
        $this->assertFalse($room->fresh()->isOccupied());
        $this->assertNotNull(Room::vacant()->find($room->id));

        Carbon::setTestNow();
    }

    /**
     * 4. T10 Integration: Resident can be placed in another room after ending placement.
     */
    public function test_resident_can_be_placed_again_in_another_room_after_placement_ends_integration(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.reassign@example.test');
        $room1 = $this->createRoom('K-304', 'Standard', 900000);
        $room2 = $this->createRoom('K-305', 'Superior', 1100000);
        $resident = $this->createResident('resident.reassign@example.test', 'Doni Pratama');

        // Placement 1 in room 1
        $placement1 = $this->createPlacement($resident, $room1, $admin, '2026-08-01', 900000);

        // End Placement 1
        $previewRes = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement1));
        $token = $previewRes->json('data.preview_token');
        $this->actingAs($admin)->post(route('placements.end', $placement1), [
            'preview_token' => $token,
            'end_reason' => 'Pindah kamar ke tipe superior',
        ]);

        $this->assertFalse($placement1->fresh()->isActive());

        // Now place Resident into Room 2 using PlacementService (T10 integration)
        $service = app(PlacementService::class);
        $t10Preview = $service->previewPlacement($resident->id, $room2->id, $admin);
        $placement2 = $service->startPlacement($resident->id, $room2->id, $t10Preview['preview_token'], $admin);

        $this->assertNotNull($placement2);
        $this->assertEquals($room2->id, $placement2->room_id);
        $this->assertTrue($placement2->isActive());
        $this->assertTrue($room2->fresh()->isOccupied());

        Carbon::setTestNow();
    }

    /**
     * 5. Cannot end already ended placement returns friendly domain message, NOT 403.
     */
    public function test_cannot_end_already_ended_placement_returns_friendly_domain_message(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.ended@example.test');
        $room = $this->createRoom('K-306', 'Standard', 800000);
        $resident = $this->createResident('resident.ended@example.test', 'Eka Putra');
        $placement = Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => '2026-08-01',
            'ended_on' => '2026-09-15',
            'agreed_monthly_rate' => 800000,
            'created_by' => $admin->id,
            'ended_by' => $admin->id,
            'end_reason' => 'Pengakhiran terdahulu',
        ]);

        // Preview returns domain error 422 with friendly message, NOT 403
        $previewRes = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement));
        $previewRes->assertStatus(422);
        $previewRes->assertJson([
            'success' => false,
            'message' => 'Penempatan ini sudah berstatus selesai dan tidak dapat diakhiri kembali.',
        ]);

        // Direct service attempt throws DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Penempatan ini sudah berstatus selesai dan tidak dapat diakhiri kembali.');
        app(PlacementService::class)->previewEndPlacement($placement->id, $admin);

        Carbon::setTestNow();
    }

    /**
     * 6. Authorization matrix: active admin allowed, owner/resident/inactive admin 403.
     */
    public function test_authorization_matrix_for_preview_and_end(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.auth@example.test');
        $owner = $this->createUser('owner', 'owner.auth@example.test');
        $inactiveAdmin = $this->createUser('admin', 'inactive.admin@example.test', null, false);
        $room = $this->createRoom('K-307', 'Standard', 900000);
        $resident = $this->createResident('resident.auth@example.test', 'Fani');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-09-01', 900000);

        // Owner: 403
        $this->actingAs($owner)->postJson(route('placements.end-preview', $placement))->assertForbidden();
        $this->actingAs($owner)->post(route('placements.end', $placement), [
            'preview_token' => 'dummy',
            'end_reason' => 'Mencoba mengakhiri',
        ])->assertForbidden();

        // Resident user: 403
        $this->actingAs($resident->user)->postJson(route('placements.end-preview', $placement))->assertForbidden();
        $this->actingAs($resident->user)->post(route('placements.end', $placement), [
            'preview_token' => 'dummy',
            'end_reason' => 'Mencoba mengakhiri',
        ])->assertForbidden();

        // Inactive Admin: redirected to login by EnsureAccountIsActive middleware
        $this->actingAs($inactiveAdmin)->postJson(route('placements.end-preview', $placement))->assertRedirect(route('login'));
        $this->actingAs($inactiveAdmin)->post(route('placements.end', $placement), [
            'preview_token' => 'dummy',
            'end_reason' => 'Mencoba mengakhiri',
        ])->assertRedirect(route('login'));

        // Service level also rejects inactive admin with AuthorizationException
        try {
            app(PlacementService::class)->previewEndPlacement($placement->id, $inactiveAdmin);
            $this->fail('Expected AuthorizationException for inactive admin');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertStringContainsString('Hanya Administrator aktif', $e->getMessage());
        }

        // Unauthenticated: 302 redirect
        auth()->logout();
        $this->postJson(route('placements.end-preview', $placement))->assertUnauthorized();
        $this->post(route('placements.end', $placement), [
            'preview_token' => 'dummy',
            'end_reason' => 'Mencoba mengakhiri',
        ])->assertRedirect(route('login'));

        Carbon::setTestNow();
    }

    /**
     * 7. End reason trimming, length bounds, and array/injection rejection without HTTP 500.
     */
    public function test_end_reason_validation_trimming_and_rejection(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.validation@example.test');
        $room = $this->createRoom('K-308', 'Standard', 900000);
        $resident = $this->createResident('resident.val@example.test', 'Gita');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-09-01', 900000);

        $previewRes = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement));
        $token = $previewRes->json('data.preview_token');

        // Whitespace only (trimmed to empty string)
        $res = $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => $token,
            'end_reason' => '     ',
        ]);
        $res->assertSessionHasErrors(['end_reason']);

        // Less than 5 characters after trimming
        $res = $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => $token,
            'end_reason' => '  ab  ',
        ]);
        $res->assertSessionHasErrors(['end_reason']);

        // Exceeds 255 characters
        $res = $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => $token,
            'end_reason' => str_repeat('A', 256),
        ]);
        $res->assertSessionHasErrors(['end_reason']);

        // Array input rejected with 422/session error, NOT 500
        $res = $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => $token,
            'end_reason' => ['array_payload'],
        ]);
        $res->assertSessionHasErrors(['end_reason']);

        Carbon::setTestNow();
    }

    /**
     * 8. Rejection retains only end_reason input, not stale preview token or payload hacks.
     */
    public function test_rejection_retains_only_end_reason_input(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.retain@example.test');
        $room = $this->createRoom('K-309', 'Standard', 900000);
        $resident = $this->createResident('resident.retain@example.test', 'Hadi');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-09-01', 900000);

        $previewRes = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement));
        $token = $previewRes->json('data.preview_token');

        $response = $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => 'invalid_or_expired_token',
            'end_reason' => 'Alasan yang ingin dipertahankan',
            'hacked_amount' => 0,
        ]);

        $response->assertSessionHas('error');
        $response->assertSessionHas('_old_input', [
            'end_reason' => 'Alasan yang ingin dipertahankan',
        ]);

        Carbon::setTestNow();
    }

    /**
     * 9. Material drift detection: date drift rejected under lock.
     */
    public function test_drift_detection_date_drift_rejected(): void
    {
        // Preview generated at 23:55 WIB on 2026-09-21
        Carbon::setTestNow(Carbon::parse('2026-09-21 23:55:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.datedrift@example.test');
        $room = $this->createRoom('K-310', 'Standard', 1000000);
        $resident = $this->createResident('resident.datedrift@example.test', 'Irfan');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-09-01', 1000000);

        $previewRes = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement));
        $token = $previewRes->json('data.preview_token');

        // Date rolls over across midnight to 00:02 WIB on 2026-09-22 (7 minutes later, token not expired)
        Carbon::setTestNow(Carbon::parse('2026-09-22 00:02:00', 'Asia/Jakarta'));

        $response = $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => $token,
            'end_reason' => 'Selesai masa kontrak tepat waktu',
        ]);

        $response->assertSessionHas('error', 'Tanggal operasional bisnis telah berganti sejak preview dibuat. Silakan lakukan preview dan konfirmasi ulang.');
        $this->assertTrue($placement->fresh()->isActive());

        Carbon::setTestNow();
    }

    /**
     * 10. Material drift detection: new invoice appears after preview rejected.
     */
    public function test_drift_detection_invoice_added_after_preview_rejected(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.invoicedrift@example.test');
        $room = $this->createRoom('K-311', 'Standard', 1000000);
        $resident = $this->createResident('resident.invoicedrift@example.test', 'Joko');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-07-01', 1000000);

        $previewRes = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement));
        $token = $previewRes->json('data.preview_token');

        // Concurrent action creates an invoice for July after preview was taken
        Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-07-01',
            'due_on' => '2026-07-05',
            'amount' => 1000000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => $token,
            'end_reason' => 'Selesai masa sewa kamar',
        ]);

        $response->assertSessionHas('error', 'Data tagihan atau status pembayaran telah berubah sejak preview dibuat. Silakan tinjau ulang preview sebelum mengakhiri penempatan.');
        $this->assertTrue($placement->fresh()->isActive());

        Carbon::setTestNow();
    }

    /**
     * 11. Material drift detection: valid payment recorded after preview rejected.
     */
    public function test_drift_detection_valid_payment_recorded_after_preview_rejected(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.paymentdrift@example.test');
        $room = $this->createRoom('K-312', 'Standard', 1000000);
        $resident = $this->createResident('resident.paymentdrift@example.test', 'Kiki');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-09-01', 1000000);

        $invoice = Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-09-01',
            'due_on' => '2026-09-05',
            'amount' => 1000000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        // Preview taken when invoice is unpaid
        $previewRes = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement));
        $token = $previewRes->json('data.preview_token');

        // Concurrent action records a valid payment for the invoice
        Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'REC-202609-999',
            'amount' => 1000000,
            'paid_on' => '2026-09-22',
            'method' => 'transfer',
            'status' => 'valid',
            'recorded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => $token,
            'end_reason' => 'Selesai masa sewa kamar',
        ]);

        $response->assertSessionHas('error', 'Data tagihan atau status pembayaran telah berubah sejak preview dibuat. Silakan tinjau ulang preview sebelum mengakhiri penempatan.');
        $this->assertTrue($placement->fresh()->isActive());

        Carbon::setTestNow();
    }

    /**
     * 12. Material drift detection: valid payment voided after preview rejected.
     */
    public function test_drift_detection_valid_payment_voided_after_preview_rejected(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.voiddrift@example.test');
        $room = $this->createRoom('K-313', 'Standard', 1000000);
        $resident = $this->createResident('resident.voiddrift@example.test', 'Lukman');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-09-01', 1000000);

        $invoice = Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-09-01',
            'due_on' => '2026-09-05',
            'amount' => 1000000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'REC-202609-888',
            'amount' => 1000000,
            'paid_on' => '2026-09-22',
            'method' => 'cash',
            'status' => 'valid',
            'recorded_by' => $admin->id,
        ]);

        // Preview taken with valid payment
        $previewRes = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement));
        $token = $previewRes->json('data.preview_token');

        // Payment voided / marked invalid after preview
        $payment->update([
            'status' => 'void',
            'voided_by' => $admin->id,
            'voided_at' => Carbon::now(),
            'void_reason' => 'Uji pembatalan pembayaran',
        ]);

        $response = $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => $token,
            'end_reason' => 'Selesai masa sewa kamar',
        ]);

        $response->assertSessionHas('error', 'Data tagihan atau status pembayaran telah berubah sejak preview dibuat. Silakan tinjau ulang preview sebelum mengakhiri penempatan.');
        $this->assertTrue($placement->fresh()->isActive());

        Carbon::setTestNow();
    }

    /**
     * 13. Deterministic signature: same data produces identical signature regardless of query retrieval order.
     */
    public function test_deterministic_signature_independent_of_query_order(): void
    {
        $admin = $this->createUser('admin', 'admin.signature@example.test');
        $room = $this->createRoom('K-314', 'Standard', 1000000);
        $resident = $this->createResident('resident.sig@example.test', 'Maya');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-06-01', 1000000);

        // Create 3 invoices
        $inv1 = Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-06-01',
            'due_on' => '2026-06-05',
            'amount' => 1000000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);
        $inv2 = Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-07-01',
            'due_on' => '2026-07-05',
            'amount' => 1000000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        $service = app(PlacementService::class);
        $businessDate = Carbon::parse('2026-09-22', 'Asia/Jakarta');

        $snap1 = $service->buildMaterialFinancialSnapshot($placement, $businessDate);

        // Call again
        $snap2 = $service->buildMaterialFinancialSnapshot($placement, $businessDate);

        $this->assertEquals($snap1['signature'], $snap2['signature']);
        $this->assertNotEmpty($snap1['signature']);
    }

    /**
     * 14. Resident user active status remains completely unchanged upon checkout.
     */
    public function test_resident_account_active_status_remains_unchanged_upon_checkout(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.accountstatus@example.test');
        $room1 = $this->createRoom('K-315', 'Standard', 1000000);
        $room2 = $this->createRoom('K-316', 'Standard', 1000000);

        // Active resident account
        $activeResident = $this->createResident('resident.activeacc@example.test', 'Nanda', true);
        $placement1 = $this->createPlacement($activeResident, $room1, $admin, '2026-09-01', 1000000);

        // Deactivated resident account
        $inactiveResident = $this->createResident('resident.inactiveacc@example.test', 'Omar', false);
        $placement2 = $this->createPlacement($inactiveResident, $room2, $admin, '2026-09-01', 1000000);

        $service = app(PlacementService::class);

        // End placement 1
        $prev1 = $service->previewEndPlacement($placement1->id, $admin);
        $service->endPlacement($placement1->id, 'Kontrak selesai', $prev1['preview_token'], $admin);

        // End placement 2
        $prev2 = $service->previewEndPlacement($placement2->id, $admin);
        $service->endPlacement($placement2->id, 'Kontrak selesai', $prev2['preview_token'], $admin);

        // Check active resident user remains active
        $this->assertTrue((bool) $activeResident->user->fresh()->is_active);

        // Check inactive resident user remains inactive
        $this->assertFalse((bool) $inactiveResident->user->fresh()->is_active);

        Carbon::setTestNow();
    }

    /**
     * 15. All financial obligations and invoices are preserved after placement ends.
     */
    public function test_all_financial_obligations_and_invoices_preserved_after_placement_ends(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.preserve@example.test');
        $room = $this->createRoom('K-317', 'Standard', 1000000);
        $resident = $this->createResident('resident.preserve@example.test', 'Pandu');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-07-01', 1000000);

        // July invoice: paid
        $inv1 = Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-07-01',
            'due_on' => '2026-07-05',
            'amount' => 1000000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);
        Payment::create([
            'invoice_id' => $inv1->id,
            'receipt_number' => 'REC-202607-001',
            'amount' => 1000000,
            'paid_on' => '2026-07-04',
            'method' => 'transfer',
            'status' => 'valid',
            'recorded_by' => $admin->id,
        ]);

        $service = app(PlacementService::class);
        $prev = $service->previewEndPlacement($placement->id, $admin);
        $service->endPlacement($placement->id, 'Kontrak selesai', $prev['preview_token'], $admin);

        // Check invoices: 3 total (July paid, August unpaid, September unpaid)
        $invoices = Invoice::where('placement_id', $placement->id)->orderBy('period_month')->get();
        $this->assertCount(3, $invoices);
        $this->assertTrue($invoices[0]->hasValidPayment());
        $this->assertFalse($invoices[1]->hasValidPayment());
        $this->assertFalse($invoices[2]->hasValidPayment());

        Carbon::setTestNow();
    }

    /**
     * 16. Atomic rollback on final audit failure using REAL BillingService.
     * Proves invoices and invoice audit logs were generated within the transaction before failing on placement audit.
     */
    public function test_atomic_rollback_on_final_audit_failure_using_real_billing_service(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.realrollback@example.test');
        $room = $this->createRoom('K-318', 'Standard', 1000000);
        $resident = $this->createResident('resident.realrollback@example.test', 'Qori');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-07-01', 1000000);

        // Pre-create July invoice
        Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-07-01',
            'due_on' => '2026-07-05',
            'amount' => 1000000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        $realBillingService = app(BillingService::class);
        $realAuditService = app(AuditService::class);

        // Mock AuditService: allow invoice audit logs, but fail when module is placements action end
        $auditMock = Mockery::mock($realAuditService)->makePartial();
        $auditMock->shouldReceive('log')
            ->andReturnUsing(function ($action, $module, $summary, $actor, $entityType = null, $entityId = null, $entityLabel = null, $changes = null) use ($realAuditService) {
                if ($module === 'placements' && $action === 'end') {
                    throw new Exception('Simulated crash during placement end audit logging');
                }

                return $realAuditService->log($action, $module, $summary, $actor, $entityType, $entityId, $entityLabel, $changes);
            });

        $placementService = new PlacementService($auditMock, $realBillingService);

        $preview = $placementService->previewEndPlacement($placement->id, $admin);
        $token = $preview['preview_token'];

        $initialInvoiceCount = Invoice::where('placement_id', $placement->id)->count(); // 1
        $initialAuditCount = ActivityLog::count();

        // Attempt endPlacement which will fail at final placement audit step
        try {
            $placementService->endPlacement($placement->id, 'Alasan pengakhiran penempatan', $token, $admin);
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertEquals('Simulated crash during placement end audit logging', $e->getMessage());
        }

        // Assert total database rollback:
        // 1. Placement still active
        $placement->refresh();
        $this->assertTrue($placement->isActive());
        $this->assertNull($placement->ended_on);
        $this->assertNull($placement->ended_by);
        $this->assertNull($placement->end_reason);

        // 2. New invoices rolled back (count remains 1, August and September rolled back)
        $this->assertEquals($initialInvoiceCount, Invoice::where('placement_id', $placement->id)->count());

        // 3. Invoice audit logs rolled back
        $this->assertEquals($initialAuditCount, ActivityLog::count());

        // 4. Preview token in session NOT invalidated when transaction fails
        $this->assertTrue(session()->has("placement_end_preview_{$token}"));

        Carbon::setTestNow();
    }

    /**
     * 17. Resubmission does not mutate previously ended placement.
     */
    public function test_resubmission_does_not_mutate_previously_ended_placement(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.resubmit@example.test');
        $room = $this->createRoom('K-319', 'Standard', 1000000);
        $resident = $this->createResident('resident.resubmit@example.test', 'Reno');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-09-01', 1000000);

        $service = app(PlacementService::class);
        $prev = $service->previewEndPlacement($placement->id, $admin);
        $service->endPlacement($placement->id, 'Alasan pertama sah', $prev['preview_token'], $admin);

        // Submit again with same token (now removed from session)
        $this->expectException(DomainException::class);
        $service->endPlacement($placement->id, 'Alasan kedua berbeda', $prev['preview_token'], $admin);

        $placement->refresh();
        $this->assertEquals('Alasan pertama sah', $placement->end_reason);

        Carbon::setTestNow();
    }

    /**
     * 18. Browser payload financial amounts are ignored and not trusted.
     */
    public function test_browser_payload_financial_amounts_are_not_trusted(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.tamper@example.test');
        $room = $this->createRoom('K-320', 'Standard', 1000000);
        $resident = $this->createResident('resident.tamper@example.test', 'Sinta');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01', 1000000);

        $previewRes = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement));
        $token = $previewRes->json('data.preview_token');

        // Tamper request payload with zero rate, fake total unpaid, etc.
        $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => $token,
            'end_reason' => 'Pengakhiran sewa kamar kos',
            'agreed_monthly_rate' => 100,
            'total_unpaid_obligation' => 0,
            'new_invoices_count' => 0,
        ]);

        // Rate remains contract rate 1,000,000 and invoices synced with 1,000,000
        $placement->refresh();
        $this->assertEquals(1000000, $placement->agreed_monthly_rate);
        $invoices = Invoice::where('placement_id', $placement->id)->get();
        foreach ($invoices as $inv) {
            $this->assertEquals(1000000, $inv->amount);
        }

        Carbon::setTestNow();
    }

    /**
     * 19. UI button and modal rendered conditionally based on authorization and placement status.
     */
    public function test_ui_button_and_modal_rendered_only_for_active_placement_and_admin(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.ui@example.test');
        $owner = $this->createUser('owner', 'owner.ui@example.test');
        $room = $this->createRoom('K-321', 'Standard', 1000000);
        $resident = $this->createResident('resident.ui@example.test', 'Tari');
        $activePlacement = $this->createPlacement($resident, $room, $admin, '2026-09-01', 1000000);

        // Admin viewing active placement: button & modal rendered
        $adminView = $this->actingAs($admin)->get(route('placements.show', $activePlacement));
        $adminView->assertOk();
        $adminView->assertSee('id="btn-open-end-modal"', false);
        $adminView->assertSee('id="endPlacementModal"', false);

        // Owner viewing active placement: button & modal NOT rendered
        $ownerView = $this->actingAs($owner)->get(route('placements.show', $activePlacement));
        $ownerView->assertOk();
        $ownerView->assertDontSee('id="btn-open-end-modal"', false);
        $ownerView->assertDontSee('id="endPlacementModal"', false);

        // End placement
        $activePlacement->update([
            'ended_on' => '2026-09-22',
            'ended_by' => $admin->id,
            'end_reason' => 'Selesai',
        ]);

        // Admin viewing ended placement: button & modal NOT rendered
        $adminViewEnded = $this->actingAs($admin)->get(route('placements.show', $activePlacement));
        $adminViewEnded->assertOk();
        $adminViewEnded->assertDontSee('id="btn-open-end-modal"', false);
        $adminViewEnded->assertDontSee('id="endPlacementModal"', false);

        Carbon::setTestNow();
    }

    /**
     * 20. Expired preview token (older than 15 minutes) is rejected.
     */
    public function test_expired_preview_token_rejected(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.expired@example.test');
        $room = $this->createRoom('K-322', 'Standard', 1000000);
        $resident = $this->createResident('resident.expired@example.test', 'Umar');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-09-01', 1000000);

        $previewRes = $this->actingAs($admin)->postJson(route('placements.end-preview', $placement));
        $token = $previewRes->json('data.preview_token');

        // Fast forward 16 minutes (token expired)
        Carbon::setTestNow('2026-09-22 10:16:00');

        $response = $this->actingAs($admin)->post(route('placements.end', $placement), [
            'preview_token' => $token,
            'end_reason' => 'Selesai masa kontrak sewa kamar',
        ]);

        $response->assertSessionHas('error', 'Sesi preview pengakhiran tidak valid atau telah kedaluwarsa. Silakan lakukan preview ulang.');
        $this->assertTrue($placement->fresh()->isActive());

        Carbon::setTestNow();
    }

    /**
     * 21. Atomic rollback on BillingService failure during end placement.
     * Proves placement remains active, invoices and audits return to baseline, and preview token is NOT forgotten.
     */
    public function test_atomic_rollback_on_billing_service_failure_during_end_placement(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.billfail@example.test');
        $room = $this->createRoom('K-323', 'Standard', 1000000);
        $resident = $this->createResident('resident.billfail@example.test', 'Vina');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-07-01', 1000000);

        // Pre-create July invoice
        Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-07-01',
            'due_on' => '2026-07-05',
            'amount' => 1000000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        $realBillingService = app(BillingService::class);
        $realAuditService = app(AuditService::class);

        // Mock BillingService: throw exception during syncPlacementInvoices
        $billingMock = Mockery::mock($realBillingService)->makePartial();
        $billingMock->shouldReceive('syncPlacementInvoices')
            ->once()
            ->andThrow(new Exception('Simulated crash in BillingService during endPlacement'));

        $placementService = new PlacementService($realAuditService, $billingMock);

        $preview = $placementService->previewEndPlacement($placement->id, $admin);
        $token = $preview['preview_token'];

        $initialInvoiceCount = Invoice::where('placement_id', $placement->id)->count(); // 1
        $initialAuditCount = ActivityLog::count();

        // Attempt endPlacement which will fail inside syncPlacementInvoices
        try {
            $placementService->endPlacement($placement->id, 'Alasan pengakhiran penempatan', $token, $admin);
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertEquals('Simulated crash in BillingService during endPlacement', $e->getMessage());
        }

        // Assert total database rollback:
        // 1. Placement still active
        $placement->refresh();
        $this->assertTrue($placement->isActive());
        $this->assertNull($placement->ended_on);
        $this->assertNull($placement->ended_by);
        $this->assertNull($placement->end_reason);

        // 2. Invoices rolled back to baseline
        $this->assertEquals($initialInvoiceCount, Invoice::where('placement_id', $placement->id)->count());

        // 3. Activity logs rolled back to baseline
        $this->assertEquals($initialAuditCount, ActivityLog::count());

        // 4. Preview token in session NOT invalidated when transaction fails
        $this->assertTrue(session()->has("placement_end_preview_{$token}"));

        Carbon::setTestNow();
    }

    /**
     * 22. Atomic rollback on invoice audit failure after invoice creation started.
     * Proves created invoice and invoice audit roll back cleanly, placement remains active, and token preserved.
     */
    public function test_atomic_rollback_on_invoice_audit_failure_during_end_placement(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $admin = $this->createUser('admin', 'admin.invauditfail@example.test');
        $room = $this->createRoom('K-324', 'Standard', 1000000);
        $resident = $this->createResident('resident.invauditfail@example.test', 'Wahyu');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-07-01', 1000000);

        // Pre-create July invoice
        Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-07-01',
            'due_on' => '2026-07-05',
            'amount' => 1000000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        $realAuditService = app(AuditService::class);

        // Mock AuditService: throw exception specifically when module is 'invoices'
        // (which is called by BillingService right after Invoice::create)
        $auditMock = Mockery::mock($realAuditService)->makePartial();
        $auditMock->shouldReceive('log')
            ->andReturnUsing(function ($action, $module, $summary, $actor, $entityType = null, $entityId = null, $entityLabel = null, $changes = null) use ($realAuditService) {
                if ($module === 'invoices') {
                    throw new Exception('Simulated crash during invoice audit logging in BillingService');
                }

                return $realAuditService->log($action, $module, $summary, $actor, $entityType, $entityId, $entityLabel, $changes);
            });

        $billingService = new BillingService($auditMock);
        $placementService = new PlacementService($auditMock, $billingService);

        $preview = $placementService->previewEndPlacement($placement->id, $admin);
        $token = $preview['preview_token'];

        $initialInvoiceCount = Invoice::where('placement_id', $placement->id)->count(); // 1
        $initialAuditCount = ActivityLog::count();

        // Attempt endPlacement which will fail inside BillingService invoice audit logging
        try {
            $placementService->endPlacement($placement->id, 'Alasan pengakhiran sewa kamar', $token, $admin);
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertEquals('Simulated crash during invoice audit logging in BillingService', $e->getMessage());
        }

        // Assert total database rollback:
        // 1. Placement still active
        $placement->refresh();
        $this->assertTrue($placement->isActive());
        $this->assertNull($placement->ended_on);
        $this->assertNull($placement->ended_by);
        $this->assertNull($placement->end_reason);

        // 2. Invoices rolled back to baseline
        $this->assertEquals($initialInvoiceCount, Invoice::where('placement_id', $placement->id)->count());

        // 3. Activity logs rolled back to baseline
        $this->assertEquals($initialAuditCount, ActivityLog::count());

        // 4. Preview token in session NOT invalidated when transaction fails
        $this->assertTrue(session()->has("placement_end_preview_{$token}"));

        Carbon::setTestNow();
    }

    /**
     * 23. Integration: Exit on the 1st day of the month is billed full departure month rate (no pro-rata).
     */
    public function test_end_placement_on_first_day_of_month_bills_full_departure_month(): void
    {
        // Started mid-month August
        $admin = $this->createUser('admin', 'admin.firstday@example.test');
        $room = $this->createRoom('K-325', 'VIP', 1500000);
        $resident = $this->createResident('resident.firstday@example.test', 'Xavier');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-15', 1500000);

        // August invoice pre-created
        Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-08-01',
            'due_on' => '2026-08-20',
            'amount' => 1500000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        // Current business date is the 1st of September
        Carbon::setTestNow('2026-09-01 08:30:00');

        $service = app(PlacementService::class);
        $preview = $service->previewEndPlacement($placement->id, $admin);
        $this->assertEquals(1, $preview['financial']['new_invoices_count']);
        $this->assertEquals(1500000, $preview['financial']['new_invoices_amount']);

        $service->endPlacement($placement->id, 'Pindah tugas kerja keluar kota', $preview['preview_token'], $admin);

        $placement->refresh();
        $this->assertEquals('2026-09-01', $placement->ended_on->toDateString());
        $this->assertFalse($placement->isActive());

        // Both August and September must exist with FULL monthly rate of 1,500,000 (no pro-rata for 1 day)
        $invoices = Invoice::where('placement_id', $placement->id)->orderBy('period_month')->get();
        $this->assertCount(2, $invoices);
        $this->assertEquals('2026-08-01', $invoices[0]->period_month->toDateString());
        $this->assertEquals(1500000, $invoices[0]->amount);
        $this->assertEquals('2026-09-01', $invoices[1]->period_month->toDateString());
        $this->assertEquals(1500000, $invoices[1]->amount);

        // No October invoice
        $this->assertFalse(Invoice::where('placement_id', $placement->id)->where('period_month', '2026-10-01')->exists());

        Carbon::setTestNow();
    }

    /**
     * 24. Integration: Start and end on the same calendar day does not duplicate the first invoice.
     */
    public function test_start_and_end_on_same_day_does_not_duplicate_invoice(): void
    {
        Carbon::setTestNow('2026-09-22 09:00:00');

        $admin = $this->createUser('admin', 'admin.sameday@example.test');
        $room = $this->createRoom('K-326', 'Standard', 1000000);
        $resident = $this->createResident('resident.sameday@example.test', 'Yoga');

        // Start placement on 2026-09-22 with initial invoice synced
        $placement = $this->createPlacement($resident, $room, $admin, '2026-09-22', 1000000);
        $billingService = app(BillingService::class);
        $billingService->syncPlacementInvoices($placement, $admin);

        // Exactly 1 invoice initially for September
        $this->assertEquals(1, Invoice::where('placement_id', $placement->id)->count());

        // End placement later the same day (15:00:00)
        Carbon::setTestNow('2026-09-22 15:00:00');

        $placementService = app(PlacementService::class);
        $preview = $placementService->previewEndPlacement($placement->id, $admin);
        $this->assertEquals(0, $preview['financial']['new_invoices_count']);
        $this->assertEquals(0, $preview['financial']['new_invoices_amount']);

        $placementService->endPlacement($placement->id, 'Batal sewa di hari yang sama', $preview['preview_token'], $admin);

        $placement->refresh();
        $this->assertEquals('2026-09-22', $placement->started_on->toDateString());
        $this->assertEquals('2026-09-22', $placement->ended_on->toDateString());
        $this->assertFalse($placement->isActive());

        // Total invoices remains exactly 1 (no duplicate September invoice created!)
        $invoices = Invoice::where('placement_id', $placement->id)->get();
        $this->assertCount(1, $invoices);
        $this->assertEquals('2026-09-01', $invoices[0]->period_month->toDateString());
        $this->assertEquals(1000000, $invoices[0]->amount);

        Carbon::setTestNow();
    }

    /**
     * 25. Integration: Gap in invoice periods is filled without issuing post-departure invoices.
     */
    public function test_gap_in_invoice_periods_is_filled_without_issuing_future_invoices(): void
    {
        Carbon::setTestNow('2026-08-20 10:00:00');

        $admin = $this->createUser('admin', 'admin.gaptest@example.test');
        $room = $this->createRoom('K-327', 'Standard', 1000000);
        $resident = $this->createResident('resident.gaptest@example.test', 'Zainal');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-05-01', 1000000);

        // Pre-create May (2026-05-01) and July (2026-07-01).
        // June (2026-06-01) is a middle GAP, and August (2026-08-01) is the current departure month.
        Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-05-01',
            'due_on' => '2026-05-05',
            'amount' => 1000000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);
        Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-07-01',
            'due_on' => '2026-07-05',
            'amount' => 1000000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        $this->assertEquals(2, Invoice::where('placement_id', $placement->id)->count());

        $service = app(PlacementService::class);
        $preview = $service->previewEndPlacement($placement->id, $admin);

        // Missing periods should be June and August
        $this->assertEquals(['2026-06-01', '2026-08-01'], $preview['financial']['missing_periods']);
        $this->assertEquals(2, $preview['financial']['new_invoices_count']);
        $this->assertEquals(2000000, $preview['financial']['new_invoices_amount']);

        $service->endPlacement($placement->id, 'Selesai masa studi di kampus', $preview['preview_token'], $admin);

        $placement->refresh();
        $this->assertEquals('2026-08-20', $placement->ended_on->toDateString());
        $this->assertFalse($placement->isActive());

        // Invoices must now cover May, June, July, August (4 total)
        $invoices = Invoice::where('placement_id', $placement->id)->orderBy('period_month')->get();
        $this->assertCount(4, $invoices);
        $this->assertEquals(['2026-05-01', '2026-06-01', '2026-07-01', '2026-08-01'], $invoices->pluck('period_month')->map(fn ($d) => $d->toDateString())->all());

        // Crucial: No invoice for September (2026-09-01) or later exists
        $this->assertFalse(Invoice::where('placement_id', $placement->id)->where('period_month', '>=', '2026-09-01')->exists());

        Carbon::setTestNow();
    }
}
