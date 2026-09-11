@props([
    'variant' => 'info', // success | warning | danger | info
    'dismissible' => false,
])

@php
    $variants = [
        'success' => ['bg' => 'bg-brand-success-soft', 'text' => 'text-brand-success', 'icon' => 'check-circle'],
        'warning' => ['bg' => 'bg-brand-warning-soft', 'text' => 'text-brand-warning', 'icon' => 'alert-triangle'],
        'danger' => ['bg' => 'bg-brand-danger-soft', 'text' => 'text-brand-danger', 'icon' => 'alert-triangle'],
        'info' => ['bg' => 'bg-brand-info-soft', 'text' => 'text-brand-info', 'icon' => 'info'],
    ];
    $v = $variants[$variant] ?? $variants['info'];
@endphp

<div
    @if($dismissible) x-data="{ show: true }" x-show="show" @endif
    role="alert"
    {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-brand-sm p-4 {$v['bg']} {$v['text']}"]) }}
>
    <x-icon :name="$v['icon']" class="w-5 h-5 shrink-0 mt-0.5" />
    <div class="flex-1 text-sm">{{ $slot }}</div>

    @if ($dismissible)
        <button type="button" @click="show = false" aria-label="Fechar aviso" class="shrink-0 opacity-70 hover:opacity-100">
            <x-icon name="x" class="w-4 h-4" />
        </button>
    @endif
</div>
