@extends('layouts.app')

@section('title', 'Tambah Fasilitas Baru')
@section('page_title', 'Tambah Fasilitas Baru')
@section('page_subtitle', 'Masukkan rincian inventaris fasilitas kos, kondisi fisik, dan lokasi penempatan.')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card simkos-card">
            <div class="card-header bg-white py-3">
                <span class="h6 fw-bold mb-0 text-dark">Formulir Inventaris Fasilitas</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('facilities.store') }}" novalidate id="facilityForm">
                    @csrf

                    {{-- Kode Fasilitas --}}
                    <div class="mb-3">
                        <label for="code" class="form-label">
                            Kode Fasilitas <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="text"
                            name="code"
                            id="code"
                            value="{{ old('code') }}"
                            class="form-control font-monospace @error('code') is-invalid @enderror"
                            placeholder="Contoh: AC-101, WTR-01, KLS-DAPUR"
                            required
                            autofocus
                            maxlength="30"
                            aria-describedby="code-help @error('code') code-error @enderror"
                            @error('code') aria-invalid="true" @enderror
                        >
                        <div id="code-help" class="form-text">
                            Kode wajib unik lintas fasilitas aktif dan diarsipkan (maksimal 30 karakter).
                        </div>
                        @error('code')
                            <div id="code-error" class="invalid-feedback d-block" role="alert">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Nama Fasilitas --}}
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            Nama Fasilitas <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            value="{{ old('name') }}"
                            class="form-control @error('name') is-invalid @enderror"
                            placeholder="Contoh: AC Daikin 1/2 PK, Dispenser Air Galon, Kasur Springbed"
                            required
                            minlength="2"
                            maxlength="100"
                            aria-describedby="name-help @error('name') name-error @enderror"
                            @error('name') aria-invalid="true" @enderror
                        >
                        <div id="name-help" class="form-text">
                            Nama deskriptif barang fasilitas (2 sampai 100 karakter).
                        </div>
                        @error('name')
                            <div id="name-error" class="invalid-feedback d-block" role="alert">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Kondisi Fasilitas --}}
                    <div class="mb-3">
                        <label for="condition" class="form-label">
                            Kondisi Fasilitas <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select
                            name="condition"
                            id="condition"
                            class="form-select @error('condition') is-invalid @enderror"
                            required
                            aria-describedby="condition-help @error('condition') condition-error @enderror"
                            @error('condition') aria-invalid="true" @enderror
                        >
                            <option value="">-- Pilih Kondisi Barang --</option>
                            <option value="good" {{ old('condition', 'good') === 'good' ? 'selected' : '' }}>Baik (Berfungsi Normal)</option>
                            <option value="broken" {{ old('condition') === 'broken' ? 'selected' : '' }}>Rusak (Perlu Tindakan)</option>
                            <option value="repairing" {{ old('condition') === 'repairing' ? 'selected' : '' }}>Dalam Perbaikan (Sedang Dikerjakan)</option>
                        </select>
                        <div id="condition-help" class="form-text">
                            Status kelayakan fisik inventaris fasilitas saat ini.
                        </div>
                        @error('condition')
                            <div id="condition-error" class="invalid-feedback d-block" role="alert">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Tipe Lokasi --}}
                    <div class="mb-3">
                        <label class="form-label d-block">
                            Tipe Lokasi Penempatan <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <div class="form-check form-check-inline">
                            <input
                                class="form-check-input @error('location_type') is-invalid @enderror"
                                type="radio"
                                name="location_type"
                                id="location_type_room"
                                value="room"
                                {{ old('location_type', 'room') === 'room' ? 'checked' : '' }}
                                onchange="toggleLocationInputs()"
                            >
                            <label class="form-check-label fw-semibold text-dark" for="location_type_room">
                                Kamar Kos
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input
                                class="form-check-input @error('location_type') is-invalid @enderror"
                                type="radio"
                                name="location_type"
                                id="location_type_shared"
                                value="shared"
                                {{ old('location_type') === 'shared' ? 'checked' : '' }}
                                onchange="toggleLocationInputs()"
                            >
                            <label class="form-check-label fw-semibold text-dark" for="location_type_shared">
                                Area Bersama (Fasilitas Umum)
                            </label>
                        </div>
                        @error('location_type')
                            <div class="invalid-feedback d-block" role="alert">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Pilihan Kamar (Tampil jika tipe = room) --}}
                    <div class="mb-3" id="container_room_id">
                        <label for="room_id" class="form-label">
                            Pilih Kamar <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select
                            name="room_id"
                            id="room_id"
                            class="form-select @error('room_id') is-invalid @enderror"
                            aria-describedby="room_id-help @error('room_id') room_id-error @enderror"
                            @error('room_id') aria-invalid="true" @enderror
                        >
                            <option value="">-- Pilih Kamar Aktif --</option>
                            @foreach($rooms as $r)
                                <option value="{{ $r->id }}" {{ old('room_id') == $r->id ? 'selected' : '' }}>
                                    Kamar {{ $r->number }} ({{ $r->type }})
                                </option>
                            @endforeach
                        </select>
                        <div id="room_id-help" class="form-text">
                            Hanya kamar aktif yang dapat dipilih untuk fasilitas baru.
                        </div>
                        @error('room_id')
                            <div id="room_id-error" class="invalid-feedback d-block" role="alert">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Input Nama Area Bersama (Tampil jika tipe = shared) --}}
                    <div class="mb-3 d-none" id="container_area_name">
                        <label for="area_name" class="form-label">
                            Nama Area Bersama <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="text"
                            name="area_name"
                            id="area_name"
                            value="{{ old('area_name') }}"
                            class="form-control @error('area_name') is-invalid @enderror"
                            placeholder="Contoh: Dapur Lantai 1, Ruang Tamu, Garasi, Ruang Cuci"
                            maxlength="100"
                            aria-describedby="area_name-help @error('area_name') area_name-error @enderror"
                            @error('area_name') aria-invalid="true" @enderror
                        >
                        <div id="area_name-help" class="form-text">
                            Nama lokasi area bersama fasilitas umum (2 sampai 100 karakter).
                        </div>
                        @error('area_name')
                            <div id="area_name-error" class="invalid-feedback d-block" role="alert">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Catatan (Textarea) --}}
                    <div class="mb-3">
                        <label for="notes" class="form-label">
                            Catatan / Keterangan
                            <span class="text-muted fw-normal small">(Opsional)</span>
                        </label>
                        <textarea
                            name="notes"
                            id="notes"
                            rows="3"
                            class="form-control @error('notes') is-invalid @enderror"
                            placeholder="Keterangan nomor seri, tanggal pembelian, atau garansi..."
                            maxlength="1000"
                            aria-describedby="notes-help @error('notes') notes-error @enderror"
                            @error('notes') aria-invalid="true" @enderror
                        >{{ old('notes') }}</textarea>
                        <div id="notes-help" class="form-text">
                            Maksimal 1000 karakter.
                        </div>
                        @error('notes')
                            <div id="notes-error" class="invalid-feedback d-block" role="alert">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Tombol Aksi --}}
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <a href="{{ route('facilities.index') }}" class="btn btn-sm btn-outline-secondary">
                            &larr; Batal &amp; Kembali
                        </a>
                        <button type="submit" class="btn btn-sm btn-teal px-4">
                            Simpan Fasilitas
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleLocationInputs() {
        const isRoom = document.getElementById('location_type_room').checked;
        const roomContainer = document.getElementById('container_room_id');
        const areaContainer = document.getElementById('container_area_name');
        const roomSelect = document.getElementById('room_id');
        const areaInput = document.getElementById('area_name');

        if (isRoom) {
            roomContainer.classList.remove('d-none');
            areaContainer.classList.add('d-none');
            areaInput.value = '';
        } else {
            roomContainer.classList.add('d-none');
            areaContainer.classList.remove('d-none');
            roomSelect.value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        toggleLocationInputs();
    });
</script>
@endsection
