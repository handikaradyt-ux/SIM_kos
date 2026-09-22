<?php

namespace App\Http\Controllers;

use App\Http\Requests\Facility\StoreFacilityRequest;
use App\Http\Requests\Facility\UpdateFacilityRequest;
use App\Models\Facility;
use App\Models\Room;
use App\Services\FacilityService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function __construct(
        protected FacilityService $facilityService
    ) {}

    /**
     * Display a paginated listing of facilities with search and filters.
     * Accessible by Admin and Owner. Resident is 403 Forbidden.
     */
    public function index(Request $request): View|RedirectResponse|JsonResponse
    {
        Gate::authorize('viewAny', Facility::class);

        // Sanitize and validate query parameters gracefully
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'tab' => ['nullable', 'string', Rule::in(['active', 'archived', 'all'])],
            'condition' => ['nullable', 'string', Rule::in(['all', 'good', 'broken', 'repairing'])],
            'location_type' => ['nullable', 'string', Rule::in(['all', 'room', 'shared'])],
            'room_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', Rule::in([10, 25, 50, '10', '25', '50'])],
        ], [
            'search.string' => 'Parameter pencarian harus berupa teks.',
            'search.max' => 'Parameter pencarian maksimal 100 karakter.',
            'tab.in' => 'Tab status arsip yang dipilih tidak valid.',
            'tab.string' => 'Parameter tab harus berupa teks.',
            'condition.in' => 'Filter kondisi yang dipilih tidak valid.',
            'location_type.in' => 'Filter lokasi yang dipilih tidak valid.',
            'room_id.integer' => 'Filter kamar harus berupa ID yang valid.',
            'per_page.in' => 'Batas data per halaman harus 10, 25, atau 50.',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            return redirect()->route('facilities.index')->withErrors($validator);
        }

        $validated = $validator->validated();

        // Eager-load relations and existence checks to prevent N+1 queries
        $query = Facility::query()
            ->with(['room'])
            ->withExists(['complaints as has_complaints'])
            ->withExists([
                'complaints as has_active_complaints' => fn ($q) => $q->whereIn('status', ['open', 'in_progress']),
            ]);

        // Search term (code, name, area_name, room number)
        $search = isset($validated['search']) ? trim($validated['search']) : '';
        if ($search !== '') {
            $query->search($search);
        }

        // Archive status tab
        $tab = $validated['tab'] ?? 'active';
        if ($tab === 'active') {
            $query->active();
        } elseif ($tab === 'archived') {
            $query->archived();
        }

        // Condition filter
        $condition = $validated['condition'] ?? 'all';
        if ($condition !== 'all' && $condition !== '') {
            $query->condition($condition);
        }

        // Location type filter
        $locationType = $validated['location_type'] ?? 'all';
        if ($locationType !== 'all' && $locationType !== '') {
            $query->locationType($locationType);
        }

        // Specific Room filter
        $roomId = isset($validated['room_id']) && $validated['room_id'] ? (int) $validated['room_id'] : null;
        if ($roomId) {
            $query->room($roomId);
        }

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 10;

        $facilities = $query->orderBy('code', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        $rooms = Room::active()->orderBy('number', 'asc')->get(['id', 'number', 'type']);

        return view('facilities.index', compact(
            'facilities',
            'rooms',
            'search',
            'tab',
            'condition',
            'locationType',
            'roomId',
            'perPage'
        ));
    }

    /**
     * Show the form for creating a new facility.
     */
    public function create(): View
    {
        Gate::authorize('create', Facility::class);

        $rooms = Room::active()->orderBy('number', 'asc')->get();

        return view('facilities.create', compact('rooms'));
    }

    /**
     * Store a newly created facility in storage.
     */
    public function store(StoreFacilityRequest $request): RedirectResponse
    {
        Gate::authorize('create', Facility::class);

        try {
            $facility = $this->facilityService->createFacility($request->validated(), Auth::user());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('facilities.index')
            ->with('status', "Fasilitas {$facility->code} berhasil ditambahkan.");
    }

    /**
     * Display the specified facility details.
     */
    public function show(Facility $facility): View
    {
        Gate::authorize('view', $facility);

        $facility->load([
            'room',
            'complaints' => fn ($q) => $q->orderBy('created_at', 'desc'),
            'complaints.submitter',
            'complaints.placement.resident',
            'complaints.placement.room',
        ]);

        $canBeDeleted = $facility->canBeDeleted();
        $canBeArchived = $facility->canBeArchived();

        return view('facilities.show', compact('facility', 'canBeDeleted', 'canBeArchived'));
    }

    /**
     * Show the form for editing the specified facility.
     */
    public function edit(Facility $facility): View
    {
        Gate::authorize('update', $facility);

        $rooms = Room::active()->orderBy('number', 'asc')->get();

        // If facility is currently in an archived room, include it in dropdown so it can be viewed/retained
        if ($facility->location_type === 'room' && $facility->room && $facility->room->isArchived()) {
            if (! $rooms->contains('id', $facility->room_id)) {
                $rooms->push($facility->room);
            }
        }

        $hasComplaints = $facility->complaints()->exists();

        return view('facilities.edit', compact('facility', 'rooms', 'hasComplaints'));
    }

    /**
     * Update the specified facility in storage.
     */
    public function update(UpdateFacilityRequest $request, Facility $facility): RedirectResponse
    {
        Gate::authorize('update', $facility);

        try {
            $updatedFacility = $this->facilityService->updateFacility($facility, $request->validated(), Auth::user());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('facilities.index')
            ->with('status', "Data fasilitas {$updatedFacility->code} berhasil diperbarui.");
    }

    /**
     * Physically delete the specified facility.
     */
    public function destroy(Facility $facility): RedirectResponse
    {
        Gate::authorize('delete', $facility);

        try {
            $this->facilityService->deleteFacility($facility, Auth::user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('facilities.index')
            ->with('status', "Fasilitas {$facility->code} berhasil dihapus permanen.");
    }

    /**
     * Archive the specified facility.
     */
    public function archive(Facility $facility): RedirectResponse
    {
        Gate::authorize('archive', $facility);

        try {
            $archived = $this->facilityService->archiveFacility($facility, Auth::user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (! $archived) {
            return redirect()->route('facilities.index')
                ->with('status', "Fasilitas {$facility->code} sudah dalam status diarsipkan.");
        }

        return redirect()->route('facilities.index')
            ->with('status', "Fasilitas {$facility->code} berhasil diarsipkan.");
    }

    /**
     * Unarchive the specified facility.
     */
    public function unarchive(Facility $facility): RedirectResponse
    {
        Gate::authorize('unarchive', $facility);

        $unarchived = $this->facilityService->unarchiveFacility($facility, Auth::user());

        if (! $unarchived) {
            return redirect()->route('facilities.index')
                ->with('status', "Fasilitas {$facility->code} sudah dalam status aktif.");
        }

        return redirect()->route('facilities.index')
            ->with('status', "Arsip fasilitas {$facility->code} berhasil dibuka kembali (diaktifkan).");
    }
}
