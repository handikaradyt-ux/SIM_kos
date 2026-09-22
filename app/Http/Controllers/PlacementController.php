<?php

namespace App\Http\Controllers;

use App\Http\Requests\Placement\PreviewPlacementRequest;
use App\Http\Requests\Placement\StorePlacementRequest;
use App\Models\Placement;
use App\Models\Resident;
use App\Models\Room;
use App\Services\PlacementService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlacementController extends Controller
{
    public function __construct(
        protected PlacementService $placementService
    ) {}

    /**
     * Display a paginated listing of placements with search and status tabs.
     * Accessible by Admin and Owner. Resident is 403 Forbidden.
     */
    public function index(Request $request): View|RedirectResponse|JsonResponse
    {
        Gate::authorize('viewAny', Placement::class);

        // Sanitize and validate query parameters gracefully against array injection
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['active', 'ended', 'all'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50, '10', '25', '50'])],
        ], [
            'search.string' => 'Parameter pencarian harus berupa teks.',
            'search.max' => 'Parameter pencarian maksimal 100 karakter.',
            'status.in' => 'Filter status penempatan tidak valid.',
            'status.string' => 'Parameter status harus berupa teks.',
            'per_page.in' => 'Batas data per halaman harus 10, 25, atau 50.',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            return redirect()->route('placements.index')->withErrors($validator);
        }

        $validated = $validator->validated();

        $query = Placement::query()
            ->with(['resident.user', 'room', 'creator', 'ender'])
            ->withCount('invoices');

        // Search term (resident name, phone, room number)
        $search = isset($validated['search']) ? trim((string) $validated['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('resident', function ($rq) use ($search) {
                    $rq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })->orWhereHas('room', function ($rq) use ($search) {
                    $rq->where('number', 'like', "%{$search}%");
                });
            });
        }

        // Status tab: active (default), ended, all
        $status = $validated['status'] ?? 'active';
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'ended') {
            $query->ended();
        }

        // Consistent ordering: recent started_on first, then ID descending
        $query->orderBy('started_on', 'desc')->orderBy('id', 'desc');

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 10;
        $placements = $query->paginate($perPage)->withQueryString();

        // Status tab counts for navigation badges
        $activeCount = Placement::active()->count();
        $endedCount = Placement::ended()->count();
        $allCount = Placement::count();

        return view('placements.index', compact(
            'placements',
            'search',
            'status',
            'perPage',
            'activeCount',
            'endedCount',
            'allCount'
        ));
    }

    /**
     * Show the form for starting a new placement.
     * Admin only.
     */
    public function create(): View
    {
        Gate::authorize('create', Placement::class);

        // Load vacant, non-archived rooms
        $availableRooms = Room::active()
            ->vacant()
            ->orderBy('number')
            ->get();

        // Load non-archived, active resident users with no active placement
        $availableResidents = Resident::active()
            ->whereHas('user', function ($q) {
                $q->where('is_active', true)
                    ->whereHas('role', fn ($rq) => $rq->where('code', 'resident'));
            })
            ->whereDoesntHave('placements', function ($q) {
                $q->whereNull('ended_on');
            })
            ->orderBy('name')
            ->get();

        return view('placements.create', compact('availableRooms', 'availableResidents'));
    }

    /**
     * Preview placement and generate a server-verified session token.
     * Admin only, strictly read-only.
     */
    public function preview(PreviewPlacementRequest $request): JsonResponse
    {
        Gate::authorize('create', Placement::class);

        if (config('app.env') === 'local' && $request->filled('_delay_ms')) {
            usleep(min((int) $request->input('_delay_ms'), 5000) * 1000);
        }

        try {
            $previewData = $this->placementService->previewPlacement(
                (int) $request->validated('resident_id'),
                (int) $request->validated('room_id'),
                Auth::user()
            );

            return response()->json([
                'success' => true,
                'data' => $previewData,
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Store a newly created placement in storage atomically.
     * Admin only.
     */
    public function store(StorePlacementRequest $request): RedirectResponse
    {
        Gate::authorize('create', Placement::class);

        try {
            $placement = $this->placementService->startPlacement(
                (int) $request->validated('resident_id'),
                (int) $request->validated('room_id'),
                (string) $request->validated('preview_token'),
                Auth::user()
            );

            return redirect()->route('placements.show', $placement)
                ->with('success', 'Penempatan penghuni berhasil dibuat dan invoice pertama telah diterbitkan.');
        } catch (DomainException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified placement detail.
     * Accessible by Admin and Owner. Resident is 403 Forbidden.
     */
    public function show(Placement $placement): View
    {
        Gate::authorize('view', $placement);

        $placement->load([
            'resident.user',
            'room',
            'creator',
            'ender',
            'invoices' => function ($query) {
                $query->orderBy('period_month', 'desc')
                    ->withExists('validPayment')
                    ->with('validPayment');
            },
        ]);

        return view('placements.show', compact('placement'));
    }
}
