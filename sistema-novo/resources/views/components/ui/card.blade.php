@props(['padded' => true])

<div {{ $attributes->merge(['class' => 'bg-brand-surface border border-brand-border/40 rounded-brand-md shadow-brand-sm '.($padded ? 'p-4 sm:p-5' : '')]) }}>
    {{ $slot }}
</div>
