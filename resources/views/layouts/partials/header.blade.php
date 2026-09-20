<header class="simkos-topbar">
    <div class="d-flex align-items-center gap-2">
        {{-- Tombol Toggle Menu Mobile (< 992px) --}}
        <button
            class="btn btn-sm btn-outline-secondary d-lg-none"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#simkosOffcanvasSidebar"
            aria-controls="simkosOffcanvasSidebar"
            aria-label="Buka menu navigasi"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>

        <div>
            <h1 class="simkos-page-title">@yield('page_title', 'Dashboard')</h1>
            @hasSection('page_subtitle')
                <p class="simkos-page-subtitle">@yield('page_subtitle')</p>
            @endif
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        {{-- Identitas Pengguna & Role --}}
        <div class="d-none d-sm-block text-end">
            <div class="fw-semibold small text-dark">{{ auth()->user()->name }}</div>
            <div>
                @php
                    $roleCode = auth()->user()->role?->code ?? 'user';
                    $roleLabel = match($roleCode) {
                        'admin' => 'Admin',
                        'owner' => 'Pemilik',
                        'resident' => 'Penghuni',
                        default => ucfirst($roleCode),
                    };
                @endphp
                <span class="badge badge-role-{{ $roleCode }}">
                    {{ $roleLabel }}
                </span>
            </div>
        </div>

        {{-- Dropdown Akun / Logout --}}
        <div class="dropdown">
            <button
                class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-1"
                type="button"
                id="userDropdownMenu"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="Menu akun {{ auth()->user()->name }}"
            >
                <span class="d-inline-block rounded-circle bg-secondary text-white text-center fw-bold" style="width: 24px; height: 24px; line-height: 24px; font-size: 11px;">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </span>
                <span class="d-none d-md-inline small">{{ auth()->user()->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userDropdownMenu">
                <li class="px-3 py-1 text-muted small border-bottom mb-1">
                    Masuk sebagai: <strong>{{ auth()->user()->email }}</strong>
                </li>
                <li>
                    <a class="dropdown-item small" href="{{ route('password.change') }}">
                        Ganti Password
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item small text-danger">
                            Keluar (Logout)
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
