@extends('layouts.app')

@php
    $role = auth()->user()?->role?->code;
    $isOwner = $role === 'owner';
    $isResident = $role === 'resident';
    $isAdmin = $role === 'admin';

    $invNumber = '#INV-' . str_pad($invoice->id, 5, '0', STR_PAD_LEFT);
@endphp

@section('title', 'Rincian Tagihan ' . $invNumber)
@section('page_title', 'Rincian Tagihan ' . $invNumber)
@section('page_subtitle', 'Informasi lengkap tagihan sewa kamar kos periode ' . ($invoice->period_month ? $invoice->period_month->translatedFormat('F Y') : '-'))

@section('content')
<div class="row g-3 mb-4">
    {{-- Bar Navigasi Kembali --}}
    <div class="col-12 d-flex justify-content-between align-items-center">
        <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
            </svg>
            <span>Kembali ke Daftar Tagihan</span>
        </a>

        @if(!$isResident && $invoice->placement)
            <a href="{{ route('placements.show', $invoice->placement) }}" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                <span>Lihat Data Penempatan</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                </svg>
            </a>
        @endif
    </div>

    {{-- Ringkasan Tagihan & Status Finansial --}}
    <div class="col-12 col-lg-8">
        <div class="card simkos-card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h2 class="h6 fw-bold mb-0 text-dark">Informasi Tagihan</h2>
                    <span class="badge bg-light text-secondary border small">{{ $invNumber }}</span>
                </div>
                <div>
                    @if($invoice->isPaid())
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1 fs-6">
                            Lunas
                        </span>
                    @elseif($invoice->isOverdue($businessDate))
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-1 fs-6">
                            Terlambat
                        </span>
                    @elseif($invoice->isDueToday($businessDate))
                        <span class="badge bg-warning bg-opacity-25 text-dark border border-warning px-3 py-1 fs-6">
                            Jatuh Tempo Hari Ini
                        </span>
                    @else
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-1 fs-6">
                            Belum Jatuh Tempo
                        </span>
                    @endif
                </div>
            </div>

            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-12 col-md-6">
                        <span class="text-muted small d-block mb-1">Periode Tagihan</span>
                        <h3 class="h4 fw-bold text-dark mb-0">
                            {{ $invoice->period_month ? $invoice->period_month->translatedFormat('F Y') : '-' }}
                        </h3>
                        <small class="text-muted">Kode Periode: {{ $invoice->period_month ? $invoice->period_month->format('Y-m-01') : '-' }}</small>
                    </div>

                    <div class="col-12 col-md-6">
                        <span class="text-muted small d-block mb-1">Nominal Tagihan</span>
                        <h3 class="h4 fw-bold text-teal mb-0">
                            Rp {{ number_format($invoice->amount, 0, ',', '.') }}
                        </h3>
                        <small class="text-muted">Tarif Sewa Kamar Bulanan</small>
                    </div>

                    <div class="col-12 col-md-6">
                        <span class="text-muted small d-block mb-1">Batas Jatuh Tempo (Due Date)</span>
                        <span class="fw-semibold text-dark fs-6">
                            {{ $invoice->due_on ? $invoice->due_on->translatedFormat('d F Y') : '-' }}
                        </span>
                        @if(!$invoice->isPaid())
                            @if($invoice->isOverdue($businessDate))
                                <div class="small text-danger fw-semibold mt-1">
                                    Telah melewati tanggal jatuh tempo (terlambat).
                                </div>
                            @elseif($invoice->isDueToday($businessDate))
                                <div class="small text-warning fw-semibold mt-1">
                                    Jatuh tempo pada tanggal bisnis hari ini.
                                </div>
                            @else
                                <div class="small text-muted mt-1">
                                    Belum jatuh tempo.
                                </div>
                            @endif
                        @endif
                    </div>

                    <div class="col-12 col-md-6">
                        <span class="text-muted small d-block mb-1">Waktu Penerbitan</span>
                        <span class="text-dark fw-medium">
                            {{ $invoice->created_at ? $invoice->created_at->copy()->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') : '-' }} WIB
                        </span>
                        <div class="small text-muted mt-1">
                            Diterbitkan oleh: {{ $invoice->creator?->name ?? 'Sistem Otomatis' }}
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                {{-- Status Pembayaran & Rincian Transaksi --}}
                <h4 class="h6 fw-bold text-dark mb-3">Status dan Rincian Pembayaran</h4>

                @if($invoice->validPayment)
                    <div class="p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="text-success" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                            </svg>
                            <span class="fw-bold text-success">Pembayaran Sah Terkonfirmasi</span>
                        </div>
                        <div class="row g-2 text-dark small">
                            <div class="col-12 col-md-4">
                                <span class="text-muted d-block">Nominal Dibayar:</span>
                                <strong>Rp {{ number_format($invoice->validPayment->amount, 0, ',', '.') }}</strong>
                            </div>
                            <div class="col-12 col-md-4">
                                <span class="text-muted d-block">Tanggal Bayar:</span>
                                <strong>{{ $invoice->validPayment->paid_on ? $invoice->validPayment->paid_on->translatedFormat('d F Y') : '-' }}</strong>
                            </div>
                            <div class="col-12 col-md-4">
                                <span class="text-muted d-block">Metode:</span>
                                <strong>{{ ucfirst($invoice->validPayment->method ?? 'Transfer') }}</strong>
                            </div>
                            <div class="col-12 col-md-6 mt-2">
                                <span class="text-muted d-block">Dicatat Oleh:</span>
                                <span>{{ $invoice->validPayment->recorder?->name ?? 'Administrator' }}</span>
                            </div>
                            @if($invoice->validPayment->notes)
                                <div class="col-12 col-md-6 mt-2">
                                    <span class="text-muted d-block">Catatan:</span>
                                    <span>{{ $invoice->validPayment->notes }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="p-3 bg-light border rounded mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="text-muted" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                            </svg>
                            <span class="fw-semibold text-secondary">Belum ada pembayaran yang dicatat untuk tagihan ini.</span>
                        </div>
                    </div>
                @endif

                {{-- Riwayat Pembayaran Void (Jika Ada) --}}
                @php
                    $voidPayments = $invoice->payments->whereNotNull('voided_at');
                @endphp
                @if($voidPayments->isNotEmpty())
                    <div class="mt-3">
                        <h5 class="small fw-bold text-danger mb-2">Riwayat Pembayaran Dibatalkan (Void)</h5>
                        <div class="border border-danger border-opacity-25 rounded bg-danger bg-opacity-10 p-3">
                            <div class="small text-danger mb-2">
                                <em>Catatan Keamanan Finansial:</em> Pembayaran di bawah ini telah dibatalkan (void) dan <strong>tidak membuat status tagihan lunas</strong>.
                            </div>
                            <ul class="list-unstyled mb-0 small">
                                @foreach($voidPayments as $vp)
                                    <li class="border-bottom border-danger border-opacity-25 pb-2 mb-2 last-border-none">
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-semibold">Rp {{ number_format($vp->amount, 0, ',', '.') }}</span>
                                            <span class="text-muted">Void: {{ $vp->voided_at ? $vp->voided_at->copy()->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') : '-' }} WIB</span>
                                        </div>
                                        <div class="text-muted">Alasan Void: {{ $vp->void_reason ?? 'Tidak dicantumkan' }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Kartu Snapshot Finansial & Data Penempatan --}}
    <div class="col-12 col-lg-4">
        <div class="card simkos-card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h3 class="h6 fw-bold mb-0 text-dark">Snapshot Finansial</h3>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <span class="text-muted small d-block">Nomor Kamar (Snapshot):</span>
                    <span class="fw-bold text-dark fs-5">Kamar {{ $invoice->room_number_snapshot }}</span>
                    @if($invoice->placement?->room && $invoice->placement->room->number !== $invoice->room_number_snapshot)
                        <div class="small text-muted fst-italic">
                            (Nomor kamar operasional saat ini: {{ $invoice->placement->room->number }})
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block">Nama Penghuni (Snapshot):</span>
                    <span class="fw-bold text-dark fs-6">{{ $invoice->resident_name_snapshot }}</span>
                    @if($invoice->placement?->resident && $invoice->placement->resident->name !== $invoice->resident_name_snapshot)
                        <div class="small text-muted fst-italic">
                            (Nama penghuni operasional saat ini: {{ $invoice->placement->resident->name }})
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block">Status Penempatan:</span>
                    @if($invoice->placement)
                        @if($invoice->placement->isActive())
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                Penempatan Aktif
                            </span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">
                                Selesai ({{ $invoice->placement->ended_on ? $invoice->placement->ended_on->translatedFormat('d M Y') : '-' }})
                            </span>
                        @endif
                    @else
                        <span class="badge bg-light text-muted border">Tidak diketahui</span>
                    @endif
                </div>

                @if($invoice->placement)
                    <div class="mb-3">
                        <span class="text-muted small d-block">Tarif Kontrak Sewa:</span>
                        <span class="fw-semibold text-dark">
                            Rp {{ number_format($invoice->placement->agreed_monthly_rate, 0, ',', '.') }} / bulan
                        </span>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small d-block">Tanggal Mulai Sewa:</span>
                        <span class="text-dark">
                            {{ $invoice->placement->started_on ? $invoice->placement->started_on->translatedFormat('d F Y') : '-' }}
                        </span>
                    </div>
                @endif

                <div class="alert alert-light border small text-muted mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="me-1 text-primary" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                    </svg>
                    Data snapshot nama penghuni dan kamar bersifat permanen dan tidak dapat diubah (immutable) untuk menjamin keaslian bukti penagihan finansial.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
