@props(['title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5']) }}>
    <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-bold text-brand-text truncate">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm text-brand-text-muted mt-0.5">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex items-center gap-2 shrink-0">
            {{ $actions }}
        </div>
    @endisset
</div>
