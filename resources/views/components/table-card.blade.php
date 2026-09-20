@props([
    'title' => null,
])

<div class="simkos-card">
    @if($title)
        <div class="simkos-card-header">
            <span>{{ $title }}</span>
            @isset($actions)
                <div>{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div class="table-responsive">
        {{ $slot }}
    </div>
</div>
