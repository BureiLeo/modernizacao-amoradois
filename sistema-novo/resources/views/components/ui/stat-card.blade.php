@props([
    'label',
    'value',
    'icon' => 'info',
    'subtitle' => null,
    'trend' => null, // ['direction' => 'up'|'down', 'text' => '+12%']
])

<x-ui.card class="min-w-0">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm text-brand-text-muted leading-snug">{{ $label }}</p>
            <p class="mt-1 text-xl sm:text-2xl font-bold text-brand-text">{{ $value }}</p>

            @if ($subtitle)
                <p class="mt-1 text-xs text-brand-text-muted">{{ $subtitle }}</p>
            @endif

            @if ($trend)
                <p @class([
                    'mt-1 inline-flex items-center gap-1 text-xs font-semibold',
                    'text-brand-success' => $trend['direction'] === 'up',
                    'text-brand-danger' => $trend['direction'] === 'down',
                ])>
                    <x-icon :name="$trend['direction'] === 'up' ? 'trending-up' : 'trending-down'" class="w-3.5 h-3.5" />
                    {{ $trend['text'] }}
                </p>
            @endif
        </div>

        <div class="shrink-0 flex flex-col items-end gap-2">
            <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-brand-sm bg-brand-primary/10 text-brand-primary flex items-center justify-center">
                <x-icon :name="$icon" class="w-4 h-4 sm:w-5 sm:h-5" />
            </div>

            {{ $toggle ?? '' }}
        </div>
    </div>
</x-ui.card>
