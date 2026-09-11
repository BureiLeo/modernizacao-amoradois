@props([
    'variant' => 'primary', // primary | secondary | outline | ghost | danger
    'size' => 'md', // sm | md | lg
    'href' => null,
    'loading' => false,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-medium rounded-brand-sm transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-primary disabled:opacity-50 disabled:cursor-not-allowed select-none';

    $sizes = [
        'sm' => 'text-sm px-3 py-2 min-h-[36px]',
        'md' => 'text-sm px-4 py-2.5 min-h-[44px]', // 44px = touch target adequado no mobile
        'lg' => 'text-base px-5 py-3 min-h-[48px]',
    ];

    $variants = [
        'primary' => 'bg-brand-primary text-brand-branco hover:bg-brand-primary-dark active:bg-brand-primary-dark',
        'secondary' => 'bg-brand-soft text-brand-text hover:bg-brand-rose-antigo/60',
        'outline' => 'border border-brand-border text-brand-primary bg-transparent hover:bg-brand-soft/40',
        'ghost' => 'text-brand-primary bg-transparent hover:bg-brand-soft/40',
        'danger' => 'bg-brand-danger text-brand-branco hover:bg-brand-danger/90',
    ];

    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($loading)
            <x-icon name="loader-2" class="w-4 h-4 animate-spin" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} @if($loading) disabled @endif>
        @if ($loading)
            <x-icon name="loader-2" class="w-4 h-4 animate-spin" />
        @endif
        {{ $slot }}
    </button>
@endif
