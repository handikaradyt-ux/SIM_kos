<?php

namespace App\Http\Controllers;

use App\Http\Requests\Resident\StoreResidentRequest;
use App\Http\Requests\Resident\UpdateResidentRequest;
use App\Models\Resident;
use App\Services\ResidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ResidentController extends Controller
{
    public function __construct(
        protected ResidentService $residentService
    ) {}

    /**
     * Display a paginated listing of residents with search and filter.
     * Admin and Owner can view. Resident role is strictly 403 Forbidden.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Resident::class);

        // Sanitize filter and search query parameters gracefully
        $rawStatus = $request->query('status');
        $status = is_string($rawStatus) && in_array($rawStatus, ['active', 'archived', 'all'], true)
            ? $rawStatus
            : 'active';

        $rawAccountStatus = $request->query('account_status');
        $accountStatus = is_string($rawAccountStatus) && in_array($rawAccountStatus, ['all', 'active', 'inactive'], true)
            ? $rawAccountStatus
            : 'all';

        $rawPerPage = $request->query('per_page');
        $perPage = is_numeric($rawPerPage) && in_array((int) $rawPerPage, [10, 25, 50], true)
            ? (int) $rawPerPage
            : 10;

        $rawSearch = $request->query('q');
        $search = is_string($rawSearch) ? trim(substr($rawSearch, 0, 100)) : null;

        $query = Resident::query()
            ->with([
                'user.role',
                'activePlacement.room',
            ])
            ->withExists([
                'activePlacement as has_active_placement',
                'placements as has_placements',
                'user as has_complaints' => fn ($q) => $q->whereHas('complaints'),
            ]);

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'archived') {
            $query->archived();
        }

        if ($accountStatus === 'active') {
            $query->whereHas('user', fn ($uq) => $uq->where('is_active', true));
        } elseif ($accountStatus === 'inactive') {
            $query->whereHas('user', fn ($uq) => $uq->where('is_active', false));
        }

        if ($search !== null && $search !== '') {
            $query->search($search);
        }

        $residents = $query->orderBy('name', 'asc')->paginate($perPage)->withQueryString();

        return view('residents.index', [
            'residents' => $residents,
            'currentStatus' => $status,
            'currentAccountStatus' => $accountStatus,
            'currentPerPage' => $perPage,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new resident and user account.
     */
    public function create(): View
    {
        Gate::authorize('create', Resident::class);

        return view('residents.create');
    }

    /**
     * Store a newly created resident and user account in storage.
     */
    public function store(StoreResidentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Resident::class);

        $result = $this->residentService->createResidentWithAccount(
            $request->validated(),
            $request->user()
        );

        $request->session()->flash('temporary_credentials', [
            'name' => $result['resident']->name,
            'email' => $result['user']->email,
            'password' => $result['temporary_password'],
        ]);

        return redirect()->route('residents.show', $result['resident'])
            ->with('status', 'Penghuni dan akun pengguna berhasil dibuat. Serahkan password sementara kepada penghuni.');
    }

    /**
     * Display the specified resident details.
     * Admin and Owner can view. Resident role is strictly 403 Forbidden even for self.
     */
    public function show(Resident $resident): Response|View
    {
        Gate::authorize('viewMaster', $resident);

        $resident->load([
            'user.role',
            'activePlacement.room',
            'placements' => fn ($q) => $q->orderBy('started_on', 'desc')->with('room'),
        ]);

        $response = response()->view('residents.show', compact('resident'));

        if (session()->has('temporary_credentials')) {
            $response->header('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
        }

        return $response;
    }

    /**
     * Show the form for editing the specified resident.
     */
    public function edit(Resident $resident): View
    {
        Gate::authorize('update', $resident);

        $resident->load('user.role');

        return view('residents.edit', compact('resident'));
    }

    /**
     * Update the specified resident and synchronized user account in storage.
     */
    public function update(UpdateResidentRequest $request, Resident $resident): RedirectResponse
    {
        Gate::authorize('update', $resident);

        $this->residentService->updateResident(
            $resident,
            $request->validated(),
            $request->user()
        );

        return redirect()->route('residents.show', $resident)
            ->with('status', 'Data penghuni dan sinkronisasi akun berhasil diperbarui.');
    }

    /**
     * Remove the specified resident profile from storage and deactivate associated account.
     */
    public function destroy(Resident $resident, Request $request): RedirectResponse
    {
        Gate::authorize('delete', $resident);

        $this->residentService->deleteResidentProfile($resident, $request->user());

        return redirect()->route('residents.index')
            ->with('status', 'Profil penghuni berhasil dihapus dan akun pengguna terkait telah dinonaktifkan.');
    }

    /**
     * Archive the specified resident and deactivate associated account.
     */
    public function archive(Resident $resident, Request $request): RedirectResponse
    {
        Gate::authorize('archive', $resident);

        $this->residentService->archiveResident($resident, $request->user());

        return redirect()->route('residents.show', $resident)
            ->with('status', 'Penghuni berhasil diarsipkan dan akun login terkait telah dinonaktifkan.');
    }

    /**
     * Unarchive the specified resident and reactivate associated account.
     */
    public function unarchive(Resident $resident, Request $request): RedirectResponse
    {
        Gate::authorize('unarchive', $resident);

        $this->residentService->unarchiveResident($resident, $request->user());

        return redirect()->route('residents.show', $resident)
            ->with('status', 'Arsip penghuni berhasil dibuka dan akun login terkait telah diaktifkan kembali.');
    }

    /**
     * Explicitly activate the resident user account.
     */
    public function activate(Resident $resident, Request $request): RedirectResponse
    {
        Gate::authorize('activate', $resident);

        $this->residentService->activateAccount($resident, $request->user());

        return redirect()->route('residents.show', $resident)
            ->with('status', 'Akun pengguna penghuni berhasil diaktifkan.');
    }

    /**
     * Explicitly deactivate the resident user account and revoke old sessions.
     */
    public function deactivate(Resident $resident, Request $request): RedirectResponse
    {
        Gate::authorize('deactivate', $resident);

        $this->residentService->deactivateAccount($resident, $request->user());

        return redirect()->route('residents.show', $resident)
            ->with('status', 'Akun pengguna penghuni berhasil dinonaktifkan dan sesi lama telah dicabut.');
    }

    /**
     * Reset temporary password for the resident account.
     */
    public function resetPassword(Resident $resident, Request $request): RedirectResponse
    {
        Gate::authorize('resetPassword', $resident);

        $tempPassword = $this->residentService->resetTemporaryPassword($resident, $request->user());

        $request->session()->flash('temporary_credentials', [
            'name' => $resident->name,
            'email' => $resident->user->email,
            'password' => $tempPassword,
        ]);

        return redirect()->route('residents.show', $resident)
            ->with('status', 'Password sementara berhasil di-reset. Serahkan password kepada penghuni.');
    }
}
