@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-brand-border/70 bg-brand-surface text-brand-text focus:border-brand-primary focus:ring-brand-primary rounded-brand-sm shadow-sm min-h-[44px]']) }}>
