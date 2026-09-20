@props([
    'variant' => 'secondary',
    'prefix' => null,
])

@php
    $badgeClass = match($variant) {
        'admin' => 'badge-role-admin',
        'owner' => 'badge-role-owner',
        'resident' => 'badge-role-resident',
        'success' => 'bg-success text-white',
        'warning' => 'bg-warning text-dark',
        'danger' => 'bg-danger text-white',
        'info' => 'bg-info text-dark',
        'secondary' => 'bg-secondary text-white',
        'teal' => 'bg-teal text-white',
        default => 'bg-' . $variant,
    };
@endphp

<span class="badge {{ $badgeClass }} {{ $attributes->get('class') }}" {{ $attributes->except('class') }}>
    @if($prefix)
        <span class="visually-hidden">{{ $prefix }}: </span>
    @endif
    {{ $slot }}
</span>
