@extends('layouts.app')

@section('title', auth()->user()?->role?->code === 'owner' ? 'Data Penempatan' : 'Penempatan Kamar')
@section('page_title', auth()->user()?->role?->code === 'owner' ? 'Data Penempatan' : 'Penempatan Kamar')
@section('page_subtitle', 'Kelola penempatan penghuni kos ke kamar yang tersedia dan pemantauan riwayat sewa.')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card simkos-card">
            <div class="card-body p-3 p-md-4">
                {{-- Form Filter dan Pencarian --}}
                <form method="GET" action="{{ route('placements.index') }}" class="row g-2 align-items-center">
                    {{-- Pencarian Nama Penghuni / Nomor Kamar --}}
                    <div class="col-12 col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted" id="search-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                                </svg>
                            </span>
                            <input
                                type="text"
                                name="search"
                                value="{{ $search }}"
                                class="form-control form-control-sm"
                                placeholder="Cari nama penghuni, kontak, atau no. kamar..."
                                aria-label="Cari data penempatan"
                                aria-describedby="search-addon"
                            >
                        </div>
                    </div>

                    {{-- Filter Status Penempatan --}}
                    <div class="col-6 col-md-3">
                        <select name="status" class="form-select form-select-sm" aria-label="Filter status penempatan">
                            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif ({{ $activeCount }})</option>
                            <option value="ended" {{ $status === 'ended' ? 'selected' : '' }}>Selesai ({{ $endedCount }})</option>
                            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>Semua Riwayat ({{ $allCount }})</option>
                        </select>
                    </div>

                    {{-- Filter Jumlah Per Halaman --}}
                    <div class="col-6 col-md-2">
                        <select name="per_page" class="form-select form-select-sm" aria-label="Jumlah per halaman">
                            <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10 / hal</option>
                            <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25 / hal</option>
                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 / hal</option>
                        </select>
                    </div>

                    {{-- Tombol Filter & Reset --}}
                    <div class="col-12 col-md-2 d-flex gap-1 justify-content-end">
                        <button type="submit" class="btn btn-sm btn-teal flex-grow-1">
                            Saring
                        </button>
                        @if($search !== '' || $status !== 'active' || $perPage != 10)
                            <a href="{{ route('placements.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter">
                                Reset
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Tabel Data Penempatan --}}
<div class="card simkos-card">
    <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h2 class="h6 fw-bold mb-0 text-dark">
            Daftar Penempatan Kamar
            <span class="text-muted fw-normal small">({{ $placements->total() }} data ditemukan)</span>
        </h2>

        @can('create', App\Models\Placement::class)
            <a href="{{ route('placements.create') }}" class="btn btn-sm btn-teal d-inline-flex align-items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                </svg>
                <span>Mulai Penempatan Baru</span>
            </a>
        @endcan
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-3" style="width: 15%;">Kamar</th>
                    <th scope="col" style="width: 25%;">Penghuni</th>
                    <th scope="col" style="width: 15%;">Tanggal Mulai</th>
                    <th scope="col" style="width: 15%;">Tarif Sewa</th>
                    <th scope="col" class="text-center" style="width: 10%;">Tagihan</th>
                    <th scope="col" class="text-center" style="width: 10%;">Status</th>
                    <th scope="col" class="text-end pe-3" style="width: 10%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($placements as $placement)
                    <tr>
                        {{-- Kamar --}}
                        <td class="ps-3">
                            <span class="fw-bold text-dark d-block">Kamar {{ $placement->room?->number ?? '-' }}</span>
                            <span class="badge bg-light text-secondary border small">{{ ucfirst($placement->room?->type ?? 'Standard') }}</span>
                        </td>

                        {{-- Penghuni --}}
                        <td>
                            <div class="fw-semibold text-dark">{{ $placement->resident?->name ?? 'Penghuni Tidak Diketahui' }}</div>
                            <div class="small text-muted">{{ $placement->resident?->phone ?? '-' }}</div>
                        </td>

                        {{-- Tanggal Mulai & Selesai --}}
                        <td>
                            <div class="small text-dark fw-medium">{{ $placement->started_on?->translatedFormat('d M Y') ?? '-' }}</div>
                            @if($placement->ended_on)
                                <div class="small text-muted">s/d {{ $placement->ended_on->translatedFormat('d M Y') }}</div>
                            @else
                                <div class="small text-success">Sedang Berjalan</div>
                            @endif
                        </td>

                        {{-- Tarif Sewa --}}
                        <td>
                            <span class="fw-bold text-dark">Rp {{ number_format($placement->agreed_monthly_rate, 0, ',', '.') }}</span>
                            <span class="small text-muted d-block">/ bulan</span>
                        </td>

                        {{-- Tagihan Count --}}
                        <td class="text-center">
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">
                                {{ $placement->invoices_count }} invoice
                            </span>
                        </td>

                        {{-- Status --}}
                        <td class="text-center">
                            @if($placement->isActive())
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                    Aktif
                                </span>
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">
                                    Selesai
                                </span>
                            @endif
                        </td>

                        {{-- Aksi --}}
                        <td class="text-end pe-3">
                            <a href="{{ route('placements.show', $placement) }}" class="btn btn-sm btn-outline-primary" title="Lihat Detail Penempatan">
                                Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="mb-2 text-secondary opacity-50" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M8 1a2 2 0 0 1 2 2v2H6V3a2 2 0 0 1 2-2zm3 4V3a3 3 0 1 0-6 0v2H3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-2zM4 6h8v7H4V6z"/>
                            </svg>
                            <p class="mb-1 fw-semibold">Tidak ada data penempatan ditemukan.</p>
                            <small class="text-muted">Gunakan tombol "Mulai Penempatan Baru" untuk mendaftarkan sewa kamar baru.</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($placements->hasPages())
        <div class="card-footer bg-white border-top-0 py-3">
            <div class="d-flex justify-content-center">
                {{ $placements->links() }}
            </div>
        </div>
    @endif
</div>
@endsection
