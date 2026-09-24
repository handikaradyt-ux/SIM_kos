<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Placement;
use App\Models\Resident;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use DatabaseTransactions;

    protected BillingService $billingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->billingService = app(BillingService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createUser(string $roleCode, string $email, bool $isActive = true, ?string $name = null, bool $tempPassword = false): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();

        return User::create([
            'role_id' => $role->id,
            'name' => $name ?? ('User ' . ucfirst($roleCode)),
            'email' => $email,
            'password' => Hash::make('Password123456!'),
            'is_active' => $isActive,
            'must_change_password' => $tempPassword,
        ]);
    }

    private function createRoom(string $number = 'K-101', int $rate = 850000): Room
    {
        return Room::create([
            'number' => $number,
            'type' => 'Standard',
            'monthly_rate' => $rate,
        ]);
    }

    private function createResident(string $email = 'resident.test@example.test', string $name = 'Budi Santoso'): Resident
    {
        $user = $this->createUser('resident', $email, true, $name);

        return Resident::create([
            'user_id' => $user->id,
            'name' => $name,
            'phone' => '0812' . rand(10000000, 99999999),
            'origin_address' => 'Jl. Mawar No. 10, Jakarta',
        ]);
    }

    private function createPlacement(
        Resident $resident,
        Room $room,
        User $creator,
        string $startedOn,
        ?string $endedOn = null,
        int $agreedRate = 850000,
        ?User $ender = null,
        ?string $endReason = null
    ): Placement {
        return Placement::create([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => $startedOn,
            'ended_on' => $endedOn,
            'agreed_monthly_rate' => $agreedRate,
            'created_by' => $creator->id,
            'ended_by' => $endedOn ? ($ender?->id ?? $creator->id) : null,
            'end_reason' => $endedOn ? ($endReason ?? 'Selesai masa sewa') : null,
        ]);
    }

    private function createInvoice(Placement $placement, User $creator, string $periodMonth, string $dueOn, int $amount = 850000): Invoice
    {
        return Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => $periodMonth,
            'due_on' => $dueOn,
            'amount' => $amount,
            'room_number_snapshot' => $placement->room->number,
            'resident_name_snapshot' => $placement->resident->name,
            'created_by' => $creator->id,
        ]);
    }

    private function createPayment(Invoice $invoice, User $recorder, int $amount, string $paidOn, ?string $voidedAt = null, ?string $voidReason = null): Payment
    {
        return Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCP-' . strtoupper(Str::random(10)),
            'amount' => $amount,
            'paid_on' => $paidOn,
            'method' => 'transfer',
            'status' => $voidedAt ? 'void' : 'valid',
            'recorded_by' => $recorder->id,
            'voided_at' => $voidedAt,
            'voided_by' => $voidedAt ? $recorder->id : null,
            'void_reason' => $voidReason,
        ]);
    }

    // =========================================================================
    // 1. OTORISASI & AKSES ROLE (TC-03, TC-04, ANTI-IDOR)
    // =========================================================================

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('invoices.index'));
        $response->assertRedirect(route('login'));

        $responsePost = $this->post(route('invoices.sync-preview'));
        $responsePost->assertRedirect(route('login'));
    }

    public function test_user_with_temp_password_is_redirected_to_password_change(): void
    {
        $admin = $this->createUser('admin', 'temp.admin@example.test', true, null, true);

        $response = $this->actingAs($admin)->get(route('invoices.index'));
        $response->assertRedirect(route('password.change'));
    }

    public function test_inactive_user_is_logged_out_or_forbidden(): void
    {
        $inactive = $this->createUser('admin', 'inactive.admin@example.test', false);

        $response = $this->actingAs($inactive)->get(route('invoices.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_view_all_invoices_and_global_coverage(): void
    {
        $admin = $this->createUser('admin', 'admin.view@example.test');
        $resident1 = $this->createResident('res1@example.test', 'Penghuni Satu');
        $resident2 = $this->createResident('res2@example.test', 'Penghuni Dua');
        $room1 = $this->createRoom('K-101');
        $room2 = $this->createRoom('K-102');

        $p1 = $this->createPlacement($resident1, $room1, $admin, '2026-08-01');
        $p2 = $this->createPlacement($resident2, $room2, $admin, '2026-08-01');

        $inv1 = $this->createInvoice($p1, $admin, '2026-08-01', '2026-08-10');
        $inv2 = $this->createInvoice($p2, $admin, '2026-08-01', '2026-08-10');

        $response = $this->actingAs($admin)->get(route('invoices.index'));

        $response->assertStatus(200);
        $response->assertSee('Manajemen Tagihan (Invoice)');
        $response->assertSee('Penghuni Satu');
        $response->assertSee('Penghuni Dua');
        $response->assertSee('Sinkronkan Tagihan');
    }

    public function test_owner_can_view_all_invoices_and_coverage_read_only_without_sync_action(): void
    {
        $admin = $this->createUser('admin', 'admin.setup@example.test');
        $owner = $this->createUser('owner', 'owner.view@example.test');
        $resident = $this->createResident('res.owner@example.test', 'Penghuni Pantau');
        $room = $this->createRoom('K-201');

        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01');
        $inv = $this->createInvoice($placement, $admin, '2026-08-01', '2026-08-10');

        $response = $this->actingAs($owner)->get(route('invoices.index'));

        $response->assertStatus(200);
        $response->assertSee('Status Tagihan Kos');
        $response->assertSee('Penghuni Pantau');
        $response->assertSee('Sinkronisasi dijalankan oleh Administrator');
        $response->assertDontSee('id="btnOpenGlobalSync"', false);

        // Owner cannot access sync-preview or sync POST routes
        $previewResp = $this->actingAs($owner)->postJson(route('invoices.sync-preview'));
        $previewResp->assertStatus(403);

        $syncResp = $this->actingAs($owner)->post(route('invoices.sync'), ['preview_token' => 'dummy']);
        $syncResp->assertStatus(403);
    }

    public function test_resident_can_only_view_own_invoices_and_global_coverage_is_strictly_hidden(): void
    {
        $admin = $this->createUser('admin', 'admin.owner@example.test');
        $residentA = $this->createResident('res.a@example.test', 'Penghuni Rahasia A');
        $residentB = $this->createResident('res.b@example.test', 'Penghuni Rahasia B');
        $roomA = $this->createRoom('K-301');
        $roomB = $this->createRoom('K-302');

        $pA = $this->createPlacement($residentA, $roomA, $admin, '2026-08-01');
        $pB = $this->createPlacement($residentB, $roomB, $admin, '2026-08-01');

        $invA = $this->createInvoice($pA, $admin, '2026-08-01', '2026-08-10');
        $invB = $this->createInvoice($pB, $admin, '2026-08-01', '2026-08-10');

        $responseA = $this->actingAs($residentA->user)->get(route('invoices.index'));

        $responseA->assertStatus(200);
        $responseA->assertSee('Tagihan Saya');
        $responseA->assertSee('Kamar K-301');
        // Resident B's data must NEVER appear in Resident A's listing
        $responseA->assertDontSee('Kamar K-302');
        $responseA->assertDontSee('Penghuni Rahasia B');
        // Global coverage banner must be strictly absent
        $responseA->assertDontSee('Tagihan Periode Berjalan Belum Lengkap');
        $responseA->assertDontSee('Pemeriksaan cakupan global');
        $responseA->assertDontSee('Sinkronkan Tagihan');

        // Resident cannot access sync-preview or sync POST routes
        $previewResp = $this->actingAs($residentA->user)->postJson(route('invoices.sync-preview'));
        $previewResp->assertStatus(403);
    }

    public function test_resident_cannot_access_other_resident_invoice_detail_anti_idor(): void
    {
        $admin = $this->createUser('admin', 'admin.idor@example.test');
        $residentA = $this->createResident('res.idor.a@example.test', 'Penghuni IDOR A');
        $residentB = $this->createResident('res.idor.b@example.test', 'Penghuni IDOR B');
        $roomA = $this->createRoom('K-401');
        $roomB = $this->createRoom('K-402');

        $pA = $this->createPlacement($residentA, $roomA, $admin, '2026-08-01');
        $pB = $this->createPlacement($residentB, $roomB, $admin, '2026-08-01');

        $invA = $this->createInvoice($pA, $admin, '2026-08-01', '2026-08-10');
        $invB = $this->createInvoice($pB, $admin, '2026-08-01', '2026-08-10');

        // Resident A accessing own invoice -> 200 OK
        $ownResp = $this->actingAs($residentA->user)->get(route('invoices.show', $invA));
        $ownResp->assertStatus(200);
        $ownResp->assertSee('Kamar K-401');

        // Resident A attempting to access Resident B's invoice -> 403 Forbidden
        $otherResp = $this->actingAs($residentA->user)->get(route('invoices.show', $invB));
        $otherResp->assertStatus(403);
    }

    public function test_resident_user_without_resident_profile_renders_safe_empty_page(): void
    {
        $userWithoutProfile = $this->createUser('resident', 'orphan.res@example.test');

        $response = $this->actingAs($userWithoutProfile)->get(route('invoices.index'));

        $response->assertStatus(200);
        $response->assertSee('Tagihan Saya');
        $response->assertSee('Tidak ada data tagihan ditemukan');
    }

    // =========================================================================
    // 2. SEMANTIK STATUS, FILTER & TANGGAL KALENDER
    // =========================================================================

    public function test_unpaid_filter_includes_overdue_due_today_and_future_due_dates(): void
    {
        // Freeze business date at 2026-09-15 10:00:00 Asia/Jakarta
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.status@example.test');
        $resident = $this->createResident('res.status@example.test', 'Penghuni Status');
        $room = $this->createRoom('K-501');
        $p = $this->createPlacement($resident, $room, $admin, '2026-07-01');

        // 1. Overdue: due_on yesterday (2026-09-14)
        $invOverdue = $this->createInvoice($p, $admin, '2026-07-01', '2026-09-14', 800000);
        // 2. Due Today: due_on today (2026-09-15)
        $invDueToday = $this->createInvoice($p, $admin, '2026-08-01', '2026-09-15', 850000);
        // 3. Future: due_on tomorrow (2026-09-16)
        $invFuture = $this->createInvoice($p, $admin, '2026-09-01', '2026-09-16', 900000);
        // 4. Paid: has valid payment
        $invPaid = $this->createInvoice($p, $admin, '2026-06-01', '2026-06-10', 750000);
        $this->createPayment($invPaid, $admin, 750000, '2026-06-05');

        // Test Filter: unpaid
        $responseUnpaid = $this->actingAs($admin)->get(route('invoices.index', ['status' => 'unpaid']));
        $responseUnpaid->assertStatus(200);
        $responseUnpaid->assertSee('Rp 800.000'); // overdue
        $responseUnpaid->assertSee('Rp 850.000'); // due today
        $responseUnpaid->assertSee('Rp 900.000'); // future
        $responseUnpaid->assertDontSee('Rp 750.000'); // paid must not appear

        // Test Filter: overdue (strictly due_on < 2026-09-15)
        $responseOverdue = $this->actingAs($admin)->get(route('invoices.index', ['status' => 'overdue']));
        $responseOverdue->assertStatus(200);
        $responseOverdue->assertSee('Rp 800.000'); // overdue
        $responseOverdue->assertDontSee('Rp 850.000'); // due today is NOT overdue
        $responseOverdue->assertDontSee('Rp 900.000'); // future is NOT overdue
        $responseOverdue->assertDontSee('Rp 750.000'); // paid is NOT overdue

        // Test Filter: paid
        $responsePaid = $this->actingAs($admin)->get(route('invoices.index', ['status' => 'paid']));
        $responsePaid->assertStatus(200);
        $responsePaid->assertSee('Rp 750.000');
        $responsePaid->assertDontSee('Rp 800.000');
        $responsePaid->assertDontSee('Rp 850.000');
        $responsePaid->assertDontSee('Rp 900.000');
    }

    public function test_void_payment_does_not_make_invoice_paid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.void@example.test');
        $resident = $this->createResident('res.void@example.test', 'Penghuni Void');
        $room = $this->createRoom('K-502');
        $p = $this->createPlacement($resident, $room, $admin, '2026-08-01');

        $invoice = $this->createInvoice($p, $admin, '2026-08-01', '2026-08-10', 880000);
        // Add voided payment
        $this->createPayment($invoice, $admin, 880000, '2026-08-05', '2026-08-06 14:00:00', 'Salah transfer bank');

        // Invoice status checks
        $this->assertFalse($invoice->isPaid());
        $this->assertTrue($invoice->isOverdue());
        $this->assertEquals('Terlambat', $invoice->statusLabel());

        // In index listing under unpaid filter: MUST appear
        $respUnpaid = $this->actingAs($admin)->get(route('invoices.index', ['status' => 'unpaid']));
        $respUnpaid->assertSee('Rp 880.000');

        // In index listing under paid filter: MUST NOT appear
        $respPaid = $this->actingAs($admin)->get(route('invoices.index', ['status' => 'paid']));
        $respPaid->assertDontSee('Rp 880.000');

        // In detail view: show voided payment notice
        $respDetail = $this->actingAs($admin)->get(route('invoices.show', $invoice));
        $respDetail->assertSee('Riwayat Pembayaran Dibatalkan (Void)');
        $respDetail->assertSee('Salah transfer bank');
        $respDetail->assertSee('tidak membuat status tagihan lunas');
    }

    public function test_due_date_boundary_around_midnight_wib_and_utc_normalization(): void
    {
        // 2026-09-15 17:30:00 UTC is 2026-09-16 00:30:00 Asia/Jakarta
        Carbon::setTestNow(Carbon::createFromFormat('Y-m-d H:i:s', '2026-09-15 17:30:00', 'UTC'));

        $admin = $this->createUser('admin', 'admin.boundary@example.test');
        $resident1 = $this->createResident('res.boundary1@example.test', 'Penghuni Batas 1');
        $resident2 = $this->createResident('res.boundary2@example.test', 'Penghuni Batas 2');
        $room1 = $this->createRoom('K-503');
        $room2 = $this->createRoom('K-504');
        $p1 = $this->createPlacement($resident1, $room1, $admin, '2026-09-01');
        $p2 = $this->createPlacement($resident2, $room2, $admin, '2026-09-01');

        // Invoice with due_on = '2026-09-16'
        $inv16 = $this->createInvoice($p1, $admin, '2026-09-01', '2026-09-16', 700000);
        // Invoice with due_on = '2026-09-15'
        $inv15 = $this->createInvoice($p2, $admin, '2026-09-01', '2026-09-15', 710000);

        $refJakarta = Carbon::now('Asia/Jakarta')->startOfDay();
        $this->assertEquals('2026-09-16', $refJakarta->toDateString());

        // Under 2026-09-16 Asia/Jakarta:
        // inv16 is due today (NOT overdue)
        $this->assertTrue($inv16->isDueToday($refJakarta));
        $this->assertFalse($inv16->isOverdue($refJakarta));

        // inv15 is yesterday (IS overdue)
        $this->assertTrue($inv15->isOverdue($refJakarta));
        $this->assertFalse($inv15->isDueToday($refJakarta));
    }

    // =========================================================================
    // 3. PENCARIAN, FILTER PERIODE, STATUS PENEMPATAN & SANITASI INPUT (ANTI-500)
    // =========================================================================

    public function test_search_and_filter_period_and_placement_status(): void
    {
        $admin = $this->createUser('admin', 'admin.filter@example.test');
        $residentActive = $this->createResident('res.act@example.test', 'Ahmad Dani');
        $residentEnded = $this->createResident('res.end@example.test', 'Bambang Pamungkas');
        $room1 = $this->createRoom('K-601');
        $room2 = $this->createRoom('K-602');

        $pActive = $this->createPlacement($residentActive, $room1, $admin, '2026-05-01');
        $pEnded = $this->createPlacement($residentEnded, $room2, $admin, '2026-05-01', '2026-06-30');

        $inv1 = $this->createInvoice($pActive, $admin, '2026-05-01', '2026-05-10', 601000);
        $inv2 = $this->createInvoice($pEnded, $admin, '2026-06-01', '2026-06-10', 602000);

        // Search by resident name
        $respSearchName = $this->actingAs($admin)->get(route('invoices.index', ['search' => 'Ahmad']));
        $respSearchName->assertSee('Rp 601.000');
        $respSearchName->assertDontSee('Rp 602.000');

        // Search by room number
        $respSearchRoom = $this->actingAs($admin)->get(route('invoices.index', ['search' => 'K-602']));
        $respSearchRoom->assertSee('Rp 602.000');
        $respSearchRoom->assertDontSee('Rp 601.000');

        // Filter period
        $respPeriod = $this->actingAs($admin)->get(route('invoices.index', ['period' => '2026-05']));
        $respPeriod->assertSee('Rp 601.000');
        $respPeriod->assertDontSee('Rp 602.000');

        // Filter placement status: active
        $respPlActive = $this->actingAs($admin)->get(route('invoices.index', ['placement_status' => 'active']));
        $respPlActive->assertSee('Rp 601.000');
        $respPlActive->assertDontSee('Rp 602.000');

        // Filter placement status: ended
        $respPlEnded = $this->actingAs($admin)->get(route('invoices.index', ['placement_status' => 'ended']));
        $respPlEnded->assertSee('Rp 602.000');
        $respPlEnded->assertDontSee('Rp 601.000');
    }

    public function test_invalid_query_parameters_sanitized_without_500(): void
    {
        $admin = $this->createUser('admin', 'admin.sanitize@example.test');

        // Test array parameter injection which often triggers 500 in unvalidated code
        $resp = $this->actingAs($admin)->get(route('invoices.index', [
            'status' => ['invalid', 'array'],
            'search' => ['bad' => 'payload'],
            'period' => 'invalid-period',
        ]));

        $resp->assertRedirect(route('invoices.index'));
        $resp->assertSessionHasErrors(['status', 'search', 'period']);
    }

    // =========================================================================
    // 4. HISTORICAL ENDED PLACEMENT & SNAPSHOT IMMUTABILITY
    // =========================================================================

    public function test_invoice_snapshots_remain_immutable_after_room_and_resident_updates(): void
    {
        $admin = $this->createUser('admin', 'admin.immut@example.test');
        $resident = $this->createResident('res.immut@example.test', 'Nama Asli Sebelum Edit');
        $room = $this->createRoom('K-701');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-07-01');

        $invoice = $this->createInvoice($placement, $admin, '2026-07-01', '2026-07-10', 950000);

        $this->assertEquals('K-701', $invoice->room_number_snapshot);
        $this->assertEquals('Nama Asli Sebelum Edit', $invoice->resident_name_snapshot);

        // Update Room number and Resident name in DB
        $room->update(['number' => 'K-999-RENOV']);
        $resident->update(['name' => 'Nama Setelah Diperbarui Total']);

        // Reload invoice fresh from DB
        $invoice->refresh();

        $this->assertEquals('K-701', $invoice->room_number_snapshot);
        $this->assertEquals('Nama Asli Sebelum Edit', $invoice->resident_name_snapshot);

        // In show view, both snapshot and live are safely presented
        $respShow = $this->actingAs($admin)->get(route('invoices.show', $invoice));
        $respShow->assertStatus(200);
        $respShow->assertSee('K-701');
        $respShow->assertSee('Nama Asli Sebelum Edit');
        $respShow->assertSee('K-999-RENOV');
        $respShow->assertSee('Nama Setelah Diperbarui Total');
    }

    // =========================================================================
    // 5. PREVIEW SINKRONISASI (0 MUTASI, SESI SERVER & ESTIMASI)
    // =========================================================================

    public function test_sync_preview_performs_zero_database_mutations_and_returns_bound_token(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.preview@example.test');
        $resident = $this->createResident('res.prev@example.test', 'Penghuni Preview');
        $room = $this->createRoom('K-801', 900000);
        $placement = $this->createPlacement($resident, $room, $admin, '2026-07-01');

        $initialInvoiceCount = Invoice::count();
        $initialAuditCount = DB::table('activity_logs')->count();

        $response = $this->actingAs($admin)->postJson(route('invoices.sync-preview'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'preview_token',
                'scope_type',
                'placement_id',
                'business_date',
                'total_missing_invoices',
                'total_missing_amount',
                'total_missing_amount_formatted',
                'placements_count',
                'items',
                'is_complete',
                'disclaimer',
                'expires_at',
            ],
        ]);

        // Assert 0 mutations occurred
        $this->assertEquals($initialInvoiceCount, Invoice::count(), 'Preview must not create invoices.');
        $this->assertEquals($initialAuditCount, DB::table('activity_logs')->count(), 'Preview must not generate audit logs.');

        $token = $response->json('data.preview_token');
        $this->assertNotEmpty($token);
        $this->assertEquals(40, strlen($token));

        // Assert session binding
        $sessionKey = "billing_sync_preview_{$token}";
        $this->assertTrue(session()->has($sessionKey));
        $sessionData = session($sessionKey);
        $this->assertEquals($admin->id, $sessionData['admin_id']);
        $this->assertEquals('global', $sessionData['scope_type']);
        $this->assertNull($sessionData['placement_id']);
    }

    public function test_sync_preview_for_single_placement(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.prevsingle@example.test');
        $resident = $this->createResident('res.prevsingle@example.test', 'Penghuni Single');
        $room = $this->createRoom('K-802', 750000);
        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01');

        $response = $this->actingAs($admin)->postJson(route('invoices.sync-preview'), [
            'placement_id' => $placement->id,
        ]);

        $response->assertStatus(200);
        $token = $response->json('data.preview_token');
        $sessionKey = "billing_sync_preview_{$token}";
        $sessionData = session($sessionKey);

        $this->assertEquals('placement', $sessionData['scope_type']);
        $this->assertEquals($placement->id, $sessionData['placement_id']);
    }

    // =========================================================================
    // 6. EKSEKUSI SINKRONISASI, SCOPE TAMPERING, ACTOR MISMATCH & IDEMPOTENCY
    // =========================================================================

    public function test_sync_execution_creates_invoices_and_invalidates_token(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.sync@example.test');
        $resident = $this->createResident('res.sync@example.test', 'Penghuni Sinkron');
        $room = $this->createRoom('K-803', 1000000);
        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01');

        // Step 1: Preview
        $previewResp = $this->actingAs($admin)->postJson(route('invoices.sync-preview'), [
            'placement_id' => $placement->id,
        ]);
        $token = $previewResp->json('data.preview_token');
        $sessionKey = "billing_sync_preview_{$token}";

        $this->assertTrue(session()->has($sessionKey));

        // Step 2: Execute Sync
        $syncResp = $this->actingAs($admin)->post(route('invoices.sync'), [
            'preview_token' => $token,
            'placement_id' => $placement->id,
        ]);

        $syncResp->assertRedirect(route('invoices.index'));
        $syncResp->assertSessionHas('success');

        // Token must be invalidated
        $this->assertFalse(session()->has($sessionKey));

        // 2 invoices must be created (2026-08-01 and 2026-09-01)
        $this->assertEquals(2, $placement->invoices()->count());
    }

    public function test_scope_tampering_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.tamper@example.test');
        $resident1 = $this->createResident('res.tamper1@example.test', 'Penghuni 1');
        $resident2 = $this->createResident('res.tamper2@example.test', 'Penghuni 2');
        $room1 = $this->createRoom('K-804');
        $room2 = $this->createRoom('K-805');

        $p1 = $this->createPlacement($resident1, $room1, $admin, '2026-08-01');
        $p2 = $this->createPlacement($resident2, $room2, $admin, '2026-08-01');

        // Admin previews placement 1
        $previewResp = $this->actingAs($admin)->postJson(route('invoices.sync-preview'), [
            'placement_id' => $p1->id,
        ]);
        $token = $previewResp->json('data.preview_token');

        // Attacker submits payload changing placement_id to p2
        $tamperedResp = $this->actingAs($admin)->post(route('invoices.sync'), [
            'preview_token' => $token,
            'placement_id' => $p2->id,
        ]);

        $tamperedResp->assertRedirect(route('invoices.index'));
        $tamperedResp->assertSessionHas('error', 'Lingkup penempatan tidak sesuai dengan data pratinjau terverifikasi.');

        // Neither placement should have been modified
        $this->assertEquals(0, $p1->invoices()->count());
        $this->assertEquals(0, $p2->invoices()->count());
    }

    public function test_actor_mismatch_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Jakarta'));

        $adminA = $this->createUser('admin', 'admin.a@example.test');
        $adminB = $this->createUser('admin', 'admin.b@example.test');
        $resident = $this->createResident('res.mismatch@example.test', 'Penghuni Mismatch');
        $room = $this->createRoom('K-806');
        $p = $this->createPlacement($resident, $room, $adminA, '2026-08-01');

        // Admin A generates preview
        $previewResp = $this->actingAs($adminA)->postJson(route('invoices.sync-preview'));
        $token = $previewResp->json('data.preview_token');

        // Admin B attempts to execute with Admin A's token
        $resp = $this->actingAs($adminB)->post(route('invoices.sync'), [
            'preview_token' => $token,
        ]);

        $resp->assertRedirect(route('invoices.index'));
        $resp->assertSessionHas('error', 'Token pratinjau dibuat oleh Administrator lain. Silakan lakukan pratinjau ulang.');
        $this->assertEquals(0, $p->invoices()->count());
    }

    public function test_expired_preview_token_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.expire@example.test');
        $resident = $this->createResident('res.exp@example.test', 'Penghuni Expire');
        $room = $this->createRoom('K-807');
        $p = $this->createPlacement($resident, $room, $admin, '2026-08-01');

        $previewResp = $this->actingAs($admin)->postJson(route('invoices.sync-preview'));
        $token = $previewResp->json('data.preview_token');

        // Advance time by 16 minutes (exceeding 15-minute token TTL)
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:16:00', 'Asia/Jakarta'));

        $resp = $this->actingAs($admin)->post(route('invoices.sync'), [
            'preview_token' => $token,
        ]);

        $resp->assertRedirect(route('invoices.index'));
        $resp->assertSessionHas('error', 'Sesi pratinjau sinkronisasi tidak valid atau telah kedaluwarsa. Silakan lakukan pratinjau ulang.');
    }

    public function test_sync_execution_is_strictly_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.idemp@example.test');
        $resident = $this->createResident('res.idemp@example.test', 'Penghuni Idemp');
        $room = $this->createRoom('K-808');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01');

        // First sync
        $prev1 = $this->actingAs($admin)->postJson(route('invoices.sync-preview'), ['placement_id' => $placement->id]);
        $tok1 = $prev1->json('data.preview_token');
        $this->actingAs($admin)->post(route('invoices.sync'), ['preview_token' => $tok1, 'placement_id' => $placement->id]);

        $this->assertEquals(2, $placement->invoices()->count());

        // Second sync (fresh preview)
        $prev2 = $this->actingAs($admin)->postJson(route('invoices.sync-preview'), ['placement_id' => $placement->id]);
        $tok2 = $prev2->json('data.preview_token');
        $this->assertEquals(0, $prev2->json('data.total_missing_invoices'));

        $resp2 = $this->actingAs($admin)->post(route('invoices.sync'), ['preview_token' => $tok2, 'placement_id' => $placement->id]);
        $resp2->assertSessionHas('info', 'Seluruh tagihan sudah lengkap. Tidak ada invoice baru yang perlu diterbitkan.');

        // Invoice count remains exactly 2
        $this->assertEquals(2, $placement->invoices()->count());
    }

    // =========================================================================
    // 7. PREVENSI N+1 QUERY PADA DAFTAR & DETAIL TAGIHAN
    // =========================================================================

    public function test_invoices_listing_table_eager_loads_relations_without_n_plus_one(): void
    {
        $admin = $this->createUser('admin', 'admin.nplus1@example.test');
        $resident = $this->createResident('res.nplus1@example.test', 'Penghuni NPlus1');
        $room = $this->createRoom('K-N101');
        $placement = $this->createPlacement($resident, $room, $admin, '2025-01-01');

        // Create 10 invoices across months for this placement
        for ($i = 1; $i <= 10; $i++) {
            $month = str_pad($i, 2, '0', STR_PAD_LEFT);
            $this->createInvoice($placement, $admin, "2024-{$month}-01", "2024-{$month}-10");
        }

        // Warm up auth session and permissions
        $this->actingAs($resident->user)->get(route('invoices.index', ['per_page' => 10]));

        // Measure queries for 10 records (eager loading placement.room, resident, validPayment)
        DB::flushQueryLog();
        DB::enableQueryLog();

        $resp1 = $this->actingAs($resident->user)->get(route('invoices.index', ['per_page' => 10]));
        $resp1->assertStatus(200);

        $queriesCount10First = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Create 10 more invoices across months (total 20)
        for ($i = 1; $i <= 10; $i++) {
            $month = str_pad($i, 2, '0', STR_PAD_LEFT);
            $this->createInvoice($placement, $admin, "2025-{$month}-01", "2025-{$month}-10");
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $resp2 = $this->actingAs($resident->user)->get(route('invoices.index', ['per_page' => 10]));
        $resp2->assertStatus(200);

        $queriesCount10Second = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Query count for paginated listing of 10 items must remain constant (O(1) relation queries)
        $this->assertGreaterThan(0, $queriesCount10First);
        $this->assertEquals($queriesCount10First, $queriesCount10Second, 'Query count for invoice listing table must remain constant (no N+1).');
    }

    // =========================================================================
    // 8. PENGUJIAN PENGURUTAN (SORTING) & PRESERVASI FILTER / PAGINATION
    // =========================================================================

    public function test_invoices_sorting_preserves_filters_and_orders_correctly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.sort@example.test');
        $residentA = $this->createResident('res.sorta@example.test', 'Andi Santoso');
        $residentB = $this->createResident('res.sortb@example.test', 'Budi Pratama');
        $room1 = $this->createRoom('K-101', 500000);
        $room2 = $this->createRoom('K-202', 900000);

        $p1 = $this->createPlacement($residentA, $room1, $admin, '2026-07-01', null, 500000);
        $p2 = $this->createPlacement($residentB, $room2, $admin, '2026-08-01', null, 900000);

        // Invoice 1: Andi, K-101, 2026-07-01, due 2026-07-10, Rp 500.000 (unpaid)
        $inv1 = $this->createInvoice($p1, $admin, '2026-07-01', '2026-07-10', 500000);
        // Invoice 2: Budi, K-202, 2026-08-01, due 2026-08-10, Rp 900.000 (unpaid)
        $inv2 = $this->createInvoice($p2, $admin, '2026-08-01', '2026-08-10', 900000);
        // Invoice 3: Andi, K-101, 2026-08-01, due 2026-08-10, Rp 500.000 (paid)
        $inv3 = $this->createInvoice($p1, $admin, '2026-08-01', '2026-08-10', 500000);
        $this->createPayment($inv3, $admin, 500000, '2026-08-05');

        // 1. Sort by amount ASC with filter status=unpaid
        // Expected order: inv1 (500.000) then inv2 (900.000), inv3 (paid) excluded
        $respAmountAsc = $this->actingAs($admin)->get(route('invoices.index', [
            'status' => 'unpaid',
            'sort' => 'amount',
            'direction' => 'asc',
        ]));
        $respAmountAsc->assertStatus(200);
        $invoicesAmountAsc = $respAmountAsc->viewData('invoices')->items();
        $this->assertCount(2, $invoicesAmountAsc);
        $this->assertEquals($inv1->id, $invoicesAmountAsc[0]->id);
        $this->assertEquals($inv2->id, $invoicesAmountAsc[1]->id);

        // 2. Sort by amount DESC with filter status=unpaid
        $respAmountDesc = $this->actingAs($admin)->get(route('invoices.index', [
            'status' => 'unpaid',
            'sort' => 'amount',
            'direction' => 'desc',
        ]));
        $respAmountDesc->assertStatus(200);
        $invoicesAmountDesc = $respAmountDesc->viewData('invoices')->items();
        $this->assertEquals($inv2->id, $invoicesAmountDesc[0]->id);
        $this->assertEquals($inv1->id, $invoicesAmountDesc[1]->id);

        // 3. Sort by resident_name ASC with filter status=unpaid
        $respResidentAsc = $this->actingAs($admin)->get(route('invoices.index', [
            'status' => 'unpaid',
            'sort' => 'resident_name',
            'direction' => 'asc',
        ]));
        $respResidentAsc->assertStatus(200);
        $invoicesResAsc = $respResidentAsc->viewData('invoices')->items();
        $this->assertEquals('Andi Santoso', $invoicesResAsc[0]->resident_name_snapshot);
        $this->assertEquals('Budi Pratama', $invoicesResAsc[1]->resident_name_snapshot);

        // 4. Sort by room_number DESC
        $respRoomDesc = $this->actingAs($admin)->get(route('invoices.index', [
            'status' => 'unpaid',
            'sort' => 'room_number',
            'direction' => 'desc',
        ]));
        $respRoomDesc->assertStatus(200);
        $invoicesRoomDesc = $respRoomDesc->viewData('invoices')->items();
        $this->assertEquals('K-202', $invoicesRoomDesc[0]->room_number_snapshot);
        $this->assertEquals('K-101', $invoicesRoomDesc[1]->room_number_snapshot);

        // 5. Sort by due_on ASC
        $respDueAsc = $this->actingAs($admin)->get(route('invoices.index', [
            'status' => 'unpaid',
            'sort' => 'due_on',
            'direction' => 'asc',
        ]));
        $respDueAsc->assertStatus(200);
        $invoicesDueAsc = $respDueAsc->viewData('invoices')->items();
        $this->assertEquals('2026-07-10', $invoicesDueAsc[0]->due_on->toDateString());
        $this->assertEquals('2026-08-10', $invoicesDueAsc[1]->due_on->toDateString());

        // 6. Verify HTML sort links preserve filters and omit page parameter
        $html = $respAmountAsc->getContent();
        $this->assertStringContainsString('status=unpaid', $html);
        $this->assertStringNotContainsString('page=', $html);
    }

    // =========================================================================
    // 9. PENGUJIAN FORMAT TIMESTAMP PENERBITAN & VOID (WIB)
    // =========================================================================

    public function test_invoice_detail_displays_created_at_and_voided_at_in_wib_timezone_without_shifting_date_columns(): void
    {
        // 10:00:00 WIB is 03:00:00 UTC
        Carbon::setTestNow(Carbon::parse('2026-09-20 03:00:00', 'UTC'));

        $admin = $this->createUser('admin', 'admin.tz@example.test');
        $resident = $this->createResident('res.tz@example.test', 'Penghuni Timezone');
        $room = $this->createRoom('K-TZ1');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01');

        $invoice = $this->createInvoice($placement, $admin, '2026-08-01', '2026-08-10', 850000);
        // Force created_at to 2026-09-20 03:30:00 UTC (which is 10:30 WIB)
        $invoice->created_at = Carbon::parse('2026-09-20 03:30:00', 'UTC');
        $invoice->save();

        // Create a voided payment at 2026-09-20 04:15:00 UTC (which is 11:15 WIB)
        $this->createPayment($invoice, $admin, 850000, '2026-08-10', '2026-09-20 04:15:00', 'Koreksi administratif');

        $response = $this->actingAs($admin)->get(route('invoices.show', $invoice));
        $response->assertStatus(200);

        // Verification of WIB conversion for created_at (03:30 UTC -> 10:30 WIB)
        $response->assertSee('20 September 2026, 10:30 WIB');

        // Verification of WIB conversion for voided_at (04:15 UTC -> 11:15 WIB)
        $response->assertSee('Void: 20 Sep 2026, 11:15 WIB');

        // Verification that DATE columns (due_on and paid_on) remain strictly unshifted calendar dates
        $response->assertSee('10 Agustus 2026'); // due_on
    }

    // =========================================================================
    // 10. PENGUJIAN HTTP T12: PREVIEW GLOBAL, PARTIAL FAILURE, RETRY, DRIFT & RECALCULATION
    // =========================================================================

    public function test_global_preview_and_sync_genuinely_creates_invoices_via_billing_service(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.globalsync@example.test');
        $resident1 = $this->createResident('res.gs1@example.test', 'Penghuni Global 1');
        $resident2 = $this->createResident('res.gs2@example.test', 'Penghuni Global 2');
        $room1 = $this->createRoom('K-G1', 600000);
        $room2 = $this->createRoom('K-G2', 700000);

        // Placement 1: starts 2026-08-01 -> missing Aug, Sept (2 invoices)
        $p1 = $this->createPlacement($resident1, $room1, $admin, '2026-08-01', null, 600000);
        // Placement 2: starts 2026-09-01 -> missing Sept (1 invoice)
        $p2 = $this->createPlacement($resident2, $room2, $admin, '2026-09-01', null, 700000);

        // 1. Admin generates global preview
        $previewResp = $this->actingAs($admin)->postJson(route('invoices.sync-preview'));
        $previewResp->assertStatus(200);
        $previewResp->assertJsonPath('data.scope_type', 'global');
        $previewResp->assertJsonPath('data.total_missing_invoices', 3);
        $token = $previewResp->json('data.preview_token');

        // 0 invoices created during preview
        $this->assertEquals(0, $p1->invoices()->count());
        $this->assertEquals(0, $p2->invoices()->count());

        // 2. Admin submits sync with token
        $syncResp = $this->actingAs($admin)->post(route('invoices.sync'), [
            'preview_token' => $token,
        ]);

        $syncResp->assertRedirect(route('invoices.index'));
        $syncResp->assertSessionHas('success', 'Sinkronisasi berhasil: 3 invoice baru berhasil diterbitkan.');

        // Invoices genuinely created via BillingService
        $this->assertEquals(2, $p1->invoices()->count());
        $this->assertEquals(1, $p2->invoices()->count());

        // Verify audit logs were created for each invoice
        $this->assertEquals(3, DB::table('activity_logs')->where('module', 'invoices')->where('action', 'create')->count());
    }

    /**
     * Catatan Batas Transaksi:
     * Pengujian feature menggunakan trait DatabaseTransactions, sehingga seluruh eksekusi dibungkus dalam
     * transaksi terluar (outer transaction) yang di-rollback pada akhir pengujian untuk isolasi fixture.
     * Oleh karena itu, pengujian ini memverifikasi bahwa BillingService::syncAllPlacements menangkap
     * kegagalan penempatan individual secara terisolasi tanpa menggugurkan loop batch, dan InvoiceController::sync
     * menyajikan laporan parsial (warning) serta penempatan yang sukses tetap tersimpan bersama auditnya
     * dalam konteks eksekusi tersebut.
     */
    public function test_partial_failure_reporting_when_one_placement_fails_during_sync(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.partial@example.test');
        $resident1 = $this->createResident('res.part1@example.test', 'Penghuni Sukses');
        $resident2 = $this->createResident('res.part2@example.test', 'Penghuni Gagal');
        $room1 = $this->createRoom('K-P1', 600000);
        $room2 = $this->createRoom('K-P2', 700000);

        $p1 = $this->createPlacement($resident1, $room1, $admin, '2026-08-01', null, 600000);
        $p2 = $this->createPlacement($resident2, $room2, $admin, '2026-08-01', null, 700000);

        // Preview global
        $previewResp = $this->actingAs($admin)->postJson(route('invoices.sync-preview'));
        $token = $previewResp->json('data.preview_token');

        // Partial mock BillingService to simulate failure exclusively on placement 2
        $billingReal = app(BillingService::class);
        $mock = $this->partialMock(BillingService::class);
        $mock->shouldReceive('syncPlacementInvoices')
            ->andReturnUsing(function ($placement, $actor, $asOfDate, $maxPeriodMonth) use ($billingReal, $p2) {
                if ($placement->id === $p2->id) {
                    throw new \DomainException("Simulasi kendala teknis penempatan #{$placement->id}");
                }
                return $billingReal->syncPlacementInvoices($placement, $actor, $asOfDate, $maxPeriodMonth);
            });

        // Submit sync
        $syncResp = $this->actingAs($admin)->post(route('invoices.sync'), [
            'preview_token' => $token,
        ]);

        $syncResp->assertRedirect(route('invoices.index'));
        $syncResp->assertSessionHas('warning');
        $warningMsg = session('warning');
        $this->assertStringContainsString('Sinkronisasi selesai sebagian', $warningMsg);
        $this->assertStringContainsString("penempatan #{$p2->id}", $warningMsg);

        // Placement 1 succeeded and created 2 invoices with audit logs
        $this->assertEquals(2, $p1->invoices()->count());
        // Placement 2 failed and has 0 invoices
        $this->assertEquals(0, $p2->invoices()->count());
    }

    public function test_retry_with_new_preview_does_not_duplicate_invoices_or_audits(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.retry@example.test');
        $resident = $this->createResident('res.retry@example.test', 'Penghuni Retry');
        $room = $this->createRoom('K-R1', 800000);
        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01', null, 800000);

        // Run first sync: creates 2 invoices (Aug, Sept)
        $prev1 = $this->actingAs($admin)->postJson(route('invoices.sync-preview'), ['placement_id' => $placement->id]);
        $tok1 = $prev1->json('data.preview_token');
        $this->actingAs($admin)->post(route('invoices.sync'), [
            'preview_token' => $tok1,
            'placement_id' => $placement->id,
        ]);

        $this->assertEquals(2, $placement->invoices()->count());
        $initialAuditCount = DB::table('activity_logs')->where('module', 'invoices')->where('action', 'create')->count();
        $this->assertEquals(2, $initialAuditCount);

        // Admin retries with a new preview
        $prev2 = $this->actingAs($admin)->postJson(route('invoices.sync-preview'), ['placement_id' => $placement->id]);
        $prev2->assertStatus(200);
        $prev2->assertJsonPath('data.total_missing_invoices', 0);
        $prev2->assertJsonPath('data.is_complete', true);
        $tok2 = $prev2->json('data.preview_token');

        // Submit sync with new preview token
        $resp2 = $this->actingAs($admin)->post(route('invoices.sync'), [
            'preview_token' => $tok2,
            'placement_id' => $placement->id,
        ]);
        $resp2->assertRedirect(route('invoices.index'));
        $resp2->assertSessionHas('info', 'Seluruh tagihan sudah lengkap. Tidak ada invoice baru yang perlu diterbitkan.');

        // Invoice count must remain exactly 2 (no duplicates)
        $this->assertEquals(2, $placement->invoices()->count());

        // Audit count must remain exactly 2 (no duplicate audit logs)
        $afterRetryAuditCount = DB::table('activity_logs')->where('module', 'invoices')->where('action', 'create')->count();
        $this->assertEquals(2, $afterRetryAuditCount);
    }

    public function test_sync_rejected_when_wib_date_drifts_even_within_token_ttl(): void
    {
        // 23:55:00 WIB on 2026-09-20
        Carbon::setTestNow(Carbon::parse('2026-09-20 23:55:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.drift@example.test');
        $resident = $this->createResident('res.drift@example.test', 'Penghuni Drift');
        $room = $this->createRoom('K-D1');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01');

        // Step 1: Admin generates preview at 23:55:00 WIB
        $previewResp = $this->actingAs($admin)->postJson(route('invoices.sync-preview'), [
            'placement_id' => $placement->id,
        ]);
        $previewResp->assertStatus(200);
        $token = $previewResp->json('data.preview_token');

        // Advance time by only 6 minutes to 00:01:00 WIB on next day (2026-09-21)
        // 6 minutes elapsed is strictly WITHIN the 15-minute token TTL
        Carbon::setTestNow(Carbon::parse('2026-09-21 00:01:00', 'Asia/Jakarta'));

        // Step 2: Attempt sync
        $syncResp = $this->actingAs($admin)->post(route('invoices.sync'), [
            'preview_token' => $token,
            'placement_id' => $placement->id,
        ]);

        $syncResp->assertRedirect(route('invoices.index'));
        $syncResp->assertSessionHas('error', 'Tanggal operasional bisnis telah berganti sejak pratinjau dibuat. Silakan lakukan pratinjau ulang.');

        // No mutations occurred
        $this->assertEquals(0, $placement->invoices()->count());
    }

    public function test_post_preview_data_changes_are_recalculated_safely_upon_sync(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Jakarta'));

        $admin = $this->createUser('admin', 'admin.recalc@example.test');
        $resident = $this->createResident('res.recalc@example.test', 'Penghuni Recalc');
        $room = $this->createRoom('K-RC1');
        // Placement starts 2026-07-01 -> originally 3 missing invoices (July, August, September)
        $placement = $this->createPlacement($resident, $room, $admin, '2026-07-01');

        // 1. Preview reports 3 missing invoices
        $prevResp = $this->actingAs($admin)->postJson(route('invoices.sync-preview'), [
            'placement_id' => $placement->id,
        ]);
        $prevResp->assertStatus(200);
        $prevResp->assertJsonPath('data.total_missing_invoices', 3);
        $token = $prevResp->json('data.preview_token');

        // 2. Data changes post-preview: Placement is ended on 2026-08-15
        // (So September is no longer required, only July and August are required)
        $placement->update([
            'ended_on' => '2026-08-15',
            'ended_by' => $admin->id,
            'end_reason' => 'Pindah domisili mendadak',
        ]);

        // 3. Admin submits sync using the token obtained before the change
        $syncResp = $this->actingAs($admin)->post(route('invoices.sync'), [
            'preview_token' => $token,
            'placement_id' => $placement->id,
        ]);

        $syncResp->assertRedirect(route('invoices.index'));
        $syncResp->assertSessionHas('success', 'Sinkronisasi berhasil: 2 invoice baru berhasil diterbitkan.');

        // Verify only 2 invoices were created (July and August), September was NOT created
        $this->assertEquals(2, $placement->invoices()->count());
        $periods = $placement->invoices()->pluck('period_month')->map(fn($d) => Carbon::parse($d)->format('Y-m'))->all();
        $this->assertContains('2026-07', $periods);
        $this->assertContains('2026-08', $periods);
        $this->assertNotContains('2026-09', $periods);
    }
}
