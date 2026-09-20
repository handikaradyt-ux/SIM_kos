@extends('layouts.app')

@section('title', 'Dashboard Pemilik')
@section('page_title', 'Dashboard Pemilik')
@section('page_subtitle', 'Ringkasan pemantauan kinerja bisnis dan operasional kos.')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card simkos-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                    <div>
                        <h2 class="h4 fw-bold text-dark mb-1">Selamat Datang, {{ auth()->user()->name }}</h2>
                        <p class="text-muted mb-0">
                            Anda masuk dengan hak akses <strong>Pemilik</strong> (Read-Only). Anda dapat memantau kinerja keuangan, hunian, dan laporan operasional secara berkala.
                        </p>
                    </div>
                    <div>
                        <x-badge role="owner" label="Pemilik" />
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
                <h3 class="h6 fw-bold mb-0 text-dark">Ringkasan Pemantauan</h3>
            </div>
            <div class="card-body p-4">
                <x-empty-state
                    title="Informasi Pemantauan Belum Tersedia"
                    description="Ringkasan pemantauan keuangan, laporan okupansi, dan data operasional kos akan tersedia pada tahap implementasi modul terkait."
                />
            </div>
        </div>
    </div>
</div>
@endsection
