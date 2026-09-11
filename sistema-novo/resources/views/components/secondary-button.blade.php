<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2.5 bg-brand-surface border border-brand-border/70 rounded-brand-sm font-semibold text-sm text-brand-text shadow-sm hover:bg-brand-soft/30 focus:outline-none focus:ring-2 focus:ring-brand-primary focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150 min-h-[44px]']) }}>
    {{ $slot }}
</button>
