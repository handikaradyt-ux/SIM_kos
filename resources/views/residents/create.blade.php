@extends('layouts.app')

@section('title', 'Tambah Penghuni Baru')
@section('page_title', 'Tambah Penghuni Baru')
@section('page_subtitle', 'Pendaftaran identitas penghuni baru dan pembuatan akun login portal mandiri secara atomik.')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        {{-- Card Petunjuk Pembuatan Akun & Profil --}}
        <div class="alert alert-info py-2 px-3 small d-flex align-items-center mb-3" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle-fill me-2 flex-shrink-0" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2"/>
            </svg>
            <div>
                <strong>Ketentuan Akun Otomatis:</strong> Pembuatan penghuni baru akan otomatis membuatkan akun pengguna dengan role <code>resident</code> dan password sementara acak 12 karakter. Password sementara akan ditampilkan satu kali di layar setelah berhasil disimpan agar dapat diserahkan langsung kepada penghuni.
            </div>
        </div>

        <div class="card simkos-card mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="card-title fs-6 fw-bold mb-0">Formulir Identitas & Akun Penghuni</h5>
            </div>
            <div class="card-body p-3 p-md-4">
                <form method="POST" action="{{ route('residents.store') }}" novalidate>
                    @csrf

                    {{-- Nama Lengkap --}}
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">
                            Nama Lengkap <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name') }}"
                            placeholder="Contoh: Budi Santoso"
                            required
                            maxlength="100"
                            aria-required="true"
                            aria-describedby="name-helper @error('name') name-error @enderror"
                            @error('name') aria-invalid="true" @enderror
                        >
                        <div id="name-helper" class="form-text small text-muted">
                            Nama lengkap penghuni (2–100 karakter). Nama ini akan disinkronkan sebagai nama profil dan nama akun.
                        </div>
                        @error('name')
                            <div id="name-error" class="invalid-feedback d-block">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Email Akun Pengguna --}}
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">
                            Alamat Email (Login) <span class="text-danger">*</span>
                        </label>
                        <input
                            type="email"
                            name="email"
                            id="email"
                            class="form-control @error('email') is-invalid @enderror"
                            value="{{ old('email') }}"
                            placeholder="Contoh: budi.santoso@example.test"
                            required
                            maxlength="191"
                            aria-required="true"
                            aria-describedby="email-helper @error('email') email-error @enderror"
                            @error('email') aria-invalid="true" @enderror
                        >
                        <div id="email-helper" class="form-text small text-muted">
                            Email unik yang digunakan penghuni untuk masuk ke portal mandiri.
                        </div>
                        @error('email')
                            <div id="email-error" class="invalid-feedback d-block">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Nomor Telepon --}}
                    <div class="mb-3">
                        <label for="phone" class="form-label fw-semibold">
                            Nomor Telepon / Kontak <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            name="phone"
                            id="phone"
                            class="form-control font-monospace @error('phone') is-invalid @enderror"
                            value="{{ old('phone') }}"
                            placeholder="Contoh: 081234567890 atau +62 812-3456-7890"
                            required
                            maxlength="20"
                            aria-required="true"
                            aria-describedby="phone-helper @error('phone') phone-error @enderror"
                            @error('phone') aria-invalid="true" @enderror
                        >
                        <div id="phone-helper" class="form-text small text-muted">
                            Nomor telepon aktif (8–20 karakter, wajib mengandung angka, dapat memuat tanda + atau spasi).
                        </div>
                        @error('phone')
                            <div id="phone-error" class="invalid-feedback d-block">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Alamat Asal --}}
                    <div class="mb-4">
                        <label for="origin_address" class="form-label fw-semibold">
                            Alamat Asal <span class="text-danger">*</span>
                        </label>
                        <textarea
                            name="origin_address"
                            id="origin_address"
                            rows="3"
                            class="form-control @error('origin_address') is-invalid @enderror"
                            placeholder="Contoh: Jl. Mawar No. 12, Kel. Sukamaju, Kec. Cilincing, Jakarta Utara"
                            required
                            maxlength="255"
                            aria-required="true"
                            aria-describedby="address-helper @error('origin_address') address-error @enderror"
                            @error('origin_address') aria-invalid="true" @enderror
                        >{{ old('origin_address') }}</textarea>
                        <div id="address-helper" class="form-text small text-muted">
                            Alamat domisili atau alamat asal KTP penghuni (5–255 karakter).
                        </div>
                        @error('origin_address')
                            <div id="address-error" class="invalid-feedback d-block">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="{{ route('residents.index') }}" class="btn btn-sm btn-outline-secondary">
                            Batal
                        </a>
                        <button type="submit" class="btn btn-sm btn-primary">
                            Simpan & Buat Akun
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
