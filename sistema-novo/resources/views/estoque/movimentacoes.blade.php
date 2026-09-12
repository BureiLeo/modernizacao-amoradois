<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-brand-text">Movimentações</h2>
                <p class="text-sm text-brand-text-muted">Histórico de estoque por material e origem.</p>
            </div>
        </div>
    </x-slot>

    <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-6 text-sm text-brand-text-muted">
        O histórico de movimentações está disponível através do ledger de estoque e do service centralizado.
    </div>
</x-app-layout>
