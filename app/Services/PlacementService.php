<?php

namespace App\Services;

use App\Models\Placement;
use App\Models\Resident;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
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
