<div class="space-y-4">
    <x-ui.page-header title="Material: {{ $material->nome }}" subtitle="Detalhes e movimentações.">
        <x-slot name="actions">
            <x-ui.button variant="outline" :href="route('estoque.index')" wire:navigate>Voltar ao estoque</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="text-sm text-brand-text-muted">Unidade</div>
                <x-ui.status-badge :status="$material->stockStatus()" />
            </div>
            <div class="mt-1 text-lg font-semibold text-brand-text">{{ $material->unidade_base }}</div>
            <div class="mt-4 text-sm text-brand-text-muted">Estoque atual</div>
            <div class="mt-1 text-lg font-semibold text-brand-text">{{ \App\Support\Format::quantidade($material->estoque) }} {{ $material->unidade_base }}</div>
        </div>

        <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
            <div class="text-sm text-brand-text-muted">Estoque mínimo</div>
            <div class="mt-1 text-lg font-semibold text-brand-text">{{ \App\Support\Format::quantidade($material->estoque_minimo) }} {{ $material->unidade_base }}</div>
            <div class="mt-4 text-sm text-brand-text-muted">Custo médio</div>
            <div class="mt-1 text-lg font-semibold text-brand-text">R$ {{ \App\Support\Format::dinheiro($material->custo_medio) }}</div>
        </div>
    </div>

    <div class="flex gap-3">
        <a href="{{ route('materiais.edit', $material) }}" wire:navigate class="rounded-brand-sm bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white">Editar</a>
        <button type="button" wire:click="excluir" class="rounded-brand-sm border border-red-500/60 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-600">Excluir</button>
    </div>

    <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
        <div class="mb-3 text-sm font-medium text-brand-text-muted">Movimentações recentes</div>
        @forelse ($movimentacoes as $mov)
            <div class="flex items-center justify-between border-b border-brand-border/50 py-2 last:border-0">
                <div>
                    <div class="font-medium text-brand-text">{{ $mov->type?->label() ?? $mov->type }}</div>
                    <div class="text-xs text-brand-text-muted">{{ $mov->created_at?->format('d/m/Y H:i') }}</div>
                </div>
                <div class="text-sm font-semibold {{ $mov->quantity >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $mov->quantity >= 0 ? '+' : '' }}{{ \App\Support\Format::quantidade($mov->quantity) }}
                </div>
            </div>
        @empty
            <div class="text-sm text-brand-text-muted">Nenhuma movimentação registrada.</div>
        @endforelse
    </div>
</div>
