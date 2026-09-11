{{-- Wrapper de tabela para desktop. No mobile, prefira <x-ui.mobile-list-card>
     em vez de espremer esta tabela (Etapa 5 #19). --}}
<div {{ $attributes->merge(['class' => 'hidden md:block overflow-x-auto rounded-brand-md border border-brand-border/40']) }}>
    <table class="min-w-full divide-y divide-brand-border/30 text-sm">
        {{ $slot }}
    </table>
</div>
