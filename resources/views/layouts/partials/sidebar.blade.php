{{-- Sidebar Desktop (Hanya Tampil di Layar >= 992px) --}}
<aside class="simkos-sidebar-desktop" aria-label="Navigasi Utama Desktop">
    <div class="simkos-sidebar-brand">
        <span class="simkos-sidebar-brand-badge" aria-hidden="true">SIM</span>
        <span class="simkos-sidebar-brand-title">SIM Kos</span>
    </div>

    <div class="py-2 flex-grow-1">
        @include('layouts.partials.sidebar-nav')
    </div>

    <div class="p-3 border-top border-secondary border-opacity-25 mt-auto">
        <div class="d-flex align-items-center justify-content-between">
            <div class="small text-truncate me-2">
                <div class="fw-bold text-light text-truncate">{{ auth()->user()->name }}</div>
                <div class="text-muted small text-capitalize">{{ auth()->user()->role?->code ?? 'Pengguna' }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Keluar dari sistem" aria-label="Keluar dari sistem">
                    Keluar
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- Sidebar Mobile Offcanvas (Tampil di Layar < 992px via Tombol Menu) --}}
<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="simkosOffcanvasSidebar" aria-labelledby="simkosOffcanvasLabel" style="background-color: var(--simkos-sidebar-bg) !important;">
    <div class="offcanvas-header border-bottom border-secondary border-opacity-25 py-3">
        <div class="d-flex align-items-center gap-2" id="simkosOffcanvasLabel">
            <span class="simkos-sidebar-brand-badge" aria-hidden="true">SIM</span>
            <span class="fs-5 fw-bold text-white">SIM Kos</span>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Tutup navigasi"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <div class="py-2 flex-grow-1">
            @include('layouts.partials.sidebar-nav')
        </div>

        <div class="p-3 border-top border-secondary border-opacity-25 mt-auto bg-dark bg-opacity-25">
            <div class="d-flex align-items-center justify-content-between">
                <div class="small text-truncate me-2">
                    <div class="fw-bold text-light text-truncate">{{ auth()->user()->name }}</div>
                    <div class="text-muted small text-capitalize">{{ auth()->user()->role?->code ?? 'Pengguna' }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Keluar dari sistem" aria-label="Keluar dari sistem">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
