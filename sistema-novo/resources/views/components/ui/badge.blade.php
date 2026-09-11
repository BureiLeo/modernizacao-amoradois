@props([
    'variant' => 'neutral', // neutral | success | warning | danger | info | primary
])

@php
    $variants = [
        'neutral' => 'bg-brand-soft/60 text-brand-text',
        'success' => 'bg-brand-success-soft text-brand-success',
        'warning' => 'bg-brand-warning-soft text-brand-warning',
        'danger' => 'bg-brand-danger-soft text-brand-danger',
        'info' => 'bg-brand-info-soft text-brand-info',
        'primary' => 'bg-brand-primary/10 text-brand-primary',
    ];

    $classes = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap '
        .($variants[$variant] ?? $variants['neutral']);
@endphp

{{-- O texto do status e sempre visivel (nao depende so da cor) --}}
<span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
