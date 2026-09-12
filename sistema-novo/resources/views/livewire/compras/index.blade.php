<div>
    <x-ui.page-header title="Compras" subtitle="{{ $compras->total() }} lançamento(s) de entrada no estoque.">
        <x-slot name="actions">
            <x-ui.button :href="route('compras.create')" wire:navigate>
                <x-icon name="plus" class="w-4 h-4" />
                Cadastrar compra
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>

    @if (session('success'))
        <x-ui.alert variant="success" class="mb-4">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="mb-4">
        <x-ui.search-input wire:model.live.debounce.400ms="search" placeholder="Buscar por nota, fornecedor ou material..." />
    </div>

    <div class="flex gap-2 overflow-x-auto mb-4">
        @foreach (['todas' => 'Todas', 'pagas' => 'Pagas', 'pendentes' => 'Pendentes', 'mes' => 'Mês atual'] as $value => $label)
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

    @if ($compras->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="truck"
                title="Nenhuma compra encontrada"
                :description="$search !== '' ? 'Tente ajustar sua busca ou filtro.' : 'Lance a nota do fornecedor para dar entrada em vários materiais de uma vez.'"
            >
                <x-slot name="actions">
                    <x-ui.button :href="route('compras.create')" wire:navigate variant="outline">Cadastrar compra</x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        @php
            $total = fn ($c) => (float) $c->total_valor + (float) $c->total_frete - (float) $c->total_desconto;
        @endphp

        {{-- Mobile: cards --}}
        <div class="space-y-3 md:hidden">
            @foreach ($compras as $compra)
                <a href="{{ route('compras.show', ['lote' => $compra->lote_id ?? $compra->id]) }}" wire:navigate class="block rounded-brand-md border border-brand-border/40 bg-brand-surface p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-brand-text">{{ $compra->numero_nota ? 'NF '.$compra->numero_nota : 'Compra #'.$compra->id }}</p>
                            <p class="text-xs text-brand-text-muted">{{ $fornecedores[$compra->fornecedor_id] ?? 'Sem fornecedor' }}</p>
                        </div>
                        <x-ui.status-badge :status="$compra->pago ? 'pago' : 'pendente'" />
                    </div>
                    <div class="mt-2 flex items-center justify-between text-sm">
                        <span class="text-brand-text-muted">
                            {{ \Illuminate\Support\Carbon::parse($compra->data)->format('d/m/Y') }} · {{ $compra->total_itens }} item(ns)
                        </span>
                        <span class="font-semibold text-brand-text">R$ {{ number_format($total($compra), 2, ',', '.') }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Desktop: tabela --}}
        <x-ui.table>
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-brand-text-muted">
                    <th class="px-4 py-3">Data</th>
                    <th class="px-4 py-3">Nota</th>
                    <th class="px-4 py-3">Fornecedor</th>
                    <th class="px-4 py-3">Itens</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-border/20">
                @foreach ($compras as $compra)
                    <tr class="hover:bg-brand-soft/10">
                        <td class="px-4 py-3 text-brand-text-muted">{{ \Illuminate\Support\Carbon::parse($compra->data)->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('compras.show', ['lote' => $compra->lote_id ?? $compra->id]) }}" wire:navigate class="font-medium text-brand-text hover:text-brand-primary">
                                {{ $compra->numero_nota ? 'NF '.$compra->numero_nota : 'Compra #'.$compra->id }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-brand-text-muted">{{ $fornecedores[$compra->fornecedor_id] ?? 'Sem fornecedor' }}</td>
                        <td class="px-4 py-3 text-brand-text-muted">{{ $compra->total_itens }}</td>
                        <td class="px-4 py-3 font-medium text-brand-text">R$ {{ number_format($total($compra), 2, ',', '.') }}</td>
                        <td class="px-4 py-3"><x-ui.status-badge :status="$compra->pago ? 'pago' : 'pendente'" /></td>
                        <td class="px-4 py-3 text-right">
                            <x-ui.button variant="ghost" size="sm" :href="route('compras.show', ['lote' => $compra->lote_id ?? $compra->id])" wire:navigate>Ver</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>

        <div class="mt-4">
            <x-ui.pagination :paginator="$compras" />
        </div>
    @endif
</div>
