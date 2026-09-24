@extends('layouts.app')

@php
    $role = auth()->user()?->role?->code;
    $isOwner = $role === 'owner';
    $isResident = $role === 'resident';
    $isAdmin = $role === 'admin';

    $pageTitle = match ($role) {
        'owner' => 'Status Tagihan Kos',
        'resident' => 'Tagihan Saya',
        default => 'Manajemen Tagihan (Invoice)',
    };

    $pageSubtitle = match ($role) {
        'owner' => 'Pemantauan status penagihan dan piutang sewa kamar kos secara transparan.',
        'resident' => 'Daftar tagihan sewa kamar kos Anda beserta status dan rincian jatuh tempo.',
        default => 'Kelola penerbitan, pemantauan status tagihan, dan sinkronisasi invoice sewa kamar kos.',
    };
@endphp

@section('title', $pageTitle)
@section('page_title', $pageTitle)
@section('page_subtitle', $pageSubtitle)

@section('content')
<div class="row g-3 mb-4">
    {{-- Banner Cakupan Global (Hanya Admin dan Owner, Tidak Ditampilkan ke Resident) --}}
    @if(!$isResident && $coverage !== null)
        <div class="col-12">
            @if(! $coverage['is_complete'])
                <div class="card border-warning bg-warning bg-opacity-10 shadow-sm">
                    <div class="card-body p-3 p-md-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-warning text-dark px-2 py-1">Perlu Sinkronisasi</span>
                                <h3 class="h6 fw-bold mb-0 text-dark">Tagihan Periode Berjalan Belum Lengkap</h3>
                            </div>
                            <p class="mb-1 text-secondary small">
                                Terdapat <strong>{{ $coverage['total_missing_invoices'] }}</strong> invoice belum diterbitkan dengan total estimasi <strong>Rp {{ number_format($coverage['total_missing_amount'], 0, ',', '.') }}</strong> pada <strong>{{ count($coverage['incomplete_placements']) }}</strong> penempatan.
                            </p>
                            <p class="mb-0 text-muted small fst-italic">
                                Evaluasi cakupan global mencakup seluruh penempatan aktif dan historis hingga bulan operasional berjalan ({{ $businessDate->translatedFormat('F Y') }}), independen dari filter pencarian tabel di bawah.
                            </p>
                        </div>
                        <div class="d-flex flex-shrink-0 align-items-center gap-2">
                            @if($isAdmin)
                                <button type="button" class="btn btn-warning text-dark fw-bold d-inline-flex align-items-center gap-1" id="btnOpenGlobalSync">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                        <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/>
                                        <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"/>
                                    </svg>
                                    <span>Sinkronkan Tagihan</span>
                                </button>
                            @else
                                <span class="badge bg-light text-muted border px-2 py-2">
                                    Sinkronisasi dijalankan oleh Administrator
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="card border-success bg-success bg-opacity-10 shadow-sm">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success px-2 py-1">Lengkap</span>
                            <span class="text-dark small fw-medium">
                                Seluruh tagihan kos untuk seluruh penempatan hingga bulan operasional berjalan ({{ $businessDate->translatedFormat('F Y') }}) telah lengkap dan tersinkronisasi.
                            </span>
                        </div>
                        @if($isAdmin)
                            <button type="button" class="btn btn-sm btn-outline-success" id="btnOpenGlobalSync">
                                Periksa Ulang
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Filter dan Pencarian --}}
    <div class="col-12">
        <div class="card simkos-card">
            <div class="card-body p-3 p-md-4">
                <form method="GET" action="{{ route('invoices.index') }}" class="row g-2 align-items-center">
                    {{-- Pencarian --}}
                    <div class="col-12 col-md-{{ $isResident ? '6' : '4' }}">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted" id="search-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                                </svg>
                            </span>
                            <input
                                type="text"
                                name="search"
                                value="{{ $filters['search'] ?? '' }}"
                                class="form-control form-control-sm"
                                placeholder="{{ $isResident ? 'Cari periode (YYYY-MM) atau kamar...' : 'Cari penghuni, kamar, atau periode...' }}"
                                aria-label="Cari data tagihan"
                                aria-describedby="search-addon"
                            >
                        </div>
                    </div>

                    {{-- Filter Status Tagihan --}}
                    <div class="col-6 col-md-{{ $isResident ? '3' : '2' }}">
                        <select name="status" class="form-select form-select-sm" aria-label="Filter status tagihan">
                            <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>Semua Status</option>
                            <option value="paid" {{ ($filters['status'] ?? '') === 'paid' ? 'selected' : '' }}>Lunas</option>
                            <option value="unpaid" {{ ($filters['status'] ?? '') === 'unpaid' ? 'selected' : '' }}>Belum Lunas (Semua)</option>
                            <option value="overdue" {{ ($filters['status'] ?? '') === 'overdue' ? 'selected' : '' }}>Terlambat (Overdue)</option>
                        </select>
                    </div>

                    {{-- Filter Periode (YYYY-MM) --}}
                    <div class="col-6 col-md-2">
                        <input
                            type="month"
                            name="period"
                            value="{{ $filters['period'] ?? '' }}"
                            class="form-control form-control-sm"
                            aria-label="Filter periode bulan tagihan"
                            placeholder="Periode (YYYY-MM)"
                        >
                    </div>

                    {{-- Filter Status Penempatan (Admin / Owner Only) --}}
                    @if(!$isResident)
                        <div class="col-6 col-md-2">
                            <select name="placement_status" class="form-select form-select-sm" aria-label="Filter status penempatan">
                                <option value="all" {{ ($filters['placementStatus'] ?? 'all') === 'all' ? 'selected' : '' }}>Semua Penempatan</option>
                                <option value="active" {{ ($filters['placementStatus'] ?? '') === 'active' ? 'selected' : '' }}>Penempatan Aktif</option>
                                <option value="ended" {{ ($filters['placementStatus'] ?? '') === 'ended' ? 'selected' : '' }}>Penempatan Selesai</option>
                            </select>
                        </div>
                    @endif

                    {{-- Jumlah Per Halaman --}}
                    <div class="col-6 col-md-1">
                        <select name="per_page" class="form-select form-select-sm" aria-label="Jumlah per halaman">
                            <option value="10" {{ ($filters['perPage'] ?? 15) == 10 ? 'selected' : '' }}>10</option>
                            <option value="15" {{ ($filters['perPage'] ?? 15) == 15 ? 'selected' : '' }}>15</option>
                            <option value="25" {{ ($filters['perPage'] ?? 15) == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ ($filters['perPage'] ?? 15) == 50 ? 'selected' : '' }}>50</option>
                        </select>
                    </div>

                    {{-- Pertahankan pengurutan saat menyaring --}}
                    @if(!empty($filters['sort']))
                        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    @endif
                    @if(!empty($filters['direction']))
                        <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
                    @endif

                    {{-- Tombol Filter & Reset --}}
                    <div class="col-12 col-md-1 d-flex gap-1 justify-content-end">
                        <button type="submit" class="btn btn-sm btn-teal flex-grow-1" title="Terapkan saringan">
                            Saring
                        </button>
                        @if(!empty($filters['search']) || ($filters['status'] ?? 'all') !== 'all' || !empty($filters['period']) || (($filters['placementStatus'] ?? 'all') !== 'all') || ($filters['perPage'] ?? 15) != 15)
                            <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter">
                                Reset
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Tabel Data Tagihan --}}
<div class="card simkos-card shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h2 class="h6 fw-bold mb-0 text-dark">
            {{ $isResident ? 'Daftar Tagihan Anda' : 'Daftar Tagihan Kamar' }}
            <span class="text-muted fw-normal small">({{ $invoices->total() }} tagihan ditemukan)</span>
        </h2>
    </div>

    @php
        $currentSort = $filters['sort'] ?? 'period_month';
        $currentDirection = $filters['direction'] ?? 'desc';

        $buildSortUrl = function (string $column) use ($currentSort, $currentDirection) {
            $isActive = ($currentSort === $column);
            $nextDirection = ($isActive && $currentDirection === 'asc') ? 'desc' : 'asc';

            if (! $isActive) {
                $nextDirection = in_array($column, ['period_month', 'due_on']) ? 'desc' : 'asc';
            }

            // Exclude page so pagination resets to page 1 upon sorting
            $params = request()->except(['page', 'sort', 'direction']);
            $params['sort'] = $column;
            $params['direction'] = $nextDirection;

            return route('invoices.index', $params);
        };
    @endphp

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    {{-- Periode Tagihan --}}
                    <th scope="col" class="ps-3" style="width: 15%;">
                        <a href="{{ $buildSortUrl('period_month') }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1" title="Urutkan berdasarkan periode tagihan">
                            <span class="fw-bold">Periode Tagihan</span>
                            @if($currentSort === 'period_month')
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" class="text-teal" viewBox="0 0 16 16" aria-label="Urutan {{ $currentDirection === 'asc' ? 'naik' : 'turun' }}">
                                    @if($currentDirection === 'asc')
                                        <path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z"/>
                                    @else
                                        <path fill-rule="evenodd" d="M8 1a.5.5 0 0 0-.5.5v11.793l-3.146-3.147a.5.5 0 0 0-.708.708l4 4a.5.5 0 0 0 .708 0l4-4a.5.5 0 0 0-.708-.708L8.5 13.293V1.5A.5.5 0 0 0 8 1z"/>
                                    @endif
                                </svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="text-muted opacity-25" viewBox="0 0 16 16" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M11.5 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L11 2.707V14.5a.5.5 0 0 0 .5.5zm-7-14a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L4 13.293V1.5a.5.5 0 0 1 .5-.5z"/>
                                </svg>
                            @endif
                        </a>
                    </th>

                    {{-- Kamar --}}
                    <th scope="col" style="width: 15%;">
                        <a href="{{ $buildSortUrl('room_number') }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1" title="Urutkan berdasarkan nomor kamar">
                            <span class="fw-bold">Kamar</span>
                            @if($currentSort === 'room_number')
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" class="text-teal" viewBox="0 0 16 16" aria-label="Urutan {{ $currentDirection === 'asc' ? 'naik' : 'turun' }}">
                                    @if($currentDirection === 'asc')
                                        <path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z"/>
                                    @else
                                        <path fill-rule="evenodd" d="M8 1a.5.5 0 0 0-.5.5v11.793l-3.146-3.147a.5.5 0 0 0-.708.708l4 4a.5.5 0 0 0 .708 0l4-4a.5.5 0 0 0-.708-.708L8.5 13.293V1.5A.5.5 0 0 0 8 1z"/>
                                    @endif
                                </svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="text-muted opacity-25" viewBox="0 0 16 16" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M11.5 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L11 2.707V14.5a.5.5 0 0 0 .5.5zm-7-14a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L4 13.293V1.5a.5.5 0 0 1 .5-.5z"/>
                                </svg>
                            @endif
                        </a>
                    </th>

                    {{-- Penghuni (Admin / Owner Only) --}}
                    @if(!$isResident)
                        <th scope="col" style="width: 20%;">
                            <a href="{{ $buildSortUrl('resident_name') }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1" title="Urutkan berdasarkan nama penghuni">
                                <span class="fw-bold">Penghuni</span>
                                @if($currentSort === 'resident_name')
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" class="text-teal" viewBox="0 0 16 16" aria-label="Urutan {{ $currentDirection === 'asc' ? 'naik' : 'turun' }}">
                                        @if($currentDirection === 'asc')
                                            <path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z"/>
                                        @else
                                            <path fill-rule="evenodd" d="M8 1a.5.5 0 0 0-.5.5v11.793l-3.146-3.147a.5.5 0 0 0-.708.708l4 4a.5.5 0 0 0 .708 0l4-4a.5.5 0 0 0-.708-.708L8.5 13.293V1.5A.5.5 0 0 0 8 1z"/>
                                        @endif
                                    </svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="text-muted opacity-25" viewBox="0 0 16 16" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M11.5 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L11 2.707V14.5a.5.5 0 0 0 .5.5zm-7-14a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L4 13.293V1.5a.5.5 0 0 1 .5-.5z"/>
                                    </svg>
                                @endif
                            </a>
                        </th>
                    @endif

                    {{-- Nominal --}}
                    <th scope="col" style="width: 15%;">
                        <a href="{{ $buildSortUrl('amount') }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1" title="Urutkan berdasarkan nominal tagihan">
                            <span class="fw-bold">Nominal</span>
                            @if($currentSort === 'amount')
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" class="text-teal" viewBox="0 0 16 16" aria-label="Urutan {{ $currentDirection === 'asc' ? 'naik' : 'turun' }}">
                                    @if($currentDirection === 'asc')
                                        <path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z"/>
                                    @else
                                        <path fill-rule="evenodd" d="M8 1a.5.5 0 0 0-.5.5v11.793l-3.146-3.147a.5.5 0 0 0-.708.708l4 4a.5.5 0 0 0 .708 0l4-4a.5.5 0 0 0-.708-.708L8.5 13.293V1.5A.5.5 0 0 0 8 1z"/>
                                    @endif
                                </svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="text-muted opacity-25" viewBox="0 0 16 16" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M11.5 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L11 2.707V14.5a.5.5 0 0 0 .5.5zm-7-14a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L4 13.293V1.5a.5.5 0 0 1 .5-.5z"/>
                                </svg>
                            @endif
                        </a>
                    </th>

                    {{-- Jatuh Tempo --}}
                    <th scope="col" style="width: 15%;">
                        <a href="{{ $buildSortUrl('due_on') }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1" title="Urutkan berdasarkan batas jatuh tempo">
                            <span class="fw-bold">Jatuh Tempo</span>
                            @if($currentSort === 'due_on')
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" class="text-teal" viewBox="0 0 16 16" aria-label="Urutan {{ $currentDirection === 'asc' ? 'naik' : 'turun' }}">
                                    @if($currentDirection === 'asc')
                                        <path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z"/>
                                    @else
                                        <path fill-rule="evenodd" d="M8 1a.5.5 0 0 0-.5.5v11.793l-3.146-3.147a.5.5 0 0 0-.708.708l4 4a.5.5 0 0 0 .708 0l4-4a.5.5 0 0 0-.708-.708L8.5 13.293V1.5A.5.5 0 0 0 8 1z"/>
                                    @endif
                                </svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="text-muted opacity-25" viewBox="0 0 16 16" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M11.5 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L11 2.707V14.5a.5.5 0 0 0 .5.5zm-7-14a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L4 13.293V1.5a.5.5 0 0 1 .5-.5z"/>
                                </svg>
                            @endif
                        </a>
                    </th>

                    {{-- Status --}}
                    <th scope="col" class="text-center" style="width: 15%;">Status</th>

                    {{-- Aksi --}}
                    <th scope="col" class="text-end pe-3" style="width: 10%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    <tr>
                        {{-- Periode Tagihan --}}
                        <td class="ps-3">
                            <span class="fw-bold text-dark d-block">
                                {{ $invoice->period_month ? $invoice->period_month->translatedFormat('F Y') : '-' }}
                            </span>
                            <span class="small text-muted">
                                #INV-{{ str_pad($invoice->id, 5, '0', STR_PAD_LEFT) }}
                            </span>
                        </td>

                        {{-- Kamar Snapshot --}}
                        <td>
                            <span class="fw-semibold text-dark d-block">
                                Kamar {{ $invoice->room_number_snapshot }}
                            </span>
                            @if($invoice->placement?->room)
                                <span class="badge bg-light text-secondary border small">
                                    {{ ucfirst($invoice->placement->room->type ?? 'Standard') }}
                                </span>
                            @endif
                        </td>

                        {{-- Penghuni Snapshot (Admin / Owner Only) --}}
                        @if(!$isResident)
                            <td>
                                <div class="fw-medium text-dark">{{ $invoice->resident_name_snapshot }}</div>
                                @if($invoice->placement && $invoice->placement->isEnded())
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 small">
                                        Penempatan Selesai
                                    </span>
                                @endif
                            </td>
                        @endif

                        {{-- Nominal --}}
                        <td>
                            <span class="fw-bold text-dark">
                                Rp {{ number_format($invoice->amount, 0, ',', '.') }}
                            </span>
                        </td>

                        {{-- Jatuh Tempo --}}
                        <td>
                            <span class="d-block text-dark fw-medium">
                                {{ $invoice->due_on ? $invoice->due_on->translatedFormat('d M Y') : '-' }}
                            </span>
                            @if(!$invoice->isPaid())
                                @if($invoice->isOverdue($businessDate))
                                    <small class="text-danger fw-semibold">Terlewat</small>
                                @elseif($invoice->isDueToday($businessDate))
                                    <small class="text-warning fw-semibold">Hari Ini</small>
                                @endif
                            @endif
                        </td>

                        {{-- Status Badge --}}
                        <td class="text-center">
                            @if($invoice->isPaid())
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                    Lunas
                                </span>
                            @elseif($invoice->isOverdue($businessDate))
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                    Terlambat
                                </span>
                            @elseif($invoice->isDueToday($businessDate))
                                <span class="badge bg-warning bg-opacity-25 text-dark border border-warning px-2 py-1">
                                    Jatuh Tempo Hari Ini
                                </span>
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">
                                    Belum Jatuh Tempo
                                </span>
                            @endif
                        </td>

                        {{-- Aksi --}}
                        <td class="text-end pe-3">
                            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary" title="Lihat Detail Tagihan">
                                Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isResident ? '6' : '7' }}" class="text-center py-5 text-muted">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="mb-2 text-secondary opacity-50" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                                <path d="M4.5 8a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5z"/>
                            </svg>
                            <p class="mb-1 fw-semibold">Tidak ada data tagihan ditemukan.</p>
                            <small class="text-muted">Coba ubah kata kunci atau sesuaikan filter pencarian di atas.</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($invoices->hasPages())
        <div class="card-footer bg-white border-top-0 py-3">
            <div class="d-flex justify-content-center">
                {{ $invoices->links() }}
            </div>
        </div>
    @endif
</div>

{{-- Modal Sinkronisasi Tagihan (Khusus Admin) --}}
@if($isAdmin)
    <div class="modal fade" id="syncInvoicesModal" tabindex="-1" aria-labelledby="syncInvoicesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="syncInvoicesModalLabel">Pratinjau Sinkronisasi Tagihan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    {{-- State: Loading --}}
                    <div id="syncLoadingState" class="text-center py-4">
                        <div class="spinner-border text-teal mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Memuat pratinjau...</span>
                        </div>
                        <p class="text-muted fw-medium mb-0">Menghitung cakupan tagihan dan estimasi invoice baru...</p>
                    </div>

                    {{-- State: Error --}}
                    <div id="syncErrorState" class="d-none alert alert-danger mb-0" role="alert">
                        <h6 class="alert-heading fw-bold mb-1">Gagal Memuat Pratinjau</h6>
                        <p id="syncErrorMessage" class="mb-0 small"></p>
                    </div>

                    {{-- State: Loaded --}}
                    <div id="syncLoadedState" class="d-none">
                        {{-- Ringkasan Estimasi --}}
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-4">
                                <div class="p-3 bg-light rounded border text-center">
                                    <span class="text-muted small d-block">Invoice Akan Diterbitkan</span>
                                    <span class="fs-4 fw-bold text-dark" id="previewMissingCount">0</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="p-3 bg-light rounded border text-center">
                                    <span class="text-muted small d-block">Total Nominal Baru</span>
                                    <span class="fs-4 fw-bold text-success" id="previewMissingAmount">Rp 0</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="p-3 bg-light rounded border text-center">
                                    <span class="text-muted small d-block">Penempatan Terdampak</span>
                                    <span class="fs-4 fw-bold text-dark" id="previewPlacementsCount">0</span>
                                </div>
                            </div>
                        </div>

                        {{-- Rincian Penempatan yang Belum Lengkap --}}
                        <div class="mb-3">
                            <h6 class="fw-bold text-dark mb-2">Rincian Penempatan dan Periode</h6>
                            <div class="table-responsive border rounded" style="max-height: 220px; overflow-y: auto;">
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th scope="col" class="ps-3">Kamar</th>
                                            <th scope="col">Penghuni</th>
                                            <th scope="col">Periode Kurang</th>
                                            <th scope="col" class="text-end pe-3">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewItemsTableBody">
                                        {{-- Diisi secara dinamis via textContent aman --}}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Catatan & Disclaimer Estimasi --}}
                        <div class="alert alert-info py-2 px-3 small mb-0">
                            <strong>Catatan:</strong> <span id="previewDisclaimerText">Pratinjau ini merupakan estimasi terikat waktu. Sinkronisasi akan mengevaluasi dan mengunci setiap penempatan secara mandiri. Hasil aktual akan dilaporkan setelah proses selesai.</span>
                        </div>
                    </div>
                </div>

                {{-- Form Konfirmasi Sinkronisasi --}}
                <div class="modal-footer bg-light">
                    <form method="POST" action="{{ route('invoices.sync') }}" id="syncForm" class="d-inline-flex gap-2 w-100 justify-content-end">
                        @csrf
                        <input type="hidden" name="preview_token" id="syncPreviewToken" value="">
                        <input type="hidden" name="placement_id" id="syncPlacementId" value="">

                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-teal" id="btnSubmitSync" disabled>
                            <span id="btnSubmitSyncText">Konfirmasi & Terbitkan Tagihan</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const btnOpenGlobalSync = document.getElementById('btnOpenGlobalSync');
        const syncModalEl = document.getElementById('syncInvoicesModal');
        if (!syncModalEl) return;

        const syncModal = new bootstrap.Modal(syncModalEl);
        const syncLoadingState = document.getElementById('syncLoadingState');
        const syncErrorState = document.getElementById('syncErrorState');
        const syncErrorMessage = document.getElementById('syncErrorMessage');
        const syncLoadedState = document.getElementById('syncLoadedState');

        const previewMissingCount = document.getElementById('previewMissingCount');
        const previewMissingAmount = document.getElementById('previewMissingAmount');
        const previewPlacementsCount = document.getElementById('previewPlacementsCount');
        const previewItemsTableBody = document.getElementById('previewItemsTableBody');
        const previewDisclaimerText = document.getElementById('previewDisclaimerText');

        const syncPreviewToken = document.getElementById('syncPreviewToken');
        const syncPlacementId = document.getElementById('syncPlacementId');
        const btnSubmitSync = document.getElementById('btnSubmitSync');
        const btnSubmitSyncText = document.getElementById('btnSubmitSyncText');
        const syncForm = document.getElementById('syncForm');

        let abortController = null;

        function loadPreview(placementId = null) {
            if (abortController) {
                abortController.abort();
            }
            abortController = new AbortController();

            syncLoadingState.classList.remove('d-none');
            syncErrorState.classList.add('d-none');
            syncLoadedState.classList.add('d-none');
            btnSubmitSync.disabled = true;

            const payload = {
                _token: '{{ csrf_token() }}'
            };
            if (placementId) {
                payload.placement_id = placementId;
            }

            fetch('{{ route("invoices.sync-preview") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload),
                signal: abortController.signal
            })
            .then(async response => {
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Gagal memuat pratinjau sinkronisasi.');
                }
                return data;
            })
            .then(res => {
                const d = res.data;
                syncPreviewToken.value = d.preview_token;
                syncPlacementId.value = d.placement_id || '';

                previewMissingCount.textContent = d.total_missing_invoices;
                previewMissingAmount.textContent = d.total_missing_amount_formatted;
                previewPlacementsCount.textContent = d.placements_count;
                if (d.disclaimer) {
                    previewDisclaimerText.textContent = d.disclaimer;
                }

                // Render table rows safely using textContent (prevent XSS)
                previewItemsTableBody.replaceChildren();

                if (d.items && d.items.length > 0) {
                    d.items.forEach(item => {
                        const tr = document.createElement('tr');

                        const tdRoom = document.createElement('td');
                        tdRoom.className = 'ps-3 fw-medium';
                        tdRoom.textContent = 'Kamar ' + (item.room_number || '-');
                        tr.appendChild(tdRoom);

                        const tdResident = document.createElement('td');
                        tdResident.textContent = item.resident_name || '-';
                        tr.appendChild(tdResident);

                        const tdPeriods = document.createElement('td');
                        const periods = item.missing_periods || [];
                        tdPeriods.textContent = periods.length > 0 ? periods.join(', ') : '-';
                        tr.appendChild(tdPeriods);

                        const tdAmount = document.createElement('td');
                        tdAmount.className = 'text-end pe-3 fw-semibold text-dark';
                        tdAmount.textContent = 'Rp ' + Number(item.missing_amount || 0).toLocaleString('id-ID');
                        tr.appendChild(tdAmount);

                        previewItemsTableBody.appendChild(tr);
                    });
                } else {
                    const tr = document.createElement('tr');
                    const tdEmpty = document.createElement('td');
                    tdEmpty.colSpan = 4;
                    tdEmpty.className = 'text-center py-3 text-muted';
                    tdEmpty.textContent = 'Seluruh tagihan sudah lengkap. Tidak ada invoice baru.';
                    tr.appendChild(tdEmpty);
                    previewItemsTableBody.appendChild(tr);
                }

                syncLoadingState.classList.add('d-none');
                syncLoadedState.classList.remove('d-none');

                if (d.total_missing_invoices > 0) {
                    btnSubmitSync.disabled = false;
                    btnSubmitSyncText.textContent = 'Konfirmasi & Terbitkan ' + d.total_missing_invoices + ' Tagihan';
                } else {
                    btnSubmitSync.disabled = true;
                    btnSubmitSyncText.textContent = 'Semua Tagihan Sudah Lengkap';
                }
            })
            .catch(err => {
                if (err.name === 'AbortError') return;
                syncLoadingState.classList.add('d-none');
                syncErrorMessage.textContent = err.message || 'Terjadi kesalahan saat memuat pratinjau.';
                syncErrorState.classList.remove('d-none');
                btnSubmitSync.disabled = true;
            });
        }

        if (btnOpenGlobalSync) {
            btnOpenGlobalSync.addEventListener('click', function () {
                syncModal.show();
                loadPreview(null);
            });
        }

        // Handle single placement trigger from other elements if present
        document.querySelectorAll('[data-sync-placement-id]').forEach(btn => {
            btn.addEventListener('click', function () {
                const placementId = this.getAttribute('data-sync-placement-id');
                syncModal.show();
                loadPreview(placementId);
            });
        });

        // Submit form animation
        syncForm.addEventListener('submit', function () {
            btnSubmitSync.disabled = true;
            btnSubmitSyncText.textContent = 'Memproses Sinkronisasi...';
        });
    });
    </script>
    @endpush
@endif
@endsection
