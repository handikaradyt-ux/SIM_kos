@extends('layouts.app')

@section('title', 'Portal Penghuni')
@section('page_title', 'Portal Penghuni')
@section('page_subtitle', 'Akses layanan mandiri informasi sewa, tagihan, dan keluhan fasilitas.')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card simkos-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                    <div>
                        <h2 class="h4 fw-bold text-dark mb-1">Selamat Datang, {{ auth()->user()->name }}</h2>
                        <p class="text-muted mb-0">
                            Anda masuk dengan hak akses <strong>Penghuni</strong>. Melalui portal ini, Anda dapat mengakses informasi masa sewa, status tagihan, dan pengajuan keluhan.
                        </p>
                    </div>
                    <div>
                        <x-badge role="resident" label="Penghuni" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card simkos-card">
            <div class="card-header bg-transparent py-3">
                <h3 class="h6 fw-bold mb-0 text-dark">Layanan Mandiri</h3>
            </div>
            <div class="card-body p-4">
                <x-empty-state
                    title="Informasi Layanan Mandiri Belum Tersedia"
                    description="Informasi kamar aktif, rincian tagihan sewa bulanan, dan form pengajuan keluhan fasilitas akan tersedia pada tahap implementasi modul terkait."
                />
            </div>
        </div>
    </div>
</div>
@endsection
