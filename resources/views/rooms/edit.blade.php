@extends('layouts.app')

@section('title', 'Ubah Data Kamar ' . $room->number)
@section('page_title', 'Ubah Data Kamar ' . $room->number)
@section('page_subtitle', 'Perbarui rincian tipe, tarif sewa, atau catatan kamar kos.')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        {{-- Peringatan Imutabilitas Tarif --}}
        <div class="alert alert-info py-2 px-3 small mb-3 border-0 shadow-sm" role="alert">
            <div class="d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="flex-shrink-0" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                </svg>
                <div>
                    <strong>Pemberitahuan Tarif:</strong> Perubahan tarif bulanan kamar ini hanya akan berlaku untuk kontrak penempatan baru. Tarif penempatan aktif dan faktur tagihan yang sudah berjalan tetap menggunakan tarif kesepakatan semula.
                </div>
            </div>
        </div>

        <div class="card simkos-card">
            <div class="card-header bg-white py-3">
                <span class="h6 fw-bold mb-0 text-dark">Formulir Pembaruan Kamar</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('rooms.update', $room) }}" novalidate>
                    @csrf
                    @method('PUT')

                    {{-- Nomor Kamar --}}
                    <x-input
                        name="number"
                        label="Nomor Kamar"
                        type="text"
                        :value="$room->number"
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
                        :value="$room->type"
                        :required="true"
                        placeholder="Contoh: Standard, Deluxe AC, VIP"
                        helper="Jenis atau tipe kamar (maksimal 50 karakter)."
                    />

                    {{-- Tarif Bulanan --}}
                    <x-input
                        name="monthly_rate"
                        label="Tarif Sewa Bulanan (Rp)"
                        type="number"
                        :value="$room->monthly_rate"
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
                        >{{ old('notes', $room->notes) }}</textarea>
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
                            Simpan Perubahan
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
