@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-brand-text']) }}>
    {{ $value ?? $slot }}
</label>
