@props(['name' => 'search', 'placeholder' => 'Buscar...'])

<div class="relative">
    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-brand-text-muted pointer-events-none">
        <x-icon name="search" class="w-4 h-4" />
    </span>
    <input
        type="search"
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' =>
            'block w-full rounded-full border-brand-border/70 bg-brand-surface text-brand-text placeholder:text-brand-text-muted/70 '
            .'focus:border-brand-primary focus:ring-brand-primary pl-9 min-h-[44px]'
        ]) }}
    />
</div>
