<div>
    <x-ui.page-header title="Vendas" subtitle="{{ $vendas->total() }} venda(s) no registro.">
        <x-slot name="actions">
            <x-ui.button :href="route('vendas.create')" wire:navigate>
                <x-icon name="plus" class="w-4 h-4" />
                Nova venda
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>

    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="flex-1">
            <x-ui.search-input wire:model.live.debounce.400ms="search" placeholder="Buscar por número, cliente ou telefone..." />
        </div>

        <select wire:model.live="ordenar" class="rounded-brand-sm border-brand-border/70 bg-brand-surface text-sm text-brand-text focus:border-brand-primary focus:ring-brand-primary min-h-[44px]">
            <option value="recentes">Mais recentes</option>
            <option value="maior">Maior valor</option>
            <option value="menor">Menor valor</option>
        </select>
    </div>

    <div class="flex gap-2 overflow-x-auto mb-4">
        @foreach (['todas' => 'Todas', 'pagas' => 'Pagas', 'pendentes' => 'Pendentes'] as $value => $label)
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

    @if ($vendas->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="shopping-bag"
                title="Nenhuma venda encontrada"
                :description="$search !== '' ? 'Tente ajustar a busca ou o filtro.' : 'Ainda não há vendas registradas.'"
            >
                <x-slot name="actions">
                    <x-ui.button :href="route('vendas.create')" wire:navigate variant="outline">Nova venda</x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="space-y-2 md:hidden">
            @foreach ($vendas as $venda)
                <x-ui.mobile-list-card
                    :title="'#'.$venda->id"
                    :subtitle="$venda->cliente?->nome ?? 'Sem cliente'"
                    :href="route('vendas.show', $venda)"
                >
                    <x-slot name="meta">
                        <span>{{ $venda->created_at?->format('d/m/Y H:i') }}</span>
                        <span>· {{ $venda->cliente?->telefone ?? 'Sem telefone' }}</span>
                    </x-slot>
                    <x-slot name="trailing">
                        <div class="text-right">
                            <div class="font-semibold text-brand-text">R$ {{ number_format((float) $venda->total, 2, ',', '.') }}</div>
                            <div class="mt-1">
                                <x-ui.status-badge :status="$venda->pago ? 'ativo' : 'inativo'" />
                            </div>
                        </div>
                    </x-slot>
                </x-ui.mobile-list-card>
            @endforeach
        </div>

        <x-ui.table class="hidden md:table">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-brand-text-muted">
                    <th class="px-4 py-3">Número</th>
                    <th class="px-4 py-3">Data</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Pagamento</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-border/20">
                @foreach ($vendas as $venda)
                    <tr class="hover:bg-brand-soft/10">
                        <td class="px-4 py-3 text-brand-text-muted">#{{ $venda->id }}</td>
                        <td class="px-4 py-3 text-brand-text-muted">{{ $venda->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('vendas.show', $venda) }}" wire:navigate class="font-medium text-brand-text hover:text-brand-primary">
                                {{ $venda->cliente?->nome ?? 'Sem cliente' }}
                            </a>
                        </td>
                        <td class="px-4 py-3 font-semibold text-brand-text">R$ {{ number_format((float) $venda->total, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-brand-text-muted">{{ $venda->forma_pagamento ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <x-ui.status-badge :status="$venda->pago ? 'ativo' : 'inativo'" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-ui.button variant="ghost" size="sm" :href="route('vendas.show', $venda)" wire:navigate>Ver</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>

        <div class="mt-4">
            <x-ui.pagination :paginator="$vendas" />
        </div>
    @endif
</div>
