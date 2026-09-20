<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SIM Kos') - Sistem Informasi Manajemen Kos</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
    <div class="simkos-wrapper">
        {{-- Sidebar Desktop & Mobile Offcanvas --}}
        @include('layouts.partials.sidebar')

        {{-- Area Konten Utama --}}
        <div class="simkos-main-wrapper">
            @include('layouts.partials.header')

            <main class="simkos-content-area" id="mainContent">
                {{-- Notifikasi Flash Pesan & Error Validasi Otomatis --}}
                <x-alert />

                {{-- Konten Halaman --}}
                @yield('content')
            </main>

            <footer class="mt-auto py-3 px-4 text-center text-muted small border-top bg-white no-print">
                &copy; {{ date('Y') }} SIM Kos &mdash; Sistem Informasi Manajemen Kos. Semua hak dilindungi.
            </footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
