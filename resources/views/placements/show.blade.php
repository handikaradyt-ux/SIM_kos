@extends('layouts.app')

@section('title', 'Detail Penempatan Kamar ' . ($placement->room?->number ?? '-'))
@section('page_title', 'Detail Penempatan')
@section('page_subtitle', 'Informasi kontrak sewa penempatan, penghuni, kamar, dan riwayat tagihan.')

@section('content')
<div class="row g-4">

    {{-- Header Ringkasan Status --}}
    <div class="col-12">
        <div class="card simkos-card shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h2 class="h4 fw-bold mb-0 text-dark">
                                Kamar {{ $placement->room?->number ?? '-' }} - {{ $placement->resident?->name ?? 'Penghuni' }}
                            </h2>
                            @if($placement->isActive())
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                    Penempatan Aktif
                                </span>
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">
                                    Penempatan Selesai
                                </span>
                            @endif
                        </div>
                        <div class="text-muted small">
                            Dimulai pada {{ $placement->started_on?->translatedFormat('d F Y') ?? '-' }}
                            @if($placement->ended_on)
                                &bull; Selesai pada {{ $placement->ended_on->translatedFormat('d F Y') }}
                            @endif
                            &bull; Dicatat oleh {{ $placement->creator?->name ?? 'Administrator' }}
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('placements.index') }}" class="btn btn-outline-secondary">
                            &larr; Kembali ke Daftar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail Penghuni & Kamar --}}
    <div class="col-12 col-md-6">
        <div class="card simkos-card h-100 shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="h6 fw-bold mb-0 text-dark">Data Penghuni</h3>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <span class="text-muted small d-block">Nama Lengkap:</span>
                    <span class="fw-bold text-dark fs-6">{{ $placement->resident?->name ?? '-' }}</span>
                </div>
                <div class="mb-3">
                    <span class="text-muted small d-block">Nomor Telepon:</span>
                    <span class="text-dark">{{ $placement->resident?->phone ?? '-' }}</span>
                </div>
                <div class="mb-3">
                    <span class="text-muted small d-block">Email Akun:</span>
                    <span class="text-dark">{{ $placement->resident?->user?->email ?? '-' }}</span>
                </div>
                <div class="mb-0">
                    <span class="text-muted small d-block">Alamat Asal:</span>
                    <span class="text-dark">{{ $placement->resident?->origin_address ?? '-' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card simkos-card h-100 shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="h6 fw-bold mb-0 text-dark">Data Kamar</h3>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <span class="text-muted small d-block">Nomor Kamar:</span>
                    <span class="fw-bold text-dark fs-6">Kamar {{ $placement->room?->number ?? '-' }}</span>
                </div>
                <div class="mb-3">
                    <span class="text-muted small d-block">Tipe Kamar:</span>
                    <span class="badge bg-light text-dark border">{{ ucfirst($placement->room?->type ?? 'Standard') }}</span>
                </div>
                <div class="mb-3">
                    <span class="text-muted small d-block">Tarif Kesepakatan Kontrak:</span>
                    <span class="fw-bold text-primary fs-5">
                        Rp {{ number_format($placement->agreed_monthly_rate, 0, ',', '.') }}
                    </span>
                    <span class="text-muted small">/ bulan</span>
                </div>
                <div class="mb-0">
                    <span class="text-muted small d-block">Tarif Standar Kamar Terkini:</span>
                    <span class="text-muted">
                        Rp {{ number_format($placement->room?->monthly_rate ?? $placement->agreed_monthly_rate, 0, ',', '.') }} / bulan
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail Riwayat Selesai (Jika Ada) --}}
    @if($placement->ended_on)
        <div class="col-12">
            <div class="card simkos-card shadow-sm border-warning border-opacity-50">
                <div class="card-header bg-warning bg-opacity-10 py-3">
                    <h3 class="h6 fw-bold mb-0 text-dark">Informasi Penghentian Penempatan</h3>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <span class="text-muted small d-block">Tanggal Penghentian:</span>
                            <span class="fw-semibold text-dark">{{ $placement->ended_on->translatedFormat('d F Y') }}</span>
                        </div>
                        <div class="col-12 col-md-4">
                            <span class="text-muted small d-block">Diakhiri Oleh:</span>
                            <span class="text-dark">{{ $placement->ender?->name ?? 'Administrator' }}</span>
                        </div>
                        <div class="col-12 col-md-4">
                            <span class="text-muted small d-block">Alasan Penghentian:</span>
                            <span class="text-dark">{{ $placement->end_reason ?? 'Tidak dicantumkan' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Riwayat Tagihan / Invoice Terkait --}}
    <div class="col-12">
        <div class="card simkos-card shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="h6 fw-bold mb-0 text-dark">Daftar Tagihan (Invoice)</h3>
                    <div class="text-muted small">
                        Daftar tagihan yang diterbitkan otomatis oleh sistem untuk penempatan ini.
                    </div>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                    {{ $placement->invoices->count() }} Invoice
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-3" style="width: 25%;">Periode Tagihan</th>
                            <th scope="col" style="width: 25%;">Jatuh Tempo</th>
                            <th scope="col" style="width: 25%;">Nominal</th>
                            <th scope="col" class="text-end pe-3" style="width: 25%;">Status Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($placement->invoices as $invoice)
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-bold text-dark d-block">
                                        {{ $invoice->period_month ? $invoice->period_month->translatedFormat('F Y') : '-' }}
                                    </span>
                                    <span class="small text-muted">
                                        {{ $invoice->period_month ? $invoice->period_month->format('Y-m-01') : '-' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-medium {{ ($invoice->due_on && $invoice->due_on->isPast() && !($invoice->valid_payment_exists ?? $invoice->validPayment)) ? 'text-danger' : 'text-dark' }}">
                                        {{ $invoice->due_on ? $invoice->due_on->translatedFormat('d F Y') : '-' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark">
                                        Rp {{ number_format($invoice->amount, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    @if($invoice->valid_payment_exists ?? $invoice->validPayment)
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                            Lunas
                                        </span>
                                    @else
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">
                                            Belum Lunas
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    Belum ada tagihan yang tercatat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
