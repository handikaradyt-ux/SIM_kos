@extends('layouts.app')

@section('title', 'Edit Penghuni: ' . $resident->name)
@section('page_title', 'Edit Penghuni')
@section('page_subtitle', 'Perbarui profil penghuni dan sinkronkan data identitas akun pengguna.')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        {{-- Alert Edukasi Imutabilitas Faktur --}}
        <div class="alert alert-info py-2 px-3 small d-flex align-items-center mb-3" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-check me-2 flex-shrink-0" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M5.338 1.59a61 61 0 0 0-2.837.856.48.48 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.7 10.7 0 0 0 2.287 2.233c.346.244.652.42.893.533q.18.085.293.134a.1.1 0 0 0 .101 0q.114-.05.293-.134c.24-.114.546-.29.893-.533a10.7 10.7 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.8 11.8 0 0 1-2.517 2.453 7 7 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7 7 0 0 1-1.048-.625 11.8 11.8 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 63 63 0 0 1 5.072.56"/>
                <path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0"/>
            </svg>
            <div>
                <strong>Integritas Dokumen Historis:</strong> Pembaruan nama akan otomatis disinkronkan ke profil dan akun login pengguna. Namun, <em>snapshot</em> nama penghuni pada faktur atau kuitansi yang pernah diterbitkan tetap dipertahankan sesuai nama saat dokumen dibuat demi keabsahan arsip transaksi.
            </div>
        </div>

        <div class="card simkos-card mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="card-title fs-6 fw-bold mb-0">Formulir Perubahan Data Penghuni</h5>
            </div>
            <div class="card-body p-3 p-md-4">
                <form method="POST" action="{{ route('residents.update', $resident) }}" novalidate>
                    @csrf
                    @method('PUT')

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
                            value="{{ old('name', $resident->name) }}"
                            placeholder="Contoh: Budi Santoso"
                            required
                            maxlength="100"
                            aria-required="true"
                            aria-describedby="name-helper @error('name') name-error @enderror"
                            @error('name') aria-invalid="true" @enderror
                        >
                        <div id="name-helper" class="form-text small text-muted">
                            Nama lengkap penghuni (2–100 karakter). Nama akun pengguna akan ikut diperbarui secara otomatis.
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
                            value="{{ old('email', $resident->user?->email) }}"
                            placeholder="Contoh: budi.santoso@example.test"
                            required
                            maxlength="191"
                            aria-required="true"
                            aria-describedby="email-helper @error('email') email-error @enderror"
                            @error('email') aria-invalid="true" @enderror
                        >
                        <div id="email-helper" class="form-text small text-muted">
                            Email akun pengguna yang digunakan penghuni untuk masuk ke sistem.
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
                            value="{{ old('phone', $resident->phone) }}"
                            placeholder="Contoh: 081234567890"
                            required
                            maxlength="20"
                            aria-required="true"
                            aria-describedby="phone-helper @error('phone') phone-error @enderror"
                            @error('phone') aria-invalid="true" @enderror
                        >
                        <div id="phone-helper" class="form-text small text-muted">
                            Nomor telepon aktif (8–20 karakter, wajib mengandung angka).
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
                            placeholder="Contoh: Jl. Mawar No. 12, Kel. Sukamaju, Jakarta"
                            required
                            maxlength="255"
                            aria-required="true"
                            aria-describedby="address-helper @error('origin_address') address-error @enderror"
                            @error('origin_address') aria-invalid="true" @enderror
                        >{{ old('origin_address', $resident->origin_address) }}</textarea>
                        <div id="address-helper" class="form-text small text-muted">
                            Alamat domisili atau asal KTP penghuni (5–255 karakter).
                        </div>
                        @error('origin_address')
                            <div id="address-error" class="invalid-feedback d-block">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="{{ route('residents.show', $resident) }}" class="btn btn-sm btn-outline-secondary">
                            Batal
                        </a>
                        <button type="submit" class="btn btn-sm btn-primary">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
