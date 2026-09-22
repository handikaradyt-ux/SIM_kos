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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BillingServiceTest extends TestCase
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
        Carbon::setTestNow(); // Reset frozen time
        Mockery::close();
        parent::tearDown();
    }

    private function createUser(string $roleCode, string $email, bool $isActive = true, ?string $name = null): User
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

    private function createRoom(string $number = 'K-101', int $rate = 800000, ?string $archivedAt = null): Room
    {
        return Room::create([
            'number' => $number,
            'type' => 'Standard',
            'monthly_rate' => $rate,
            'archived_at' => $archivedAt,
        ]);
    }

    private function createResident(string $email = 'resident.bill@example.test', string $name = 'Penghuni Billing'): Resident
    {
        $user = $this->createUser('resident', $email, true, $name);

        return Resident::create([
            'user_id' => $user->id,
            'name' => $name,
            'phone' => '0812' . rand(10000000, 99999999),
            'origin_address' => 'Jl. Merdeka No. 1, Jakarta',
        ]);
    }

    private function createPlacement(
        Resident $resident,
        Room $room,
        User $creator,
        string $startedOn,
        ?string $endedOn = null,
        int $agreedRate = 800000,
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
            'ended_by' => $ender?->id,
            'end_reason' => $endReason,
        ]);
    }

    // -------------------------------------------------------------------------
    // 1. TC-12: Due Date & Full Amount for started_on on the 1st, 5th, and 10th
    // -------------------------------------------------------------------------

    public function test_due_date_and_full_amount_for_started_on_1st_5th_and_10th(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00'); // Frozen in Asia/Jakarta

        $admin = $this->createUser('admin', 'admin.tc12@example.test');
        $room1 = $this->createRoom('R-TC12-1');
        $room2 = $this->createRoom('R-TC12-2');
        $room3 = $this->createRoom('R-TC12-3');

        $res1 = $this->createResident('res1.tc12@example.test', 'Penghuni Tgl 1');
        $res2 = $this->createResident('res2.tc12@example.test', 'Penghuni Tgl 5');
        $res3 = $this->createResident('res3.tc12@example.test', 'Penghuni Tgl 10');

        $p1 = $this->createPlacement($res1, $room1, $admin, '2026-09-01', null, 800000);
        $p2 = $this->createPlacement($res2, $room2, $admin, '2026-09-05', null, 900000);
        $p3 = $this->createPlacement($res3, $room3, $admin, '2026-09-10', null, 1000000);

        // Period 1 (September 2026):
        // p1 (started 1st): due date is 5th, amount 800.000 full
        $this->assertEquals('2026-09-05', $this->billingService->calculateDueDate($p1, '2026-09-01'));
        // p2 (started 5th): due date is 5th, amount 900.000 full
        $this->assertEquals('2026-09-05', $this->billingService->calculateDueDate($p2, '2026-09-01'));
        // p3 (started 10th): started after 5th -> first invoice due date is 10th (started_on), amount 1.000.000 full
        $this->assertEquals('2026-09-10', $this->billingService->calculateDueDate($p3, '2026-09-01'));

        // Period 2 (October 2026):
        // Subsequent invoice for p3 returns to the 5th
        $this->assertEquals('2026-10-05', $this->billingService->calculateDueDate($p3, '2026-10-01'));

        // Perform sync on p3 and assert stored amounts and due dates
        $result = $this->billingService->syncPlacementInvoices($p3, $admin);
        $this->assertEquals(2, $result['created_count']);

        $invoices = Invoice::where('placement_id', $p3->id)->orderBy('period_month')->get();
        $this->assertCount(2, $invoices);

        // Invoice 1: Sept 2026 -> due 2026-09-10, amount 1.000.000 full
        $this->assertEquals('2026-09-01', $invoices[0]->period_month->toDateString());
        $this->assertEquals('2026-09-10', $invoices[0]->due_on->toDateString());
        $this->assertEquals(1000000, $invoices[0]->amount);

        // Invoice 2: Oct 2026 -> due 2026-10-05, amount 1.000.000 full
        $this->assertEquals('2026-10-01', $invoices[1]->period_month->toDateString());
        $this->assertEquals('2026-10-05', $invoices[1]->due_on->toDateString());
        $this->assertEquals(1000000, $invoices[1]->amount);
    }

    // -------------------------------------------------------------------------
    // 2. TC-33: End of Month, February (Leap vs Common), and Year Crossover
    // -------------------------------------------------------------------------

    public function test_end_of_month_february_leap_and_december_january_crossover(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.tc33@example.test');
        $room = $this->createRoom('R-TC33');
        $resident = $this->createResident('res.tc33@example.test');

        // A. Started on 31st of August
        $pMonthEnd = $this->createPlacement($resident, $room, $admin, '2026-08-31', null, 850000);
        $periods = $this->billingService->getRequiredPeriods($pMonthEnd);
        $this->assertEquals(['2026-08-01', '2026-09-01', '2026-10-01'], $periods);

        // Period 1 (August): started on 31st (> 5th) -> due date is 2026-08-31
        $this->assertEquals('2026-08-31', $this->billingService->calculateDueDate($pMonthEnd, '2026-08-01'));
        // Period 2 (September): due date is 2026-09-05
        $this->assertEquals('2026-09-05', $this->billingService->calculateDueDate($pMonthEnd, '2026-09-01'));

        // B. February in Leap Year (2024: 29 days)
        $r2024 = $this->createRoom('R-2024');
        $res2024 = $this->createResident('res2024@example.test');
        $pLeap = $this->createPlacement($res2024, $r2024, $admin, '2024-01-20', '2024-03-05', 800000, $admin, 'Sewa berakhir');
        $periodsLeap = $this->billingService->getRequiredPeriods($pLeap);
        $this->assertEquals(['2024-01-01', '2024-02-01', '2024-03-01'], $periodsLeap);
        $this->assertEquals('2024-02-05', $this->billingService->calculateDueDate($pLeap, '2024-02-01'));

        // C. February in Common Year (2025: 28 days)
        $r2025 = $this->createRoom('R-2025');
        $res2025 = $this->createResident('res2025@example.test');
        $pCommon = $this->createPlacement($res2025, $r2025, $admin, '2025-01-10', '2025-03-02', 800000, $admin, 'Sewa berakhir');
        $periodsCommon = $this->billingService->getRequiredPeriods($pCommon);
        $this->assertEquals(['2025-01-01', '2025-02-01', '2025-03-01'], $periodsCommon);

        // D. Year crossover (November 2025 to February 2026)
        $rCross = $this->createRoom('R-CROSS');
        $resCross = $this->createResident('rescross@example.test');
        $pCross = $this->createPlacement($resCross, $rCross, $admin, '2025-11-15', '2026-02-10', 800000, $admin, 'Sewa berakhir');
        $periodsCross = $this->billingService->getRequiredPeriods($pCross);
        $this->assertEquals(['2025-11-01', '2025-12-01', '2026-01-01', '2026-02-01'], $periodsCross);
    }

    // -------------------------------------------------------------------------
    // 3. Started and Ended in Same Month produces exactly 1 period
    // -------------------------------------------------------------------------

    public function test_placement_started_and_ended_in_same_month_produces_single_period(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.samemonth@example.test');
        $room = $this->createRoom('R-SAME');
        $resident = $this->createResident('res.samemonth@example.test');

        $placement = $this->createPlacement($resident, $room, $admin, '2026-04-05', '2026-04-20', 800000, $admin, 'Selesai magang');

        $periods = $this->billingService->getRequiredPeriods($placement);
        $this->assertCount(1, $periods);
        $this->assertEquals(['2026-04-01'], $periods);

        // Full month rate (no prorating)
        $preview = $this->billingService->previewPlacement($placement);
        $this->assertEquals(1, $preview['new_invoices_count']);
        $this->assertEquals(800000, $preview['total_new_amount']);
        $this->assertEquals('2026-04-05', $preview['items'][0]['due_on']);
    }

    // -------------------------------------------------------------------------
    // 4. Month of departure is billed full, subsequent months are not created
    // -------------------------------------------------------------------------

    public function test_month_of_departure_is_billed_full_and_subsequent_months_are_not_created(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.depmonth@example.test');
        $room = $this->createRoom('R-DEP');
        $resident = $this->createResident('res.depmonth@example.test');

        // Ended on 2026-03-01 (1st of March) -> March MUST still be billed in full!
        $placement = $this->createPlacement($resident, $room, $admin, '2026-01-10', '2026-03-01', 800000, $admin, 'Pindah dinas');

        $periods = $this->billingService->getRequiredPeriods($placement);
        $this->assertEquals(['2026-01-01', '2026-02-01', '2026-03-01'], $periods);

        // Sync and verify that April 2026 is NOT created
        $this->billingService->syncPlacementInvoices($placement, $admin);

        $invoices = Invoice::where('placement_id', $placement->id)->orderBy('period_month')->get();
        $this->assertCount(3, $invoices);
        $this->assertEquals('2026-03-01', $invoices[2]->period_month->toDateString());
        $this->assertEquals(800000, $invoices[2]->amount);

        $this->assertFalse(
            Invoice::where('placement_id', $placement->id)->where('period_month', '2026-04-01')->exists()
        );
    }

    // -------------------------------------------------------------------------
    // 5. Placement starting after reference date in same month returns empty array
    // -------------------------------------------------------------------------

    public function test_placement_starting_after_reference_date_in_same_month_returns_empty_array(): void
    {
        // Reference date frozen at September 5th
        Carbon::setTestNow('2026-09-05 10:00:00');

        $admin = $this->createUser('admin', 'admin.futurestart@example.test');
        $room = $this->createRoom('R-FUT-START');
        $resident = $this->createResident('res.futstart@example.test');

        // Placement starts on September 10th (after reference date September 5th, but same calendar month)
        $placement = $this->createPlacement($resident, $room, $admin, '2026-09-10', null, 800000);

        // Required periods must be EMPTY as of September 5th!
        $periods = $this->billingService->getRequiredPeriods($placement);
        $this->assertEmpty($periods);

        $preview = $this->billingService->previewPlacement($placement);
        $this->assertEquals(0, $preview['new_invoices_count']);
        $this->assertEmpty($preview['items']);

        // Sync does nothing and creates 0 invoices
        $sync = $this->billingService->syncPlacementInvoices($placement, $admin);
        $this->assertEquals(0, $sync['created_count']);
        $this->assertEmpty($sync['created_invoices']);
    }

    // -------------------------------------------------------------------------
    // 6. Future months beyond asOfDate are never created and cannot be bypassed
    // -------------------------------------------------------------------------

    public function test_future_months_beyond_as_of_date_are_never_created_and_cannot_be_bypassed(): void
    {
        Carbon::setTestNow('2026-03-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.nofuture@example.test');
        $room = $this->createRoom('R-NOFUT');
        $resident = $this->createResident('res.nofut@example.test');

        $placement = $this->createPlacement($resident, $room, $admin, '2026-01-01', null, 800000);

        // Required periods up to current month (March 2026)
        $periods = $this->billingService->getRequiredPeriods($placement);
        $this->assertEquals(['2026-01-01', '2026-02-01', '2026-03-01'], $periods);

        // Attempting to pass an asOfDate in the future (e.g. 2026-05-01) must be rejected
        $this->expectException(InvalidArgumentException::class);
        $this->billingService->getRequiredPeriods($placement, Carbon::parse('2026-05-01'));
    }

    // -------------------------------------------------------------------------
    // 7. maxPeriodMonth narrows range and handles boundary constraints
    // -------------------------------------------------------------------------

    public function test_max_period_month_narrows_range_and_handles_boundaries(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.maxperiod@example.test');
        $room = $this->createRoom('R-MAX');
        $resident = $this->createResident('res.max@example.test');

        $placement = $this->createPlacement($resident, $room, $admin, '2026-01-01', null, 800000);

        // 1. maxPeriodMonth narrows to March 2026
        $periodsNarrow = $this->billingService->getRequiredPeriods($placement, null, '2026-03-01');
        $this->assertEquals(['2026-01-01', '2026-02-01', '2026-03-01'], $periodsNarrow);

        // 2. maxPeriodMonth before start date (December 2025) returns empty array
        $periodsBefore = $this->billingService->getRequiredPeriods($placement, null, '2025-12-01');
        $this->assertEmpty($periodsBefore);

        // 3. maxPeriodMonth exceeding current month (September 2026) is clamped to June 2026
        $periodsClamped = $this->billingService->getRequiredPeriods($placement, null, '2026-09-01');
        $this->assertEquals([
            '2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01', '2026-06-01',
        ], $periodsClamped);
    }

    // -------------------------------------------------------------------------
    // 8. calculateDueDate and calculateInvoicePayload reject out-of-range/invalid periods
    // -------------------------------------------------------------------------

    public function test_calculate_due_date_and_payload_reject_out_of_range_or_invalid_periods(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.invaliddates@example.test');
        $room = $this->createRoom('R-INVD');
        $resident = $this->createResident('res.invd@example.test');

        // Placement ended on 2026-05-31
        $placement = $this->createPlacement($resident, $room, $admin, '2026-03-01', '2026-05-31', 800000, $admin, 'Sewa berakhir');

        // --- calculateDueDate checks ---
        // A. Period before started_on
        try {
            $this->billingService->calculateDueDate($placement, '2026-02-01');
            $this->fail('Expected InvalidArgumentException for period before started_on');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sebelum tanggal mulai', $e->getMessage());
        }

        // B. Period after ended_on
        try {
            $this->billingService->calculateDueDate($placement, '2026-06-01');
            $this->fail('Expected InvalidArgumentException for period after ended_on');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('melampaui bulan akhir', $e->getMessage());
        }

        // C. Ambiguous or invalid date format
        try {
            $this->billingService->calculateDueDate($placement, '01/04/2026');
            $this->fail('Expected InvalidArgumentException for ambiguous date format');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Format tanggal periode tidak valid', $e->getMessage());
        }

        // --- calculateInvoicePayload direct checks ---
        // D. Period before started_on
        try {
            $this->billingService->calculateInvoicePayload($placement, '2026-02-01', $admin);
            $this->fail('Expected InvalidArgumentException on payload for period before started_on');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sebelum tanggal mulai', $e->getMessage());
        }

        // E. Period after ended_on
        try {
            $this->billingService->calculateInvoicePayload($placement, '2026-06-01', $admin);
            $this->fail('Expected InvalidArgumentException on payload for period after ended_on');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('melampaui bulan akhir', $e->getMessage());
        }

        // F. Period after current business month (future period)
        $room2 = $this->createRoom('R-INVD2');
        $resident2 = $this->createResident('res.invd2@example.test');
        $activePlacement = $this->createPlacement($resident2, $room2, $admin, '2026-07-01', null, 800000);
        try {
            $this->billingService->calculateInvoicePayload($activePlacement, '2026-10-01', $admin);
            $this->fail('Expected InvalidArgumentException on payload for period after current business month');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('berada setelah bulan berjalan', $e->getMessage());
        }

        // G. Placement not started yet as of reference date, including started after today in the same month
        $room3 = $this->createRoom('R-INVD3');
        $resident3 = $this->createResident('res.invd3@example.test');
        $futureStartPlacement = $this->createPlacement($resident3, $room3, $admin, '2026-09-20', null, 800000);
        try {
            $this->billingService->calculateInvoicePayload($futureStartPlacement, '2026-09-01', $admin);
            $this->fail('Expected InvalidArgumentException on payload for placement not started yet');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('belum mulai per tanggal acuan', $e->getMessage());
        }

        // H. Valid issuance-ready payload returns complete snapshot and verified actor
        $payload = $this->billingService->calculateInvoicePayload($placement, '2026-04-01', $admin);
        $this->assertEquals($placement->id, $payload['placement_id']);
        $this->assertEquals('2026-04-01', $payload['period_month']);
        $this->assertEquals('2026-04-05', $payload['due_on']);
        $this->assertEquals(800000, $payload['amount']);
        $this->assertEquals($resident->name, $payload['resident_name_snapshot']);
        $this->assertEquals($room->number, $payload['room_number_snapshot']);
        $this->assertEquals($admin->id, $payload['created_by']);
    }

    // -------------------------------------------------------------------------
    // 9. Room rate change does not affect existing placement agreed rate
    // -------------------------------------------------------------------------

    public function test_room_rate_change_does_not_affect_existing_placement_agreed_rate(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.ratechange@example.test');
        $room = $this->createRoom('R-RATE-1', 800000);
        $resident = $this->createResident('res.ratechange@example.test');

        $placement = $this->createPlacement($resident, $room, $admin, '2026-07-01', null, 800000);

        // Admin updates room monthly_rate to 1.500.000 in rooms table
        $room->update(['monthly_rate' => 1500000]);

        // Sync placement invoices
        $this->billingService->syncPlacementInvoices($placement, $admin);

        $invoices = Invoice::where('placement_id', $placement->id)->get();
        $this->assertCount(3, $invoices);

        // All invoices MUST still be 800.000, NOT the new room rate of 1.500.000!
        foreach ($invoices as $inv) {
            $this->assertEquals(800000, $inv->amount);
        }
    }

    // -------------------------------------------------------------------------
    // 10. Changing resident name or room number preserves existing invoice snapshots
    // -------------------------------------------------------------------------

    public function test_changing_resident_name_or_room_number_preserves_existing_invoice_snapshots(): void
    {
        Carbon::setTestNow('2026-08-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.snapshot@example.test');
        $room = $this->createRoom('R-SNAP-1');
        $resident = $this->createResident('res.snap@example.test', 'Nama Asli Penghuni');

        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01', null, 800000);

        // Sync August invoice
        $this->billingService->syncPlacementInvoices($placement, $admin);

        $invAug = Invoice::where('placement_id', $placement->id)->where('period_month', '2026-08-01')->firstOrFail();
        $this->assertEquals('Nama Asli Penghuni', $invAug->resident_name_snapshot);
        $this->assertEquals('R-SNAP-1', $invAug->room_number_snapshot);

        // Advance time to September
        Carbon::setTestNow('2026-09-15 10:00:00');

        // Modify resident name and room number in database
        $resident->update(['name' => 'Nama Berubah Drastis']);
        $room->update(['number' => 'R-SNAP-RENAMED']);

        // Sync September invoice
        $this->billingService->syncPlacementInvoices($placement, $admin);

        // Refresh August invoice: snapshots MUST REMAIN UNTOUCHED!
        $invAug->refresh();
        $this->assertEquals('Nama Asli Penghuni', $invAug->resident_name_snapshot);
        $this->assertEquals('R-SNAP-1', $invAug->room_number_snapshot);

        // September invoice captured the new values at creation time
        $invSep = Invoice::where('placement_id', $placement->id)->where('period_month', '2026-09-01')->firstOrFail();
        $this->assertEquals('Nama Berubah Drastis', $invSep->resident_name_snapshot);
        $this->assertEquals('R-SNAP-RENAMED', $invSep->room_number_snapshot);
    }

    // -------------------------------------------------------------------------
    // 11. Repeated sync is idempotent and does not duplicate invoices or audits
    // -------------------------------------------------------------------------

    public function test_repeated_sync_is_idempotent_and_does_not_duplicate_invoices_or_audits(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.idempotent@example.test');
        $room = $this->createRoom('R-IDEM');
        $resident = $this->createResident('res.idem@example.test');

        $placement = $this->createPlacement($resident, $room, $admin, '2026-07-01', null, 800000);

        // First sync
        $res1 = $this->billingService->syncPlacementInvoices($placement, $admin);
        $this->assertEquals(3, $res1['created_count']);
        $this->assertEquals(0, $res1['already_existed_count']);
        $this->assertEquals(3, Invoice::where('placement_id', $placement->id)->count());

        $auditCountAfterFirst = ActivityLog::where('module', 'invoices')->count();
        $this->assertEquals(3, $auditCountAfterFirst);

        // Second sync (repeat immediately)
        $res2 = $this->billingService->syncPlacementInvoices($placement, $admin);
        $this->assertEquals(0, $res2['created_count']);
        $this->assertEquals(3, $res2['already_existed_count']);
        $this->assertEmpty($res2['created_invoices']);

        // Assert no new records and no new audits
        $this->assertEquals(3, Invoice::where('placement_id', $placement->id)->count());
        $this->assertEquals($auditCountAfterFirst, ActivityLog::where('module', 'invoices')->count());
    }

    // -------------------------------------------------------------------------
    // 12. TC-13: Missing invoice in middle of range is detected and synced (active & ended)
    // -------------------------------------------------------------------------

    public function test_missing_invoice_in_middle_of_range_is_detected_and_synced(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.coveragegap@example.test');
        $roomActive = $this->createRoom('R-GAP-ACT');
        $resActive = $this->createResident('res.gapact@example.test', 'Penghuni Gap Aktif');

        // Active placement from January 2026
        $pActive = $this->createPlacement($resActive, $roomActive, $admin, '2026-01-01', null, 800000);

        // Pre-create invoices for Jan and Mar, intentionally omitting Feb (the gap)
        Invoice::create([
            'placement_id' => $pActive->id,
            'period_month' => '2026-01-01',
            'due_on' => '2026-01-05',
            'amount' => 800000,
            'resident_name_snapshot' => $resActive->name,
            'room_number_snapshot' => $roomActive->number,
            'created_by' => $admin->id,
        ]);
        Invoice::create([
            'placement_id' => $pActive->id,
            'period_month' => '2026-03-01',
            'due_on' => '2026-03-05',
            'amount' => 800000,
            'resident_name_snapshot' => $resActive->name,
            'room_number_snapshot' => $roomActive->number,
            'created_by' => $admin->id,
        ]);

        // Ended placement from 2025: Oct and Dec exist, Nov missing
        $roomEnded = $this->createRoom('R-GAP-END');
        $resEnded = $this->createResident('res.gapend@example.test', 'Penghuni Gap Selesai');
        $pEnded = $this->createPlacement($resEnded, $roomEnded, $admin, '2025-10-01', '2025-12-31', 750000, $admin, 'Selesai');

        Invoice::create([
            'placement_id' => $pEnded->id,
            'period_month' => '2025-10-01',
            'due_on' => '2025-10-05',
            'amount' => 750000,
            'resident_name_snapshot' => $resEnded->name,
            'room_number_snapshot' => $roomEnded->number,
            'created_by' => $admin->id,
        ]);
        Invoice::create([
            'placement_id' => $pEnded->id,
            'period_month' => '2025-12-01',
            'due_on' => '2025-12-05',
            'amount' => 750000,
            'resident_name_snapshot' => $resEnded->name,
            'room_number_snapshot' => $roomEnded->number,
            'created_by' => $admin->id,
        ]);

        // Coverage check on active placement: missing Feb (and Apr-Sep)
        $covActive = $this->billingService->checkCoverage($pActive);
        $this->assertFalse($covActive['is_complete']);
        $this->assertContains('2026-02-01', $covActive['missing_periods']);

        // Coverage check on ended placement: missing Nov 2025
        $covEnded = $this->billingService->checkCoverage($pEnded);
        $this->assertFalse($covEnded['is_complete']);
        $this->assertEquals(['2025-11-01'], $covEnded['missing_periods']);

        // Global coverage detects both
        $globalCov = $this->billingService->checkGlobalCoverage();
        $this->assertFalse($globalCov['is_complete']);
        $this->assertGreaterThanOrEqual(2, $globalCov['placements_with_missing_count']);

        // Sync active placement
        $this->billingService->syncPlacementInvoices($pActive, $admin);
        $this->assertTrue($this->billingService->checkCoverage($pActive)['is_complete']);

        // Verify the missing Feb invoice was inserted with full rate and correct due date
        $febInv = Invoice::where('placement_id', $pActive->id)->where('period_month', '2026-02-01')->firstOrFail();
        $this->assertEquals('2026-02-05', $febInv->due_on->toDateString());
        $this->assertEquals(800000, $febInv->amount);

        // Sync ended placement
        $this->billingService->syncPlacementInvoices($pEnded, $admin);
        $this->assertTrue($this->billingService->checkCoverage($pEnded)['is_complete']);

        $novInv = Invoice::where('placement_id', $pEnded->id)->where('period_month', '2025-11-01')->firstOrFail();
        $this->assertEquals('2025-11-05', $novInv->due_on->toDateString());
        $this->assertEquals(750000, $novInv->amount);
    }

    // -------------------------------------------------------------------------
    // 13. Paid invoice remains intact and unaltered during sync
    // -------------------------------------------------------------------------

    public function test_paid_invoice_remains_intact_and_unaltered_during_sync(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.paidstay@example.test');
        $room = $this->createRoom('R-PAID');
        $resident = $this->createResident('res.paid@example.test');

        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01', null, 800000);

        // Pre-create August invoice and record a valid payment for it
        $invAug = Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-08-01',
            'due_on' => '2026-08-05',
            'amount' => 800000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        Payment::create([
            'invoice_id' => $invAug->id,
            'receipt_number' => 'PAY-TC13-001',
            'amount' => 800000,
            'paid_on' => '2026-08-03',
            'method' => 'transfer',
            'reference' => 'TRF-123456',
            'status' => 'valid',
            'recorded_by' => $admin->id,
        ]);

        // Sync placement: should create September invoice, but leave August invoice untouched
        $res = $this->billingService->syncPlacementInvoices($placement, $admin);
        $this->assertEquals(1, $res['created_count']);
        $this->assertEquals(1, $res['already_existed_count']);

        // Verify August invoice
        $invAug->refresh();
        $this->assertEquals(800000, $invAug->amount);
        $this->assertEquals(1, $invAug->payments()->where('status', 'valid')->count());
    }

    // -------------------------------------------------------------------------
    // 14. Read-only methods (preview, coverage, global coverage) do not alter database
    // -------------------------------------------------------------------------

    public function test_preview_and_coverage_methods_are_read_only_and_do_not_alter_database(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.readonly@example.test');
        $room = $this->createRoom('R-RO');
        $resident = $this->createResident('res.ro@example.test');

        $placement = $this->createPlacement($resident, $room, $admin, '2026-06-01', null, 800000);

        $initialInvoiceCount = Invoice::count();
        $initialAuditCount = ActivityLog::count();

        DB::flushQueryLog();
        DB::enableQueryLog();

        // 1. Call previewPlacement
        $preview = $this->billingService->previewPlacement($placement);
        $this->assertNotEmpty($preview['items']);

        // 2. Call checkCoverage
        $cov = $this->billingService->checkCoverage($placement);
        $this->assertFalse($cov['is_complete']);

        // 3. Call checkGlobalCoverage
        $global = $this->billingService->checkGlobalCoverage();
        $this->assertFalse($global['is_complete']);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Ensure no INSERT, UPDATE, or DELETE statements were executed
        foreach ($queries as $q) {
            $sql = strtoupper($q['query']);
            $this->assertFalse(str_starts_with($sql, 'INSERT'), "Unexpected insert in read-only method: {$sql}");
            $this->assertFalse(str_starts_with($sql, 'UPDATE'), "Unexpected update in read-only method: {$sql}");
            $this->assertFalse(str_starts_with($sql, 'DELETE'), "Unexpected delete in read-only method: {$sql}");
        }

        $this->assertEquals($initialInvoiceCount, Invoice::count(), 'Invoice count changed during read-only call');
        $this->assertEquals($initialAuditCount, ActivityLog::count(), 'ActivityLog count changed during read-only call');
    }

    // -------------------------------------------------------------------------
    // 15. Non-Admin or Inactive actor is rejected on sync operations
    // -------------------------------------------------------------------------

    public function test_non_admin_or_inactive_actor_is_rejected_on_sync_operations(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.authcheck@example.test');
        $inactiveAdmin = $this->createUser('admin', 'inactive.admin@example.test', false);
        $owner = $this->createUser('owner', 'owner.authcheck@example.test');
        $residentUser = $this->createUser('resident', 'resident.authcheck@example.test');

        $room = $this->createRoom('R-AUTH');
        $resident = $this->createResident('res.authprofile@example.test');
        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01', null, 800000);

        $initialInvoices = Invoice::count();
        $initialAudits = ActivityLog::count();

        // 1. Inactive Admin rejected
        try {
            $this->billingService->syncPlacementInvoices($placement, $inactiveAdmin);
            $this->fail('Expected AuthorizationException for inactive admin');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Pengguna tidak aktif', $e->getMessage());
        }

        // 2. Owner rejected
        try {
            $this->billingService->syncPlacementInvoices($placement, $owner);
            $this->fail('Expected AuthorizationException for Owner');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Hanya pengguna dengan peran Admin', $e->getMessage());
        }

        // 3. Resident rejected
        try {
            $this->billingService->syncPlacementInvoices($placement, $residentUser);
            $this->fail('Expected AuthorizationException for Resident');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Hanya pengguna dengan peran Admin', $e->getMessage());
        }

        // 4. Batch sync rejected for Owner
        try {
            $this->billingService->syncAllPlacements($owner);
            $this->fail('Expected AuthorizationException for Owner in batch sync');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Hanya pengguna dengan peran Admin', $e->getMessage());
        }

        // 5. Unsaved actor rejected
        $unsavedUser = new User(['name' => 'Ghost', 'is_active' => true]);
        try {
            $this->billingService->syncPlacementInvoices($placement, $unsavedUser);
            $this->fail('Expected AuthorizationException for unsaved actor in syncPlacementInvoices');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('belum tersimpan di database', $e->getMessage());
        }

        try {
            $this->billingService->calculateInvoicePayload($placement, '2026-08-01', $unsavedUser);
            $this->fail('Expected AuthorizationException for unsaved actor in calculateInvoicePayload');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('belum tersimpan di database', $e->getMessage());
        }

        // 6. Stale in-memory Admin object where DB active status was set to false
        $staleAdminDeactivated = $this->createUser('admin', 'admin.staledeact@example.test');
        User::where('id', $staleAdminDeactivated->id)->update(['is_active' => false]);
        try {
            $this->billingService->syncPlacementInvoices($placement, $staleAdminDeactivated);
            $this->fail('Expected AuthorizationException for stale in-memory admin deactivated in DB');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Pengguna tidak aktif', $e->getMessage());
        }

        try {
            $this->billingService->calculateInvoicePayload($placement, '2026-08-01', $staleAdminDeactivated);
            $this->fail('Expected AuthorizationException for stale in-memory admin deactivated in DB in calculateInvoicePayload');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Pengguna tidak aktif', $e->getMessage());
        }

        // 7. Stale in-memory Admin object where DB role was changed to non-admin
        $staleAdminDemoted = $this->createUser('admin', 'admin.staledemote@example.test');
        $residentRole = Role::where('code', 'resident')->first();
        User::where('id', $staleAdminDemoted->id)->update(['role_id' => $residentRole->id]);
        try {
            $this->billingService->syncPlacementInvoices($placement, $staleAdminDemoted);
            $this->fail('Expected AuthorizationException for stale in-memory admin demoted in DB');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Hanya pengguna dengan peran Admin', $e->getMessage());
        }

        $this->assertEquals($initialInvoices, Invoice::count());
        $this->assertEquals($initialAudits, ActivityLog::count());
    }

    // -------------------------------------------------------------------------
    // 16. Audit failure on second invoice rolls back entire placement changes
    // -------------------------------------------------------------------------

    public function test_audit_failure_on_second_invoice_rolls_back_entire_placement_changes(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.auditrollback@example.test');
        $room = $this->createRoom('R-AUD-RB');
        $resident = $this->createResident('res.audrb@example.test');

        // Placement requires 2 invoices (August and September)
        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01', null, 800000);

        $initialInvoiceCount = Invoice::count();
        $initialAuditCount = ActivityLog::count();

        // Create a mock AuditService that succeeds on the 1st call, but throws RuntimeException on the 2nd call
        $mockAudit = Mockery::mock(AuditService::class);
        $callCount = 0;

        $mockAudit->shouldReceive('log')
            ->andReturnUsing(function () use (&$callCount, $admin) {
                $callCount++;
                if ($callCount === 1) {
                    // 1st invoice audit passes
                    return ActivityLog::create([
                        'actor_id' => $admin->id,
                        'actor_name' => $admin->name,
                        'action' => 'create',
                        'module' => 'invoices',
                        'summary' => 'Test summary for invoice audit',
                        'occurred_at' => now('UTC'),
                    ]);
                }
                // 2nd invoice audit fails
                throw new RuntimeException('Simulated database audit disk write error.');
            });

        $serviceWithMock = new BillingService($mockAudit);

        try {
            $serviceWithMock->syncPlacementInvoices($placement, $admin);
            $this->fail('Expected RuntimeException when second audit log fails');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated database audit disk write error', $e->getMessage());
        }

        // Entire transaction must have rolled back: 0 invoices created, 0 audits created!
        $this->assertEquals($initialInvoiceCount, Invoice::count(), 'Invoices were not rolled back');
        $this->assertEquals($initialAuditCount, ActivityLog::count(), 'ActivityLogs were not rolled back');
        $this->assertFalse(Invoice::where('placement_id', $placement->id)->exists());
    }

    // -------------------------------------------------------------------------
    // 17. Batch sync isolates partial failure and allows safe retry
    // -------------------------------------------------------------------------

    public function test_batch_sync_isolates_partial_failure_and_allows_safe_retry(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.batchretry@example.test');

        $r1 = $this->createRoom('R-B1');
        $r2 = $this->createRoom('R-B2');
        $r3 = $this->createRoom('R-B3');

        $res1 = $this->createResident('res.b1@example.test');
        $res2 = $this->createResident('res.b2@example.test');
        $res3 = $this->createResident('res.b3@example.test');

        $p1 = $this->createPlacement($res1, $r1, $admin, '2026-08-01', null, 800000);
        $p2 = $this->createPlacement($res2, $r2, $admin, '2026-08-01', null, 800000);
        $p3 = $this->createPlacement($res3, $r3, $admin, '2026-08-01', null, 800000);

        // Simulate an issue on p2 by using a custom BillingService extension or mocking
        $realAudit = app(AuditService::class);
        $mockAudit = Mockery::mock($realAudit)->makePartial();

        // Fail audit specifically when processing p2
        $mockAudit->shouldReceive('log')
            ->andReturnUsing(function ($action, $module, $summary, $actor, $entityType = null, $entityId = null, $entityLabel = null, $changes = null) use ($realAudit, $p2) {
                if ($entityId !== null) {
                    $inv = Invoice::find($entityId);
                    if ($inv && $inv->placement_id === $p2->id) {
                        throw new RuntimeException('Simulated disk error on placement 2.');
                    }
                }
                return $realAudit->log($action, $module, $summary, $actor, $entityType, $entityId, $entityLabel, $changes);
            });

        $serviceWithMock = new BillingService($mockAudit);

        // Run batch sync
        $summary = $serviceWithMock->syncAllPlacements($admin);

        // Placements 1 and 3 should succeed (2 invoices each = 4 created)
        // Placement 2 should be in failed_placements
        $this->assertEquals(1, $summary['failed_count']);
        $this->assertCount(1, $summary['failed_placements']);
        $this->assertEquals($p2->id, $summary['failed_placements'][0]['placement_id']);
        // Error message must be safe (no stack trace)
        $this->assertEquals('Terjadi kesalahan sistem saat memproses sinkronisasi penempatan.', $summary['failed_placements'][0]['error']);

        $this->assertEquals(2, Invoice::where('placement_id', $p1->id)->count());
        $this->assertEquals(0, Invoice::where('placement_id', $p2->id)->count());
        $this->assertEquals(2, Invoice::where('placement_id', $p3->id)->count());

        // Retry with normal billingService (simulating issue resolved)
        $retrySummary = $this->billingService->syncAllPlacements($admin);

        // Retry should process placement 2 successfully without duplicating placements 1 & 3
        $this->assertEquals(0, $retrySummary['failed_count']);
        $this->assertEquals(2, Invoice::where('placement_id', $p2->id)->count());
        $this->assertEquals(2, Invoice::where('placement_id', $p1->id)->count());
        $this->assertEquals(2, Invoice::where('placement_id', $p3->id)->count());
    }

    // -------------------------------------------------------------------------
    // 18. Parent transaction rollback reverts synced invoices and audits
    // -------------------------------------------------------------------------

    public function test_parent_transaction_rollback_reverts_synced_invoices_and_audits(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.parenttx@example.test');
        $room = $this->createRoom('R-PTX');
        $resident = $this->createResident('res.ptx@example.test');

        $placement = $this->createPlacement($resident, $room, $admin, '2026-08-01', null, 800000);

        $initialInvoices = Invoice::count();
        $initialAudits = ActivityLog::count();

        try {
            DB::transaction(function () use ($placement, $admin) {
                // Call service within an outer parent transaction (simulating T10 PlacementService call)
                $res = $this->billingService->syncPlacementInvoices($placement, $admin);
                $this->assertEquals(2, $res['created_count']);

                // Parent transaction intentionally fails/rolls back
                throw new RuntimeException('Parent transaction failed, e.g. room conflict.');
            });
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Parent transaction failed', $e->getMessage());
        }

        // Verify that the invoices and audits created by the service call were completely rolled back
        $this->assertEquals($initialInvoices, Invoice::count());
        $this->assertEquals($initialAudits, ActivityLog::count());
        $this->assertFalse(Invoice::where('placement_id', $placement->id)->exists());
    }

    // -------------------------------------------------------------------------
    // 19. Database UNIQUE constraint enforces idempotency and concurrency boundary
    // -------------------------------------------------------------------------

    public function test_database_unique_constraint_enforces_idempotency_and_concurrency_boundaries(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $admin = $this->createUser('admin', 'admin.unique@example.test');
        $room = $this->createRoom('R-UNIQ');
        $resident = $this->createResident('res.uniq@example.test');

        $placement = $this->createPlacement($resident, $room, $admin, '2026-09-01', null, 800000);

        // Create first invoice
        Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-09-01',
            'due_on' => '2026-09-05',
            'amount' => 800000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        // Attempt direct database insert for duplicate period on the same placement
        $this->expectException(QueryException::class);
        Invoice::create([
            'placement_id' => $placement->id,
            'period_month' => '2026-09-01', // duplicate
            'due_on' => '2026-09-05',
            'amount' => 800000,
            'resident_name_snapshot' => $resident->name,
            'room_number_snapshot' => $room->number,
            'created_by' => $admin->id,
        ]);

        /**
         * Note on Concurrency Testing Boundary:
         * This test suite runs within a single PHP process using MySQL database transactions.
         * True simultaneous concurrency (two parallel HTTP/CLI connections racing to insert the same period)
         * is protected at the database tier by:
         * 1. Row-level pessimistic locking (`Placement::lockForUpdate()` and `Invoice::lockForUpdate()`), which serializes execution.
         * 2. The `UNIQUE(placement_id, period_month)` constraint on MySQL, which guarantees duplicate prevention even if locks were somehow bypassed.
         * Sequential repetition tests prove idempotency; true concurrent stress testing requires external process orchestration.
         */
    }
}
