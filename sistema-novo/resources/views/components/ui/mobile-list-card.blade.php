@props([
    'title',
    'subtitle' => null,
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

{{-- Card usado como alternativa a tabela no mobile (Etapa 5 #19). --}}
<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'md:hidden flex items-center gap-3 p-4 bg-brand-surface border border-brand-border/40 rounded-brand-md']) }}
>
    <div class="min-w-0 flex-1">
        <p class="font-semibold text-brand-text truncate">{{ $title }}</p>
        @if ($subtitle)
            <p class="text-xs text-brand-text-muted mt-0.5 truncate">{{ $subtitle }}</p>
        @endif

        @isset($meta)
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-brand-text-muted">
                {{ $meta }}
            </div>
        @endisset
    </div>

    @isset($trailing)
        <div class="shrink-0 flex flex-col items-end gap-1.5">
            {{ $trailing }}
        </div>
    @endisset

    @if ($href)
        <x-icon name="chevron-right" class="w-4 h-4 text-brand-text-muted shrink-0" />
    @endif
</{{ $tag }}>
