@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <h1 class="h4 fw-bold text-center mb-1">Masuk</h1>
    <p class="text-muted small text-center mb-4">Silakan masukkan email dan kata sandi Anda</p>

    {{-- Alert Notifikasi Flash & Error --}}
    <x-alert />

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        {{-- Input Email --}}
        <x-input
            name="email"
            label="Alamat Email"
            type="email"
            :required="true"
            :autofocus="true"
            autocomplete="username"
            placeholder="nama@domain.test"
        />

        {{-- Input Password --}}
        <x-input
            name="password"
            label="Kata Sandi"
            type="password"
            :required="true"
            autocomplete="current-password"
            placeholder="Masukkan kata sandi"
        />

        {{-- Remember Me --}}
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label small text-secondary" for="remember">
                Ingat Sesi Saya
            </label>
        </div>

        {{-- Tombol Submit --}}
        <x-button type="submit" variant="teal" class="w-100 py-2">
            Masuk ke Sistem
        </x-button>
    </form>
@endsection
