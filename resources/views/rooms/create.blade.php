@extends('layouts.app')

@section('title', 'Tambah Kamar Baru')
@section('page_title', 'Tambah Kamar Baru')
@section('page_subtitle', 'Masukkan rincian identitas, tipe, dan tarif sewa kamar kos.')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card simkos-card">
            <div class="card-header bg-white py-3">
                <span class="h6 fw-bold mb-0 text-dark">Formulir Pendaftaran Kamar</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('rooms.store') }}" novalidate>
                    @csrf

                    {{-- Nomor Kamar --}}
                    <x-input
                        name="number"
                        label="Nomor Kamar"
                        type="text"
                        :required="true"
                        :autofocus="true"
                        placeholder="Contoh: A-01, B-05, R-101"
                        helper="Nomor kamar wajib unik dan maksimal 20 karakter."
                    />

                    {{-- Tipe Kamar --}}
                    <x-input
                        name="type"
                        label="Tipe / Jenis Kamar"
                        type="text"
                        :required="true"
                        placeholder="Contoh: Standard, Deluxe AC, VIP"
                        helper="Jenis atau tipe kamar (maksimal 50 karakter)."
                    />

                    {{-- Tarif Bulanan --}}
                    <x-input
                        name="monthly_rate"
                        label="Tarif Sewa Bulanan (Rp)"
                        type="number"
                        :required="true"
                        placeholder="Contoh: 850000"
                        helper="Nominal bulat positif dalam Rupiah (Rp1 sampai Rp999.999.999)."
                    />

                    {{-- Catatan Kamar (Textarea) --}}
                    <div class="mb-3">
                        <label for="notes" class="form-label">
                            Catatan Kamar
                            <span class="text-muted fw-normal small">(Opsional)</span>
                        </label>
                        <textarea
                            name="notes"
                            id="notes"
                            rows="3"
                            class="form-control @error('notes') is-invalid @enderror"
                            placeholder="Informasi tambahan terkait kondisi kamar, posisi lantai, dll."
                            aria-describedby="notes-help @error('notes') notes-error @enderror"
                            @error('notes') aria-invalid="true" @enderror
                        >{{ old('notes') }}</textarea>
                        <div id="notes-help" class="form-text">
                            Catatan tambahan deskripsi kamar maksimal 1000 karakter.
                        </div>
                        @error('notes')
                            <div id="notes-error" class="invalid-feedback d-block" role="alert">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Tombol Aksi --}}
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <a href="{{ route('rooms.index') }}" class="btn btn-sm btn-outline-secondary">
                            &larr; Batal &amp; Kembali
                        </a>
                        <x-button type="submit" variant="teal" class="px-4">
                            Simpan Kamar
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
