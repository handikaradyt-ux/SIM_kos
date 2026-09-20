<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Login') - SIM Kos</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 py-4">
    <main class="w-100" style="max-width: 440px; padding: 1rem;">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center gap-2 mb-2">
                <span class="simkos-sidebar-brand-badge fs-4 px-3 py-1">SIM</span>
                <span class="fs-3 fw-bold text-dark tracking-tight">SIM Kos</span>
            </div>
            <p class="text-muted small mb-0">Sistem Informasi Manajemen Kos</p>
        </div>

        <div class="simkos-card shadow-sm p-4">
            @yield('content')
        </div>

        <div class="text-center mt-3 text-muted small">
            &copy; {{ date('Y') }} SIM Kos. Hak Cipta Dilindungi.
        </div>
    </main>
</body>
</html>
