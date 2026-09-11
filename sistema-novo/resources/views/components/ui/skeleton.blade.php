@props(['lines' => 3])

<div {{ $attributes->merge(['class' => 'animate-pulse space-y-2']) }} role="status" aria-label="Carregando">
    @for ($i = 0; $i < $lines; $i++)
        <div class="h-4 bg-brand-soft/50 rounded-brand-sm" style="width: {{ $i === $lines - 1 ? '60%' : '100%' }}"></div>
    @endfor
    <span class="sr-only">Carregando...</span>
</div>
