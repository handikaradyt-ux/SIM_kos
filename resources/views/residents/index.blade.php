@extends('layouts.app')

@section('title', 'Master Penghuni & Akun')
@section('page_title', 'Master Penghuni & Akun')
@section('page_subtitle', 'Kelola data identitas penghuni, pembuatan akun login, penonaktifan, dan pengarsipan.')

@section('content')
{{-- Alert Kredensial Password Sementara (Ditampilkan Sekali) --}}
@if(session('temporary_credentials'))
    <div class="alert alert-warning border-warning shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-start justify-content-between">
            <div>
                <h5 class="alert-heading fw-bold mb-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-key-fill me-2" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M3.5 11.5a3.5 3.5 0 1 1 3.163-5H14L15.5 8 14 9.5l-1-1-1 1-1-1-1 1-1.337-1.337A3.5 3.5 0 0 1 3.5 11.5M3 8a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/>
                    </svg>
                    Kredensial Akun Penghuni
                </h5>
                <p class="mb-2 text-dark">
                    Password sementara di bawah hanya ditampilkan <strong>satu kali</strong> demi keamanan. Segera salin dan serahkan kepada penghuni:
                </p>
                <div class="p-3 bg-white rounded border mb-2 font-monospace d-inline-block shadow-sm">
                    <div><strong>Nama:</strong> {{ session('temporary_credentials')['name'] }}</div>
                    <div><strong>Email:</strong> {{ session('temporary_credentials')['email'] }}</div>
                    <div><strong>Password Sementara:</strong>
                        <span id="temp-pwd-masked" class="text-danger fw-bold fs-6">••••••••••••</span>
                        <span id="temp-pwd-raw" class="text-danger fw-bold fs-6 d-none">{{ session('temporary_credentials')['password'] }}</span>
                        <button type="button" class="btn btn-sm btn-link py-0 text-decoration-none" onclick="document.getElementById('temp-pwd-masked').classList.toggle('d-none'); document.getElementById('temp-pwd-raw').classList.toggle('d-none'); this.innerText = this.innerText === 'Tampilkan' ? 'Sembunyikan' : 'Tampilkan';">
                            Tampilkan
                        </button>
                    </div>
                </div>
                <div class="mt-1">
                    <button type="button" class="btn btn-sm btn-dark" onclick="navigator.clipboard.writeText('{{ session('temporary_credentials')['password'] }}'); this.innerText = 'Password Tersalin!';">
                        Salin Password
                    </button>
                </div>
                <div class="small text-muted mt-2">
                    * Penghuni wajib mengganti password sementara ini saat pertama kali login ke sistem.
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Banner Read-Only untuk Pemilik Kos --}}
@if(auth()->user()->role?->code === 'owner')
    <div class="alert alert-info py-2 px-3 small d-flex align-items-center mb-3" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle-fill me-2 flex-shrink-0" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2"/>
        </svg>
        <span><strong>Mode Baca Saja (Pemilik):</strong> Anda dapat memantau data dan identitas seluruh penghuni serta riwayat kamar. Penambahan, pengubahan, reset password, dan pengarsipan dikelola oleh Admin.</span>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card simkos-card">
            <div class="card-body p-3 p-md-4">
                {{-- Form Filter dan Pencarian --}}
                <form method="GET" action="{{ route('residents.index') }}" class="row g-2 align-items-center">
                    {{-- Pencarian Nama, Telepon, Email --}}
                    <div class="col-12 col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted" id="search-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                                </svg>
                            </span>
                            <input
                                type="text"
                                name="q"
                                value="{{ $search }}"
                                class="form-control form-control-sm"
                                placeholder="Cari nama, telepon, email..."
                                aria-label="Cari nama, telepon, email"
                                aria-describedby="search-addon"
                            >
                        </div>
                    </div>

                    {{-- Filter Status Arsip (Tab) --}}
                    <div class="col-6 col-md-2">
                        <select name="status" class="form-select form-select-sm" aria-label="Filter status arsip">
                            <option value="active" {{ $currentStatus === 'active' ? 'selected' : '' }}>Penghuni Aktif</option>
                            <option value="archived" {{ $currentStatus === 'archived' ? 'selected' : '' }}>Diarsipkan</option>
                            <option value="all" {{ $currentStatus === 'all' ? 'selected' : '' }}>Semua Status Arsip</option>
                        </select>
                    </div>

                    {{-- Filter Status Akun --}}
                    <div class="col-6 col-md-2">
                        <select name="account_status" class="form-select form-select-sm" aria-label="Filter status akun">
                            <option value="all" {{ $currentAccountStatus === 'all' ? 'selected' : '' }}>Semua Akun</option>
                            <option value="active" {{ $currentAccountStatus === 'active' ? 'selected' : '' }}>Akun Aktif</option>
                            <option value="inactive" {{ $currentAccountStatus === 'inactive' ? 'selected' : '' }}>Akun Nonaktif</option>
                        </select>
                    </div>

                    {{-- Filter Jumlah Per Halaman --}}
                    <div class="col-6 col-md-2">
                        <select name="per_page" class="form-select form-select-sm" aria-label="Jumlah per halaman">
                            <option value="10" {{ $currentPerPage == 10 ? 'selected' : '' }}>10 / hal</option>
                            <option value="25" {{ $currentPerPage == 25 ? 'selected' : '' }}>25 / hal</option>
                            <option value="50" {{ $currentPerPage == 50 ? 'selected' : '' }}>50 / hal</option>
                        </select>
                    </div>

                    {{-- Tombol Submit Filter --}}
                    <div class="col-6 col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-primary flex-fill">
                            Terapkan
                        </button>
                        <a href="{{ route('residents.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Header Tabel & Tombol Tambah --}}
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
    {{-- Tab Navigasi Cepat Status Arsip --}}
    <ul class="nav nav-pills simkos-nav-pills">
        <li class="nav-item">
            <a class="nav-link py-1 px-3 {{ $currentStatus === 'active' ? 'active' : '' }}" href="{{ route('residents.index', array_merge(request()->except('status'), ['status' => 'active'])) }}">
                Aktif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 {{ $currentStatus === 'archived' ? 'active' : '' }}" href="{{ route('residents.index', array_merge(request()->except('status'), ['status' => 'archived'])) }}">
                Diarsipkan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 {{ $currentStatus === 'all' ? 'active' : '' }}" href="{{ route('residents.index', array_merge(request()->except('status'), ['status' => 'all'])) }}">
                Semua
            </a>
        </li>
    </ul>

    {{-- Tombol Tambah Penghuni (Admin Only) --}}
    @if(auth()->user()->role?->code === 'admin')
        <a href="{{ route('residents.create') }}" class="btn btn-sm btn-primary d-inline-flex align-items-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg me-1" viewBox="0 0 16 16" aria-hidden="true">
                <path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
            </svg>
            Tambah Penghuni
        </a>
    @endif
</div>

{{-- Tabel Data Penghuni --}}
<div class="card simkos-card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="residents-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="ps-3" style="width: 24%;">Nama Lengkap</th>
                        <th scope="col" style="width: 22%;">Akun Pengguna</th>
                        <th scope="col" style="width: 14%;">Kontak Telepon</th>
                        <th scope="col" style="width: 14%;">Penempatan</th>
                        <th scope="col" style="width: 10%;">Status Master</th>
                        <th scope="col" class="text-end pe-3" style="width: 16%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($residents as $res)
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('residents.show', $res) }}" class="fw-bold text-decoration-none text-dark d-block">
                                    {{ $res->name }}
                                </a>
                                <small class="text-muted text-truncate d-block" style="max-width: 250px;" title="{{ $res->origin_address }}">
                                    {{ $res->origin_address }}
                                </small>
                            </td>
                            <td>
                                <div class="text-dark small">{{ $res->user?->email ?? '-' }}</div>
                                <div>
                                    @if($res->user?->is_active)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2">
                                            Akun Aktif
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-1 px-2">
                                            Akun Nonaktif
                                        </span>
                                    @endif

                                    @if($res->user?->must_change_password)
                                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle py-1 px-2" title="Wajib mengganti password sementara saat login">
                                            Password Sementara
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="font-monospace small">{{ $res->phone }}</span>
                            </td>
                            <td>
                                @if($res->activePlacement)
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1 px-2">
                                        Kamar {{ $res->activePlacement->room?->number ?? '-' }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-1 px-2">
                                        Belum Ditempatkan
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($res->archived_at)
                                    <span class="badge bg-secondary py-1 px-2">
                                        Diarsipkan
                                    </span>
                                @else
                                    <span class="badge bg-success py-1 px-2">
                                        Aktif
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                {{-- Tombol Detail (Admin & Owner) --}}
                                <a href="{{ route('residents.show', $res) }}" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Detail Profil">
                                    Detail
                                </a>

                                @if(auth()->user()->role?->code === 'admin')
                                    {{-- Tombol Edit --}}
                                    <a href="{{ route('residents.edit', $res) }}" class="btn btn-sm btn-outline-primary py-0 px-2 ms-1" title="Edit Data">
                                        Edit
                                    </a>

                                    {{-- Tombol Arsipkan / Buka Arsip --}}
                                    @if($res->archived_at)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-success py-0 px-2 ms-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#unarchiveResidentModal{{ $res->id }}"
                                            title="Buka Arsip"
                                        >
                                            Buka Arsip
                                        </button>
                                    @else
                                        @if($res->canBeArchived())
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-warning py-0 px-2 ms-1 text-dark"
                                                data-bs-toggle="modal"
                                                data-bs-target="#archiveResidentModal{{ $res->id }}"
                                                title="Arsipkan Penghuni"
                                            >
                                                Arsipkan
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-warning py-0 px-2 ms-1 text-dark opacity-50"
                                                disabled
                                                title="Penghuni sedang memiliki penempatan aktif. Akhiri penempatan terlebih dahulu."
                                            >
                                                Arsipkan
                                            </button>
                                        @endif
                                    @endif

                                    {{-- Tombol Hapus Fisik --}}
                                    @if($res->canBeDeleted())
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger py-0 px-2 ms-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteResidentModal{{ $res->id }}"
                                            title="Hapus Profil"
                                        >
                                            Hapus
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger py-0 px-2 ms-1 opacity-50"
                                            disabled
                                            title="Penghuni memiliki riwayat penempatan atau keluhan dan tidak dapat dihapus fisik."
                                        >
                                            Hapus
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>

                        {{-- Modal Konfirmasi Arsipkan (Admin Only) --}}
                        @if(auth()->user()->role?->code === 'admin' && ! $res->archived_at)
                            <div class="modal fade" id="archiveResidentModal{{ $res->id }}" tabindex="-1" aria-labelledby="archiveResidentModalLabel{{ $res->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title fs-6 fw-bold" id="archiveResidentModalLabel{{ $res->id }}">Konfirmasi Pengarsipan Penghuni</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                        </div>
                                        <div class="modal-body text-start">
                                            <p class="mb-2">Apakah Anda yakin ingin mengarsipkan data penghuni <strong>{{ $res->name }}</strong>?</p>
                                            <div class="alert alert-warning py-2 px-3 small mb-0">
                                                <strong>Konsekuensi Pengarsipan:</strong>
                                                <ul class="mb-0 ps-3 mt-1">
                                                    <li>Status master penghuni akan berubah menjadi <strong>Diarsipkan</strong>.</li>
                                                    <li>Akun login (<strong>{{ $res->user?->email }}</strong>) akan otomatis <strong>dinonaktifkan</strong> dan sesi aktif dicabut.</li>
                                                    <li>Seluruh riwayat penempatan, tagihan, dan keluhan masa lalu tetap utuh.</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <form method="POST" action="{{ route('residents.archive', $res) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-warning text-dark">
                                                    Ya, Arsipkan Penghuni
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Modal Konfirmasi Buka Arsip (Admin Only) --}}
                        @if(auth()->user()->role?->code === 'admin' && $res->archived_at)
                            <div class="modal fade" id="unarchiveResidentModal{{ $res->id }}" tabindex="-1" aria-labelledby="unarchiveResidentModalLabel{{ $res->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title fs-6 fw-bold" id="unarchiveResidentModalLabel{{ $res->id }}">Konfirmasi Buka Arsip</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                        </div>
                                        <div class="modal-body text-start">
                                            <p class="mb-2">Buka arsip untuk penghuni <strong>{{ $res->name }}</strong>?</p>
                                            <div class="alert alert-info py-2 px-3 small mb-0">
                                                <strong>Dampak Tindakan:</strong> Status master penghuni akan kembali aktif dan akun pengguna login (<strong>{{ $res->user?->email }}</strong>) akan diaktifkan kembali.
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <form method="POST" action="{{ route('residents.unarchive', $res) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    Ya, Buka Arsip
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Modal Konfirmasi Hapus Fisik (Admin Only) --}}
                        @if(auth()->user()->role?->code === 'admin' && $res->canBeDeleted())
                            <div class="modal fade" id="deleteResidentModal{{ $res->id }}" tabindex="-1" aria-labelledby="deleteResidentModalLabel{{ $res->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title fs-6 fw-bold text-danger" id="deleteResidentModalLabel{{ $res->id }}">Konfirmasi Hapus Profil</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                        </div>
                                        <div class="modal-body text-start">
                                            <p class="mb-2">Apakah Anda yakin ingin menghapus profil penghuni <strong>{{ $res->name }}</strong> secara permanen?</p>
                                            <div class="alert alert-danger py-2 px-3 small mb-0">
                                                <strong>Perhatian:</strong> Profil penghuni akan dihapus permanen. Sesuai aturan audit sistem, akun pengguna login terkait (<strong>{{ $res->user?->email }}</strong>) akan <strong>dinonaktifkan</strong> untuk menjaga jejak histori audit. Tindakan ini tidak dapat dibatalkan.
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <form method="POST" action="{{ route('residents.destroy', $res) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    Ya, Hapus Profil
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-people text-muted opacity-50 mb-2" viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.022.004zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/>
                                </svg>
                                <div>Tidak ada data penghuni yang cocok dengan filter atau pencarian Anda.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Paginasi --}}
    @if($residents->hasPages())
        <div class="card-footer bg-white border-top-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="small text-muted">
                Menampilkan {{ $residents->firstItem() ?? 0 }} sampai {{ $residents->lastItem() ?? 0 }} dari {{ $residents->total() }} data penghuni
            </div>
            <div>
                {{ $residents->links('pagination::bootstrap-5') }}
            </div>
        </div>
    @endif
</div>
@endsection
