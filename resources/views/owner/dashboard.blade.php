<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Pemilik - SIM Kos</title>
    <style>body { font-family: system-ui; padding: 2rem; background: #f8fafc; }</style>
</head>
<body>
    <h1>Dashboard Pemilik</h1>
    <p>Selamat datang, <strong>{{ auth()->user()->name }}</strong> (Role: Pemilik / Read-Only).</p>
    @if(session('status'))
        <p style="color: green;">{{ session('status') }}</p>
    @endif
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>
</body>
</html>
