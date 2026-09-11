<div>
    @php
        $telDigits = $cliente->telefone ? preg_replace('/\D/', '', $cliente->telefone) : null;
        $waDigits = $telDigits ? ((str_starts_with($telDigits, '55') && strlen($telDigits) >= 12) ? $telDigits : '55'.$telDigits) : null;
        $telFormatado = $telDigits && strlen($telDigits) >= 10
            ? '('.substr($telDigits, 0, 2).') '.substr($telDigits, 2, strlen($telDigits) - 6).'-'.substr($telDigits, -4)
            : $cliente->telefone;
    @endphp

    <x-ui.page-header title="{{ $cliente->nome }}" subtitle="Cliente #{{ $cliente->codigo }} · cadastrado em {{ $cliente->created_at->format('d/m/Y') }}">
        <x-slot name="actions">
            @can('update', $cliente)
                <x-ui.button variant="outline" :href="route('clientes.edit', $cliente)" wire:navigate>Editar</x-ui.button>
            @endcan
            @if ($cliente->ativo)
                @can('delete', $cliente)
                    <x-ui.button variant="danger" wire:click="alternarAtivo" wire:confirm="Inativar este cliente? O histórico de vendas será mantido normalmente.">Inativar</x-ui.button>
                @endcan
            @else
                @can('restore', $cliente)
                    <x-ui.button variant="primary" wire:click="alternarAtivo" wire:confirm="Reativar este cliente?">Reativar</x-ui.button>
                @endcan
            @endif
        </x-slot>
    </x-ui.page-header>

    @if (session('success'))
        <x-ui.alert variant="success" class="mb-4">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="mb-4">
        <x-ui.status-badge :status="$cliente->ativo ? 'ativo' : 'inativo'" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <x-ui.card>
                <h2 class="font-semibold text-brand-text mb-4">Dados cadastrais</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-brand-text-muted">Telefone</dt>
                        <dd class="text-brand-text font-medium flex items-center gap-2 mt-0.5">
                            {{ $telFormatado ?: '—' }}
                            @if ($waDigits)
                                <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-brand-success text-xs font-semibold hover:underline">
                                    WhatsApp
                                </a>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-brand-text-muted">E-mail</dt>
                        <dd class="text-brand-text font-medium mt-0.5">{{ $cliente->email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-brand-text-muted">CPF</dt>
                        <dd class="text-brand-text font-medium mt-0.5">{{ $cliente->cpf ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-brand-text-muted">RG</dt>
                        <dd class="text-brand-text font-medium mt-0.5">{{ $cliente->rg ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-brand-text-muted">CNPJ</dt>
                        <dd class="text-brand-text font-medium mt-0.5">{{ $cliente->cnpj ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-brand-text-muted">Razão social</dt>
                        <dd class="text-brand-text font-medium mt-0.5">{{ $cliente->razao_social ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-brand-text-muted">Endereço</dt>
                        <dd class="text-brand-text font-medium mt-0.5">{{ $cliente->endereco ?: '—' }}</dd>
                    </div>
                    @if ($cliente->observacoes)
                        <div class="sm:col-span-2">
                            <dt class="text-brand-text-muted">Observações</dt>
                            <dd class="text-brand-text mt-0.5">{{ $cliente->observacoes }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card :padded="false">
                <div class="p-4 sm:p-5 pb-0">
                    <h2 class="font-semibold text-brand-text mb-3">Últimas vendas</h2>
                </div>

                @if ($vendasRecentes->isEmpty())
                    <div class="p-4 sm:p-5 pt-0">
                        <x-ui.empty-state icon="shopping-bag" title="Nenhuma venda registrada" description="Este cliente ainda não possui vendas." />
                    </div>
                @else
                    <div class="px-4 sm:px-5 pb-4 sm:pb-5 space-y-2">
                        @foreach ($vendasRecentes as $venda)
                            <x-ui.mobile-list-card
                                :title="'Venda #'.$venda->id"
                                :subtitle="$venda->data->format('d/m/Y H:i').' · '.$venda->itens_count.' ite'.($venda->itens_count === 1 ? 'm' : 'ns')"
                            >
                                <x-slot name="trailing">
                                    <p class="text-sm font-semibold text-brand-text">R$ {{ number_format((float) $venda->total, 2, ',', '.') }}</p>
                                    <x-ui.status-badge :status="$venda->pago ? 'pago' : 'pendente'" />
                                </x-slot>
                            </x-ui.mobile-list-card>
                        @endforeach
                    </div>

                    <x-ui.table>
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-brand-text-muted">
                                <th class="px-4 py-3">Venda</th>
                                <th class="px-4 py-3">Data</th>
                                <th class="px-4 py-3">Itens</th>
                                <th class="px-4 py-3">Total</th>
                                <th class="px-4 py-3">Pagamento</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border/20">
                            @foreach ($vendasRecentes as $venda)
                                <tr>
                                    <td class="px-4 py-3 text-brand-text">#{{ $venda->id }}</td>
                                    <td class="px-4 py-3 text-brand-text-muted">{{ $venda->data->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-brand-text-muted">{{ $venda->itens_count }}</td>
                                    <td class="px-4 py-3 font-semibold text-brand-text">R$ {{ number_format((float) $venda->total, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3"><x-ui.status-badge :status="$venda->pago ? 'pago' : 'pendente'" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-4">
            <x-ui.stat-card label="Total de vendas" :value="$totalVendas" icon="shopping-bag" />
            <x-ui.stat-card label="Total comprado" :value="'R$ '.number_format($totalComprado, 2, ',', '.')" icon="wallet" />
            <x-ui.stat-card label="Ticket médio" :value="'R$ '.number_format($ticketMedio, 2, ',', '.')" icon="bar-chart-3" />
            <x-ui.stat-card label="Última compra" :value="$ultimaCompra ? \Illuminate\Support\Carbon::parse($ultimaCompra)->format('d/m/Y') : '—'" icon="calendar" />
        </div>
    </div>
</div>
