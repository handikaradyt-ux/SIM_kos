@extends('layouts.app')

@section('title', 'Data Kamar')
@section('page_title', 'Data Kamar')
@section('page_subtitle', 'Kelola data kamar, tarif sewa bulanan, status hunian, dan pengarsipan.')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card simkos-card">
            <div class="card-body p-3 p-md-4">
                {{-- Form Filter dan Pencarian --}}
                <form method="GET" action="{{ route('rooms.index') }}" class="row g-2 align-items-center">
                    {{-- Pencarian Nomor atau Tipe --}}
                    <div class="col-12 col-md-4">
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
                                placeholder="Cari nomor atau jenis kamar..."
                                aria-label="Cari nomor atau jenis kamar"
                                aria-describedby="search-addon"
                            >
                        </div>
                    </div>

                    {{-- Filter Status Arsip (Tab) --}}
                    <div class="col-6 col-md-2">
                        <select name="tab" class="form-select form-select-sm" aria-label="Filter status arsip">
                            <option value="active" {{ $tab === 'active' ? 'selected' : '' }}>Kamar Aktif</option>
                            <option value="archived" {{ $tab === 'archived' ? 'selected' : '' }}>Kamar Diarsipkan</option>
                            <option value="all" {{ $tab === 'all' ? 'selected' : '' }}>Semua Status Arsip</option>
                        </select>
                    </div>

                    {{-- Filter Status Hunian --}}
                    <div class="col-6 col-md-2">
                        <select name="occupancy" class="form-select form-select-sm" aria-label="Filter status hunian">
                            <option value="all" {{ $occupancy === 'all' ? 'selected' : '' }}>Semua Hunian</option>
                            <option value="vacant" {{ $occupancy === 'vacant' ? 'selected' : '' }}>Kosong</option>
                            <option value="occupied" {{ $occupancy === 'occupied' ? 'selected' : '' }}>Terisi</option>
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
                    <div class="col-6 col-md-2 d-flex gap-1 justify-content-end">
                        <button type="submit" class="btn btn-sm btn-teal flex-grow-1">
                            Saring
                        </button>
                        @if($search !== '' || $tab !== 'active' || $occupancy !== 'all' || $perPage != 10)
                            <a href="{{ route('rooms.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter">
                                Reset
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Tabel Data Kamar --}}
<div class="card simkos-card">
    <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h2 class="h6 fw-bold mb-0 text-dark">
            Daftar Kamar
            <span class="text-muted fw-normal small">({{ $rooms->total() }} kamar ditemukan)</span>
        </h2>

        @can('create', App\Models\Room::class)
            <a href="{{ route('rooms.create') }}" class="btn btn-sm btn-teal d-inline-flex align-items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                </svg>
                <span>Tambah Kamar</span>
            </a>
        @endcan
    </div>

    <div class="card-body p-0">
        @if($rooms->isEmpty())
            <div class="p-4">
                <x-empty-state
                    title="Tidak Ada Kamar Ditemukan"
                    description="Tidak ada data kamar yang sesuai dengan kriteria penyaringan yang Anda pilih."
                >
                    @can('create', App\Models\Room::class)
                        <a href="{{ route('rooms.create') }}" class="btn btn-sm btn-teal">
                            Tambah Kamar Pertama
                        </a>
                    @endcan
                </x-empty-state>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-3 py-3" style="width: 140px;">No. Kamar</th>
                            <th scope="col" class="py-3">Tipe / Jenis</th>
                            <th scope="col" class="py-3 text-end">Tarif Bulanan</th>
                            <th scope="col" class="py-3 text-center">Status Hunian</th>
                            <th scope="col" class="py-3 text-center">Status Arsip</th>
                            <th scope="col" class="py-3 text-center">Fasilitas</th>
                            <th scope="col" class="pe-3 py-3 text-end" style="min-width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rooms as $room)
                            <tr class="{{ $room->is_archived ? 'table-secondary text-muted' : '' }}">
                                {{-- Nomor Kamar --}}
                                <td class="ps-3 fw-bold text-dark">
                                    <a href="{{ route('rooms.show', $room) }}" class="text-decoration-none text-dark fw-bold">
                                        {{ $room->number }}
                                    </a>
                                </td>

                                {{-- Tipe / Jenis --}}
                                <td>{{ $room->type }}</td>

                                {{-- Tarif Bulanan --}}
                                <td class="text-end font-monospace fw-semibold">
                                    Rp {{ number_format($room->monthly_rate, 0, ',', '.') }}
                                </td>

                                {{-- Status Hunian --}}
                                <td class="text-center">
                                    @if($room->is_occupied)
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                            <span class="visually-hidden">Status: </span>Terisi
                                        </span>
                                        @if($room->activePlacement?->resident)
                                            <div class="small text-muted text-truncate" style="max-width: 120px; font-size: 0.75rem;" title="{{ $room->activePlacement->resident->name }}">
                                                {{ $room->activePlacement->resident->name }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                            <span class="visually-hidden">Status: </span>Kosong
                                        </span>
                                    @endif
                                </td>

                                {{-- Status Arsip --}}
                                <td class="text-center">
                                    @if($room->is_archived)
                                        <span class="badge bg-secondary px-2 py-1" title="Diarsipkan pada {{ $room->archived_at?->format('d/m/Y H:i') }}">
                                            Diarsipkan
                                        </span>
                                    @else
                                        <span class="badge bg-light text-secondary border px-2 py-1">
                                            Aktif
                                        </span>
                                    @endif
                                </td>

                                {{-- Jumlah Fasilitas --}}
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border">
                                        {{ $room->facilities_count }} item
                                    </span>
                                </td>

                                {{-- Tombol Aksi --}}
                                <td class="pe-3 text-end">
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Aksi untuk kamar {{ $room->number }}">
                                        {{-- Detail --}}
                                        <a href="{{ route('rooms.show', $room) }}" class="btn btn-outline-secondary" title="Lihat detail kamar {{ $room->number }}">
                                            Detail
                                        </a>

                                        {{-- Edit (Admin Only) --}}
                                        @can('update', $room)
                                            <a href="{{ route('rooms.edit', $room) }}" class="btn btn-outline-primary" title="Ubah kamar {{ $room->number }}">
                                                Edit
                                            </a>
                                        @endcan

                                        {{-- Arsip / Buka Arsip (Admin Only) --}}
                                        @can('archive', $room)
                                            @if(!$room->is_archived)
                                                @if($room->is_occupied)
                                                    <button type="button" class="btn btn-outline-secondary text-muted" disabled title="Kamar sedang terisi dan tidak dapat diarsipkan">
                                                        Arsip
                                                    </button>
                                                @else
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-warning text-dark"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#archiveModal-{{ $room->id }}"
                                                        title="Arsipkan kamar {{ $room->number }}"
                                                    >
                                                        Arsip
                                                    </button>
                                                @endif
                                            @else
                                                <form method="POST" action="{{ route('rooms.unarchive', $room) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-success" title="Buka arsip kamar {{ $room->number }}">
                                                        Aktifkan
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan

                                        {{-- Hapus (Admin Only) --}}
                                        @can('delete', $room)
                                            @if($room->hasHistoricalReferences())
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-secondary text-muted"
                                                    disabled
                                                    title="Kamar memiliki referensi historis penempatan/fasilitas sehingga tidak dapat dihapus permanen"
                                                >
                                                    Hapus
                                                </button>
                                            @else
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteModal-{{ $room->id }}"
                                                    title="Hapus permanen kamar {{ $room->number }}"
                                                >
                                                    Hapus
                                                </button>
                                            @endif
                                        @endcan
                                    </div>

                                    {{-- Modal Konfirmasi Arsip --}}
                                    @can('archive', $room)
                                        @if(!$room->is_archived && !$room->is_occupied)
                                            <div class="modal fade" id="archiveModal-{{ $room->id }}" tabindex="-1" aria-labelledby="archiveModalLabel-{{ $room->id }}" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered text-start">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h3 class="modal-title fs-6 fw-bold text-dark" id="archiveModalLabel-{{ $room->id }}">
                                                                Konfirmasi Pengarsipan Kamar
                                                            </h3>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                        </div>
                                                        <div class="modal-body text-dark">
                                                            <p class="mb-2">
                                                                Apakah Anda yakin ingin mengarsipkan kamar <strong>{{ $room->number }}</strong> ({{ $room->type }})?
                                                            </p>
                                                            <div class="alert alert-warning py-2 px-3 small mb-0">
                                                                <strong>Dampak:</strong> Kamar yang diarsipkan tidak akan tersedia untuk pemilihan penempatan penghuni baru. Anda dapat mengaktifkannya kembali sewaktu-waktu.
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                                                                Batal
                                                            </button>
                                                            <form method="POST" action="{{ route('rooms.archive', $room) }}" class="d-inline m-0">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-warning text-dark">
                                                                    Ya, Arsipkan Kamar
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endcan

                                    {{-- Modal Konfirmasi Hapus --}}
                                    @can('delete', $room)
                                        @if(!$room->hasHistoricalReferences())
                                            <div class="modal fade" id="deleteModal-{{ $room->id }}" tabindex="-1" aria-labelledby="deleteModalLabel-{{ $room->id }}" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered text-start">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h3 class="modal-title fs-6 fw-bold text-danger" id="deleteModalLabel-{{ $room->id }}">
                                                                Konfirmasi Hapus Permanen Kamar
                                                            </h3>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                        </div>
                                                        <div class="modal-body text-dark">
                                                            <p class="mb-2">
                                                                Apakah Anda yakin ingin menghapus permanen kamar <strong>{{ $room->number }}</strong> ({{ $room->type }})?
                                                            </p>
                                                            <div class="alert alert-danger py-2 px-3 small mb-0">
                                                                <strong>Perhatian:</strong> Aksi ini akan menghapus data kamar dari database secara fisik. Tindakan ini tidak dapat dibatalkan.
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                                                                Batal
                                                            </button>
                                                            <form method="POST" action="{{ route('rooms.destroy', $room) }}" class="d-inline m-0">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-danger">
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

            {{-- Paginasi Bootstrap 5 --}}
            <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="small text-muted">
                    Menampilkan data ke {{ $rooms->firstItem() ?? 0 }} sampai {{ $rooms->lastItem() ?? 0 }} dari total {{ $rooms->total() }} kamar.
                </div>
                <div>
                    {{ $rooms->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
