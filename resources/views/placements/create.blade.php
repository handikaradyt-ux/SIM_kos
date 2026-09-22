@extends('layouts.app')

@section('title', 'Mulai Penempatan Baru')
@section('page_title', 'Mulai Penempatan Baru')
@section('page_subtitle', 'Tempatkan penghuni ke kamar yang tersedia. Penempatan, invoice periode pertama, dan audit log akan diproses secara atomik.')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">

        <div class="card simkos-card shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <h2 class="h6 fw-bold mb-0 text-dark">Formulir Penempatan Kamar</h2>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                        Langkah 1 dari 2: Pilih & Preview
                    </span>
                </div>
            </div>

            <div class="card-body p-4">
                {{-- Info Operasional Server --}}
                <div class="alert alert-info py-2 px-3 mb-4 small" role="alert">
                    <div class="d-flex align-items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle-fill flex-shrink-0 me-2 text-info" viewBox="0 0 16 16">
                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                        </svg>
                        <div>
                            <strong>Tanggal Mulai Operasional:</strong> Server menetapkan tanggal mulai hari ini, 
                            <span class="fw-bold text-dark">{{ now('Asia/Jakarta')->translatedFormat('d F Y') }}</span> (WIB).
                            Invoice periode pertama langsung diterbitkan tanpa prorata sesuai tarif bulanan kamar.
                        </div>
                    </div>
                </div>

                {{-- Client Error Container --}}
                <div id="preview-error-alert" class="alert alert-danger d-none mb-4" role="alert">
                    <div class="d-flex align-items-start">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-exclamation-circle-fill flex-shrink-0 me-2 mt-1" viewBox="0 0 16 16">
                            <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                        </svg>
                        <div id="preview-error-message" class="small"></div>
                    </div>
                </div>

                <form id="placement-form" method="POST" action="{{ route('placements.store') }}">
                    @csrf
                    <input type="hidden" name="preview_token" id="preview_token_input" value="">

                    {{-- Pilihan Kamar --}}
                    <div class="mb-3">
                        <label for="room_id" class="form-label fw-semibold">
                            Pilih Kamar Kosong <span class="text-danger">*</span>
                        </label>
                        <select name="room_id" id="room_id" class="form-select @error('room_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Kamar yang Tersedia --</option>
                            @foreach($availableRooms as $room)
                                <option 
                                    value="{{ $room->id }}" 
                                    data-rate="{{ $room->monthly_rate }}"
                                    data-number="{{ $room->number }}"
                                    data-type="{{ $room->type }}"
                                    {{ old('room_id') == $room->id ? 'selected' : '' }}
                                >
                                    Kamar {{ $room->number }} ({{ ucfirst($room->type) }}) - Rp {{ number_format($room->monthly_rate, 0, ',', '.') }}/bln
                                </option>
                            @endforeach
                        </select>
                        @error('room_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if($availableRooms->isEmpty())
                            <div class="form-text text-danger">
                                <strong>Perhatian:</strong> Tidak ada kamar kosong yang siap huni saat ini.
                            </div>
                        @else
                            <div class="form-text text-muted">
                                Hanya menampilkan kamar aktif yang belum ditempati ({{ $availableRooms->count() }} kamar tersedia).
                            </div>
                        @endif
                    </div>

                    {{-- Pilihan Penghuni --}}
                    <div class="mb-4">
                        <label for="resident_id" class="form-label fw-semibold">
                            Pilih Penghuni <span class="text-danger">*</span>
                        </label>
                        <select name="resident_id" id="resident_id" class="form-select @error('resident_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Penghuni Terdaftar --</option>
                            @foreach($availableResidents as $resident)
                                <option 
                                    value="{{ $resident->id }}"
                                    data-name="{{ $resident->name }}"
                                    data-phone="{{ $resident->phone }}"
                                    data-email="{{ $resident->user?->email }}"
                                    {{ old('resident_id') == $resident->id ? 'selected' : '' }}
                                >
                                    {{ $resident->name }} ({{ $resident->phone }}) - {{ $resident->user?->email }}
                                </option>
                            @endforeach
                        </select>
                        @error('resident_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if($availableResidents->isEmpty())
                            <div class="form-text text-danger">
                                <strong>Perhatian:</strong> Tidak ada penghuni aktif yang belum memiliki kamar.
                            </div>
                        @else
                            <div class="form-text text-muted">
                                Hanya menampilkan profil penghuni aktif dengan akun ber-role resident tanpa penempatan aktif ({{ $availableResidents->count() }} penghuni).
                            </div>
                        @endif
                    </div>

                    {{-- Tombol Aksi --}}
                    <div class="d-flex gap-2 justify-content-between pt-3 border-top">
                        <a href="{{ route('placements.index') }}" class="btn btn-outline-secondary">
                            Batal
                        </a>
                        <button 
                            type="button" 
                            id="btn-preview" 
                            class="btn btn-teal d-inline-flex align-items-center gap-2"
                            {{ ($availableRooms->isEmpty() || $availableResidents->isEmpty()) ? 'disabled' : '' }}
                        >
                            <span id="btn-preview-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <span id="btn-preview-text">Lihat Preview & Konfirmasi</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Hidden trigger button for modal fallback --}}
<button type="button" id="btn-trigger-modal" class="d-none" data-bs-toggle="modal" data-bs-target="#previewModal"></button>

{{-- Modal Konfirmasi Penempatan (Server-Verified Preview) --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark" id="previewModalLabel">
                    Konfirmasi Penempatan & Tagihan Pertama
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Same Month Warning Alert --}}
                <div id="modal-same-month-warning" class="alert alert-warning d-none mb-3" role="alert">
                    <div class="d-flex">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                        </svg>
                        <div>
                            <strong>Peringatan Tagihan Bulan Berjalan:</strong><br>
                            Penghuni ini memiliki riwayat penempatan yang telah selesai pada bulan ini. Sesuai aturan SIM Kos, penempatan baru tetap menerbitkan tagihan penuh untuk bulan berjalan tanpa prorata.
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    {{-- Kartu Penghuni --}}
                    <div class="col-12 col-md-6">
                        <div class="card h-100 border bg-light">
                            <div class="card-body p-3">
                                <h6 class="fw-bold text-dark border-bottom pb-2 mb-2">Data Penghuni</h6>
                                <div class="mb-1">
                                    <span class="text-muted small d-block">Nama Lengkap:</span>
                                    <span id="modal-resident-name" class="fw-semibold text-dark"></span>
                                </div>
                                <div class="mb-1">
                                    <span class="text-muted small d-block">Nomor Telepon:</span>
                                    <span id="modal-resident-phone" class="text-dark"></span>
                                </div>
                                <div>
                                    <span class="text-muted small d-block">Email Akun:</span>
                                    <span id="modal-resident-email" class="text-dark small"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Kartu Kamar --}}
                    <div class="col-12 col-md-6">
                        <div class="card h-100 border bg-light">
                            <div class="card-body p-3">
                                <h6 class="fw-bold text-dark border-bottom pb-2 mb-2">Kamar yang Ditempati</h6>
                                <div class="mb-1">
                                    <span class="text-muted small d-block">Nomor Kamar:</span>
                                    <span id="modal-room-number" class="fw-semibold text-dark"></span>
                                </div>
                                <div class="mb-1">
                                    <span class="text-muted small d-block">Tipe Kamar:</span>
                                    <span id="modal-room-type" class="text-dark"></span>
                                </div>
                                <div>
                                    <span class="text-muted small d-block">Tarif Bulanan Standar:</span>
                                    <span id="modal-room-rate" class="fw-bold text-dark"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Rincian Kontrak & Proyeksi Invoice Pertama --}}
                    <div class="col-12">
                        <div class="card border border-primary border-opacity-25 bg-primary bg-opacity-10">
                            <div class="card-body p-3">
                                <h6 class="fw-bold text-primary border-bottom border-primary border-opacity-25 pb-2 mb-3">
                                    Rincian Kontrak Sewa & Penerbitan Invoice Pertama
                                </h6>
                                <div class="row g-2">
                                    <div class="col-12 col-sm-6">
                                        <div class="small text-muted">Tanggal Mulai Sewa:</div>
                                        <div id="modal-contract-start" class="fw-bold text-dark"></div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <div class="small text-muted">Tarif Kontrak Disepakati:</div>
                                        <div id="modal-contract-rate" class="fw-bold text-dark"></div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <div class="small text-muted">Periode Invoice Pertama:</div>
                                        <div id="modal-invoice-period" class="fw-bold text-dark"></div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <div class="small text-muted">Jatuh Tempo Pembayaran:</div>
                                        <div id="modal-invoice-due" class="fw-bold text-danger"></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="small text-muted">Nominal Invoice Pertama:</div>
                                        <div id="modal-invoice-amount" class="h5 fw-bold text-primary mb-0"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-secondary py-2 px-3 mt-3 mb-0 small text-muted">
                    Dengan menekan tombol di bawah, sistem akan mengunci kamar & penghuni, menyimpan penempatan, menerbitkan invoice pertama, serta mencatat audit trail secara otomatis.
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Batal / Ubah Pilihan
                </button>
                <button type="button" id="btn-submit-placement" class="btn btn-teal d-inline-flex align-items-center gap-2" disabled>
                    <span id="btn-submit-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    <span id="btn-submit-text">Konfirmasi & Mulai Penempatan</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btnPreview = document.getElementById('btn-preview');
    const btnPreviewSpinner = document.getElementById('btn-preview-spinner');
    const btnPreviewText = document.getElementById('btn-preview-text');
    const form = document.getElementById('placement-form');
    const roomSelect = document.getElementById('room_id');
    const residentSelect = document.getElementById('resident_id');
    const previewTokenInput = document.getElementById('preview_token_input');
    const previewErrorAlert = document.getElementById('preview-error-alert');
    const previewErrorMessage = document.getElementById('preview-error-message');

    const btnSubmit = document.getElementById('btn-submit-placement');
    const btnSubmitSpinner = document.getElementById('btn-submit-spinner');
    const btnSubmitText = document.getElementById('btn-submit-text');

    const modalElement = document.getElementById('previewModal');
    let requestSequence = 0;
    let activeAbortController = null;

    function openPreviewModal() {
        const trigger = document.getElementById('btn-trigger-modal');
        if (trigger) {
            trigger.click();
        } else if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
    }

    function closePreviewModal() {
        const closeBtn = modalElement ? modalElement.querySelector('[data-bs-dismiss="modal"]') : null;
        if (closeBtn) {
            closeBtn.click();
        } else if (window.bootstrap && window.bootstrap.Modal) {
            const instance = window.bootstrap.Modal.getInstance(modalElement);
            if (instance) instance.hide();
        }
    }

    function formatRupiah(number) {
        return 'Rp ' + Number(number).toLocaleString('id-ID');
    }

    function hideError() {
        previewErrorAlert.classList.add('d-none');
        previewErrorMessage.textContent = '';
    }

    function showError(message) {
        previewErrorMessage.textContent = message;
        previewErrorAlert.classList.remove('d-none');
        previewErrorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function setPreviewLoading(loading) {
        if (loading) {
            btnPreview.disabled = true;
            btnPreviewSpinner.classList.remove('d-none');
            btnPreviewText.textContent = 'Memuat Preview...';
        } else {
            btnPreview.disabled = false;
            btnPreviewSpinner.classList.add('d-none');
            btnPreviewText.textContent = 'Lihat Preview & Konfirmasi';
        }
    }

    /**
     * Invalidate any running preview request, clear stored preview tokens,
     * disable confirmation, close modal if open, and restore UI to ready state.
     */
    function invalidatePreviewState() {
        // Invalidate in-flight promises by bumping sequence
        requestSequence++;

        // Cancel running HTTP fetch if active
        if (activeAbortController) {
            try {
                activeAbortController.abort();
            } catch (e) {
                // Ignore abort exceptions
            }
            activeAbortController = null;
        }

        // Invalidate preview token
        previewTokenInput.value = '';

        // Disable modal submit confirmation button
        btnSubmit.disabled = true;
        btnSubmitSpinner.classList.add('d-none');
        btnSubmitText.textContent = 'Konfirmasi & Mulai Penempatan';

        // Close preview modal if it happens to be open
        closePreviewModal();

        // Restore preview button loading state
        setPreviewLoading(false);

        // Hide any previous error
        hideError();
    }

    // Invalidate state immediately whenever selection changes
    roomSelect.addEventListener('change', invalidatePreviewState);
    residentSelect.addEventListener('change', invalidatePreviewState);

    btnPreview.addEventListener('click', async function () {
        hideError();

        const roomIdStr = roomSelect.value;
        const residentIdStr = residentSelect.value;

        if (!roomIdStr) {
            showError('Silakan pilih kamar yang tersedia terlebih dahulu.');
            roomSelect.focus();
            return;
        }

        if (!residentIdStr) {
            showError('Silakan pilih penghuni yang terdaftar terlebih dahulu.');
            residentSelect.focus();
            return;
        }

        const requestedRoomId = parseInt(roomIdStr, 10);
        const requestedResidentId = parseInt(residentIdStr, 10);

        // Abort previous in-flight fetch if any
        if (activeAbortController) {
            try {
                activeAbortController.abort();
            } catch (e) {}
            activeAbortController = null;
        }

        const currentSeq = ++requestSequence;
        activeAbortController = new AbortController();

        // Invalidate token and disable confirm button until verified
        previewTokenInput.value = '';
        btnSubmit.disabled = true;
        setPreviewLoading(true);

        try {
            // Optional test delay support via URL parameter e.g. /placements/create?delay=2000
            const urlParams = new URLSearchParams(window.location.search);
            const testDelay = parseInt(urlParams.get('delay') || '0', 10);

            const payload = {
                room_id: requestedRoomId,
                resident_id: requestedResidentId
            };
            if (testDelay > 0) {
                payload._delay_ms = testDelay;
            }

            const response = await fetch("{{ route('placements.preview') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                },
                body: JSON.stringify(payload),
                signal: activeAbortController.signal
            });

            // Guard 1: Verify sequence and current selection before response.json()
            if (
                currentSeq !== requestSequence ||
                parseInt(roomSelect.value, 10) !== requestedRoomId ||
                parseInt(residentSelect.value, 10) !== requestedResidentId
            ) {
                return;
            }

            const data = await response.json();

            // Guard 2: Verify sequence and current selection after await response.json()
            if (
                currentSeq !== requestSequence ||
                parseInt(roomSelect.value, 10) !== requestedRoomId ||
                parseInt(residentSelect.value, 10) !== requestedResidentId
            ) {
                return;
            }

            if (!response.ok || !data.success) {
                const message = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Terjadi kesalahan saat memproses preview.');
                showError(message);
                return;
            }

            const preview = data.data;

            // Guard 3: Verify payload matching requested entities
            if (
                !preview ||
                parseInt(preview.room?.id, 10) !== requestedRoomId ||
                parseInt(preview.resident?.id, 10) !== requestedResidentId ||
                !preview.preview_token
            ) {
                return;
            }

            // Secure rendering using textContent to prevent XSS
            document.getElementById('modal-resident-name').textContent = preview.resident.name || '-';
            document.getElementById('modal-resident-phone').textContent = preview.resident.phone || '-';
            document.getElementById('modal-resident-email').textContent = preview.resident.email || '-';

            document.getElementById('modal-room-number').textContent = 'Kamar ' + (preview.room.number || '-');
            document.getElementById('modal-room-type').textContent = preview.room.type ? preview.room.type.toUpperCase() : '-';
            document.getElementById('modal-room-rate').textContent = formatRupiah(preview.room.monthly_rate) + ' / bulan';

            document.getElementById('modal-contract-start').textContent = preview.contract.started_on_formatted || preview.contract.started_on;
            document.getElementById('modal-contract-rate').textContent = formatRupiah(preview.contract.agreed_monthly_rate) + ' / bulan';

            document.getElementById('modal-invoice-period').textContent = preview.first_invoice.period_month_formatted || preview.first_invoice.period_month;
            document.getElementById('modal-invoice-due').textContent = preview.first_invoice.due_on_formatted || preview.first_invoice.due_on;
            document.getElementById('modal-invoice-amount').textContent = formatRupiah(preview.first_invoice.amount);

            // Same month warning
            const sameMonthWarning = document.getElementById('modal-same-month-warning');
            if (preview.same_month_warning) {
                sameMonthWarning.classList.remove('d-none');
            } else {
                sameMonthWarning.classList.add('d-none');
            }

            // Save preview token in form input
            previewTokenInput.value = preview.preview_token;

            // Enable confirmation button now that preview is valid & matching
            btnSubmit.disabled = false;

            // Open modal
            openPreviewModal();

        } catch (error) {
            // Ignore intentional abort errors from input switches
            if (error.name === 'AbortError') {
                return;
            }
            if (currentSeq === requestSequence) {
                showError('Koneksi jaringan bermasalah. Gagal memuat data preview.');
            }
        } finally {
            if (currentSeq === requestSequence) {
                setPreviewLoading(false);
                activeAbortController = null;
            }
        }
    });

    // Handle submit from modal with disable-on-click protection
    btnSubmit.addEventListener('click', function () {
        if (!previewTokenInput.value || btnSubmit.disabled) {
            closePreviewModal();
            showError('Sesi preview tidak valid. Silakan lakukan preview ulang.');
            return;
        }

        btnSubmit.disabled = true;
        btnSubmitSpinner.classList.remove('d-none');
        btnSubmitText.textContent = 'Memproses Penempatan...';

        // Submit form
        form.submit();
    });
});
</script>
@endpush
