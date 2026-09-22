<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Placement;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Parse any date attribute from model or string into Carbon with Asia/Jakarta timezone.
     */
    public function parseModelDate(mixed $date): Carbon
    {
        if ($date instanceof CarbonInterface) {
            return Carbon::createFromFormat('Y-m-d', $date->format('Y-m-d'), 'Asia/Jakarta')->startOfDay();
        }

        $str = substr(trim((string) $date), 0, 10);

        return Carbon::createFromFormat('Y-m-d', $str, 'Asia/Jakarta')->startOfDay();
    }

    /**
     * Resolve the business reference date in Asia/Jakarta timezone.
     * Prevents bypassing future invoice restrictions via asOfDate parameter.
     */
    public function resolveReferenceDate(?CarbonInterface $asOfDate = null): Carbon
    {
        $businessNow = Carbon::now('Asia/Jakarta')->startOfDay();

        if ($asOfDate !== null) {
            $parsed = $this->parseModelDate($asOfDate);

            // asOfDate cannot exceed current business date to prevent future invoicing bypass
            if ($parsed->greaterThan($businessNow)) {
                throw new InvalidArgumentException('Tanggal acuan tidak boleh melebihi tanggal berjalan bisnis saat ini.');
            }

            return $parsed;
        }

        return $businessNow;
    }

    /**
     * Parse and strictly validate period month input.
     * Accepts 'YYYY-MM' or 'YYYY-MM-DD', always normalizes to the 1st of that month in Asia/Jakarta.
     * Rejects ambiguous or invalid date strings with InvalidArgumentException.
     */
    public function parsePeriodMonth(CarbonInterface|string $periodMonth): Carbon
    {
        if ($periodMonth instanceof CarbonInterface) {
            return Carbon::createFromFormat('Y-m-d', $periodMonth->format('Y-m-d'), 'Asia/Jakarta')->startOfMonth();
        }

        if (! is_string($periodMonth) || ! preg_match('/^\d{4}-\d{2}(-\d{2})?$/', trim($periodMonth))) {
            throw new InvalidArgumentException("Format tanggal periode tidak valid: '{$periodMonth}'. Gunakan format YYYY-MM atau YYYY-MM-01.");
        }

        $trimmed = trim($periodMonth);
        if (strlen($trimmed) === 7) {
            $trimmed .= '-01';
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $trimmed, 'Asia/Jakarta');
        if (! $parsed || $parsed->format('Y-m-d') !== $trimmed) {
            throw new InvalidArgumentException("Format tanggal periode tidak valid: '{$periodMonth}'.");
        }

        return $parsed->startOfMonth();
    }

    /**
     * Determine all mandatory billing periods (YYYY-MM-01) for a placement.
     *
     * @return array<int, string> List of 'YYYY-MM-01' strings
     */
    public function getRequiredPeriods(
        Placement $placement,
        ?CarbonInterface $asOfDate = null,
        CarbonInterface|string|null $maxPeriodMonth = null
    ): array {
        $refDate = $this->resolveReferenceDate($asOfDate);
        $startedOn = $this->parseModelDate($placement->started_on);

        // 1. If started_on is after the reference date, no period is required yet (even in same calendar month)
        if ($startedOn->greaterThan($refDate)) {
            return [];
        }

        $startMonth = $startedOn->copy()->startOfMonth();
        $refMonth = $refDate->copy()->startOfMonth();

        // 2. Determine upper boundary
        // Upper bound cannot exceed current business reference month
        $endBound = $refMonth;

        // If placement has ended, upper bound cannot exceed ended_on month
        if ($placement->ended_on !== null) {
            $endedMonth = $this->parseModelDate($placement->ended_on)->startOfMonth();
            if ($endedMonth->lessThan($endBound)) {
                $endBound = $endedMonth;
            }
        }

        // If maxPeriodMonth parameter is provided, validate and clamp
        if ($maxPeriodMonth !== null) {
            $parsedMax = $this->parsePeriodMonth($maxPeriodMonth);

            // If maxPeriodMonth is before startMonth, range is empty
            if ($parsedMax->lessThan($startMonth)) {
                return [];
            }

            // maxPeriodMonth can only narrow the range, cannot exceed endBound
            if ($parsedMax->lessThan($endBound)) {
                $endBound = $parsedMax;
            }
        }

        // If endBound is before startMonth, return empty array
        if ($endBound->lessThan($startMonth)) {
            return [];
        }

        // 3. Generate periods month-by-month
        $periods = [];
        $current = $startMonth->copy();

        while ($current->lessThanOrEqualTo($endBound)) {
            $periods[] = $current->toDateString(); // YYYY-MM-01
            $current->addMonthNoOverflow();
        }

        return $periods;
    }

    /**
     * Calculate the calendar due date for a specific period month of a placement.
     * Due date is the 5th of the month, EXCEPT for the first invoice if started_on > 5th,
     * in which case due date is started_on.
     *
     * Catatan Perbedaan dengan calculateInvoicePayload:
     * Method ini merupakan helper kalkulasi tanggal kalender murni dalam rentang masa sewa (startMonth <= period <= endedMonth).
     * Method ini tidak membatasi apakah periode berada di masa depan terhadap hari ini.
     * Sebaliknya, calculateInvoicePayload() menegakkan validasi penerbitan tagihan nyata yang siap simpan,
     * konsisten dengan getRequiredPeriods: menolak penempatan belum mulai dan periode setelah bulan berjalan.
     *
     * @throws InvalidArgumentException
     */
    public function calculateDueDate(Placement $placement, CarbonInterface|string $periodMonth): string
    {
        $period = $this->parsePeriodMonth($periodMonth);
        $startedOn = $this->parseModelDate($placement->started_on);
        $startMonth = $startedOn->copy()->startOfMonth();

        // Validate that periodMonth is not before the placement's starting month
        if ($period->lessThan($startMonth)) {
            throw new InvalidArgumentException(
                "Periode {$period->format('Y-m')} berada sebelum tanggal mulai penempatan ({$startedOn->toDateString()})."
            );
        }

        // If placement has ended, validate that periodMonth does not exceed ended_on month
        if ($placement->ended_on !== null) {
            $endedMonth = $this->parseModelDate($placement->ended_on)->startOfMonth();
            if ($period->greaterThan($endedMonth)) {
                throw new InvalidArgumentException(
                    "Periode {$period->format('Y-m')} melampaui bulan akhir penempatan ({$this->parseModelDate($placement->ended_on)->toDateString()})."
                );
            }
        }

        // First invoice period check
        if ($period->equalTo($startMonth)) {
            if ($startedOn->day > 5) {
                return $startedOn->toDateString();
            }

            return $period->copy()->day(5)->toDateString();
        }

        // Subsequent invoice periods are always due on the 5th of the month
        return $period->copy()->day(5)->toDateString();
    }

    /**
     * Prepare validated invoice creation payload ready for issuance.
     * Enforces the exact same period eligibility rules as getRequiredPeriods:
     * rejects unstarted placements, periods before start, periods after current month, and periods after ended_on.
     *
     * @return array{placement_id: int, period_month: string, due_on: string, amount: int, resident_name_snapshot: string, room_number_snapshot: string, created_by: int}
     * @throws InvalidArgumentException|DomainException|AuthorizationException
     */
    public function calculateInvoicePayload(
        Placement $placement,
        CarbonInterface|string $periodMonth,
        User $actor,
        ?CarbonInterface $asOfDate = null
    ): array {
        $verifiedActor = $this->ensureActorIsAdmin($actor);

        $refDate = $this->resolveReferenceDate($asOfDate);
        $period = $this->parsePeriodMonth($periodMonth);
        $startedOn = $this->parseModelDate($placement->started_on);

        // 1. Placement must have started on or before the reference date
        if ($startedOn->greaterThan($refDate)) {
            throw new InvalidArgumentException(
                "Penempatan #{$placement->id} belum mulai per tanggal acuan ({$refDate->toDateString()}); tanggal mulai: {$startedOn->toDateString()}."
            );
        }

        $startMonth = $startedOn->copy()->startOfMonth();
        $refMonth = $refDate->copy()->startOfMonth();

        // 2. Period must not be before placement start month
        if ($period->lessThan($startMonth)) {
            throw new InvalidArgumentException(
                "Periode {$period->format('Y-m')} berada sebelum tanggal mulai penempatan ({$startedOn->toDateString()})."
            );
        }

        // 3. Period must not be after current business month (no future invoice issuance)
        if ($period->greaterThan($refMonth)) {
            throw new InvalidArgumentException(
                "Periode {$period->format('Y-m')} berada setelah bulan berjalan ({$refMonth->format('Y-m')}); tagihan masa depan tidak dapat diterbitkan."
            );
        }

        // 4. If placement has ended, period must not exceed ended_on month
        if ($placement->ended_on !== null) {
            $endedMonth = $this->parseModelDate($placement->ended_on)->startOfMonth();
            if ($period->greaterThan($endedMonth)) {
                throw new InvalidArgumentException(
                    "Periode {$period->format('Y-m')} melampaui bulan akhir penempatan ({$this->parseModelDate($placement->ended_on)->toDateString()})."
                );
            }
        }

        $dueOn = $this->calculateDueDate($placement, $period);

        // Eager load relations if not loaded
        if (! $placement->relationLoaded('resident')) {
            $placement->load('resident');
        }
        if (! $placement->relationLoaded('room')) {
            $placement->load('room');
        }

        if (! $placement->resident) {
            throw new DomainException("Penempatan #{$placement->id} tidak memiliki relasi penghuni.");
        }
        if (! $placement->room) {
            throw new DomainException("Penempatan #{$placement->id} tidak memiliki relasi kamar.");
        }

        return [
            'placement_id' => (int) $placement->id,
            'period_month' => $period->toDateString(),
            'due_on' => $dueOn,
            'amount' => (int) $placement->agreed_monthly_rate,
            'resident_name_snapshot' => (string) $placement->resident->name,
            'room_number_snapshot' => (string) $placement->room->number,
            'created_by' => (int) $verifiedActor->id,
        ];
    }

    /**
     * Read-only preview of missing invoices for a placement.
     */
    public function previewPlacement(
        Placement $placement,
        ?CarbonInterface $asOfDate = null,
        CarbonInterface|string|null $maxPeriodMonth = null
    ): array {
        // Load relations if needed
        if (! $placement->relationLoaded('resident')) {
            $placement->load('resident');
        }
        if (! $placement->relationLoaded('room')) {
            $placement->load('room');
        }

        $requiredPeriods = $this->getRequiredPeriods($placement, $asOfDate, $maxPeriodMonth);

        $existingPeriodMonths = Invoice::where('placement_id', $placement->id)
            ->whereIn('period_month', $requiredPeriods)
            ->pluck('period_month')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $missingPeriods = array_values(array_diff($requiredPeriods, $existingPeriodMonths));

        $rate = (int) $placement->agreed_monthly_rate;
        $residentName = $placement->resident ? (string) $placement->resident->name : '';
        $roomNumber = $placement->room ? (string) $placement->room->number : '';

        $items = [];
        foreach ($missingPeriods as $period) {
            $items[] = [
                'period_month' => $period,
                'due_on' => $this->calculateDueDate($placement, $period),
                'amount' => $rate,
                'resident_name_snapshot' => $residentName,
                'room_number_snapshot' => $roomNumber,
            ];
        }

        return [
            'placement_id' => (int) $placement->id,
            'agreed_monthly_rate' => $rate,
            'required_periods' => $requiredPeriods,
            'existing_periods' => $existingPeriodMonths,
            'missing_periods' => $missingPeriods,
            'items' => $items,
            'new_invoices_count' => count($missingPeriods),
            'total_new_amount' => count($missingPeriods) * $rate,
            'is_complete' => empty($missingPeriods),
        ];
    }

    /**
     * Read-only coverage check comparing required periods with existing invoices.
     */
    public function checkCoverage(
        Placement $placement,
        ?CarbonInterface $asOfDate = null,
        CarbonInterface|string|null $maxPeriodMonth = null
    ): array {
        $requiredPeriods = $this->getRequiredPeriods($placement, $asOfDate, $maxPeriodMonth);

        $existingPeriodMonths = Invoice::where('placement_id', $placement->id)
            ->whereIn('period_month', $requiredPeriods)
            ->pluck('period_month')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $missingPeriods = array_values(array_diff($requiredPeriods, $existingPeriodMonths));

        return [
            'placement_id' => (int) $placement->id,
            'required_count' => count($requiredPeriods),
            'existing_count' => count($existingPeriodMonths),
            'missing_count' => count($missingPeriods),
            'missing_periods' => $missingPeriods,
            'is_complete' => empty($missingPeriods),
        ];
    }

    /**
     * Read-only global coverage check across all placements (active and historical).
     */
    public function checkGlobalCoverage(
        ?CarbonInterface $asOfDate = null,
        CarbonInterface|string|null $maxPeriodMonth = null
    ): array {
        $refDate = $this->resolveReferenceDate($asOfDate);

        $incompletePlacements = [];
        $totalMissing = 0;
        $totalMissingAmount = 0;

        // Process in chunks without loading everything into memory at once
        Placement::with(['resident', 'room'])
            ->orderBy('id')
            ->chunk(100, function ($placements) use ($refDate, $maxPeriodMonth, &$incompletePlacements, &$totalMissing, &$totalMissingAmount) {
                foreach ($placements as $placement) {
                    $cov = $this->checkCoverage($placement, $refDate, $maxPeriodMonth);
                    if (! $cov['is_complete']) {
                        $missingCount = $cov['missing_count'];
                        $missingAmount = $missingCount * (int) $placement->agreed_monthly_rate;

                        $totalMissing += $missingCount;
                        $totalMissingAmount += $missingAmount;

                        $incompletePlacements[] = [
                            'placement_id' => $placement->id,
                            'resident_name' => $placement->resident?->name ?? 'Tidak diketahui',
                            'room_number' => $placement->room?->number ?? 'Tidak diketahui',
                            'missing_count' => $missingCount,
                            'missing_periods' => $cov['missing_periods'],
                            'missing_amount' => $missingAmount,
                        ];
                    }
                }
            });

        return [
            'is_complete' => empty($incompletePlacements),
            'total_missing_invoices' => $totalMissing,
            'total_missing_amount' => $totalMissingAmount,
            'placements_with_missing_count' => count($incompletePlacements),
            'incomplete_placements' => $incompletePlacements,
        ];
    }

    /**
     * Atomically synchronize missing invoices for a single placement.
     * Re-throws any exception to ensure caller transactions (e.g. T10/T11) can roll back cleanly.
     *
     * @return array{placement_id: int, created_count: int, already_existed_count: int, created_invoices: array<int, Invoice>}
     */
    public function syncPlacementInvoices(
        Placement $placement,
        User $actor,
        ?CarbonInterface $asOfDate = null,
        CarbonInterface|string|null $maxPeriodMonth = null
    ): array {
        $verifiedActor = $this->ensureActorIsAdmin($actor);

        $refDate = $this->resolveReferenceDate($asOfDate);

        return DB::transaction(function () use ($placement, $verifiedActor, $refDate, $maxPeriodMonth) {
            // 1. Lock Placement row first to serialize concurrent operations
            $lockedPlacement = Placement::where('id', $placement->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Eager load fresh resident and room data
            $lockedPlacement->load(['resident', 'room']);

            // 2. Determine required periods using locked placement state
            $requiredPeriods = $this->getRequiredPeriods($lockedPlacement, $refDate, $maxPeriodMonth);

            if (empty($requiredPeriods)) {
                return [
                    'placement_id' => $lockedPlacement->id,
                    'created_count' => 0,
                    'already_existed_count' => 0,
                    'created_invoices' => [],
                ];
            }

            // 3. Query existing periods in this scope under lock
            $existingPeriodMonths = Invoice::where('placement_id', $lockedPlacement->id)
                ->whereIn('period_month', $requiredPeriods)
                ->lockForUpdate()
                ->pluck('period_month')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->all();

            $missingPeriods = array_values(array_diff($requiredPeriods, $existingPeriodMonths));

            // If all required periods already have invoices, return cleanly (idempotent, no audit)
            if (empty($missingPeriods)) {
                return [
                    'placement_id' => $lockedPlacement->id,
                    'created_count' => 0,
                    'already_existed_count' => count($existingPeriodMonths),
                    'created_invoices' => [],
                ];
            }

            // 4. Create missing invoices and log audit trails
            $createdInvoices = [];

            foreach ($missingPeriods as $period) {
                $payload = $this->calculateInvoicePayload($lockedPlacement, $period, $verifiedActor, $refDate);

                $invoice = Invoice::create($payload);

                $this->auditService->log(
                    action: 'create',
                    module: 'invoices',
                    summary: "Menerbitkan invoice sewa periode {$invoice->period_month->format('Y-m')} senilai Rp " . number_format($invoice->amount, 0, ',', '.') . " untuk penempatan #{$lockedPlacement->id}",
                    actor: $verifiedActor,
                    entityType: Invoice::class,
                    entityId: $invoice->id,
                    entityLabel: "Invoice {$invoice->period_month->format('Y-m')} - Kamar {$invoice->room_number_snapshot}",
                    changes: [
                        'after' => [
                            'placement_id' => $invoice->placement_id,
                            'period_month' => $invoice->period_month->toDateString(),
                            'due_on' => $invoice->due_on->toDateString(),
                            'amount' => $invoice->amount,
                            'resident_name_snapshot' => $invoice->resident_name_snapshot,
                            'room_number_snapshot' => $invoice->room_number_snapshot,
                            'created_by' => $verifiedActor->id,
                        ],
                    ]
                );

                $createdInvoices[] = $invoice;
            }

            return [
                'placement_id' => $lockedPlacement->id,
                'created_count' => count($createdInvoices),
                'already_existed_count' => count($existingPeriodMonths),
                'created_invoices' => $createdInvoices,
            ];
        });
    }

    /**
     * Batch synchronization of missing invoices across all placements.
     * Isolates errors per placement so a failure in one placement does not abort others.
     *
     * Catatan Batas Transaksi:
     * Setiap penempatan diproses secara mandiri dalam transaksi database masing-masing via syncPlacementInvoices.
     * Isolasi commit mandiri per penempatan HANYA berlaku jika syncAllPlacements dipanggil tanpa transaksi
     * luar (induk) yang membungkus seluruh batch. Apabila pemanggil membungkus batch ini dalam transaksi DB luar,
     * maka rollback transaksi luar akan membatalkan seluruh perubahan.
     *
     * @return array{total_placements_processed: int, total_created: int, total_already_existed: int, failed_count: int, failed_placements: array<int, array{placement_id: int, error: string}>}
     */
    public function syncAllPlacements(
        User $actor,
        ?CarbonInterface $asOfDate = null,
        CarbonInterface|string|null $maxPeriodMonth = null
    ): array {
        $verifiedActor = $this->ensureActorIsAdmin($actor);

        // Freeze reference date once at the beginning of the batch
        $refDate = $this->resolveReferenceDate($asOfDate);

        $totalProcessed = 0;
        $totalCreated = 0;
        $totalAlreadyExisted = 0;
        $failedPlacements = [];

        // Query all placements ordered by ID (including ended ones that may have missing invoices)
        Placement::orderBy('id')->chunk(50, function ($placements) use ($verifiedActor, $refDate, $maxPeriodMonth, &$totalProcessed, &$totalCreated, &$totalAlreadyExisted, &$failedPlacements) {
            foreach ($placements as $placement) {
                $totalProcessed++;

                try {
                    $result = $this->syncPlacementInvoices($placement, $verifiedActor, $refDate, $maxPeriodMonth);
                    $totalCreated += $result['created_count'];
                    $totalAlreadyExisted += $result['already_existed_count'];
                } catch (\Throwable $e) {
                    // Safe error message, avoiding raw SQL, stack traces, or credentials
                    $safeMessage = $e instanceof DomainException || $e instanceof InvalidArgumentException || $e instanceof AuthorizationException
                        ? $e->getMessage()
                        : 'Terjadi kesalahan sistem saat memproses sinkronisasi penempatan.';

                    $failedPlacements[] = [
                        'placement_id' => $placement->id,
                        'error' => $safeMessage,
                    ];
                }
            }
        });

        return [
            'total_placements_processed' => $totalProcessed,
            'total_created' => $totalCreated,
            'total_already_existed' => $totalAlreadyExisted,
            'failed_count' => count($failedPlacements),
            'failed_placements' => $failedPlacements,
        ];
    }

    /**
     * Ensure the actor is a persisted and currently active administrator.
     * Re-reads active status and role directly from database to protect against stale in-memory models.
     *
     * @return User The fresh, verified actor instance
     * @throws AuthorizationException
     */
    protected function ensureActorIsAdmin(User $actor): User
    {
        if (! $actor->exists || ! $actor->id) {
            throw new AuthorizationException('Actor tidak valid atau belum tersimpan di database.');
        }

        $freshActor = User::with('role')->find($actor->id);

        if (! $freshActor) {
            throw new AuthorizationException('Actor tidak ditemukan di database.');
        }

        if (! $freshActor->is_active) {
            throw new AuthorizationException('Pengguna tidak aktif tidak diizinkan melakukan operasi sinkronisasi.');
        }

        if ($freshActor->role?->code !== 'admin') {
            throw new AuthorizationException('Hanya pengguna dengan peran Admin yang dapat melakukan sinkronisasi tagihan.');
        }

        return $freshActor;
    }
}
