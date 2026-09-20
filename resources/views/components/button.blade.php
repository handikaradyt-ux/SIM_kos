@props([
    'type' => 'button',
    'variant' => 'teal',
    'as' => 'button',
    'href' => null,
    'disabled' => false,
    'class' => '',
])

@php
    $baseClass = 'btn';
    $variantClass = match($variant) {
        'teal' => 'btn-teal',
        'outline-teal' => 'btn-outline-teal',
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'danger' => 'btn-danger',
        'outline-danger' => 'btn-outline-danger',
        'outline-secondary' => 'btn-outline-secondary',
        'light' => 'btn-light',
        default => 'btn-' . $variant,
    };
    $classes = trim("{$baseClass} {$variantClass} {$class}");
@endphp

@if($as === 'a')
    <a href="{{ $href }}" class="{{ $classes }}" @if($disabled) tabindex="-1" aria-disabled="true" @endif {{ $attributes }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" class="{{ $classes }}" @if($disabled) disabled @endif {{ $attributes }}>
        {{ $slot }}
    </button>
@endif
