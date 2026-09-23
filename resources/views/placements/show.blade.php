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
                        @if($placement->isActive() && auth()->user()->can('end', $placement))
                            <button type="button" class="btn btn-danger d-inline-flex align-items-center gap-1" id="btn-open-end-modal" data-bs-toggle="modal" data-bs-target="#endPlacementModal">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right me-1" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                                    <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                                </svg>
                                Akhiri Penempatan
                            </button>
                        @endif
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

{{-- Modal Pengakhiran Penempatan --}}
@if($placement->isActive() && auth()->user()->can('end', $placement))
<div class="modal fade" id="endPlacementModal" tabindex="-1" aria-labelledby="endPlacementModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark" id="endPlacementModalLabel">
                    Konfirmasi Pengakhiran Penempatan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Alert Error Container --}}
                <div id="end-modal-error" class="alert alert-danger d-none mb-3" role="alert">
                    <div class="d-flex align-items-start">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-exclamation-circle-fill flex-shrink-0 me-2 mt-1" viewBox="0 0 16 16">
                            <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                        </svg>
                        <div class="flex-grow-1">
                            <span id="end-modal-error-text" class="small"></span>
                            <div class="mt-2">
                                <button type="button" id="btn-retry-preview" class="btn btn-sm btn-outline-danger">Coba Muat Ulang Preview</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Loading Spinner Container --}}
                <div id="end-modal-loading" class="text-center py-5">
                    <div class="spinner-border text-danger mb-3" role="status" style="width: 2.5rem; height: 2.5rem;">
                        <span class="visually-hidden">Memuat...</span>
                    </div>
                    <p class="text-muted small mb-0">Menghitung sinkronisasi tagihan dan memeriksa status pembayaran terkini...</p>
                </div>

                {{-- Content Container --}}
                <div id="end-modal-content" class="d-none">
                    {{-- Notice Aturan Bisnis --}}
                    <div class="alert alert-info py-2 px-3 mb-3 small" role="alert">
                        <div class="d-flex">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle-fill flex-shrink-0 me-2 mt-1 text-info" viewBox="0 0 16 16">
                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                            </svg>
                            <div>
                                <strong>Ketentuan Pengakhiran:</strong> Tanggal pengakhiran ditentukan server hari ini (<span id="modal-ended-date-text" class="fw-semibold"></span>). Bulan keluar dikenakan tarif penuh tanpa prorata. Sistem menyinkronkan seluruh invoice yang belum terbit sampai bulan keluar secara otomatis.
                            </div>
                        </div>
                    </div>

                    {{-- Ringkasan Penempatan & Kamar --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <div class="card h-100 bg-light border">
                                <div class="card-body p-3">
                                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-2 small text-uppercase">Penghuni & Kamar</h6>
                                    <div class="mb-1">
                                        <span class="text-muted small d-block">Penghuni:</span>
                                        <span id="modal-resident-name" class="fw-semibold text-dark"></span>
                                    </div>
                                    <div class="mb-1">
                                        <span class="text-muted small d-block">Kamar:</span>
                                        <span id="modal-room-info" class="fw-semibold text-dark"></span>
                                    </div>
                                    <div>
                                        <span class="text-muted small d-block">Tarif Kontrak:</span>
                                        <span id="modal-rate-info" class="fw-semibold text-primary"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="card h-100 bg-light border">
                                <div class="card-body p-3">
                                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-2 small text-uppercase">Status Tagihan</h6>
                                    <div class="mb-1">
                                        <span class="text-muted small d-block">Tagihan Sebelumnya:</span>
                                        <span id="modal-existing-invoices-info" class="text-dark"></span>
                                    </div>
                                    <div class="mb-1">
                                        <span class="text-muted small d-block">Tagihan Baru yang Diterbitkan:</span>
                                        <span id="modal-new-invoices-info" class="fw-semibold text-dark"></span>
                                    </div>
                                    <div>
                                        <span class="text-muted small d-block">Total Kewajiban Belum Lunas:</span>
                                        <span id="modal-total-unpaid-info" class="fw-bold text-danger"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Daftar Tagihan Baru (Jika Ada) --}}
                    <div id="modal-new-invoices-container" class="mb-3 d-none">
                        <div class="card border-primary border-opacity-25 bg-primary bg-opacity-10">
                            <div class="card-body p-3">
                                <h6 class="fw-bold text-primary mb-2 small text-uppercase">Rincian Tagihan Baru yang Akan Diterbitkan:</h6>
                                <div id="modal-new-invoices-list" class="small text-dark"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Status Kewajiban Alert --}}
                    <div id="modal-obligation-warning" class="alert alert-warning py-2 px-3 mb-3 small d-none" role="alert">
                        <strong>Perhatian:</strong> Penghuni masih memiliki kewajiban belum lunas sebesar <span id="modal-obligation-warning-amount" class="fw-bold"></span>. Riwayat penempatan dan kewajiban tagihan akan tetap tercatat di sistem setelah penempatan selesai.
                    </div>

                    <div id="modal-obligation-success" class="alert alert-success py-2 px-3 mb-3 small d-none" role="alert">
                        <strong>Lunas:</strong> Seluruh tagihan penghuni ini telah berstatus lunas. Tidak ada sisa kewajiban pembayaran yang tertunggak.
                    </div>

                    {{-- Form Input Alasan Pengakhiran --}}
                    <form id="form-end-placement" method="POST" action="{{ route('placements.end', $placement) }}">
                        @csrf
                        <input type="hidden" name="preview_token" id="end_preview_token_input" value="">

                        <div class="mb-2">
                            <label for="end_reason_input" class="form-label fw-semibold text-dark">
                                Alasan Pengakhiran Penempatan <span class="text-danger">*</span>
                            </label>
                            <textarea 
                                name="end_reason" 
                                id="end_reason_input" 
                                class="form-control @error('end_reason') is-invalid @enderror" 
                                rows="3" 
                                minlength="5" 
                                maxlength="255" 
                                required 
                                placeholder="Contoh: Masa kontrak sewa telah selesai / penghuni pindah domisili"
                            >{{ old('end_reason') }}</textarea>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <span class="form-text text-muted small">
                                    Minimal 5 karakter (bukan spasi), maksimal 255 karakter.
                                </span>
                                <span class="form-text text-muted small">
                                    <span id="end_reason_count">0</span>/255
                                </span>
                            </div>
                            @error('end_reason')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Batal
                </button>
                <button type="submit" form="form-end-placement" id="btn-submit-end" class="btn btn-danger d-inline-flex align-items-center gap-2" disabled>
                    <span id="btn-submit-end-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    <span id="btn-submit-end-text">Konfirmasi & Akhiri Penempatan</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@if($placement->isActive() && auth()->user()->can('end', $placement))
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('endPlacementModal');
    if (!modalElement) return;

    const modalErrorAlert = document.getElementById('end-modal-error');
    const modalErrorText = document.getElementById('end-modal-error-text');
    const modalLoading = document.getElementById('end-modal-loading');
    const modalContent = document.getElementById('end-modal-content');
    const btnRetry = document.getElementById('btn-retry-preview');

    const tokenInput = document.getElementById('end_preview_token_input');
    const reasonInput = document.getElementById('end_reason_input');
    const reasonCount = document.getElementById('end_reason_count');
    const formEnd = document.getElementById('form-end-placement');
    const btnSubmit = document.getElementById('btn-submit-end');
    const btnSubmitSpinner = document.getElementById('btn-submit-end-spinner');
    const btnSubmitText = document.getElementById('btn-submit-end-text');

    const modalEndedDateText = document.getElementById('modal-ended-date-text');
    const modalResidentName = document.getElementById('modal-resident-name');
    const modalRoomInfo = document.getElementById('modal-room-info');
    const modalRateInfo = document.getElementById('modal-rate-info');
    const modalExistingInvoicesInfo = document.getElementById('modal-existing-invoices-info');
    const modalNewInvoicesInfo = document.getElementById('modal-new-invoices-info');
    const modalTotalUnpaidInfo = document.getElementById('modal-total-unpaid-info');
    const modalNewInvoicesContainer = document.getElementById('modal-new-invoices-container');
    const modalNewInvoicesList = document.getElementById('modal-new-invoices-list');
    const modalObligationWarning = document.getElementById('modal-obligation-warning');
    const modalObligationWarningAmount = document.getElementById('modal-obligation-warning-amount');
    const modalObligationSuccess = document.getElementById('modal-obligation-success');

    let activeAbortController = null;
    let requestSequence = 0;

    function formatRupiah(number) {
        return 'Rp ' + Number(number).toLocaleString('id-ID');
    }

    function updateCharCount() {
        if (!reasonInput || !reasonCount) return;
        const length = reasonInput.value.trim().length;
        reasonCount.textContent = length;
        if (length < 5 || length > 255) {
            reasonCount.classList.add('text-danger');
        } else {
            reasonCount.classList.remove('text-danger');
        }
    }

    if (reasonInput) {
        reasonInput.addEventListener('input', updateCharCount);
        updateCharCount();
    }

    function resetPreviewState() {
        requestSequence++;
        if (activeAbortController) {
            try {
                activeAbortController.abort();
            } catch (e) {}
            activeAbortController = null;
        }
        tokenInput.value = '';
        btnSubmit.disabled = true;
        btnSubmitSpinner.classList.add('d-none');
        btnSubmitText.textContent = 'Konfirmasi & Akhiri Penempatan';
        modalErrorAlert.classList.add('d-none');
        modalErrorText.textContent = '';
    }

    async function loadPreview() {
        resetPreviewState();

        modalLoading.classList.remove('d-none');
        modalContent.classList.add('d-none');
        modalErrorAlert.classList.add('d-none');

        const currentSeq = ++requestSequence;
        activeAbortController = new AbortController();

        try {
            const response = await fetch("{{ route('placements.end-preview', $placement) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                },
                body: JSON.stringify({}),
                signal: activeAbortController.signal
            });

            if (currentSeq !== requestSequence) return;

            const resData = await response.json();

            if (currentSeq !== requestSequence) return;

            if (!response.ok || !resData.success) {
                const errorMsg = resData.message || (resData.errors ? Object.values(resData.errors).flat().join(', ') : 'Gagal memuat preview pengakhiran penempatan.');
                modalLoading.classList.add('d-none');
                modalErrorText.textContent = errorMsg;
                modalErrorAlert.classList.remove('d-none');
                return;
            }

            const data = resData.data;
            tokenInput.value = data.preview_token;

            // Populate preview details safely using textContent
            modalEndedDateText.textContent = data.placement.ended_on_formatted;
            modalResidentName.textContent = data.resident.name + ' (' + data.resident.phone + ')';
            modalRoomInfo.textContent = 'Kamar ' + data.room.number + ' (' + data.room.type + ')';
            modalRateInfo.textContent = formatRupiah(data.financial.agreed_monthly_rate) + ' / bulan';

            modalExistingInvoicesInfo.textContent = data.financial.existing_invoices_count + ' invoice (Lunas: ' + data.financial.existing_paid_count + ', Belum Lunas: ' + data.financial.existing_unpaid_count + ')';
            
            if (data.financial.new_invoices_count > 0) {
                modalNewInvoicesInfo.textContent = data.financial.new_invoices_count + ' invoice (' + formatRupiah(data.financial.new_invoices_amount) + ')';
                modalNewInvoicesContainer.classList.remove('d-none');
                modalNewInvoicesList.replaceChildren();
                
                (data.financial.new_invoice_items || []).forEach(function (item) {
                    const div = document.createElement('div');
                    div.className = 'd-flex justify-content-between py-1 border-bottom border-light';
                    const spanPeriod = document.createElement('span');
                    spanPeriod.textContent = item.period_month_label;
                    const spanAmount = document.createElement('span');
                    spanAmount.className = 'fw-semibold';
                    spanAmount.textContent = formatRupiah(item.amount);
                    div.appendChild(spanPeriod);
                    div.appendChild(spanAmount);
                    modalNewInvoicesList.appendChild(div);
                });
            } else {
                modalNewInvoicesInfo.textContent = '0 invoice (seluruh tagihan sudah terbit)';
                modalNewInvoicesContainer.classList.add('d-none');
            }

            modalTotalUnpaidInfo.textContent = formatRupiah(data.financial.total_unpaid_obligation);

            if (data.financial.total_unpaid_obligation > 0) {
                modalObligationWarningAmount.textContent = formatRupiah(data.financial.total_unpaid_obligation);
                modalObligationWarning.classList.remove('d-none');
                modalObligationSuccess.classList.add('d-none');
            } else {
                modalObligationWarning.classList.add('d-none');
                modalObligationSuccess.classList.remove('d-none');
            }

            modalLoading.classList.add('d-none');
            modalContent.classList.remove('d-none');
            btnSubmit.disabled = false;
        } catch (err) {
            if (err.name === 'AbortError') return;
            modalLoading.classList.add('d-none');
            modalErrorText.textContent = 'Terjadi kesalahan koneksi saat memuat preview. Silakan coba kembali.';
            modalErrorAlert.classList.remove('d-none');
        }
    }

    modalElement.addEventListener('show.bs.modal', function () {
        loadPreview();
    });

    modalElement.addEventListener('hidden.bs.modal', function () {
        resetPreviewState();
    });

    if (btnRetry) {
        btnRetry.addEventListener('click', function () {
            loadPreview();
        });
    }

    if (formEnd) {
        formEnd.addEventListener('submit', function (e) {
            const reason = reasonInput.value.trim();
            if (reason.length < 5 || reason.length > 255) {
                e.preventDefault();
                alert('Alasan pengakhiran penempatan wajib diisi minimal 5 karakter non-spasi (maksimal 255 karakter).');
                reasonInput.focus();
                return;
            }

            if (!tokenInput.value) {
                e.preventDefault();
                alert('Sesi preview belum siap atau tidak valid. Silakan muat ulang preview.');
                return;
            }

            btnSubmit.disabled = true;
            btnSubmitSpinner.classList.remove('d-none');
            btnSubmitText.textContent = 'Memproses Pengakhiran...';
        });
    }

    @if($errors->has('end_reason') || session('error'))
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        } else {
            const trigger = document.getElementById('btn-open-end-modal');
            if (trigger) trigger.click();
        }
    @endif
});
</script>
@endpush
@endif
