@extends('layouts.app')

@section('title', 'Detail Fasilitas: ' . $facility->code)
@section('page_title', 'Detail Fasilitas: ' . $facility->code)
@section('page_subtitle', 'Informasi inventaris fasilitas, lokasi penempatan, kondisi, dan riwayat keluhan terkait.')

@section('content')
<div class="row g-3">
    {{-- Kolom Kiri: Informasi Utama & Aksi --}}
    <div class="col-12 col-lg-4">
        <div class="card simkos-card mb-3">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <span class="h6 fw-bold mb-0 text-dark">Informasi Fasilitas</span>
                @if($facility->isArchived())
                    <span class="badge bg-secondary px-2 py-1">Diarsipkan</span>
                @else
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Aktif</span>
                @endif
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="text-center pb-3 border-bottom mb-3">
                    <div class="fs-2 fw-bold text-dark font-monospace">{{ $facility->code }}</div>
                    <div class="text-muted fw-semibold">{{ $facility->name }}</div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted">Kondisi Barang:</span>
                        <span class="badge {{ $facility->condition_badge_class }} px-2 py-1">
                            {{ $facility->condition_label }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted">Tipe Lokasi:</span>
                        <span class="fw-semibold text-dark">
                            {{ $facility->location_type === 'room' ? 'Kamar Kos' : 'Area Bersama' }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted">Lokasi Spesifik:</span>
                        @if($facility->location_type === 'room')
                            <span>
                                <a href="{{ route('rooms.show', $facility->room_id) }}" class="fw-bold text-teal text-decoration-none">
                                    Kamar {{ $facility->room?->number ?? "#{$facility->room_id}" }}
                                </a>
                                @if($facility->room?->isArchived())
                                    <span class="badge bg-secondary ms-1" style="font-size: 0.68rem;">Arsip</span>
                                @endif
                            </span>
                        @else
                            <span class="fw-semibold text-info-emphasis">
                                {{ $facility->area_name }}
                            </span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Status Arsip:</span>
                        <span class="fw-semibold">
                            {{ $facility->isArchived() ? 'Diarsipkan (' . $facility->archived_at?->timezone('Asia/Jakarta')->format('d/m/Y') . ')' : 'Aktif' }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Total Riwayat Keluhan:</span>
                        <span class="fw-semibold font-monospace">
                            {{ $facility->complaints->count() }} laporan
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Didaftarkan Pada:</span>
                        <span>{{ $facility->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Pembaruan Terakhir:</span>
                        <span>{{ $facility->updated_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</span>
                    </li>
                </ul>

                @if($facility->notes)
                    <div class="mt-3 pt-3 border-top">
                        <div class="fw-bold text-dark small mb-1">Catatan Tambahan:</div>
                        <p class="text-muted small mb-0 bg-light p-2 rounded">
                            {{ $facility->notes }}
                        </p>
                    </div>
                @endif
            </div>

            {{-- Panel Aksi Master --}}
            <div class="card-footer bg-white p-3 border-top d-flex flex-column gap-2">
                <div class="d-flex gap-2">
                    <a href="{{ route('facilities.index') }}" class="btn btn-sm btn-outline-secondary flex-grow-1">
                        &larr; Daftar Fasilitas
                    </a>
                    @can('update', $facility)
                        <a href="{{ route('facilities.edit', $facility) }}" class="btn btn-sm btn-outline-primary flex-grow-1">
                            Ubah Fasilitas
                        </a>
                    @endcan
                </div>

                {{-- Arsip / Buka Arsip (Admin Only) --}}
                @can('archive', $facility)
                    @if(!$facility->isArchived())
                        @if($facility->hasActiveComplaints())
                            <div class="alert alert-warning py-2 px-3 small mb-0 text-center">
                                <strong>Arsip Tidak Tersedia:</strong><br>
                                Fasilitas memiliki keluhan yang masih berstatus terbuka atau sedang diproses.
                            </div>
                        @else
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-warning text-dark w-100"
                                data-bs-toggle="modal"
                                data-bs-target="#archiveModal"
                            >
                                Arsipkan Fasilitas Ini
                            </button>
                        @endif
                    @else
                        <form method="POST" action="{{ route('facilities.unarchive', $facility) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success w-100">
                                Buka Arsip (Aktifkan Fasilitas)
                            </button>
                        </form>
                    @endif
                @endcan

                {{-- Hapus Permanen (Admin Only) --}}
                @can('delete', $facility)
                    @if($facility->hasHistoricalReferences())
                        <div class="alert alert-secondary py-2 px-3 small mb-0 text-center">
                            <strong>Hapus Fisik Tidak Tersedia:</strong><br>
                            Fasilitas memiliki riwayat keluhan sehingga dilindungi integritas referensi (FK RESTRICT). Gunakan fitur arsip.
                        </div>
                    @else
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger w-100"
                            data-bs-toggle="modal"
                            data-bs-target="#deleteModal"
                        >
                            Hapus Permanen Fasilitas Ini
                        </button>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    {{-- Kolom Kanan: Riwayat Keluhan Terkait --}}
    <div class="col-12 col-lg-8">
        <div class="card simkos-card mb-3">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <span class="h6 fw-bold mb-0 text-dark">
                    Riwayat Keluhan Terkait
                    <span class="text-muted fw-normal small">({{ $facility->complaints->count() }} keluhan tercatat)</span>
                </span>
            </div>
            <div class="card-body p-0">
                @if($facility->complaints->isEmpty())
                    <div class="p-4 text-center">
                        <div class="text-muted small">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="text-secondary opacity-50 mb-2" viewBox="0 0 16 16">
                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                            </svg>
                            <p class="mb-0 fw-semibold text-dark">Belum Ada Riwayat Keluhan</p>
                            <span class="text-muted">Fasilitas ini belum pernah dilaporkan mengalami gangguan atau kerusakan fisik.</span>
                        </div>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="ps-3 py-2">ID</th>
                                    <th scope="col" class="py-2">Subjek &amp; Deskripsi</th>
                                    <th scope="col" class="py-2">Pelapor / Kamar</th>
                                    <th scope="col" class="py-2 text-center">Status</th>
                                    <th scope="col" class="pe-3 py-2 text-end">Tanggal Lapor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($facility->complaints as $complaint)
                                    <tr>
                                        <td class="ps-3 font-monospace text-muted">
                                            #{{ $complaint->id }}
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $complaint->subject }}</div>
                                            <div class="text-muted text-truncate" style="max-width: 280px;">
                                                {{ $complaint->description }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $complaint->submitter?->name ?? 'Pengguna' }}</div>
                                            @if($complaint->placement?->room)
                                                <small class="text-muted">Kamar {{ $complaint->placement->room->number }}</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($complaint->status === 'open')
                                                <span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-25 px-2 py-1">
                                                    Terbuka
                                                </span>
                                            @elseif($complaint->status === 'in_progress')
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                                    Diproses
                                                </span>
                                            @elseif($complaint->status === 'resolved')
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                                    Selesai
                                                </span>
                                            @elseif($complaint->status === 'closed_without_action')
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">
                                                    Ditutup
                                                </span>
                                            @endif
                                        </td>
                                        <td class="pe-3 text-end text-muted">
                                            {{ $complaint->created_at?->timezone('Asia/Jakarta')->format('d/m/Y') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Kartu Panduan Integritas --}}
        <div class="card simkos-card">
            <div class="card-body p-3 small text-muted">
                <div class="fw-bold text-dark mb-1">Panduan Kebijakan &amp; Integritas Data Fasilitas:</div>
                <ul class="mb-0 ps-3">
                    <li><strong>Imutabilitas Lokasi Berhistori:</strong> Fasilitas yang pernah memiliki keluhan tidak dapat dipindahkan lokasinya demi keabsahan data riwayat teknis.</li>
                    <li><strong>Proteksi Referensi (FK RESTRICT):</strong> Fasilitas dengan riwayat keluhan tidak dapat dihapus secara fisik; gunakan fitur arsip jika barang sudah tidak aktif.</li>
                    <li><strong>Pengarsipan Bersyarat:</strong> Pengarsipan hanya dapat dilakukan jika seluruh keluhan terkait telah diselesaikan atau ditutup.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Modal Konfirmasi Arsip --}}
@can('archive', $facility)
    @if(!$facility->isArchived() && $facility->canBeArchived())
        <div class="modal fade" id="archiveModal" tabindex="-1" aria-labelledby="archiveModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered text-start">
                <div class="modal-content">
                    <div class="modal-header border-bottom-0 pb-0">
                        <h5 class="modal-title h6 fw-bold text-dark" id="archiveModalLabel">
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
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered text-start">
                <div class="modal-content">
                    <div class="modal-header border-bottom-0 pb-0">
                        <h5 class="modal-title h6 fw-bold text-danger" id="deleteModalLabel">
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
@endsection
