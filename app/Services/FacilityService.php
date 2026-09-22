<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\Room;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class FacilityService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Atomically create a new facility with audit trail.
     * Locks and verifies target room if location_type is 'room'.
     *
     * @param array{code: string, name: string, condition: string, location_type: string, room_id?: int|null, area_name?: string|null, notes?: string|null} $data
     */
    public function createFacility(array $data, User $actor): Facility
    {
        return DB::transaction(function () use ($data, $actor) {
            $locType = $data['location_type'];

            if ($locType === 'room') {
                $roomId = (int) $data['room_id'];
                $targetRoom = Room::where('id', $roomId)->lockForUpdate()->firstOrFail();

                if ($targetRoom->isArchived()) {
                    throw new DomainException("Kamar {$targetRoom->number} sedang diarsipkan dan tidak dapat digunakan untuk penempatan fasilitas baru.");
                }

                $data['room_id'] = $targetRoom->id;
                $data['area_name'] = null;
            } elseif ($locType === 'shared') {
                $data['room_id'] = null;
                $data['area_name'] = isset($data['area_name']) ? trim((string) $data['area_name']) : null;
            }

            unset($data['archived_at']);

            $code = trim((string) $data['code']);
            if (Facility::whereRaw('LOWER(code) = ?', [strtolower($code)])->exists()) {
                throw new DomainException("Kode fasilitas '{$code}' sudah digunakan (termasuk fasilitas diarsipkan).");
            }

            $facility = Facility::create($data);

            $this->auditService->log(
                action: 'create',
                module: 'facilities',
                summary: "Fasilitas {$facility->code} ({$facility->name}) berhasil ditambahkan",
                actor: $actor,
                entityType: 'Facility',
                entityId: $facility->id,
                entityLabel: $facility->code,
                changes: [
                    'after' => [
                        'code' => $facility->code,
                        'name' => $facility->name,
                        'location_type' => $facility->location_type,
                        'room_id' => $facility->room_id,
                        'area_name' => $facility->area_name,
                        'condition' => $facility->condition,
                        'notes' => $facility->notes,
                        'archived_at' => null,
                    ],
                ]
            );

            return $facility;
        });
    }

    /**
     * Atomically update a facility with audit trail.
     * Enforces consistent lock order (Facility then Room), rechecks complaints after lock,
     * and strictly forbids location changes if complaints exist.
     *
     * @param array{code?: string, name?: string, condition?: string, location_type?: string, room_id?: int|null, area_name?: string|null, notes?: string|null} $data
     */
    public function updateFacility(Facility $facility, array $data, User $actor): Facility
    {
        return DB::transaction(function () use ($facility, $data, $actor) {
            // 1. Lock Facility row first
            $lockedFacility = Facility::where('id', $facility->id)->lockForUpdate()->firstOrFail();

            // 2. Re-check complaint history after locking
            $hasComplaints = $lockedFacility->complaints()->exists();

            // 3. Normalize location values before comparison
            $currentLocType = (string) $lockedFacility->location_type;
            $currentRoomId = $lockedFacility->room_id !== null ? (int) $lockedFacility->room_id : null;
            $currentArea = $lockedFacility->area_name !== null ? trim((string) $lockedFacility->area_name) : null;

            $targetLocType = isset($data['location_type']) ? (string) $data['location_type'] : $currentLocType;
            $targetRoomId = ($targetLocType === 'room' && isset($data['room_id']) && $data['room_id'] !== '' && $data['room_id'] !== null)
                ? (int) $data['room_id']
                : null;
            $targetArea = ($targetLocType === 'shared' && isset($data['area_name']) && trim((string) $data['area_name']) !== '')
                ? trim((string) $data['area_name'])
                : null;

            $isLocationChanged = ($currentLocType !== $targetLocType)
                || ($targetLocType === 'room' && $currentRoomId !== $targetRoomId)
                || ($targetLocType === 'shared' && $currentArea !== $targetArea);

            // 4. If complaints exist, location is strictly immutable
            if ($hasComplaints) {
                if ($isLocationChanged) {
                    throw new DomainException(
                        'Lokasi fasilitas yang memiliki riwayat keluhan tidak dapat diubah demi menjaga integritas data riwayat. Silakan arsipkan fasilitas ini dan buat fasilitas baru di lokasi yang dituju.'
                    );
                }

                // Force canonical location values from database
                $targetLocType = $currentLocType;
                $targetRoomId = $currentRoomId;
                $targetArea = $currentArea;
            }

            // 5. Lock Room if involved, checking archive rule
            if ($targetLocType === 'room' && $targetRoomId !== null) {
                $targetRoom = Room::where('id', $targetRoomId)->lockForUpdate()->firstOrFail();

                // If moving to a new room (different room id), target room must NOT be archived
                if ($isLocationChanged && $targetRoom->isArchived()) {
                    throw new DomainException(
                        "Kamar {$targetRoom->number} sedang diarsipkan dan tidak dapat digunakan untuk pemindahan fasilitas."
                    );
                }
            }

            // 6. Case-insensitive unique code check
            if (isset($data['code'])) {
                $newCode = trim((string) $data['code']);
                $codeExists = Facility::whereRaw('LOWER(code) = ?', [strtolower($newCode)])
                    ->where('id', '!=', $lockedFacility->id)
                    ->exists();

                if ($codeExists) {
                    throw new DomainException("Kode fasilitas '{$newCode}' sudah digunakan oleh fasilitas lain.");
                }
            }

            // 7. Snapshot before
            $before = [
                'code' => $lockedFacility->code,
                'name' => $lockedFacility->name,
                'location_type' => $lockedFacility->location_type,
                'room_id' => $lockedFacility->room_id,
                'area_name' => $lockedFacility->area_name,
                'condition' => $lockedFacility->condition,
                'notes' => $lockedFacility->notes,
                'archived_at' => $lockedFacility->archived_at?->toIso8601String(),
            ];

            // 8. Update attributes
            if (isset($data['code'])) {
                $lockedFacility->code = trim((string) $data['code']);
            }
            if (isset($data['name'])) {
                $lockedFacility->name = trim((string) $data['name']);
            }
            if (isset($data['condition'])) {
                $lockedFacility->condition = (string) $data['condition'];
            }
            if (array_key_exists('notes', $data)) {
                $lockedFacility->notes = $data['notes'];
            }

            $lockedFacility->location_type = $targetLocType;
            $lockedFacility->room_id = $targetRoomId;
            $lockedFacility->area_name = $targetArea;

            $lockedFacility->save();

            // 9. Snapshot after
            $after = [
                'code' => $lockedFacility->code,
                'name' => $lockedFacility->name,
                'location_type' => $lockedFacility->location_type,
                'room_id' => $lockedFacility->room_id,
                'area_name' => $lockedFacility->area_name,
                'condition' => $lockedFacility->condition,
                'notes' => $lockedFacility->notes,
                'archived_at' => $lockedFacility->archived_at?->toIso8601String(),
            ];

            // 10. Audit log
            $this->auditService->log(
                action: 'update',
                module: 'facilities',
                summary: "Data fasilitas {$lockedFacility->code} ({$lockedFacility->name}) berhasil diperbarui",
                actor: $actor,
                entityType: 'Facility',
                entityId: $lockedFacility->id,
                entityLabel: $lockedFacility->code,
                changes: [
                    'before' => $before,
                    'after' => $after,
                ]
            );

            return $lockedFacility;
        });
    }

    /**
     * Physically delete a facility only if no complaints exist.
     */
    public function deleteFacility(Facility $facility, User $actor): void
    {
        DB::transaction(function () use ($facility, $actor) {
            $lockedFacility = Facility::where('id', $facility->id)->lockForUpdate()->firstOrFail();

            if ($lockedFacility->complaints()->exists()) {
                throw new DomainException(
                    "Fasilitas {$lockedFacility->code} memiliki riwayat keluhan sehingga tidak dapat dihapus permanen. Gunakan fitur arsip."
                );
            }

            $before = [
                'code' => $lockedFacility->code,
                'name' => $lockedFacility->name,
                'location_type' => $lockedFacility->location_type,
                'room_id' => $lockedFacility->room_id,
                'area_name' => $lockedFacility->area_name,
                'condition' => $lockedFacility->condition,
                'notes' => $lockedFacility->notes,
                'archived_at' => $lockedFacility->archived_at?->toIso8601String(),
            ];

            $facilityCode = $lockedFacility->code;
            $facilityId = $lockedFacility->id;

            $lockedFacility->delete();

            $this->auditService->log(
                action: 'delete',
                module: 'facilities',
                summary: "Fasilitas {$facilityCode} berhasil dihapus permanen",
                actor: $actor,
                entityType: 'Facility',
                entityId: $facilityId,
                entityLabel: $facilityCode,
                changes: [
                    'before' => $before,
                    'after' => null,
                ]
            );
        });
    }

    /**
     * Archive a facility. Blocked if open or in_progress complaints exist.
     * Idempotent: returns false if already archived.
     */
    public function archiveFacility(Facility $facility, User $actor): bool
    {
        return DB::transaction(function () use ($facility, $actor) {
            $lockedFacility = Facility::where('id', $facility->id)->lockForUpdate()->firstOrFail();

            if ($lockedFacility->complaints()->whereIn('status', ['open', 'in_progress'])->exists()) {
                throw new DomainException(
                    "Fasilitas {$lockedFacility->code} tidak dapat diarsipkan karena masih memiliki keluhan yang berstatus terbuka atau sedang diproses."
                );
            }

            if ($lockedFacility->archived_at !== null) {
                return false;
            }

            $before = [
                'archived_at' => null,
            ];

            $lockedFacility->update(['archived_at' => now('UTC')]);

            $after = [
                'archived_at' => $lockedFacility->archived_at->toIso8601String(),
            ];

            $this->auditService->log(
                action: 'archive',
                module: 'facilities',
                summary: "Fasilitas {$lockedFacility->code} berhasil diarsipkan",
                actor: $actor,
                entityType: 'Facility',
                entityId: $lockedFacility->id,
                entityLabel: $lockedFacility->code,
                changes: [
                    'before' => $before,
                    'after' => $after,
                ]
            );

            return true;
        });
    }

    /**
     * Unarchive a facility.
     * Idempotent: returns false if already active.
     */
    public function unarchiveFacility(Facility $facility, User $actor): bool
    {
        return DB::transaction(function () use ($facility, $actor) {
            $lockedFacility = Facility::where('id', $facility->id)->lockForUpdate()->firstOrFail();

            if ($lockedFacility->archived_at === null) {
                return false;
            }

            $before = [
                'archived_at' => $lockedFacility->archived_at->toIso8601String(),
            ];

            $lockedFacility->update(['archived_at' => null]);

            $after = [
                'archived_at' => null,
            ];

            $this->auditService->log(
                action: 'unarchive',
                module: 'facilities',
                summary: "Arsip fasilitas {$lockedFacility->code} berhasil dibuka kembali (diaktifkan)",
                actor: $actor,
                entityType: 'Facility',
                entityId: $lockedFacility->id,
                entityLabel: $lockedFacility->code,
                changes: [
                    'before' => $before,
                    'after' => $after,
                ]
            );

            return true;
        });
    }
}
