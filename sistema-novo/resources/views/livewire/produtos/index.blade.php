<div>
    <x-ui.page-header title="Produtos" subtitle="{{ $produtos->total() }} produto(s) cadastrado(s).">
        <x-slot name="actions">
            @can('create', \App\Models\Produto::class)
                <x-ui.button :href="route('produtos.create')" wire:navigate>
                    <x-icon name="plus" class="w-4 h-4" />
                    Novo produto
                </x-ui.button>
            @endcan
        </x-slot>
    </x-ui.page-header>

    @if (session('success'))
        <x-ui.alert variant="success" class="mb-4">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="flex-1">
            <x-ui.search-input wire:model.live.debounce.400ms="search" placeholder="Buscar por nome ou SKU..." />
        </div>

        <select wire:model.live="categoriaFiltro" class="rounded-brand-sm border-brand-border/70 bg-brand-surface text-sm text-brand-text focus:border-brand-primary focus:ring-brand-primary min-h-[44px]">
            <option value="">Todas as categorias</option>
            @foreach ($categorias as $categoria)
                <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
            @endforeach
        </select>

        <select wire:model.live="ordenar" class="rounded-brand-sm border-brand-border/70 bg-brand-surface text-sm text-brand-text focus:border-brand-primary focus:ring-brand-primary min-h-[44px]">
            <option value="nome">Ordenar por nome</option>
            <option value="preco">Ordenar por preço</option>
            <option value="recentes">Mais recentes</option>
        </select>
    </div>

    <div class="flex gap-2 overflow-x-auto mb-4">
        @foreach (['todos' => 'Todos', 'ativos' => 'Ativos', 'inativos' => 'Inativos'] as $value => $label)
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

    @if ($produtos->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="gift"
                title="Nenhum produto encontrado"
                :description="$search !== '' ? 'Tente ajustar sua busca ou filtro.' : 'Cadastre o primeiro produto para começar.'"
            >
                @can('create', \App\Models\Produto::class)
                    <x-slot name="actions">
                        <x-ui.button :href="route('produtos.create')" wire:navigate variant="outline">Novo produto</x-ui.button>
                    </x-slot>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        {{-- Mobile: cards --}}
        <div class="grid grid-cols-2 gap-3 md:hidden">
            @foreach ($produtos as $produto)
                <a href="{{ route('produtos.show', $produto) }}" wire:navigate class="bg-brand-surface border border-brand-border/40 rounded-brand-md overflow-hidden">
                    <div class="aspect-square bg-brand-soft/30 flex items-center justify-center">
                        @if ($produto->imagem)
                            <img src="{{ Storage::url($produto->imagem) }}" alt="{{ $produto->nome }}" class="w-full h-full object-cover">
                        @else
                            <x-icon name="gift" class="w-8 h-8 text-brand-primary/40" />
                        @endif
                    </div>
                    <div class="p-2.5">
                        <p class="text-sm font-medium text-brand-text truncate">{{ $produto->nome }}</p>
                        <p class="text-xs text-brand-text-muted truncate">{{ $produto->categoria?->nome ?? 'Sem categoria' }}</p>
                        <div class="flex items-center justify-between mt-1.5">
                            <span class="text-sm font-semibold text-brand-text">R$ {{ number_format((float) $produto->preco_venda, 2, ',', '.') }}</span>
                            <x-ui.status-badge :status="$produto->ativo ? 'ativo' : 'inativo'" />
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Desktop: tabela --}}
        <x-ui.table>
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-brand-text-muted">
                    <th class="px-4 py-3"></th>
                    <th class="px-4 py-3">Nome</th>
                    <th class="px-4 py-3">SKU</th>
                    <th class="px-4 py-3">Categoria</th>
                    <th class="px-4 py-3">Preço</th>
                    <th class="px-4 py-3">Custo ref.</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-border/20">
                @foreach ($produtos as $produto)
                    <tr class="hover:bg-brand-soft/10">
                        <td class="px-4 py-3">
                            <div class="w-10 h-10 rounded-brand-sm bg-brand-soft/30 overflow-hidden flex items-center justify-center">
                                @if ($produto->imagem)
                                    <img src="{{ Storage::url($produto->imagem) }}" alt="" class="w-full h-full object-cover">
                                @else
                                    <x-icon name="gift" class="w-4 h-4 text-brand-primary/40" />
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('produtos.show', $produto) }}" wire:navigate class="font-medium text-brand-text hover:text-brand-primary">
                                {{ $produto->nome }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-brand-text-muted">{{ $produto->sku ?: '—' }}</td>
                        <td class="px-4 py-3 text-brand-text-muted">{{ $produto->categoria?->nome ?? 'Sem categoria' }}</td>
                        <td class="px-4 py-3 font-medium text-brand-text">R$ {{ number_format((float) $produto->preco_venda, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-brand-text-muted">{{ $produto->custo_referencia !== null ? 'R$ '.number_format((float) $produto->custo_referencia, 2, ',', '.') : '—' }}</td>
                        <td class="px-4 py-3"><x-ui.status-badge :status="$produto->ativo ? 'ativo' : 'inativo'" /></td>
                        <td class="px-4 py-3 text-right">
                            <x-ui.button variant="ghost" size="sm" :href="route('produtos.show', $produto)" wire:navigate>Ver</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>

        <div class="mt-4">
            <x-ui.pagination :paginator="$produtos" />
        </div>
    @endif
</div>
