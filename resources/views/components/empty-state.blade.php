@props([
    'title' => 'Informasi Belum Tersedia',
    'description' => 'Fitur dan data untuk modul ini belum tersedia pada tahap ini.',
])

<div class="simkos-empty-state">
    <div class="simkos-empty-state-icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
        </svg>
    </div>
    <h2 class="simkos-empty-state-title">{{ $title }}</h2>
    <p class="simkos-empty-state-text">{{ $description }}</p>
    @if($slot->isNotEmpty())
        <div class="mt-3">
            {{ $slot }}
        </div>
    @endif
</div>
