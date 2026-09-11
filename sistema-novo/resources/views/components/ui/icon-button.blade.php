@props([
    'label', // obrigatorio: nome acessivel do botao (aria-label)
    'icon',
    'variant' => 'ghost', // ghost | primary | outline
    'size' => 'md', // sm | md
])

@php
    $sizes = [
        'sm' => 'w-8 h-8',
        'md' => 'w-11 h-11', // >=44px: touch target adequado
    ];

    $variants = [
        'ghost' => 'text-brand-text hover:bg-brand-soft/50',
        'primary' => 'bg-brand-primary text-brand-branco hover:bg-brand-primary-dark',
        'outline' => 'border border-brand-border text-brand-primary hover:bg-brand-soft/40',
    ];

    $classes = 'inline-flex items-center justify-center rounded-full transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-primary '
        .($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['ghost']);
@endphp

<button type="button" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
    <x-icon :name="$icon" class="w-5 h-5" />
</button>
