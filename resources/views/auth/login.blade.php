<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIM Kos</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background-color: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .login-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2rem; width: 100%; max-width: 400px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .login-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; text-align: center; }
        .login-subtitle { font-size: 0.875rem; color: #64748b; margin-bottom: 1.5rem; text-align: center; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.35rem; }
        input[type="email"], input[type="password"] { width: 100%; box-sizing: border-box; padding: 0.6rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; }
        .checkbox-group { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem; }
        .btn-submit { width: 100%; padding: 0.75rem; background: #0f172a; color: #ffffff; border: none; border-radius: 6px; font-size: 0.95rem; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background: #1e293b; }
        .alert { padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.875rem; margin-bottom: 1rem; }
        .alert-danger { background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-success { background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .alert-warning { background-color: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1 class="login-title">SIM Kos</h1>
        <p class="login-subtitle">Masuk ke Sistem Informasi Manajemen Kos</p>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

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

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>

            <div class="checkbox-group">
                <input id="remember" type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember" style="margin-bottom: 0;">Ingat Saya</label>
            </div>

            <button type="submit" class="btn-submit">Masuk</button>
        </form>
    </div>
</body>
</html>
