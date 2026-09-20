@extends('layouts.app')

@section('title', 'Detail Kamar ' . $room->number)
@section('page_title', 'Detail Kamar ' . $room->number)
@section('page_subtitle', 'Informasi lengkap spesifikasi kamar, fasilitas terpasang, dan riwayat penempatan.')

@section('content')
<div class="row g-3">
    {{-- Kolom Kiri: Informasi Utama & Status --}}
    <div class="col-12 col-lg-4">
        <div class="card simkos-card mb-3">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <span class="h6 fw-bold mb-0 text-dark">Informasi Kamar</span>
                @if($room->is_archived)
                    <span class="badge bg-secondary px-2 py-1">Diarsipkan</span>
                @else
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Aktif</span>
                @endif
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="text-center pb-3 border-bottom mb-3">
                    <div class="fs-1 fw-bold text-dark font-monospace">{{ $room->number }}</div>
                    <div class="text-muted small">{{ $room->type }}</div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Tarif Sewa:</span>
                        <span class="fw-bold font-monospace text-dark">
                            Rp {{ number_format($room->monthly_rate, 0, ',', '.') }} / bulan
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted">Status Hunian:</span>
                        @if($room->is_occupied)
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                Terisi
                            </span>
                        @else
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                Kosong
                            </span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Status Arsip:</span>
                        <span class="fw-semibold">
                            {{ $room->is_archived ? 'Diarsipkan (' . $room->archived_at?->format('d/m/Y') . ')' : 'Aktif' }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Dibuat Pada:</span>
                        <span>{{ $room->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Pembaruan Terakhir:</span>
                        <span>{{ $room->updated_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</span>
                    </li>
                </ul>

                @if($room->notes)
                    <div class="mt-3 pt-3 border-top">
                        <div class="fw-bold text-dark small mb-1">Catatan Kamar:</div>
                        <p class="text-muted small mb-0 bg-light p-2 rounded">
                            {{ $room->notes }}
                        </p>
                    </div>
                @endif
            </div>

            {{-- Tombol Tindakan Admin --}}
            <div class="card-footer bg-white p-3 border-top d-flex flex-column gap-2">
                <div class="d-flex gap-2">
                    <a href="{{ route('rooms.index') }}" class="btn btn-sm btn-outline-secondary flex-grow-1">
                        &larr; Daftar Kamar
                    </a>
                    @can('update', $room)
                        <a href="{{ route('rooms.edit', $room) }}" class="btn btn-sm btn-outline-primary flex-grow-1">
                            Ubah Kamar
                        </a>
                    @endcan
                </div>

                @can('archive', $room)
                    @if(!$room->is_archived)
                        @if($room->is_occupied)
                            <div class="alert alert-secondary py-1 px-2 small mb-0 text-center" title="Kamar sedang dihuni">
                                Kamar terisi tidak dapat diarsipkan
                            </div>
                        @else
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-warning text-dark w-100"
                                data-bs-toggle="modal"
                                data-bs-target="#archiveModal"
                            >
                                Arsipkan Kamar Ini
                            </button>
                        @endif
                    @else
                        <form method="POST" action="{{ route('rooms.unarchive', $room) }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success w-100">
                                Buka Arsip (Aktifkan Kembali)
                            </button>
                        </form>
                    @endif
                @endcan

                @can('delete', $room)
                    @if($canBeDeleted)
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger w-100"
                            data-bs-toggle="modal"
                            data-bs-target="#deleteModal"
                        >
                            Hapus Permanen
                        </button>
                    @else
                        <div class="alert alert-light border py-1 px-2 small mb-0 text-muted text-center" title="Memiliki riwayat penempatan atau fasilitas">
                            Penghapusan fisik dinonaktifkan (memiliki referensi data)
                        </div>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    {{-- Kolom Kanan: Penghuni Aktif, Fasilitas, & Riwayat Penempatan --}}
    <div class="col-12 col-lg-8">
        {{-- Penempatan Aktif (Jika Terisi) --}}
        @if($room->is_occupied && $room->activePlacement)
            <div class="card simkos-card mb-3 border-primary border-opacity-25">
                <div class="card-header bg-primary bg-opacity-10 py-3">
                    <span class="h6 fw-bold mb-0 text-primary">Penempatan Aktif</span>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Nama Penghuni:</div>
                            <div class="fs-6 fw-bold text-dark">
                                {{ $room->activePlacement->resident?->name ?? 'Penghuni' }}
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Nomor Telepon:</div>
                            <div class="fw-semibold text-dark">
                                {{ $room->activePlacement->resident?->phone ?? '-' }}
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Mulai Menempati:</div>
                            <div class="fw-semibold text-dark">
                                {{ \Carbon\Carbon::parse($room->activePlacement->started_on)->format('d F Y') }}
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Tarif Kesepakatan Kontrak:</div>
                            <div class="fw-bold text-dark font-monospace">
                                Rp {{ number_format($room->activePlacement->agreed_monthly_rate, 0, ',', '.') }} / bulan
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Daftar Fasilitas Terpasang --}}
        <div class="card simkos-card mb-3">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="h6 fw-bold mb-0 text-dark">
                    Fasilitas Terpasang di Kamar
                    <span class="text-muted fw-normal small">({{ $room->facilities->count() }} item)</span>
                </span>
            </div>
            <div class="card-body p-0">
                @if($room->facilities->isEmpty())
                    <div class="p-3 text-center text-muted small">
                        Belum ada fasilitas khusus yang terdaftar di kamar ini.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 py-2">Kode</th>
                                    <th class="py-2">Nama Fasilitas</th>
                                    <th class="py-2 text-center">Kondisi</th>
                                    <th class="py-2 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($room->facilities as $facility)
                                    <tr>
                                        <td class="ps-3 fw-semibold font-monospace small">{{ $facility->code }}</td>
                                        <td>{{ $facility->name }}</td>
                                        <td class="text-center">
                                            @php
                                                $condBadge = match($facility->condition) {
                                                    'good' => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
                                                    'repairing' => 'bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25',
                                                    'broken' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
                                                    default => 'bg-secondary',
                                                };
                                                $condLabel = match($facility->condition) {
                                                    'good' => 'Baik',
                                                    'repairing' => 'Dalam Perbaikan',
                                                    'broken' => 'Rusak',
                                                    default => ucfirst($facility->condition),
                                                };
                                            @endphp
                                            <span class="badge {{ $condBadge }} px-2 py-1">
                                                {{ $condLabel }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if($facility->archived_at)
                                                <span class="badge bg-secondary px-2 py-1">Diarsipkan</span>
                                            @else
                                                <span class="badge bg-light text-secondary border px-2 py-1">Aktif</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Riwayat Penempatan Terdahulu --}}
        <div class="card simkos-card mb-3">
            <div class="card-header bg-white py-3">
                <span class="h6 fw-bold mb-0 text-dark">
                    Riwayat Penempatan Kamar
                    <span class="text-muted fw-normal small">({{ $room->placements->count() }} kali)</span>
                </span>
            </div>
            <div class="card-body p-0">
                @if($room->placements->isEmpty())
                    <div class="p-3 text-center text-muted small">
                        Belum ada riwayat penempatan pada kamar ini.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 py-2">Penghuni</th>
                                    <th class="py-2">Mulai</th>
                                    <th class="py-2">Selesai</th>
                                    <th class="py-2 text-end">Tarif Kontrak</th>
                                    <th class="py-2 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($room->placements as $placement)
                                    <tr>
                                        <td class="ps-3 fw-semibold text-dark">
                                            {{ $placement->resident?->name ?? 'Penghuni' }}
                                        </td>
                                        <td class="small">{{ \Carbon\Carbon::parse($placement->started_on)->format('d/m/Y') }}</td>
                                        <td class="small">
                                            {{ $placement->ended_on ? \Carbon\Carbon::parse($placement->ended_on)->format('d/m/Y') : 'Sekarang' }}
                                        </td>
                                        <td class="text-end font-monospace small">
                                            Rp {{ number_format($placement->agreed_monthly_rate, 0, ',', '.') }}
                                        </td>
                                        <td class="text-center">
                                            @if($placement->ended_on === null)
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                                    Aktif
                                                </span>
                                            @else
                                                <span class="badge bg-secondary px-2 py-1">
                                                    Selesai
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Modal Konfirmasi Arsip --}}
@can('archive', $room)
    @if(!$room->is_archived && !$room->is_occupied)
        <div class="modal fade" id="archiveModal" tabindex="-1" aria-labelledby="archiveModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered text-start">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title fs-6 fw-bold text-dark" id="archiveModalLabel">
                            Konfirmasi Pengarsipan Kamar
                        </h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body text-dark">
                        <p class="mb-2">
                            Apakah Anda yakin ingin mengarsipkan kamar <strong>{{ $room->number }}</strong> ({{ $room->type }})?
                        </p>
                        <div class="alert alert-warning py-2 px-3 small mb-0">
                            <strong>Dampak:</strong> Kamar yang diarsipkan tidak akan muncul dalam pilihan penempatan penghuni baru. Data historis dan fasilitas tetap tersimpan utuh.
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
    @if($canBeDeleted)
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered text-start">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title fs-6 fw-bold text-danger" id="deleteModalLabel">
                            Konfirmasi Hapus Permanen Kamar
                        </h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body text-dark">
                        <p class="mb-2">
                            Apakah Anda yakin ingin menghapus permanen kamar <strong>{{ $room->number }}</strong> ({{ $room->type }})?
                        </p>
                        <div class="alert alert-danger py-2 px-3 small mb-0">
                            <strong>Peringatan:</strong> Kamar ini belum memiliki referensi data penempatan atau fasilitas sehingga dapat dihapus permanen. Tindakan ini tidak dapat dibatalkan.
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
@endsection
