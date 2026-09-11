<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2.5 bg-brand-primary border border-transparent rounded-brand-sm font-semibold text-sm text-brand-branco hover:bg-brand-primary-dark focus:bg-brand-primary-dark active:bg-brand-primary-dark focus:outline-none focus:ring-2 focus:ring-brand-primary focus:ring-offset-2 transition ease-in-out duration-150 min-h-[44px]']) }}>
    {{ $slot }}
</button>
