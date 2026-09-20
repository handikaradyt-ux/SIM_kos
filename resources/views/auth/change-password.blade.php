@extends('layouts.app')

@section('title', 'Ganti Kata Sandi')
@section('page_title', 'Ganti Kata Sandi')
@section('page_subtitle', 'Kelola dan perbarui kata sandi akun Anda')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="simkos-card shadow-sm">
            <div class="simkos-card-header">
                <span>Formulir Perubahan Kata Sandi</span>
            </div>
            <div class="simkos-card-body">
                @if(auth()->user()->must_change_password)
                    <div class="alert alert-warning py-2 px-3 small mb-3" role="alert">
                        <strong>Perhatian:</strong> Akun Anda saat ini menggunakan kata sandi sementara. Anda wajib memperbarui kata sandi sebelum dapat mengakses fitur sistem lainnya.
                    </div>
                @endif

                <form method="POST" action="{{ route('password.update') }}" novalidate>
                    @csrf

                    {{-- Kata Sandi Saat Ini --}}
                    <x-input
                        name="current_password"
                        label="Kata Sandi Saat Ini"
                        type="password"
                        :required="true"
                        :autofocus="true"
                        autocomplete="current-password"
                        helper="Masukkan kata sandi yang sedang Anda gunakan saat ini."
                    />

                    {{-- Kata Sandi Baru --}}
                    <x-input
                        name="password"
                        label="Kata Sandi Baru"
                        type="password"
                        :required="true"
                        autocomplete="new-password"
                        helper="Panjang kata sandi baru minimal 12 karakter."
                    />

                    {{-- Konfirmasi Kata Sandi Baru --}}
                    <x-input
                        name="password_confirmation"
                        label="Konfirmasi Kata Sandi Baru"
                        type="password"
                        :required="true"
                        autocomplete="new-password"
                        helper="Ketik ulang kata sandi baru untuk memastikan kecocokan."
                    />

                    <div class="d-flex align-items-center justify-content-between pt-2">
                        <x-button type="submit" variant="teal" class="px-4">
                            Simpan Kata Sandi Baru
                        </x-button>

                        <button type="submit" form="logout-form" class="btn btn-link text-muted small text-decoration-none" aria-label="Batal dan keluar dari sistem">
                            Batal &amp; Keluar
                        </button>
                    </div>
                </form>

                <form id="logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
                    @csrf
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
