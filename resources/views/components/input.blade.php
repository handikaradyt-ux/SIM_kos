@props([
    'name',
    'label',
    'type' => 'text',
    'id' => null,
    'value' => null,
    'placeholder' => '',
    'required' => false,
    'autofocus' => false,
    'autocomplete' => null,
    'helper' => null,
    'disabled' => false,
    'readonly' => false,
])

@php
    $inputId = $id ?? $name;
    $isPassword = in_array(strtolower($name), ['password', 'current_password', 'password_confirmation', 'new_password']) || strtolower($type) === 'password';
    $computedValue = $isPassword ? '' : old($name, $value ?? '');
    $hasError = $errors->has($name);

    $describedBy = [];
    if ($helper) {
        $describedBy[] = $inputId . '-help';
    }
    if ($hasError) {
        $describedBy[] = $inputId . '-error';
    }
    $describedByString = !empty($describedBy) ? implode(' ', $describedBy) : null;
@endphp

<div class="mb-3">
    <label for="{{ $inputId }}" class="form-label">
        {{ $label }}
        @if($required)
            <span class="required-mark" aria-hidden="true">*</span>
            <span class="visually-hidden">(wajib diisi)</span>
        @endif
    </label>

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $inputId }}"
        class="form-control @if($hasError) is-invalid @endif"
        @if(!$isPassword && $computedValue !== '') value="{{ $computedValue }}" @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($describedByString) aria-describedby="{{ $describedByString }}" @endif
        @if($hasError) aria-invalid="true" @endif
        @if($required) required @endif
        @if($autofocus) autofocus @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
    >

    @if($helper)
        <div id="{{ $inputId }}-help" class="form-text">
            {{ $helper }}
        </div>
    @endif

    @if($hasError)
        <div id="{{ $inputId }}-error" class="invalid-feedback d-block" role="alert">
            {{ $errors->first($name) }}
        </div>
    @endif
</div>
