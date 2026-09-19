<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - SIM Kos</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background-color: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2rem; width: 100%; max-width: 450px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .title { font-size: 1.35rem; font-weight: 700; margin-bottom: 0.5rem; }
        .subtitle { font-size: 0.875rem; color: #64748b; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.35rem; }
        input[type="password"] { width: 100%; box-sizing: border-box; padding: 0.6rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; }
        .btn-submit { width: 100%; padding: 0.75rem; background: #0f172a; color: #ffffff; border: none; border-radius: 6px; font-size: 0.95rem; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background: #1e293b; }
        .alert { padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.875rem; margin-bottom: 1rem; }
        .alert-danger { background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-warning { background-color: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .logout-link { display: block; text-align: center; margin-top: 1rem; font-size: 0.875rem; color: #64748b; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1 class="title">Ganti Password</h1>
        <p class="subtitle">Perbarui password akun Anda (minimal 12 karakter).</p>

        @if(session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <div class="form-group">
                <label for="current_password">Password Saat Ini</label>
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label for="password">Password Baru (Minimal 12 Karakter)</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="password_confirmation">Konfirmasi Password Baru</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn-submit">Simpan Password Baru</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" style="margin-top: 1rem;">
            @csrf
            <button type="submit" style="background: none; border: none; color: #64748b; font-size: 0.875rem; width: 100%; cursor: pointer; text-decoration: underline;">
                Keluar (Logout)
            </button>
        </form>
    </div>
</body>
</html>
