<div>
    <x-ui.page-header title="Estoque" subtitle="{{ $materiais->total() }} material(is) cadastrado(s).">
        <x-slot name="actions">
            <x-ui.button variant="outline" :href="route('compras.index')" wire:navigate>
                <x-icon name="truck" class="w-4 h-4" />
                Compras
            </x-ui.button>
            <x-ui.button variant="secondary" :href="route('compras.create')" wire:navigate>
                <x-icon name="plus" class="w-4 h-4" />
                Cadastrar compra
            </x-ui.button>
            <x-ui.button :href="route('materiais.create')" wire:navigate>
                <x-icon name="plus" class="w-4 h-4" />
                Novo material
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>

    @if (session('success'))
        <x-ui.alert variant="success" class="mb-4">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="mb-4">
        <x-ui.search-input wire:model.live.debounce.400ms="search" placeholder="Buscar material..." />
    </div>

    @php
        $abas = [
            'todos' => 'Todos',
            'baixo' => 'Estoque baixo'.($totalBaixo > 0 ? ' ('.$totalBaixo.')' : ''),
            'sem_estoque' => 'Sem estoque'.($totalSemEstoque > 0 ? ' ('.$totalSemEstoque.')' : ''),
        ];
    @endphp

    <div class="flex gap-2 overflow-x-auto mb-4">
        @foreach ($abas as $value => $label)
            <button
                type="button"
                wire:click="$set('filtro', '{{ $value }}')"
                @class([
                    'px-3 py-2 rounded-brand-sm text-sm font-medium whitespace-nowrap transition-colors',
                    'bg-brand-primary text-brand-branco' => $filtro === $value,
                    'bg-brand-surface border border-brand-border/50 text-brand-text hover:bg-brand-soft/30' => $filtro !== $value,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    @if ($materiais->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="archive"
                title="Nenhum material encontrado"
                :description="$search !== '' || $filtro !== 'todos' ? 'Tente ajustar sua busca ou filtro.' : 'Cadastre o primeiro insumo para começar a controlar o estoque.'"
            >
                <x-slot name="actions">
                    <x-ui.button :href="route('materiais.create')" wire:navigate variant="outline">Novo material</x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($materiais as $material)
                <a href="{{ route('materiais.show', $material) }}" wire:navigate class="block rounded-2xl border border-brand-border/70 bg-brand-surface p-4 transition hover:border-brand-primary/60 hover:shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-brand-text">{{ $material->nome }}</div>
                            <div class="text-xs text-brand-text-muted">{{ $material->unidade_base }}</div>
                        </div>
                        <x-ui.status-badge :status="$material->stockStatus()" />
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-sm text-brand-text-muted">
                        <div>Estoque</div>
                        <div class="text-right font-medium text-brand-text">
                            {{ \App\Support\Format::quantidade($material->estoque) }} {{ $material->unidade_base }}
                        </div>
                        <div>Mínimo</div>
                        <div class="text-right font-medium text-brand-text">
                            {{ \App\Support\Format::quantidade($material->estoque_minimo) }} {{ $material->unidade_base }}
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4">
            <x-ui.pagination :paginator="$materiais" />
        </div>
    @endif
</div>
