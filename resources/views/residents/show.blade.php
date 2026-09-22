@extends('layouts.app')

@section('title', 'Detail Penghuni: ' . $resident->name)
@section('page_title', 'Detail Penghuni')
@section('page_subtitle', 'Informasi identitas lengkap, akun portal, dan riwayat sewa kamar penghuni kos.')

@section('content')
{{-- Alert Kredensial Password Sementara (One-time Display) --}}
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
        <span><strong>Mode Baca Saja (Pemilik):</strong> Anda dapat melihat seluruh spesifikasi penghuni, status akun, dan histori penempatan tanpa tombol mutasi data.</span>
    </div>
@endif

<div class="row g-4 mb-4">
    {{-- Kolom Kiri: Profil & Akun Pengguna --}}
    <div class="col-12 col-lg-7">
        {{-- Kartu Profil Penghuni --}}
        <div class="card simkos-card mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title fs-6 fw-bold mb-0">Identitas Penghuni</h5>
                <div>
                    @if($resident->archived_at)
                        <span class="badge bg-secondary py-1 px-2">Diarsipkan</span>
                    @else
                        <span class="badge bg-success py-1 px-2">Master Aktif</span>
                    @endif
                </div>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="text-muted small">Nama Lengkap</div>
                        <div class="fw-bold fs-5 text-dark">{{ $resident->name }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Nomor Telepon</div>
                        <div class="font-monospace fw-semibold fs-6 text-dark">{{ $resident->phone }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Alamat Asal / KTP</div>
                        <div class="text-dark">{{ $resident->origin_address }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Terdaftar Pada</div>
                        <div class="text-dark">{{ $resident->created_at?->translatedFormat('d F Y, H:i') ?? '-' }} WIB</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Pembaruan Terakhir</div>
                        <div class="text-dark">{{ $resident->updated_at?->translatedFormat('d F Y, H:i') ?? '-' }} WIB</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kartu Riwayat Penempatan Kamar --}}
        <div class="card simkos-card mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="card-title fs-6 fw-bold mb-0">Riwayat Penempatan Kamar</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="ps-3">Kamar</th>
                                <th scope="col">Mulai Sewa</th>
                                <th scope="col">Akhir Sewa</th>
                                <th scope="col">Tarif Disepakati</th>
                                <th scope="col" class="text-end pe-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($resident->placements as $plc)
                                <tr>
                                    <td class="ps-3 fw-bold">
                                        Kamar {{ $plc->room?->number ?? '-' }}
                                        <div class="small text-muted fw-normal">{{ $plc->room?->type ?? '' }}</div>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($plc->started_on)->translatedFormat('d M Y') }}</td>
                                    <td>
                                        @if($plc->ended_on)
                                            {{ \Carbon\Carbon::parse($plc->ended_on)->translatedFormat('d M Y') }}
                                        @else
                                            <span class="text-muted fst-italic">Masih Aktif</span>
                                        @endif
                                    </td>
                                    <td class="font-monospace">
                                        Rp {{ number_format($plc->agreed_monthly_rate, 0, ',', '.') }}
                                    </td>
                                    <td class="text-end pe-3">
                                        @if($plc->ended_on === null)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2">
                                                Aktif Dihuni
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-1 px-2">
                                                Selesai
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        Penghuni ini belum pernah menempati kamar kos.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Kolom Kanan: Akun & Aksi Manajemen --}}
    <div class="col-12 col-lg-5">
        {{-- Kartu Akun Pengguna --}}
        <div class="card simkos-card mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="card-title fs-6 fw-bold mb-0">Akun Pengguna Portal</h5>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="mb-3">
                    <div class="text-muted small">Alamat Email Login</div>
                    <div class="fw-semibold text-dark fs-6">{{ $resident->user?->email ?? '-' }}</div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Peran Akses (Role)</div>
                    <div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1 px-2">
                            Penghuni (Resident)
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Status Akun Login</div>
                    <div>
                        @if($resident->user?->is_active)
                            <span class="badge bg-success py-1 px-2">Aktif (Dapat Login)</span>
                        @else
                            <span class="badge bg-danger py-1 px-2">Nonaktif (Akses Diblokir)</span>
                        @endif
                    </div>
                </div>

                <div class="mb-0">
                    <div class="text-muted small">Status Keamanan Password</div>
                    <div>
                        @if($resident->user?->must_change_password)
                            <span class="badge bg-warning-subtle text-dark border border-warning-subtle py-1 px-2">
                                Password Sementara (Wajib Diganti)
                            </span>
                        @else
                            <span class="badge bg-light text-muted border py-1 px-2">
                                Password Permanen Aktif
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Tombol Pengelolaan Akun (Admin Only) --}}
            @if(auth()->user()->role?->code === 'admin')
                <div class="card-footer bg-light p-3 d-flex flex-column gap-2">
                    {{-- Tombol Reset Password Sementara --}}
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-dark w-100 text-start d-flex justify-content-between align-items-center"
                        data-bs-toggle="modal"
                        data-bs-target="#resetPasswordModal"
                    >
                        <span>Reset Password Sementara</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-key" viewBox="0 0 16 16">
                            <path d="M0 8a4 4 0 0 1 7.465-2H14a.5.5 0 0 1 .354.146l1.5 1.5a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0L13 9.207l-.646.647a.5.5 0 0 1-.708 0L11 9.207l-.646.647a.5.5 0 0 1-.708 0L9 9.207l-.535.536A4 4 0 0 1 0 8m4-3a3 3 0 1 0 0 6 3 3 0 0 0 0-6"/>
                        </svg>
                    </button>

                    {{-- Tombol Aktifkan / Nonaktifkan Akun --}}
                    @if($resident->user?->is_active)
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger w-100 text-start d-flex justify-content-between align-items-center"
                            data-bs-toggle="modal"
                            data-bs-target="#deactivateAccountModal"
                        >
                            <span>Nonaktifkan Akun Pengguna</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-x" viewBox="0 0 16 16">
                                <path d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0M8 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m.256 7a4.5 4.5 0 0 1-.229-1.004H3c.001-.246.154-.986.832-1.664C4.484 10.68 5.711 10 8 10q.39 0 .74.025c.226-.341.496-.65.804-.918Q8.844 9.002 8 9c-5 0-6 3-6 4s1 1 1 1z"/>
                                <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m-.646-4.854.646.647.646-.647a.5.5 0 0 1 .708.708l-.647.646.647.646a.5.5 0 0 1-.708.708l-.646-.647-.646.647a.5.5 0 0 1-.708-.708l.647-.646-.647-.646a.5.5 0 0 1 .708-.708"/>
                            </svg>
                        </button>
                    @else
                        @if($resident->archived_at !== null)
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-success w-100 text-start d-flex justify-content-between align-items-center opacity-50"
                                disabled
                                title="Akun dengan profil yang diarsipkan tidak dapat diaktifkan langsung. Buka arsip terlebih dahulu."
                            >
                                <span>Aktifkan Akun Pengguna</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-check" viewBox="0 0 16 16">
                                    <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m1.679-4.493-1.335 2.226a.5.5 0 0 1-.75.111l-.82-.616a.5.5 0 0 1 .6-.8l.545.408 1.03-1.718a.5.5 0 0 1 .73-.11z"/>
                                </svg>
                            </button>
                        @else
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-success w-100 text-start d-flex justify-content-between align-items-center"
                                data-bs-toggle="modal"
                                data-bs-target="#activateAccountModal"
                            >
                                <span>Aktifkan Akun Pengguna</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-check" viewBox="0 0 16 16">
                                    <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m1.679-4.493-1.335 2.226a.5.5 0 0 1-.75.111l-.82-.616a.5.5 0 0 1 .6-.8l.545.408 1.03-1.718a.5.5 0 0 1 .73-.11z"/>
                                </svg>
                            </button>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        {{-- Kartu Aksi Master (Khusus Admin) --}}
        @if(auth()->user()->role?->code === 'admin')
            <div class="card simkos-card mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title fs-6 fw-bold mb-0">Aksi Master Penghuni</h5>
                </div>
                <div class="card-body p-3 p-md-4 d-flex flex-column gap-2">
                    {{-- Edit Profil --}}
                    <a href="{{ route('residents.edit', $resident) }}" class="btn btn-sm btn-primary">
                        Edit Data Penghuni
                    </a>

                    {{-- Arsip / Buka Arsip --}}
                    @if($resident->archived_at)
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-success"
                            data-bs-toggle="modal"
                            data-bs-target="#unarchiveResidentModal"
                        >
                            Buka Arsip Penghuni
                        </button>
                    @else
                        @if($resident->canBeArchived())
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-warning text-dark"
                                data-bs-toggle="modal"
                                data-bs-target="#archiveResidentModal"
                            >
                                Arsipkan Penghuni
                            </button>
                        @else
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-warning text-dark opacity-50"
                                disabled
                                title="Penghuni sedang menempati kamar aktif. Akhiri penempatan terlebih dahulu."
                            >
                                Arsipkan Penghuni
                            </button>
                        @endif
                    @endif

                    {{-- Hapus Fisik --}}
                    @if($resident->canBeDeleted())
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal"
                            data-bs-target="#deleteResidentModal"
                        >
                            Hapus Profil Permanen
                        </button>
                    @else
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger opacity-50"
                            disabled
                            title="Penghuni memiliki riwayat penempatan atau keluhan dan tidak dapat dihapus permanen."
                        >
                            Hapus Profil Permanen
                        </button>
                    @endif
                </div>
            </div>
        @endif

        {{-- Panel Informasi Kebijakan Hapus vs Arsip --}}
        <div class="card simkos-card">
            <div class="card-body p-3 small text-muted">
                <h6 class="fw-bold text-dark fs-6 mb-2">Panduan Kebijakan Data</h6>
                <ul class="mb-0 ps-3">
                    <li class="mb-1"><strong>Hapus Profil:</strong> Hanya diizinkan jika penghuni belum pernah ditempatkan dan belum memiliki riwayat keluhan. Akun pengguna dinonaktifkan untuk menjaga histori audit.</li>
                    <li class="mb-1"><strong>Arsip:</strong> Digunakan untuk penghuni yang pernah tinggal namun saat ini sudah tidak aktif. Penempatan aktif menghalangi pengarsipan.</li>
                    <li><strong>Status Akun:</strong> Akun nonaktif tidak dapat masuk ke sistem. Pengguna yang sedang login akan kehilangan akses pada request berikutnya.</li>
                </ul>
            </div>
        </div>

        <div class="mt-3">
            <a href="{{ route('residents.index') }}" class="btn btn-sm btn-outline-secondary w-100">
                &larr; Kembali ke Daftar Penghuni
            </a>
        </div>
    </div>
</div>

{{-- Modals Khusus Admin --}}
@if(auth()->user()->role?->code === 'admin')
    {{-- Modal Reset Password Sementara --}}
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold" id="resetPasswordModalLabel">Reset Password Sementara</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Reset password sementara untuk akun penghuni <strong>{{ $resident->name }}</strong> ({{ $resident->user?->email }})?</p>
                    <div class="alert alert-warning py-2 px-3 small mb-0">
                        <strong>Dampak Tindakan:</strong>
                        <ul class="mb-0 ps-3 mt-1">
                            <li>Sistem akan menghasilkan password sementara acak 12 karakter baru.</li>
                            <li>Seluruh sesi lama pengguna akan dicabut secara otomatis demi keamanan.</li>
                            <li>Penghuni wajib mengganti password saat pertama kali login.</li>
                            <li>Password sementara akan ditampilkan sekali di layar setelah berhasil di-reset.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" action="{{ route('residents.reset-password', $resident) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-dark">
                            Ya, Reset Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Nonaktifkan Akun --}}
    <div class="modal fade" id="deactivateAccountModal" tabindex="-1" aria-labelledby="deactivateAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold text-danger" id="deactivateAccountModalLabel">Nonaktifkan Akun Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Nonaktifkan akun pengguna untuk penghuni <strong>{{ $resident->name }}</strong> ({{ $resident->user?->email }})?</p>
                    <div class="alert alert-danger py-2 px-3 small mb-0">
                        <strong>Perhatian:</strong> Akun yang dinonaktifkan tidak akan dapat masuk ke sistem. Jika pengguna saat ini sedang login, sesi pengguna akan langsung dibatalkan pada request berikutnya.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" action="{{ route('residents.deactivate', $resident) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger">
                            Ya, Nonaktifkan Akun
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Aktifkan Akun --}}
    <div class="modal fade" id="activateAccountModal" tabindex="-1" aria-labelledby="activateAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold text-success" id="activateAccountModalLabel">Aktifkan Akun Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Aktifkan kembali akun pengguna untuk penghuni <strong>{{ $resident->name }}</strong> ({{ $resident->user?->email }})?</p>
                    <div class="alert alert-info py-2 px-3 small mb-0">
                        Pengguna akan dapat masuk kembali ke portal penghuni menggunakan email dan password yang terdaftar.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" action="{{ route('residents.activate', $resident) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                            Ya, Aktifkan Akun
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Arsipkan Penghuni --}}
    <div class="modal fade" id="archiveResidentModal" tabindex="-1" aria-labelledby="archiveResidentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold" id="archiveResidentModalLabel">Konfirmasi Pengarsipan Penghuni</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Arsipkan data master penghuni <strong>{{ $resident->name }}</strong>?</p>
                    <div class="alert alert-warning py-2 px-3 small mb-0">
                        <strong>Konsekuensi Pengarsipan:</strong>
                        <ul class="mb-0 ps-3 mt-1">
                            <li>Status master penghuni menjadi <strong>Diarsipkan</strong>.</li>
                            <li>Akun pengguna login (<strong>{{ $resident->user?->email }}</strong>) akan otomatis <strong>dinonaktifkan</strong> dan seluruh sesi aktif dicabut.</li>
                            <li>Seluruh riwayat penempatan dan keuangan masa lalu tetap utuh.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" action="{{ route('residents.archive', $resident) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning text-dark">
                            Ya, Arsipkan Penghuni
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Buka Arsip --}}
    <div class="modal fade" id="unarchiveResidentModal" tabindex="-1" aria-labelledby="unarchiveResidentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold text-success" id="unarchiveResidentModalLabel">Konfirmasi Buka Arsip</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Buka arsip penghuni <strong>{{ $resident->name }}</strong>?</p>
                    <div class="alert alert-info py-2 px-3 small mb-0">
                        Status master penghuni akan kembali aktif dan akun pengguna login (<strong>{{ $resident->user?->email }}</strong>) akan diaktifkan kembali.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" action="{{ route('residents.unarchive', $resident) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                            Ya, Buka Arsip
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Hapus Fisik --}}
    @if($resident->canBeDeleted())
        <div class="modal fade" id="deleteResidentModal" tabindex="-1" aria-labelledby="deleteResidentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold text-danger" id="deleteResidentModalLabel">Konfirmasi Hapus Profil</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Apakah Anda yakin ingin menghapus profil penghuni <strong>{{ $resident->name }}</strong> secara permanen?</p>
                        <div class="alert alert-danger py-2 px-3 small mb-0">
                            <strong>Perhatian:</strong> Profil penghuni akan dihapus permanen. Sesuai aturan audit sistem, akun pengguna login terkait (<strong>{{ $resident->user?->email }}</strong>) akan <strong>dinonaktifkan</strong> untuk menjaga jejak histori audit. Tindakan ini tidak dapat dibatalkan.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <form method="POST" action="{{ route('residents.destroy', $resident) }}" class="d-inline">
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
@endif
@endsection
