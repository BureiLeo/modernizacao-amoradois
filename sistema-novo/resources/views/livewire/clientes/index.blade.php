<div>
    <x-ui.page-header title="Clientes" subtitle="{{ $clientes->total() }} cliente(s) cadastrado(s).">
        <x-slot name="actions">
            @can('create', \App\Models\Cliente::class)
                <x-ui.button :href="route('clientes.create')" wire:navigate>
                    <x-icon name="plus" class="w-4 h-4" />
                    Novo cliente
                </x-ui.button>
            @endcan
        </x-slot>
    </x-ui.page-header>

    @if (session('success'))
        <x-ui.alert variant="success" class="mb-4">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="flex-1">
            <x-ui.search-input wire:model.live.debounce.400ms="search" placeholder="Buscar por nome, código, telefone, e-mail, CPF ou CNPJ..." />
        </div>

        <select wire:model.live="ordenar" class="rounded-brand-sm border-brand-border/70 bg-brand-surface text-sm text-brand-text focus:border-brand-primary focus:ring-brand-primary min-h-[44px]">
            <option value="nome">Ordenar por nome</option>
            <option value="codigo">Ordenar por código</option>
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

    @if ($clientes->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="users"
                title="Nenhum cliente encontrado"
                :description="$search !== '' ? 'Tente ajustar sua busca ou filtro.' : 'Cadastre o primeiro cliente para começar.'"
            >
                @can('create', \App\Models\Cliente::class)
                    <x-slot name="actions">
                        <x-ui.button :href="route('clientes.create')" wire:navigate variant="outline">Novo cliente</x-ui.button>
                    </x-slot>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        {{-- Mobile: cards --}}
        <div class="space-y-2 md:hidden">
            @foreach ($clientes as $cliente)
                <x-ui.mobile-list-card
                    :title="$cliente->nome"
                    :subtitle="'#'.$cliente->codigo.($cliente->telefone ? ' · '.$cliente->telefone : '')"
                    :href="route('clientes.show', $cliente)"
                >
                    <x-slot name="meta">
                        <span>{{ $cliente->vendas_count }} venda(s)</span>
                        @if ($cliente->vendas_sum_total)
                            <span>· R$ {{ number_format((float) $cliente->vendas_sum_total, 2, ',', '.') }}</span>
                        @endif
                    </x-slot>
                    <x-slot name="trailing">
                        <x-ui.status-badge :status="$cliente->ativo ? 'ativo' : 'inativo'" />
                    </x-slot>
                </x-ui.mobile-list-card>
            @endforeach
        </div>

        {{-- Desktop: tabela --}}
        <x-ui.table>
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-brand-text-muted">
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Nome</th>
                    <th class="px-4 py-3">Telefone</th>
                    <th class="px-4 py-3">E-mail</th>
                    <th class="px-4 py-3">Vendas</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-border/20">
                @foreach ($clientes as $cliente)
                    <tr class="hover:bg-brand-soft/10">
                        <td class="px-4 py-3 text-brand-text-muted">#{{ $cliente->codigo }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('clientes.show', $cliente) }}" wire:navigate class="font-medium text-brand-text hover:text-brand-primary">
                                {{ $cliente->nome }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-brand-text-muted">{{ $cliente->telefone ?: '—' }}</td>
                        <td class="px-4 py-3 text-brand-text-muted">{{ $cliente->email ?: '—' }}</td>
                        <td class="px-4 py-3 text-brand-text-muted">
                            {{ $cliente->vendas_count }}
                            @if ($cliente->vendas_sum_total)
                                <span class="text-xs">(R$ {{ number_format((float) $cliente->vendas_sum_total, 2, ',', '.') }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-ui.status-badge :status="$cliente->ativo ? 'ativo' : 'inativo'" /></td>
                        <td class="px-4 py-3 text-right">
                            <x-ui.button variant="ghost" size="sm" :href="route('clientes.show', $cliente)" wire:navigate>Ver</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>

        <div class="mt-4">
            <x-ui.pagination :paginator="$clientes" />
        </div>
    @endif
</div>
