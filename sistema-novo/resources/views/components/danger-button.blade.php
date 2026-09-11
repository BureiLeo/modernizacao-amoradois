<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2.5 bg-brand-danger border border-transparent rounded-brand-sm font-semibold text-sm text-brand-branco hover:bg-brand-danger/90 focus:outline-none focus:ring-2 focus:ring-brand-danger focus:ring-offset-2 transition ease-in-out duration-150 min-h-[44px]']) }}>
    {{ $slot }}
</button>
