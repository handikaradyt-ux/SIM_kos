@extends('layouts.app')

@section('title', auth()->user()?->role?->code === 'owner' ? 'Data Fasilitas' : 'Master Fasilitas')
@section('page_title', auth()->user()?->role?->code === 'owner' ? 'Data Fasilitas' : 'Master Fasilitas')
@section('page_subtitle', 'Kelola inventaris fasilitas kos, kondisi barang, penempatan kamar/area bersama, dan pengarsipan.')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card simkos-card">
            <div class="card-body p-3 p-md-4">
                {{-- Form Filter dan Pencarian --}}
                <form method="GET" action="{{ route('facilities.index') }}" class="row g-2 align-items-center">
                    {{-- Pencarian Kode, Nama, Lokasi --}}
                    <div class="col-12 col-md-3">
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
                                placeholder="Cari kode, nama, lokasi..."
                                aria-label="Cari kode, nama, lokasi fasilitas"
                                aria-describedby="search-addon"
                            >
                        </div>
                    </div>

                    {{-- Filter Status Arsip (Tab) --}}
                    <div class="col-6 col-md-2">
                        <select name="tab" class="form-select form-select-sm" aria-label="Filter status arsip">
                            <option value="active" {{ $tab === 'active' ? 'selected' : '' }}>Fasilitas Aktif</option>
                            <option value="archived" {{ $tab === 'archived' ? 'selected' : '' }}>Fasilitas Diarsipkan</option>
                            <option value="all" {{ $tab === 'all' ? 'selected' : '' }}>Semua Status</option>
                        </select>
                    </div>

                    {{-- Filter Kondisi --}}
                    <div class="col-6 col-md-2">
                        <select name="condition" class="form-select form-select-sm" aria-label="Filter kondisi">
                            <option value="all" {{ $condition === 'all' ? 'selected' : '' }}>Semua Kondisi</option>
                            <option value="good" {{ $condition === 'good' ? 'selected' : '' }}>Baik</option>
                            <option value="broken" {{ $condition === 'broken' ? 'selected' : '' }}>Rusak</option>
                            <option value="repairing" {{ $condition === 'repairing' ? 'selected' : '' }}>Dalam Perbaikan</option>
                        </select>
                    </div>

                    {{-- Filter Tipe Lokasi --}}
                    <div class="col-6 col-md-2">
                        <select name="location_type" class="form-select form-select-sm" aria-label="Filter tipe lokasi">
                            <option value="all" {{ $locationType === 'all' ? 'selected' : '' }}>Semua Lokasi</option>
                            <option value="room" {{ $locationType === 'room' ? 'selected' : '' }}>Kamar</option>
                            <option value="shared" {{ $locationType === 'shared' ? 'selected' : '' }}>Area Bersama</option>
                        </select>
                    </div>

                    {{-- Filter Jumlah Per Halaman --}}
                    <div class="col-6 col-md-1">
                        <select name="per_page" class="form-select form-select-sm" aria-label="Jumlah per halaman">
                            <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10/hal</option>
                            <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25/hal</option>
                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50/hal</option>
                        </select>
                    </div>

                    {{-- Tombol Filter & Reset --}}
                    <div class="col-12 col-md-2 d-flex gap-1 justify-content-end">
                        <button type="submit" class="btn btn-sm btn-teal flex-grow-1">
                            Saring
                        </button>
                        @if($search !== '' || $tab !== 'active' || $condition !== 'all' || $locationType !== 'all' || $roomId || $perPage != 10)
                            <a href="{{ route('facilities.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter">
                                Reset
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Tabel Data Fasilitas --}}
<div class="card simkos-card">
    <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h2 class="h6 fw-bold mb-0 text-dark">
            Daftar Fasilitas
            <span class="text-muted fw-normal small">({{ $facilities->total() }} fasilitas ditemukan)</span>
        </h2>

        @can('create', App\Models\Facility::class)
            <a href="{{ route('facilities.create') }}" class="btn btn-sm btn-teal d-inline-flex align-items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                </svg>
                <span>Tambah Fasilitas</span>
            </a>
        @endcan
    </div>

    <div class="card-body p-0">
        @if($facilities->isEmpty())
            <div class="p-4">
                <x-empty-state
                    title="Tidak Ada Fasilitas Ditemukan"
                    description="Tidak ada data fasilitas yang sesuai dengan kriteria penyaringan yang Anda pilih."
                >
                    @can('create', App\Models\Facility::class)
                        <a href="{{ route('facilities.create') }}" class="btn btn-sm btn-teal">
                            Tambah Fasilitas Pertama
                        </a>
                    @endcan
                </x-empty-state>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-3 py-3" style="width: 130px;">Kode</th>
                            <th scope="col" class="py-3">Nama Fasilitas</th>
                            <th scope="col" class="py-3">Lokasi</th>
                            <th scope="col" class="py-3 text-center">Kondisi</th>
                            <th scope="col" class="py-3 text-center">Status Arsip</th>
                            <th scope="col" class="py-3 text-center">Riwayat Keluhan</th>
                            <th scope="col" class="pe-3 py-3 text-end" style="min-width: 200px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($facilities as $facility)
                            <tr class="{{ $facility->isArchived() ? 'table-secondary text-muted' : '' }}">
                                {{-- Kode Fasilitas --}}
                                <td class="ps-3 fw-bold font-monospace text-dark">
                                    <a href="{{ route('facilities.show', $facility) }}" class="text-decoration-none text-dark fw-bold">
                                        {{ $facility->code }}
                                    </a>
                                </td>

                                {{-- Nama Fasilitas --}}
                                <td>
                                    <div class="fw-semibold text-dark">{{ $facility->name }}</div>
                                    @if($facility->notes)
                                        <small class="text-muted text-truncate d-block" style="max-width: 250px;">
                                            {{ $facility->notes }}
                                        </small>
                                    @endif
                                </td>

                                {{-- Lokasi --}}
                                <td>
                                    @if($facility->location_type === 'room')
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                            Kamar {{ $facility->room?->number ?? ($facility->room_id ? "#{$facility->room_id}" : '-') }}
                                        </span>
                                        @if($facility->room?->isArchived())
                                            <span class="badge bg-secondary text-white ms-1" style="font-size: 0.7rem;">Kamar Arsip</span>
                                        @endif
                                    @else
                                        <span class="badge bg-info bg-opacity-10 text-info-emphasis border border-info border-opacity-25 px-2 py-1">
                                            Bersama: {{ $facility->area_name }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Kondisi --}}
                                <td class="text-center">
                                    <span class="badge {{ $facility->condition_badge_class }} px-2 py-1">
                                        {{ $facility->condition_label }}
                                    </span>
                                </td>

                                {{-- Status Arsip --}}
                                <td class="text-center">
                                    @if($facility->isArchived())
                                        <span class="badge bg-secondary px-2 py-1" title="Diarsipkan pada {{ $facility->archived_at?->format('d/m/Y H:i') }}">
                                            Diarsipkan
                                        </span>
                                    @else
                                        <span class="badge bg-light text-secondary border px-2 py-1">
                                            Aktif
                                        </span>
                                    @endif
                                </td>

                                {{-- Referensi Keluhan --}}
                                <td class="text-center">
                                    @if($facility->hasHistoricalReferences())
                                        @if($facility->hasActiveComplaints())
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                Ada Keluhan Aktif
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border">
                                                Ada Riwayat
                                            </span>
                                        @endif
                                    @else
                                        <span class="badge bg-light text-muted border">
                                            Tidak Ada
                                        </span>
                                    @endif
                                </td>

                                {{-- Tombol Aksi --}}
                                <td class="pe-3 text-end">
                                    <div class="d-flex flex-column align-items-end gap-1">
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Aksi fasilitas {{ $facility->code }}">
                                            {{-- Detail --}}
                                            <a href="{{ route('facilities.show', $facility) }}" class="btn btn-outline-secondary" title="Lihat detail fasilitas {{ $facility->code }}">
                                                Detail
                                            </a>

                                            {{-- Edit (Admin Only) --}}
                                            @can('update', $facility)
                                                <a href="{{ route('facilities.edit', $facility) }}" class="btn btn-outline-primary" title="Ubah fasilitas {{ $facility->code }}">
                                                    Edit
                                                </a>
                                            @endcan

                                            {{-- Arsip / Buka Arsip (Admin Only) --}}
                                            @can('archive', $facility)
                                                @if(!$facility->isArchived())
                                                    @if($facility->hasActiveComplaints())
                                                        <button type="button" class="btn btn-outline-secondary text-muted" disabled>
                                                            Arsip
                                                        </button>
                                                    @else
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-warning text-dark"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#archiveModal-{{ $facility->id }}"
                                                        >
                                                            Arsip
                                                        </button>
                                                    @endif
                                                @else
                                                    <form method="POST" action="{{ route('facilities.unarchive', $facility) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-success">
                                                            Aktifkan
                                                        </button>
                                                    </form>
                                                @endif
                                            @endcan

                                            {{-- Hapus Permanen (Admin Only) --}}
                                            @can('delete', $facility)
                                                @if($facility->hasHistoricalReferences())
                                                    <button type="button" class="btn btn-outline-secondary text-muted" disabled>
                                                        Hapus
                                                    </button>
                                                @else
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteModal-{{ $facility->id }}"
                                                    >
                                                        Hapus
                                                    </button>
                                                @endif
                                            @endcan
                                        </div>

                                        {{-- Alasan tindakan tidak tersedia ditampilkan sebagai teks terbaca nyata di desktop dan mobile --}}
                                        @can('delete', $facility)
                                            @if($facility->hasHistoricalReferences())
                                                <span class="badge bg-secondary-subtle text-secondary border small" style="font-size: 0.72rem;">
                                                    Hapus terkunci: Memiliki riwayat keluhan
                                                </span>
                                            @endif
                                        @endcan
                                        @can('archive', $facility)
                                            @if(!$facility->isArchived() && $facility->hasActiveComplaints())
                                                <span class="badge bg-warning-subtle text-warning-emphasis border small" style="font-size: 0.72rem;">
                                                    Arsip ditolak: Keluhan aktif belum selesai
                                                </span>
                                            @endif
                                        @endcan
                                    </div>

                                    {{-- Modal Konfirmasi Arsip --}}
                                    @can('archive', $facility)
                                        @if(!$facility->isArchived() && $facility->canBeArchived())
                                            <div class="modal fade" id="archiveModal-{{ $facility->id }}" tabindex="-1" aria-labelledby="archiveModalLabel-{{ $facility->id }}" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered text-start">
                                                    <div class="modal-content">
                                                        <div class="modal-header border-bottom-0 pb-0">
                                                            <h5 class="modal-title h6 fw-bold text-dark" id="archiveModalLabel-{{ $facility->id }}">
                                                                Konfirmasi Pengarsipan Fasilitas
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                        </div>
                                                        <div class="modal-body py-3">
                                                            <p class="mb-2">
                                                                Apakah Anda yakin ingin mengarsipkan fasilitas <strong>{{ $facility->code }} ({{ $facility->name }})</strong>?
                                                            </p>
                                                            <div class="alert alert-warning py-2 px-3 small mb-0">
                                                                <strong>Dampak Pengarsipan:</strong>
                                                                <ul class="mb-0 ps-3 mt-1">
                                                                    <li>Fasilitas tidak dapat dipilih lagi untuk keluhan baru.</li>
                                                                    <li>Kode fasilitas tetap dicadangkan dan tidak dapat digunakan untuk fasilitas lain.</li>
                                                                    <li>Fasilitas dapat diaktifkan kembali kapan saja melalui tombol "Aktifkan".</li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-top-0 pt-0">
                                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                            <form method="POST" action="{{ route('facilities.archive', $facility) }}">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-warning text-dark fw-semibold">
                                                                    Ya, Arsipkan Fasilitas
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endcan

                                    {{-- Modal Konfirmasi Hapus Permanen --}}
                                    @can('delete', $facility)
                                        @if($facility->canBeDeleted())
                                            <div class="modal fade" id="deleteModal-{{ $facility->id }}" tabindex="-1" aria-labelledby="deleteModalLabel-{{ $facility->id }}" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered text-start">
                                                    <div class="modal-content">
                                                        <div class="modal-header border-bottom-0 pb-0">
                                                            <h5 class="modal-title h6 fw-bold text-danger" id="deleteModalLabel-{{ $facility->id }}">
                                                                Konfirmasi Hapus Fasilitas
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                        </div>
                                                        <div class="modal-body py-3">
                                                            <p class="mb-2">
                                                                Apakah Anda yakin ingin menghapus permanen fasilitas <strong>{{ $facility->code }} ({{ $facility->name }})</strong>?
                                                            </p>
                                                            <div class="alert alert-danger py-2 px-3 small mb-0">
                                                                <strong>Peringatan Integritas:</strong>
                                                                Tindakan ini tidak dapat dibatalkan. Fasilitas hanya dapat dihapus karena belum pernah memiliki riwayat keluhan.
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-top-0 pt-0">
                                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                            <form method="POST" action="{{ route('facilities.destroy', $facility) }}">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-danger fw-semibold">
                                                                    Ya, Hapus Permanen
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Paginasi --}}
            @if($facilities->hasPages())
                <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="small text-muted">
                        Menampilkan {{ $facilities->firstItem() }} sampai {{ $facilities->lastItem() }} dari {{ $facilities->total() }} fasilitas
                    </div>
                    <div>
                        {{ $facilities->links() }}
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
