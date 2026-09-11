@props([
    'label' => null,
    'name',
    'error' => null,
    'helper' => null,
    'type' => 'text',
])

@php
    $errorMessage = $error ?? ($errors->has($name) ? $errors->first($name) : null);
@endphp

<div>
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-brand-text mb-1.5">{{ $label }}</label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->merge(['class' =>
            'block w-full rounded-brand-sm border-brand-border/70 bg-brand-surface text-brand-text placeholder:text-brand-text-muted/70 '
            .'focus:border-brand-primary focus:ring-brand-primary min-h-[44px] '
            .($errorMessage ? 'border-brand-danger focus:border-brand-danger focus:ring-brand-danger' : '')
        ]) }}
        @if($errorMessage) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
    />

    @if ($helper && ! $errorMessage)
        <p class="mt-1.5 text-xs text-brand-text-muted">{{ $helper }}</p>
    @endif

    @if ($errorMessage)
        <p id="{{ $name }}-error" class="mt-1.5 text-xs text-brand-danger">{{ $errorMessage }}</p>
    @endif
</div>
