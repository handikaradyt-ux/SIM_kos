<?php

namespace App\Http\Controllers;

use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Models\Room;
use App\Services\AuditService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of rooms with search, filters, and pagination.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Room::class);

        $query = Room::query()
            ->withExists(['activePlacement'])
            ->withCount('facilities')
            ->with(['activePlacement.resident']);

        // Grouped OR search condition to avoid bypassing tab/occupancy filters
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        // Archive status tab filter
        $tab = (string) $request->input('tab', 'active');
        if ($tab === 'active') {
            $query->active();
        } elseif ($tab === 'archived') {
            $query->archived();
        }

        // Occupancy status filter
        $occupancy = (string) $request->input('occupancy', 'all');
        if ($occupancy === 'occupied') {
            $query->occupied();
        } elseif ($occupancy === 'vacant') {
            $query->vacant();
        }

        // Strictly validated pagination limit
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        $rooms = $query->orderBy('number', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return view('rooms.index', compact('rooms', 'search', 'tab', 'occupancy', 'perPage'));
    }

    /**
     * Show the form for creating a new room.
     */
    public function create(): View
    {
        Gate::authorize('create', Room::class);

        return view('rooms.create');
    }

    /**
     * Store a newly created room in storage.
     */
    public function store(StoreRoomRequest $request): RedirectResponse
    {
        Gate::authorize('create', Room::class);

        $validated = $request->validated();

        $room = DB::transaction(function () use ($validated) {
            $createdRoom = Room::create($validated);

            $this->auditService->log(
                action: 'create',
                module: 'rooms',
                summary: "Kamar {$createdRoom->number} ({$createdRoom->type}) berhasil ditambahkan",
                actor: Auth::user(),
                entityType: 'Room',
                entityId: $createdRoom->id,
                entityLabel: $createdRoom->number,
                changes: [
                    'after' => [
                        'number' => $createdRoom->number,
                        'type' => $createdRoom->type,
                        'monthly_rate' => $createdRoom->monthly_rate,
                        'notes' => $createdRoom->notes,
                        'archived_at' => null,
                    ],
                ]
            );

            return $createdRoom;
        });

        return redirect()->route('rooms.index')
            ->with('status', "Kamar {$room->number} berhasil ditambahkan.");
    }

    /**
     * Display the specified room.
     */
    public function show(Room $room): View
    {
        Gate::authorize('view', $room);

        $room->load([
            'activePlacement.resident',
            'facilities',
            'placements.resident' => fn ($q) => $q->orderBy('started_on', 'desc'),
        ]);

        $canBeDeleted = $room->canBeDeleted();
        $canBeArchived = $room->canBeArchived();

        return view('rooms.show', compact('room', 'canBeDeleted', 'canBeArchived'));
    }

    /**
     * Show the form for editing the specified room.
     */
    public function edit(Room $room): View
    {
        Gate::authorize('update', $room);

        return view('rooms.edit', compact('room'));
    }

    /**
     * Update the specified room in storage.
     */
    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        Gate::authorize('update', $room);

        $validated = $request->validated();

        $updatedRoom = DB::transaction(function () use ($validated, $room) {
            $lockedRoom = Room::where('id', $room->id)->lockForUpdate()->firstOrFail();

            $before = [
                'number' => $lockedRoom->number,
                'type' => $lockedRoom->type,
                'monthly_rate' => $lockedRoom->monthly_rate,
                'notes' => $lockedRoom->notes,
                'archived_at' => $lockedRoom->archived_at?->toIso8601String(),
            ];

            $lockedRoom->update($validated);

            $after = [
                'number' => $lockedRoom->number,
                'type' => $lockedRoom->type,
                'monthly_rate' => $lockedRoom->monthly_rate,
                'notes' => $lockedRoom->notes,
                'archived_at' => $lockedRoom->archived_at?->toIso8601String(),
            ];

            $this->auditService->log(
                action: 'update',
                module: 'rooms',
                summary: "Data kamar {$lockedRoom->number} berhasil diperbarui",
                actor: Auth::user(),
                entityType: 'Room',
                entityId: $lockedRoom->id,
                entityLabel: $lockedRoom->number,
                changes: [
                    'before' => $before,
                    'after' => $after,
                ]
            );

            return $lockedRoom;
        });

        return redirect()->route('rooms.index')
            ->with('status', "Data kamar {$updatedRoom->number} berhasil diperbarui.");
    }

    /**
     * Physically delete the specified room if no historical references exist.
     */
    public function destroy(Room $room): RedirectResponse
    {
        Gate::authorize('delete', $room);

        try {
            DB::transaction(function () use ($room) {
                $lockedRoom = Room::where('id', $room->id)->lockForUpdate()->firstOrFail();

                if ($lockedRoom->hasHistoricalReferences()) {
                    throw new DomainException(
                        "Kamar {$lockedRoom->number} memiliki riwayat penempatan atau fasilitas terkait sehingga tidak dapat dihapus permanen. Gunakan fitur arsip."
                    );
                }

                $before = [
                    'number' => $lockedRoom->number,
                    'type' => $lockedRoom->type,
                    'monthly_rate' => $lockedRoom->monthly_rate,
                    'notes' => $lockedRoom->notes,
                    'archived_at' => $lockedRoom->archived_at?->toIso8601String(),
                ];

                $roomNumber = $lockedRoom->number;
                $roomId = $lockedRoom->id;

                $lockedRoom->delete();

                $this->auditService->log(
                    action: 'delete',
                    module: 'rooms',
                    summary: "Kamar {$roomNumber} berhasil dihapus permanen",
                    actor: Auth::user(),
                    entityType: 'Room',
                    entityId: $roomId,
                    entityLabel: $roomNumber,
                    changes: [
                        'before' => $before,
                        'after' => null,
                    ]
                );
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('rooms.index')
            ->with('status', "Kamar {$room->number} berhasil dihapus permanen.");
    }

    /**
     * Archive the specified room (only if not currently occupied).
     */
    public function archive(Room $room): RedirectResponse
    {
        Gate::authorize('archive', $room);

        try {
            DB::transaction(function () use ($room) {
                $lockedRoom = Room::where('id', $room->id)->lockForUpdate()->firstOrFail();

                if ($lockedRoom->is_occupied) {
                    throw new DomainException(
                        "Kamar {$lockedRoom->number} sedang dihuni oleh penempatan aktif dan tidak dapat diarsipkan. Akhiri penempatan terlebih dahulu."
                    );
                }

                if ($lockedRoom->archived_at !== null) {
                    return;
                }

                $before = [
                    'archived_at' => null,
                ];

                $lockedRoom->update(['archived_at' => now('UTC')]);

                $after = [
                    'archived_at' => $lockedRoom->archived_at->toIso8601String(),
                ];

                $this->auditService->log(
                    action: 'archive',
                    module: 'rooms',
                    summary: "Kamar {$lockedRoom->number} berhasil diarsipkan",
                    actor: Auth::user(),
                    entityType: 'Room',
                    entityId: $lockedRoom->id,
                    entityLabel: $lockedRoom->number,
                    changes: [
                        'before' => $before,
                        'after' => $after,
                    ]
                );
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('rooms.index')
            ->with('status', "Kamar {$room->number} berhasil diarsipkan.");
    }

    /**
     * Unarchive the specified room (restore to active).
     */
    public function unarchive(Room $room): RedirectResponse
    {
        Gate::authorize('unarchive', $room);

        DB::transaction(function () use ($room) {
            $lockedRoom = Room::where('id', $room->id)->lockForUpdate()->firstOrFail();

            if ($lockedRoom->archived_at === null) {
                return;
            }

            $before = [
                'archived_at' => $lockedRoom->archived_at->toIso8601String(),
            ];

            $lockedRoom->update(['archived_at' => null]);

            $after = [
                'archived_at' => null,
            ];

            $this->auditService->log(
                action: 'unarchive',
                module: 'rooms',
                summary: "Arsip kamar {$lockedRoom->number} berhasil dibuka kembali (diaktifkan)",
                actor: Auth::user(),
                entityType: 'Room',
                entityId: $lockedRoom->id,
                entityLabel: $lockedRoom->number,
                changes: [
                    'before' => $before,
                    'after' => $after,
                ]
            );
        });

        return redirect()->route('rooms.index')
            ->with('status', "Arsip kamar {$room->number} berhasil dibuka kembali (diaktifkan).");
    }
}
