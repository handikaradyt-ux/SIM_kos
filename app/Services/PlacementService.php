<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Placement;
use App\Models\Resident;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlacementService
{
    public function __construct(
        protected AuditService $auditService,
        protected BillingService $billingService
    ) {}

    /**
     * Preview a proposed placement read-only.
     * Generates a server-verified session token and projects the first invoice.
     * 0 database mutations (0 inserts, 0 updates, 0 deletes, 0 audit logs).
     *
     * @return array<string, mixed>
     * @throws DomainException|AuthorizationException
     */
    public function previewPlacement(int $residentId, int $roomId, User $actor): array
    {
        $verifiedActor = $this->ensureActorIsAdmin($actor);

        $room = Room::find($roomId);
        if (! $room || $room->isArchived()) {
            throw new DomainException('Kamar tidak ditemukan atau telah diarsipkan.');
        }

        if ($room->activePlacement()->exists()) {
            throw new DomainException('Kamar sudah terisi oleh penempatan aktif.');
        }

        $resident = Resident::with('user.role')->find($residentId);
        if (! $resident || $resident->isArchived()) {
            throw new DomainException('Penghuni tidak ditemukan atau telah diarsipkan.');
        }

        $residentUser = $resident->user;
        if (! $residentUser || ! (bool) $residentUser->is_active || $residentUser->role?->code !== 'resident') {
            throw new DomainException('Akun pengguna penghuni tidak aktif atau tidak valid.');
        }

        if ($resident->activePlacement()->exists()) {
            throw new DomainException('Penghuni sudah memiliki penempatan aktif.');
        }

        // Current business date in Asia/Jakarta
        $businessDate = Carbon::now('Asia/Jakarta')->startOfDay();
        $currentMonthStr = $businessDate->format('Y-m');

        // Check if resident had an ended placement in the current month (same-month warning)
        $hasSameMonthPriorPlacement = Placement::where('resident_id', $resident->id)
            ->whereNotNull('ended_on')
            ->whereRaw("DATE_FORMAT(ended_on, '%Y-%m') = ?", [$currentMonthStr])
            ->exists();

        // Calculate due date using BillingService rule on an unpersisted Placement instance
        $unpersistedPlacement = new Placement([
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'started_on' => $businessDate->toDateString(),
            'ended_on' => null,
            'agreed_monthly_rate' => (int) $room->monthly_rate,
        ]);

        $periodMonth = $businessDate->copy()->startOfMonth()->toDateString();
        $dueDate = $this->billingService->calculateDueDate($unpersistedPlacement, $periodMonth);

        // Generate a random preview token bound to server session
        $previewToken = Str::random(40);
        $expiresAt = Carbon::now()->addMinutes(15)->timestamp;

        session()->put("placement_preview_{$previewToken}", [
            'token' => $previewToken,
            'admin_id' => $verifiedActor->id,
            'resident_id' => $resident->id,
            'room_id' => $room->id,
            'room_rate' => (int) $room->monthly_rate,
            'started_on' => $businessDate->toDateString(),
            'expires_at' => $expiresAt,
        ]);

        return [
            'preview_token' => $previewToken,
            'resident' => [
                'id' => $resident->id,
                'name' => $resident->name,
                'phone' => $resident->phone,
                'email' => $residentUser->email,
            ],
            'room' => [
                'id' => $room->id,
                'number' => $room->number,
                'type' => $room->type,
                'monthly_rate' => (int) $room->monthly_rate,
            ],
            'contract' => [
                'started_on' => $businessDate->toDateString(),
                'started_on_formatted' => $businessDate->translatedFormat('d F Y'),
                'agreed_monthly_rate' => (int) $room->monthly_rate,
            ],
            'first_invoice' => [
                'period_month' => $periodMonth,
                'period_month_formatted' => $businessDate->translatedFormat('F Y'),
                'due_on' => $dueDate,
                'due_on_formatted' => Carbon::parse($dueDate)->translatedFormat('d F Y'),
                'amount' => (int) $room->monthly_rate,
            ],
            'same_month_warning' => $hasSameMonthPriorPlacement,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Start a new placement atomically within a database transaction.
     * Pessimistic row locking order: Room -> Resident -> User.
     * Enforces preview token verification and post-lock drift detection.
     *
     * @throws DomainException|AuthorizationException|QueryException
     */
    public function startPlacement(int $residentId, int $roomId, string $previewToken, User $actor): Placement
    {
        $verifiedActor = $this->ensureActorIsAdmin($actor);

        // Verify session preview token
        $sessionKey = "placement_preview_{$previewToken}";
        $preview = session($sessionKey);

        if (! is_array($preview) || ! isset($preview['expires_at']) || Carbon::now()->timestamp > $preview['expires_at']) {
            throw new DomainException('Sesi preview penempatan tidak valid atau telah kedaluwarsa. Silakan lakukan preview ulang.');
        }

        if ((int) $preview['admin_id'] !== (int) $verifiedActor->id) {
            throw new DomainException('Token preview dibuat oleh Administrator lain. Silakan lakukan preview ulang.');
        }

        if ((int) $preview['resident_id'] !== $residentId || (int) $preview['room_id'] !== $roomId) {
            throw new DomainException('Data kamar atau penghuni tidak sesuai dengan data preview yang dikonfirmasi.');
        }

        return DB::transaction(function () use ($residentId, $roomId, $previewToken, $sessionKey, $preview, $verifiedActor) {
            // Pessimistic locking sequence: Room -> Resident -> User
            $lockedRoom = Room::where('id', $roomId)->lockForUpdate()->first();
            if (! $lockedRoom || $lockedRoom->isArchived()) {
                throw new DomainException('Kamar tidak ditemukan atau telah diarsipkan.');
            }

            if (Placement::where('room_id', $lockedRoom->id)->whereNull('ended_on')->exists()) {
                throw new DomainException('Kamar sedang terisi oleh penempatan aktif.');
            }

            $lockedResident = Resident::where('id', $residentId)->lockForUpdate()->first();
            if (! $lockedResident || $lockedResident->isArchived()) {
                throw new DomainException('Penghuni tidak ditemukan atau telah diarsipkan.');
            }

            if (Placement::where('resident_id', $lockedResident->id)->whereNull('ended_on')->exists()) {
                throw new DomainException('Penghuni sedang memiliki penempatan aktif.');
            }

            $lockedUser = User::with('role')->where('id', $lockedResident->user_id)->lockForUpdate()->first();
            if (! $lockedUser || ! (bool) $lockedUser->is_active || $lockedUser->role?->code !== 'resident') {
                throw new DomainException('Akun pengguna penghuni tidak aktif atau tidak valid.');
            }

            // Determine single business date under lock
            $businessDate = Carbon::now('Asia/Jakarta')->startOfDay();

            // Post-lock drift detection: room rate drift
            if ((int) $lockedRoom->monthly_rate !== (int) $preview['room_rate']) {
                throw new DomainException('Tarif kamar telah berubah sejak preview dibuat. Silakan lakukan preview dan konfirmasi ulang.');
            }

            // Post-lock drift detection: operational date drift (e.g. crossed midnight/month boundary)
            if ($businessDate->toDateString() !== $preview['started_on']) {
                throw new DomainException('Tanggal operasional bisnis telah berganti sejak preview dibuat. Silakan lakukan preview dan konfirmasi ulang.');
            }

            // Create Placement row
            try {
                $placement = Placement::create([
                    'resident_id' => $lockedResident->id,
                    'room_id' => $lockedRoom->id,
                    'started_on' => $businessDate->toDateString(),
                    'ended_on' => null,
                    'agreed_monthly_rate' => (int) $lockedRoom->monthly_rate,
                    'created_by' => $verifiedActor->id,
                    'ended_by' => null,
                    'end_reason' => null,
                ]);
            } catch (QueryException $e) {
                // Translate only MySQL 1062 unique constraint on active placement into business messages
                if (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062) {
                    $msg = $e->getMessage();
                    if (str_contains($msg, 'active_room_id') || str_contains($msg, 'placements_active_room_id_unique')) {
                        throw new DomainException('Kamar sedang terisi oleh penempatan aktif.');
                    }
                    if (str_contains($msg, 'active_resident_id') || str_contains($msg, 'placements_active_resident_id_unique')) {
                        throw new DomainException('Penghuni sedang memiliki penempatan aktif.');
                    }
                }

                throw $e;
            }

            // Synchronize initial invoice through BillingService
            $this->billingService->syncPlacementInvoices($placement, $verifiedActor, $businessDate);

            // Record placement audit log
            $this->auditService->log(
                action: 'create',
                module: 'placements',
                summary: "Memulai penempatan penghuni {$lockedResident->name} di kamar {$lockedRoom->number}",
                actor: $verifiedActor,
                entityType: Placement::class,
                entityId: $placement->id,
                entityLabel: "Kamar {$lockedRoom->number} - {$lockedResident->name}",
                changes: [
                    'after' => [
                        'resident_id' => $placement->resident_id,
                        'room_id' => $placement->room_id,
                        'started_on' => $placement->started_on->toDateString(),
                        'ended_on' => null,
                        'agreed_monthly_rate' => (int) $placement->agreed_monthly_rate,
                        'end_reason' => null,
                    ],
                ]
            );

            // Invalidate session preview token
            session()->forget($sessionKey);

            return $placement->load(['resident.user', 'room', 'creator', 'invoices']);
        });
    }

    /**
     * Pure calculation helper for material financial snapshot and deterministic SHA-256 signature.
     * Takes in-memory Collection of invoices (with valid payments) and required periods.
     * Does NOT execute ANY database queries, ensuring absolute consistency with locked rows.
     *
     * @param Collection<int, Invoice> $invoices
     * @param array<int, string> $requiredPeriods Array of 'YYYY-MM-01' dates
     */
    public function computeFinancialSnapshot(Placement $placement, Collection $invoices, array $requiredPeriods): array
    {
        // 1. Sort invoices deterministically by ID
        $sortedInvoices = $invoices->sortBy('id')->values();

        $existingUnpaidCount = 0;
        $existingUnpaidAmount = 0;
        $existingPaidCount = 0;
        $existingPaidAmount = 0;
        $materialInvoiceTokens = [];

        foreach ($sortedInvoices as $inv) {
            $validPayment = $inv->validPayment;
            $hasValidPayment = $validPayment !== null && $validPayment->status === 'valid';

            if ($hasValidPayment) {
                $existingPaidCount++;
                $existingPaidAmount += (int) $inv->amount;
            } else {
                $existingUnpaidCount++;
                $existingUnpaidAmount += (int) $inv->amount;
            }

            $dueOnStr = $inv->due_on ? Carbon::parse($inv->due_on)->toDateString() : '';
            $periodStr = $inv->period_month ? Carbon::parse($inv->period_month)->toDateString() : '';
            $paymentId = $hasValidPayment ? (string) $validPayment->id : 'null';
            $paymentStatus = $hasValidPayment ? (string) $validPayment->status : 'none';

            $materialInvoiceTokens[] = "{$inv->id}:{$periodStr}:{$dueOnStr}:{$inv->amount}:{$paymentId}:{$paymentStatus}";
        }

        // 2. Compute missing periods strictly against the provided invoices collection
        $existingPeriodMonths = $sortedInvoices->pluck('period_month')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $missingPeriods = array_values(array_diff($requiredPeriods, $existingPeriodMonths));
        $rate = (int) $placement->agreed_monthly_rate;
        $newInvoicesCount = count($missingPeriods);
        $newInvoicesAmount = $newInvoicesCount * $rate;

        $newInvoiceItems = [];
        foreach ($missingPeriods as $period) {
            $periodCarbon = Carbon::parse($period);
            $newInvoiceItems[] = [
                'period_month' => $period,
                'period_month_label' => $periodCarbon->translatedFormat('F Y'),
                'due_on' => $this->billingService->calculateDueDate($placement, $period),
                'amount' => $rate,
            ];
        }

        $totalUnpaidObligation = $existingUnpaidAmount + $newInvoicesAmount;

        // 3. Form deterministic SHA-256 signature
        $rawSignatureData = implode(';', [
            "rate:{$rate}",
            "missing:" . implode(',', $missingPeriods),
            "new_amount:{$newInvoicesAmount}",
            "existing_unpaid_amount:{$existingUnpaidAmount}",
            "total_unpaid:{$totalUnpaidObligation}",
            "invoices:" . implode('|', $materialInvoiceTokens),
        ]);
        $signature = hash('sha256', $rawSignatureData);

        return [
            'agreed_monthly_rate' => $rate,
            'existing_invoices_count' => $sortedInvoices->count(),
            'existing_paid_count' => $existingPaidCount,
            'existing_paid_amount' => $existingPaidAmount,
            'existing_unpaid_count' => $existingUnpaidCount,
            'existing_unpaid_amount' => $existingUnpaidAmount,
            'missing_periods' => $missingPeriods,
            'new_invoices_count' => $newInvoicesCount,
            'new_invoices_amount' => $newInvoicesAmount,
            'new_invoice_items' => $newInvoiceItems,
            'total_unpaid_obligation' => $totalUnpaidObligation,
            'signature' => $signature,
        ];
    }

    /**
     * Build unified, deterministic material financial snapshot and SHA-256 signature for end-placement.
     * Queries existing invoices and required periods, delegating calculation to computeFinancialSnapshot.
     */
    public function buildMaterialFinancialSnapshot(Placement $placement, Carbon $businessDate): array
    {
        // Query all existing invoices ordered deterministically by ID with valid payment
        $existingInvoices = Invoice::where('placement_id', $placement->id)
            ->with('validPayment')
            ->orderBy('id')
            ->get();

        $requiredPeriods = $this->billingService->getRequiredPeriods($placement, $businessDate);

        return $this->computeFinancialSnapshot($placement, $existingInvoices, $requiredPeriods);
    }

    /**
     * Preview ending an active placement read-only.
     * Generates a server-verified session token binding actor, placement, business date, and financial snapshot.
     * 0 database mutations (0 inserts, 0 updates, 0 deletes, 0 audit logs).
     *
     * @throws DomainException|AuthorizationException
     */
    public function previewEndPlacement(int $placementId, User $actor): array
    {
        $verifiedActor = $this->ensureActorIsAdmin($actor);

        $placement = Placement::with(['resident.user', 'room', 'invoices.validPayment'])->find($placementId);

        if (! $placement) {
            throw new DomainException('Penempatan tidak ditemukan.');
        }

        if (! $placement->isActive()) {
            throw new DomainException('Penempatan ini sudah berstatus selesai dan tidak dapat diakhiri kembali.');
        }

        $businessDate = Carbon::now('Asia/Jakarta')->startOfDay();
        $snapshot = $this->buildMaterialFinancialSnapshot($placement, $businessDate);

        $previewToken = Str::random(40);
        $sessionKey = "placement_end_preview_{$previewToken}";

        session([$sessionKey => [
            'token' => $previewToken,
            'admin_id' => (int) $verifiedActor->id,
            'placement_id' => (int) $placement->id,
            'business_date' => $businessDate->toDateString(),
            'agreed_monthly_rate' => (int) $placement->agreed_monthly_rate,
            'signature' => $snapshot['signature'],
            'total_unpaid_obligation' => (int) $snapshot['total_unpaid_obligation'],
            'new_invoices_count' => (int) $snapshot['new_invoices_count'],
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        return [
            'placement' => [
                'id' => $placement->id,
                'started_on' => $placement->started_on->toDateString(),
                'started_on_formatted' => $placement->started_on->translatedFormat('d F Y'),
                'ended_on' => $businessDate->toDateString(),
                'ended_on_formatted' => $businessDate->translatedFormat('d F Y'),
                'agreed_monthly_rate' => (int) $placement->agreed_monthly_rate,
            ],
            'resident' => [
                'id' => $placement->resident->id,
                'name' => $placement->resident->name,
                'phone' => $placement->resident->phone,
                'email' => $placement->resident->user?->email,
            ],
            'room' => [
                'id' => $placement->room->id,
                'number' => $placement->room->number,
                'type' => $placement->room->type,
            ],
            'financial' => [
                'agreed_monthly_rate' => $snapshot['agreed_monthly_rate'],
                'existing_invoices_count' => $snapshot['existing_invoices_count'],
                'existing_paid_count' => $snapshot['existing_paid_count'],
                'existing_paid_amount' => $snapshot['existing_paid_amount'],
                'existing_unpaid_count' => $snapshot['existing_unpaid_count'],
                'existing_unpaid_amount' => $snapshot['existing_unpaid_amount'],
                'missing_periods' => $snapshot['missing_periods'],
                'new_invoices_count' => $snapshot['new_invoices_count'],
                'new_invoices_amount' => $snapshot['new_invoices_amount'],
                'new_invoice_items' => $snapshot['new_invoice_items'],
                'total_unpaid_obligation' => $snapshot['total_unpaid_obligation'],
            ],
            'preview_token' => $previewToken,
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ];
    }

    /**
     * Atomically end an active placement within a database transaction.
     * Pessimistic row locking order: Room -> Resident -> User -> Placement -> Invoices -> Payments.
     * Synchronizes invoices up to departure month, sets end metadata, and logs audit.
     * Invalidation of session preview token strictly occurs AFTER successful database commit.
     *
     * @throws DomainException|AuthorizationException
     */
    public function endPlacement(int $placementId, string $endReason, string $previewToken, User $actor): Placement
    {
        $verifiedActor = $this->ensureActorIsAdmin($actor);

        // Verify session preview token
        $sessionKey = "placement_end_preview_{$previewToken}";
        $preview = session($sessionKey);

        if (! is_array($preview) || ! isset($preview['expires_at']) || Carbon::now()->timestamp > $preview['expires_at']) {
            throw new DomainException('Sesi preview pengakhiran tidak valid atau telah kedaluwarsa. Silakan lakukan preview ulang.');
        }

        if ((int) $preview['admin_id'] !== (int) $verifiedActor->id) {
            throw new DomainException('Token preview dibuat oleh Administrator lain. Silakan lakukan preview ulang.');
        }

        if ((int) $preview['placement_id'] !== $placementId) {
            throw new DomainException('Data penempatan tidak sesuai dengan data preview yang dikonfirmasi.');
        }

        $placement = DB::transaction(function () use ($placementId, $endReason, $preview, $verifiedActor, $sessionKey) {
            // 1. Verify placement status before lock
            $initialPlacement = Placement::where('id', $placementId)->first();
            if (! $initialPlacement) {
                throw new DomainException('Penempatan tidak ditemukan.');
            }
            if (! $initialPlacement->isActive()) {
                throw new DomainException('Penempatan ini sudah berstatus selesai dan tidak dapat diakhiri kembali.');
            }

            // 2. Pessimistic row locking order: Room -> Resident -> User -> Placement -> Invoices -> Payments
            $lockedRoom = Room::where('id', $initialPlacement->room_id)->lockForUpdate()->firstOrFail();
            $lockedResident = Resident::where('id', $initialPlacement->resident_id)->lockForUpdate()->firstOrFail();
            $lockedUser = User::where('id', $lockedResident->user_id)->lockForUpdate()->firstOrFail();
            $lockedPlacement = Placement::where('id', $placementId)->lockForUpdate()->firstOrFail();

            // Lock all existing invoices for this placement deterministically by ID
            $lockedInvoices = Invoice::where('placement_id', $lockedPlacement->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // Lock all valid payments belonging to these invoices
            $invoiceIds = $lockedInvoices->pluck('id')->all();
            $lockedValidPayments = empty($invoiceIds)
                ? collect()
                : Payment::whereIn('invoice_id', $invoiceIds)
                    ->where('status', 'valid')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('invoice_id');

            // Attach locked valid payment relation to each locked invoice instance
            foreach ($lockedInvoices as $inv) {
                $inv->setRelation('validPayment', $lockedValidPayments->get($inv->id));
            }

            // 3. Re-evaluate placement under lock
            if (! $lockedPlacement->isActive()) {
                throw new DomainException('Penempatan ini sudah berstatus selesai dan tidak dapat diakhiri kembali.');
            }

            // 4. Determine single business date under lock
            $businessDate = Carbon::now('Asia/Jakarta')->startOfDay();

            // Check token expiration under lock as well
            if (Carbon::now()->timestamp > $preview['expires_at']) {
                throw new DomainException('Sesi preview pengakhiran tidak valid atau telah kedaluwarsa. Silakan lakukan preview ulang.');
            }

            // 5. Post-lock material drift check: business date drift
            if ($businessDate->toDateString() !== $preview['business_date']) {
                throw new DomainException('Tanggal operasional bisnis telah berganti sejak preview dibuat. Silakan lakukan preview dan konfirmasi ulang.');
            }

            // 6. Post-lock material drift check: calculate financial snapshot strictly from locked rows
            $requiredPeriods = $this->billingService->getRequiredPeriods($lockedPlacement, $businessDate);
            $currentSnapshot = $this->computeFinancialSnapshot($lockedPlacement, $lockedInvoices, $requiredPeriods);

            if ($currentSnapshot['signature'] !== $preview['signature']) {
                throw new DomainException('Data tagihan atau status pembayaran telah berubah sejak preview dibuat. Silakan tinjau ulang preview sebelum mengakhiri penempatan.');
            }

            // 7. Synchronize missing invoices up to departure month via BillingService
            $this->billingService->syncPlacementInvoices($lockedPlacement, $verifiedActor, $businessDate);

            // 8. Update placement row
            $cleanEndReason = trim($endReason);
            $lockedPlacement->update([
                'ended_on' => $businessDate->toDateString(),
                'ended_by' => $verifiedActor->id,
                'end_reason' => $cleanEndReason,
            ]);

            // 9. Record audit log on module 'placements'
            $this->auditService->log(
                action: 'end',
                module: 'placements',
                summary: "Mengakhiri penempatan penghuni {$lockedResident->name} di kamar {$lockedRoom->number}",
                actor: $verifiedActor,
                entityType: Placement::class,
                entityId: $lockedPlacement->id,
                entityLabel: "Kamar {$lockedRoom->number} - {$lockedResident->name}",
                changes: [
                    'before' => [
                        'ended_on' => null,
                        'ended_by' => null,
                        'end_reason' => null,
                    ],
                    'after' => [
                        'ended_on' => $businessDate->toDateString(),
                        'ended_by' => $verifiedActor->id,
                        'end_reason' => $cleanEndReason,
                    ],
                ]
            );

            // Register post-commit session token removal on the outermost transaction
            DB::afterCommit(function () use ($sessionKey) {
                session()->forget($sessionKey);
            });

            return $lockedPlacement->fresh(['resident.user', 'room', 'creator', 'ender', 'invoices.validPayment']);
        });

        // Invalidate preview token in session AFTER commit is successful
        session()->forget($sessionKey);

        return $placement;
    }

    /**
     * Ensure the actor is an active Administrator in the database.
     */
    protected function ensureActorIsAdmin(User $actor): User
    {
        $verified = User::with('role')->find($actor->id);

        if (! $verified || ! (bool) $verified->is_active || $verified->role?->code !== 'admin') {
            throw new AuthorizationException('Hanya Administrator aktif yang berwenang mengelola penempatan.');
        }

        return $verified;
    }
}
