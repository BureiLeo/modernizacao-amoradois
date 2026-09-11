@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center py-10 px-4']) }}>
    <div class="w-14 h-14 rounded-full bg-brand-soft/50 text-brand-primary flex items-center justify-center mb-3">
        <x-icon :name="$icon" class="w-6 h-6" />
    </div>
    <p class="font-semibold text-brand-text">{{ $title }}</p>
    @if ($description)
        <p class="text-sm text-brand-text-muted mt-1 max-w-sm">{{ $description }}</p>
    @endif

    @isset($actions)
        <div class="mt-4">{{ $actions }}</div>
    @endisset
</div>
