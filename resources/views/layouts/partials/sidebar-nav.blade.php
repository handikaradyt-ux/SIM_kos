@php
    $user = auth()->user();
    $role = $user?->role?->code;
    $isTempPassword = (bool) $user?->must_change_password;
@endphp

@if($isTempPassword)
    {{-- Navigasi Terbatas untuk Pengguna dengan Password Sementara --}}
    <div class="px-3 py-2">
        <div class="alert alert-warning py-2 px-3 small mb-2 text-dark" role="alert">
            <strong>Password Sementara</strong><br>
            Akses dibatasi sampai Anda mengganti password.
        </div>
    </div>

    <div class="simkos-nav-section-title">Aksi Wajib</div>
    <ul class="simkos-nav mb-3">
        <li>
            <a href="{{ route('password.change') }}" class="simkos-nav-link {{ request()->routeIs('password.change') ? 'active' : '' }}">
                <span>Ganti Password</span>
            </a>
        </li>
        <li>
            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="simkos-nav-link border-0 bg-transparent w-100 text-start text-danger" style="cursor: pointer;">
                    <span>Keluar (Logout)</span>
                </button>
            </form>
        </li>
    </ul>
@else
    {{-- Navigasi Berdasarkan Hak Akses Role --}}
    @if($role === 'admin')
        <div class="simkos-nav-section-title">Menu Utama</div>
        <ul class="simkos-nav">
            <li>
                <a href="{{ route('admin.dashboard') }}" class="simkos-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>

        <div class="simkos-nav-section-title">Operasional Kos</div>
        <ul class="simkos-nav">
            <li>
                <a href="{{ route('rooms.index') }}" class="simkos-nav-link {{ request()->routeIs('rooms.*') ? 'active' : '' }}">
                    <span>Kamar</span>
                </a>
            </li>
            <li>
                <a href="{{ route('residents.index') }}" class="simkos-nav-link {{ request()->routeIs('residents.*') ? 'active' : '' }}">
                    <span>Penghuni</span>
                </a>
            </li>
            <li>
                <a href="{{ route('facilities.index') }}" class="simkos-nav-link {{ request()->routeIs('facilities.*') ? 'active' : '' }}">
                    <span>Fasilitas</span>
                </a>
            </li>
            <li>
                <a href="{{ route('placements.index') }}" class="simkos-nav-link {{ request()->routeIs('placements.*') ? 'active' : '' }}">
                    <span>Penempatan</span>
                </a>
            </li>
        </ul>

        <div class="simkos-nav-section-title">Keuangan</div>
        <ul class="simkos-nav">
            <li>
                <span class="simkos-nav-item-disabled">
                    <span>Tagihan</span>
                    <span class="simkos-badge-unavailable">Belum tersedia</span>
                </span>
            </li>
            <li>
                <span class="simkos-nav-item-disabled">
                    <span>Pembayaran</span>
                    <span class="simkos-badge-unavailable">Belum tersedia</span>
                </span>
            </li>
        </ul>

        <div class="simkos-nav-section-title">Layanan & Sistem</div>
        <ul class="simkos-nav">
            <li>
                <span class="simkos-nav-item-disabled">
                    <span>Keluhan</span>
                    <span class="simkos-badge-unavailable">Belum tersedia</span>
                </span>
            </li>
            <li>
                <span class="simkos-nav-item-disabled">
                    <span>Laporan</span>
                    <span class="simkos-badge-unavailable">Belum tersedia</span>
                </span>
            </li>
            <li>
                <span class="simkos-nav-item-disabled">
                    <span>Audit Trail</span>
                    <span class="simkos-badge-unavailable">Belum tersedia</span>
                </span>
            </li>
        </ul>

        <div class="simkos-nav-section-title">Akun & Keamanan</div>
        <ul class="simkos-nav mb-3">
            <li>
                <a href="{{ route('password.change') }}" class="simkos-nav-link {{ request()->routeIs('password.change') ? 'active' : '' }}">
                    <span>Ganti Password</span>
                </a>
            </li>
        </ul>
    @elseif($role === 'owner')
        <div class="simkos-nav-section-title">Menu Utama</div>
        <ul class="simkos-nav">
            <li>
                <a href="{{ route('owner.dashboard') }}" class="simkos-nav-link {{ request()->routeIs('owner.dashboard') ? 'active' : '' }}">
                    <span>Dashboard Pemantauan</span>
                </a>
            </li>
        </ul>

        <div class="simkos-nav-section-title">Pemantauan Operasional</div>
        <ul class="simkos-nav">
            <li>
                <a href="{{ route('rooms.index') }}" class="simkos-nav-link {{ request()->routeIs('rooms.*') ? 'active' : '' }}">
                    <span>Data Kamar</span>
                </a>
            </li>
            <li>
                <a href="{{ route('residents.index') }}" class="simkos-nav-link {{ request()->routeIs('residents.*') ? 'active' : '' }}">
                    <span>Data Penghuni</span>
                </a>
            </li>
            <li>
                <a href="{{ route('facilities.index') }}" class="simkos-nav-link {{ request()->routeIs('facilities.*') ? 'active' : '' }}">
                    <span>Data Fasilitas</span>
                </a>
            </li>
            <li>
                <a href="{{ route('placements.index') }}" class="simkos-nav-link {{ request()->routeIs('placements.*') ? 'active' : '' }}">
                    <span>Data Penempatan</span>
                </a>
            </li>
        </ul>

        <div class="simkos-nav-section-title">Pemantauan Finansial & Layanan</div>
        <ul class="simkos-nav">
            <li>
                <span class="simkos-nav-item-disabled">
                    <span>Status Tagihan</span>
                    <span class="simkos-badge-unavailable">Belum tersedia</span>
                </span>
            </li>
            <li>
                <span class="simkos-nav-item-disabled">
                    <span>Riwayat Pembayaran</span>
                    <span class="simkos-badge-unavailable">Belum tersedia</span>
                </span>
            </li>
            <li>
                <span class="simkos-nav-item-disabled">
                    <span>Keluhan Fasilitas</span>
                    <span class="simkos-badge-unavailable">Belum tersedia</span>
                </span>
            </li>
            <li>
                <span class="simkos-nav-item-disabled">
                    <span>Laporan Pendapatan</span>
                    <span class="simkos-badge-unavailable">Belum tersedia</span>
                </span>
            </li>
            <li>
                <span class="simkos-nav-item-disabled">
                    <span>Log Aktivitas</span>
                    <span class="simkos-badge-unavailable">Belum tersedia</span>
                </span>
            </li>
        </ul>

        <div class="simkos-nav-section-title">Akun & Keamanan</div>
        <ul class="simkos-nav mb-3">
            <li>
                <a href="{{ route('password.change') }}" class="simkos-nav-link {{ request()->routeIs('password.change') ? 'active' : '' }}">
                    <span>Ganti Password</span>
                </a>
            </li>
        </ul>
    @elseif($role === 'resident')
        <div class="simkos-nav-section-title">Menu Utama</div>
        <ul class="simkos-nav">
            <li>
                <a href="{{ route('resident.portal') }}" class="simkos-nav-link {{ request()->routeIs('resident.portal') ? 'active' : '' }}">
                    <span>Portal Penghuni</span>
                </a>
            </li>
        </ul>

        <div class="simkos-nav-section-title">Layanan Mandiri</div>
        <ul class="simkos-nav">
            <li>
                <span class="simkos-nav-item-disabled">
                    <span>Pembayaran Saya</span>
                    <span class="simkos-badge-unavailable">Belum tersedia</span>
                </span>
            </li>
            <li>
                <span class="simkos-nav-item-disabled">
                    <span>Keluhan Saya</span>
                    <span class="simkos-badge-unavailable">Belum tersedia</span>
                </span>
            </li>
        </ul>

        <div class="simkos-nav-section-title">Akun & Keamanan</div>
        <ul class="simkos-nav mb-3">
            <li>
                <a href="{{ route('password.change') }}" class="simkos-nav-link {{ request()->routeIs('password.change') ? 'active' : '' }}">
                    <span>Ganti Password</span>
                </a>
            </li>
        </ul>
    @endif
@endif
