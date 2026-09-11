@props([
    'label' => null,
    'name',
    'error' => null,
    'helper' => null,
    'options' => [], // ['value' => 'label'] ou lista de objetos com value/label via slot
    'placeholder' => null,
])

@php
    $errorMessage = $error ?? ($errors->has($name) ? $errors->first($name) : null);
@endphp

<div>
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-brand-text mb-1.5">{{ $label }}</label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->merge(['class' =>
            'block w-full rounded-brand-sm border-brand-border/70 bg-brand-surface text-brand-text '
            .'focus:border-brand-primary focus:ring-brand-primary min-h-[44px] '
            .($errorMessage ? 'border-brand-danger focus:border-brand-danger focus:ring-brand-danger' : '')
        ]) }}
    >
        @if ($placeholder)
            <option value="" disabled selected>{{ $placeholder }}</option>
        @endif

        {{ $slot }}

        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}">{{ $optionLabel }}</option>
        @endforeach
    </select>

    @if ($helper && ! $errorMessage)
        <p class="mt-1.5 text-xs text-brand-text-muted">{{ $helper }}</p>
    @endif

    @if ($errorMessage)
        <p class="mt-1.5 text-xs text-brand-danger">{{ $errorMessage }}</p>
    @endif
</div>
