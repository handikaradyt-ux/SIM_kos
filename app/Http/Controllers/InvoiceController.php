<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\IndexInvoiceRequest;
use App\Http\Requests\Invoice\SyncInvoiceRequest;
use App\Http\Requests\Invoice\SyncPreviewInvoiceRequest;
use App\Models\Invoice;
use App\Models\Placement;
use App\Services\AuditService;
use App\Services\BillingService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    public function __construct(
        protected BillingService $billingService,
        protected AuditService $auditService
    ) {}

    /**
     * Display a paginated listing of invoices with search, filters, and coverage alerts.
     * Scoped by role: Admin/Owner view all, Resident views only own placement invoices.
     */
    public function index(Request $request): View|RedirectResponse|JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        // Sanitize and validate query parameters gracefully against array injection
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['all', 'paid', 'unpaid', 'overdue'])],
            'period' => ['nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'placement_status' => ['nullable', 'string', Rule::in(['all', 'active', 'ended'])],
            'sort' => ['nullable', 'string', Rule::in(['period_month', 'due_on', 'amount', 'resident_name', 'room_number'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([10, 15, 25, 50, '10', '15', '25', '50'])],
        ], [
            'search.string' => 'Parameter pencarian harus berupa teks.',
            'search.max' => 'Parameter pencarian maksimal 100 karakter.',
            'status.in' => 'Filter status tagihan tidak valid.',
            'period.regex' => 'Format periode harus YYYY-MM.',
            'placement_status.in' => 'Filter status penempatan tidak valid.',
            'sort.in' => 'Kolom pengurutan tidak valid.',
            'direction.in' => 'Arah pengurutan tidak valid.',
            'per_page.in' => 'Batas data per halaman harus 10, 15, 25, atau 50.',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            return redirect()->route('invoices.index')->withErrors($validator);
        }

        $validated = $validator->validated();
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'all';
        $period = $validated['period'] ?? null;
        $placementStatus = $validated['placement_status'] ?? 'all';
        $sort = $validated['sort'] ?? 'period_month';
        $direction = $validated['direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 15);

        // Single reference business date in Asia/Jakarta per request
        $businessDate = Carbon::now('Asia/Jakarta')->startOfDay();

        $user = Auth::user();
        $role = $user->role?->code;

        $coverage = null;

        if ($role === 'resident') {
            // Strict role scoping: resident can ONLY query their own placement invoices
            $resident = $user->resident;

            if (! $resident) {
                // Return safe empty paginator if user has no resident profile yet
                $invoices = Invoice::whereRaw('1 = 0')->paginate($perPage);

                return view('invoices.index', [
                    'invoices' => $invoices,
                    'coverage' => null,
                    'businessDate' => $businessDate,
                    'filters' => compact('search', 'status', 'period', 'placementStatus', 'sort', 'direction', 'perPage'),
                ]);
            }

            $query = Invoice::query()->whereHas('placement', function ($q) use ($resident) {
                $q->where('resident_id', $resident->id);
            });

            // Global coverage information is strictly HIDDEN from residents (anti data leakage)
            $coverage = null;
        } else {
            // Admin and Owner see all invoices
            $query = Invoice::query();

            // Calculate global coverage strictly read-only for banner alert
            $coverage = $this->billingService->checkGlobalCoverage($businessDate);
        }

        // Apply filters
        $query->search($search)
            ->status($status, $businessDate)
            ->period($period)
            ->placementStatus($placementStatus);

        // Apply sort
        match ($sort) {
            'due_on' => $query->orderBy('due_on', $direction)->orderBy('id', 'desc'),
            'amount' => $query->orderBy('amount', $direction)->orderBy('id', 'desc'),
            'resident_name' => $query->orderBy('resident_name_snapshot', $direction)->orderBy('id', 'desc'),
            'room_number' => $query->orderBy('room_number_snapshot', $direction)->orderBy('id', 'desc'),
            default => $query->orderBy('period_month', $direction)->orderBy('id', 'desc'),
        };

        // Eager load relations to completely prevent N+1 queries on invoices table
        $invoices = $query->with(['placement.room', 'placement.resident.user', 'validPayment'])
            ->paginate($perPage)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'coverage' => $coverage,
            'businessDate' => $businessDate,
            'filters' => compact('search', 'status', 'period', 'placementStatus', 'sort', 'direction', 'perPage'),
        ]);
    }

    /**
     * Display the specified invoice details read-only.
     * Authorized via InvoicePolicy: Admin/Owner view all, Resident views only own invoice.
     */
    public function show(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load([
            'placement.room',
            'placement.resident.user',
            'payments.recorder',
            'validPayment.recorder',
            'creator',
        ]);

        $businessDate = Carbon::now('Asia/Jakarta')->startOfDay();

        return view('invoices.show', [
            'invoice' => $invoice,
            'businessDate' => $businessDate,
        ]);
    }

    /**
     * Generate a read-only preview of missing invoices for synchronization.
     * Generates a server-verified session token binding actor, scope, business date, and estimates.
     * 0 database mutations (0 inserts, 0 updates, 0 deletes, 0 audit logs).
     */
    public function syncPreview(SyncPreviewInvoiceRequest $request): JsonResponse
    {
        Gate::authorize('preview', Invoice::class);

        // Local latency simulation for testing UI abort controllers
        if ($request->filled('_delay_ms') && app()->environment('local', 'testing')) {
            usleep((int) $request->input('_delay_ms') * 1000);
        }

        $businessDate = Carbon::now('Asia/Jakarta')->startOfDay();
        $actor = Auth::user();

        if ($request->filled('placement_id')) {
            // Scope: Single placement
            $placement = Placement::with(['resident', 'room'])->findOrFail($request->input('placement_id'));
            $preview = $this->billingService->previewPlacement($placement, $businessDate);

            $scopeType = 'placement';
            $scopePlacementId = (int) $placement->id;
            $totalMissing = $preview['new_invoices_count'];
            $totalAmount = $preview['total_new_amount'];
            $isComplete = $preview['is_complete'];

            $items = empty($preview['items']) ? [] : [[
                'placement_id' => $placement->id,
                'resident_name' => $placement->resident?->name ?? 'Tidak diketahui',
                'room_number' => $placement->room?->number ?? 'Tidak diketahui',
                'missing_count' => $preview['new_invoices_count'],
                'missing_periods' => $preview['missing_periods'],
                'missing_amount' => $preview['total_new_amount'],
            ]];
        } else {
            // Scope: Global batch across all placements (active and historical)
            $coverage = $this->billingService->checkGlobalCoverage($businessDate);

            $scopeType = 'global';
            $scopePlacementId = null;
            $totalMissing = $coverage['total_missing_invoices'];
            $totalAmount = $coverage['total_missing_amount'];
            $isComplete = $coverage['is_complete'];
            $items = $coverage['incomplete_placements'];
        }

        // Generate cryptographically secure session token binding all critical parameters
        $previewToken = Str::random(40);
        $sessionKey = "billing_sync_preview_{$previewToken}";

        session([$sessionKey => [
            'token' => $previewToken,
            'admin_id' => (int) $actor->id,
            'business_date' => $businessDate->toDateString(),
            'scope_type' => $scopeType,
            'placement_id' => $scopePlacementId,
            'total_missing_invoices' => $totalMissing,
            'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
        ]]);

        return response()->json([
            'success' => true,
            'data' => [
                'preview_token' => $previewToken,
                'scope_type' => $scopeType,
                'placement_id' => $scopePlacementId,
                'business_date' => $businessDate->toDateString(),
                'total_missing_invoices' => $totalMissing,
                'total_missing_amount' => $totalAmount,
                'total_missing_amount_formatted' => 'Rp ' . number_format($totalAmount, 0, ',', '.'),
                'placements_count' => count($items),
                'items' => $items,
                'is_complete' => $isComplete,
                'disclaimer' => 'Pratinjau ini merupakan estimasi terikat waktu. Sinkronisasi akan mengevaluasi dan mengunci setiap penempatan secara mandiri. Hasil aktual akan dilaporkan setelah proses selesai.',
                'expires_at' => Carbon::now()->addMinutes(15)->timestamp,
            ],
        ]);
    }

    /**
     * Execute invoice synchronization using verified session preview scope.
     * Processes each placement in independent transactions without wrapping outer transactions.
     * Reports partial successes and failures transparently.
     */
    public function sync(SyncInvoiceRequest $request): RedirectResponse
    {
        Gate::authorize('sync', Invoice::class);

        $previewToken = (string) $request->input('preview_token');
        $sessionKey = "billing_sync_preview_{$previewToken}";
        $preview = session($sessionKey);

        if (! is_array($preview) || ! isset($preview['expires_at']) || Carbon::now()->timestamp > $preview['expires_at']) {
            return redirect()->route('invoices.index')
                ->with('error', 'Sesi pratinjau sinkronisasi tidak valid atau telah kedaluwarsa. Silakan lakukan pratinjau ulang.');
        }

        if ((int) $preview['admin_id'] !== (int) Auth::id()) {
            return redirect()->route('invoices.index')
                ->with('error', 'Token pratinjau dibuat oleh Administrator lain. Silakan lakukan pratinjau ulang.');
        }

        $businessDate = Carbon::now('Asia/Jakarta')->startOfDay();
        if ($businessDate->toDateString() !== $preview['business_date']) {
            return redirect()->route('invoices.index')
                ->with('error', 'Tanggal operasional bisnis telah berganti sejak pratinjau dibuat. Silakan lakukan pratinjau ulang.');
        }

        // Scope enforcement: scope is authoritatively taken from server session, rejecting tampering
        $serverScopeType = $preview['scope_type'] ?? 'global';
        $serverPlacementId = $preview['placement_id'] ?? null;

        if ($request->filled('placement_id') && (int) $request->input('placement_id') !== (int) $serverPlacementId) {
            return redirect()->route('invoices.index')
                ->with('error', 'Lingkup penempatan tidak sesuai dengan data pratinjau terverifikasi.');
        }

        $actor = Auth::user();

        // Always invalidate preview token after execution attempt
        session()->forget($sessionKey);

        try {
            if ($serverScopeType === 'placement' && $serverPlacementId !== null) {
                $placement = Placement::findOrFail($serverPlacementId);
                $result = $this->billingService->syncPlacementInvoices($placement, $actor, $businessDate);

                $createdCount = $result['created_count'];
                $alreadyExisted = $result['already_existed_count'];
                $failedCount = 0;
                $failedPlacements = [];
            } else {
                // Global batch sync (each placement commits independently)
                $result = $this->billingService->syncAllPlacements($actor, $businessDate);

                $createdCount = $result['total_created'];
                $alreadyExisted = $result['total_already_existed'];
                $failedCount = $result['failed_count'];
                $failedPlacements = $result['failed_placements'];
            }
        } catch (\Throwable $e) {
            $safeError = $e instanceof DomainException || $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'Terjadi kesalahan sistem saat memproses sinkronisasi.';

            return redirect()->route('invoices.index')->with('error', $safeError);
        }

        // Provide honest, transparent reporting of actual results
        if ($failedCount > 0) {
            $failDetails = collect($failedPlacements)->map(fn ($f) => "#{$f['placement_id']}: {$f['error']}")->join(', ');
            $msg = "Sinkronisasi selesai sebagian: {$createdCount} invoice baru diterbitkan, {$failedCount} penempatan gagal ({$failDetails}).";

            return redirect()->route('invoices.index')->with('warning', $msg);
        }

        if ($createdCount > 0) {
            return redirect()->route('invoices.index')
                ->with('success', "Sinkronisasi berhasil: {$createdCount} invoice baru berhasil diterbitkan.");
        }

        return redirect()->route('invoices.index')
            ->with('info', 'Seluruh tagihan sudah lengkap. Tidak ada invoice baru yang perlu diterbitkan.');
    }
}
