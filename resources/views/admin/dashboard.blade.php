@extends('layouts.app')

@section('title', 'Dashboard Admin')
@section('page_title', 'Dashboard Admin')
@section('page_subtitle', 'Pusat kendali dan administrasi operasional rumah kos.')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card simkos-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                    <div>
                        <h2 class="h4 fw-bold text-dark mb-1">Selamat Datang, {{ auth()->user()->name }}</h2>
                        <p class="text-muted mb-0">
                            Anda masuk dengan hak akses <strong>Administrator</strong>. Sistem ini mengelola seluruh operasional kos secara terpadu.
                        </p>
                    </div>
                    <div>
                        <x-badge role="admin" label="Administrator" />
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
                <h3 class="h6 fw-bold mb-0 text-dark">Ringkasan Operasional</h3>
            </div>
            <div class="card-body p-4">
                <x-empty-state
                    title="Informasi Operasional Belum Tersedia"
                    description="Ringkasan data operasional kos, statistik kamar, dan keuangan akan tersedia pada tahap implementasi modul terkait."
                />
            </div>
        </div>
    </div>
</div>
@endsection
