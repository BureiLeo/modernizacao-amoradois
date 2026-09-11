<div>
    <x-ui.page-header title="{{ $produto->nome }}" subtitle="Cadastrado em {{ $produto->created_at->format('d/m/Y') }}">
        <x-slot name="actions">
            @can('update', $produto)
                <x-ui.button variant="outline" :href="route('produtos.edit', $produto)" wire:navigate>Editar</x-ui.button>
            @endcan
            @if ($produto->ativo)
                @can('delete', $produto)
                    <x-ui.button variant="danger" wire:click="alternarAtivo" wire:confirm="Inativar este produto? O histórico de vendas será mantido normalmente.">Inativar</x-ui.button>
                @endcan
            @else
                @can('restore', $produto)
                    <x-ui.button variant="primary" wire:click="alternarAtivo" wire:confirm="Reativar este produto?">Reativar</x-ui.button>
                @endcan
            @endif
        </x-slot>
    </x-ui.page-header>

    @if (session('success'))
        <x-ui.alert variant="success" class="mb-4">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <x-ui.card>
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="w-full sm:w-32 h-32 rounded-brand-sm bg-brand-soft/30 overflow-hidden flex items-center justify-center shrink-0">
                        @if ($produto->imagem)
                            <img src="{{ Storage::url($produto->imagem) }}" alt="{{ $produto->nome }}" class="w-full h-full object-cover">
                        @else
                            <x-icon name="gift" class="w-10 h-10 text-brand-primary/40" />
                        @endif
                    </div>

                    <dl class="grid grid-cols-2 gap-4 text-sm flex-1">
                        <div>
                            <dt class="text-brand-text-muted">SKU</dt>
                            <dd class="text-brand-text font-medium mt-0.5">{{ $produto->sku ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-brand-text-muted">Categoria</dt>
                            <dd class="text-brand-text font-medium mt-0.5">{{ $produto->categoria?->nome ?? 'Sem categoria' }}</dd>
                        </div>
                        <div>
                            <dt class="text-brand-text-muted">Preço de venda</dt>
                            <dd class="text-brand-text font-medium mt-0.5">R$ {{ number_format((float) $produto->preco_venda, 2, ',', '.') }}</dd>
                        </div>
                        <div>
                            <dt class="text-brand-text-muted">Custo de referência</dt>
                            <dd class="text-brand-text font-medium mt-0.5">{{ $produto->custo_referencia !== null ? 'R$ '.number_format((float) $produto->custo_referencia, 2, ',', '.') : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-brand-text-muted">Status</dt>
                            <dd class="mt-0.5"><x-ui.status-badge :status="$produto->ativo ? 'ativo' : 'inativo'" /></dd>
                        </div>
                    </dl>
                </div>
            </x-ui.card>

            <x-ui.card>
                <h2 class="font-semibold text-brand-text mb-1">Materiais utilizados (BOM)</h2>
                <p class="text-xs text-brand-text-muted mb-4">
                    Somente leitura nesta etapa — a edição da receita será feita junto do módulo de Materiais/Estoque.
                </p>

                @if ($bom->isEmpty())
                    <x-ui.empty-state icon="archive" title="Sem receita cadastrada" description="Este produto ainda não possui materiais vinculados." />
                @else
                    <ul class="divide-y divide-brand-border/20">
                        @foreach ($bom as $item)
                            <li class="flex items-center justify-between py-2 text-sm">
                                <span class="text-brand-text">{{ $item->material->nome }}</span>
                                <span class="text-brand-text-muted">
                                    {{ rtrim(rtrim(number_format((float) $item->quantidade, 4, ',', '.'), '0'), ',') }}
                                    {{ $item->material->unidade_base }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card :padded="false">
                <div class="p-4 sm:p-5 pb-0">
                    <h2 class="font-semibold text-brand-text mb-3">Últimas vendas</h2>
                </div>

                @if ($vendasRecentes->isEmpty())
                    <div class="p-4 sm:p-5 pt-0">
                        <x-ui.empty-state icon="shopping-bag" title="Nenhuma venda registrada" description="Este produto ainda não foi vendido." />
                    </div>
                @else
                    <ul class="px-4 sm:px-5 pb-4 sm:pb-5 divide-y divide-brand-border/20">
                        @foreach ($vendasRecentes as $item)
                            <li class="flex items-center justify-between py-2 text-sm">
                                <div>
                                    <span class="text-brand-text">{{ $item->venda?->cliente?->nome ?? 'Sem cliente' }}</span>
                                    <span class="text-brand-text-muted"> · {{ $item->venda?->data?->format('d/m/Y') }}</span>
                                </div>
                                <span class="text-brand-text-muted">{{ $item->quantidade }} un. — R$ {{ number_format((float) $item->subtotal, 2, ',', '.') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-4">
            <x-ui.stat-card label="Quantidade vendida" :value="$totalVendido" icon="shopping-bag" subtitle="histórico completo" />
            <x-ui.stat-card label="Faturamento gerado" :value="'R$ '.number_format($faturamentoTotal, 2, ',', '.')" icon="wallet" subtitle="histórico completo" />
        </div>
    </div>
</div>
