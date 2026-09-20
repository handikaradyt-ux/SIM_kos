@props(['dismissible' => true])

@if(session('status') || session('success'))
    <div class="alert alert-success {{ $dismissible ? 'alert-dismissible fade show' : '' }}" role="alert">
        <div class="d-flex align-items-center">
            <span class="badge bg-success me-2" aria-hidden="true">[Sukses]</span>
            <div>{{ session('status') ?? session('success') }}</div>
        </div>
        @if($dismissible)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        @endif
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger {{ $dismissible ? 'alert-dismissible fade show' : '' }}" role="alert">
        <div class="d-flex align-items-center">
            <span class="badge bg-danger me-2" aria-hidden="true">[Kesalahan]</span>
            <div>{{ session('error') }}</div>
        </div>
        @if($dismissible)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        @endif
    </div>
@endif

@if(session('warning'))
    <div class="alert alert-warning {{ $dismissible ? 'alert-dismissible fade show' : '' }}" role="alert">
        <div class="d-flex align-items-center">
            <span class="badge bg-warning text-dark me-2" aria-hidden="true">[Peringatan]</span>
            <div>{{ session('warning') }}</div>
        </div>
        @if($dismissible)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        @endif
    </div>
@endif

@if(session('info'))
    <div class="alert alert-info {{ $dismissible ? 'alert-dismissible fade show' : '' }}" role="alert">
        <div class="d-flex align-items-center">
            <span class="badge bg-info text-dark me-2" aria-hidden="true">[Informasi]</span>
            <div>{{ session('info') }}</div>
        </div>
        @if($dismissible)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        @endif
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger {{ $dismissible ? 'alert-dismissible fade show' : '' }}" role="alert">
        <div class="d-flex align-items-start">
            <span class="badge bg-danger me-2 mt-1" aria-hidden="true">[Kesalahan]</span>
            <div>
                <div class="fw-semibold mb-1">Terdapat kesalahan pada isian form:</div>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @if($dismissible)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        @endif
    </div>
@endif
