<div>
    <x-ui.page-header title="Olá, {{ Str::of(auth()->user()->name)->before(' ') }}!" subtitle="Aqui está um resumo da sua loja.">
        <x-slot name="actions">
            <div class="flex items-center gap-2">
                <select wire:model.live="period" class="rounded-brand-sm border-brand-border/70 bg-brand-surface text-sm text-brand-text focus:border-brand-primary focus:ring-brand-primary min-h-[44px]">
                    @foreach ($periodOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot>
    </x-ui.page-header>

    @if ($period === \App\Services\Dashboard\DashboardPeriod::PERSONALIZADO)
        <div class="flex flex-wrap items-end gap-3 mb-5 -mt-2">
            <x-ui.input type="date" name="customStart" label="De" wire:model.live="customStart" class="max-w-[170px]" />
            <x-ui.input type="date" name="customEnd" label="Até" wire:model.live="customEnd" class="max-w-[170px]" />
        </div>
    @endif

    {{-- ======================================================
         INDICADORES
         ====================================================== --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mb-5">
        <x-ui.stat-card
            label="Saldo em caixa"
            :value="$showSaldoCaixa ? 'R$ '.number_format($saldoCaixa, 2, ',', '.') : '••••••'"
            icon="wallet"
            subtitle="Saldo histórico total"
        >
            <x-slot name="toggle">
                <x-ui.icon-button
                    :icon="$showSaldoCaixa ? 'eye-off' : 'eye'"
                    :label="$showSaldoCaixa ? 'Ocultar saldo em caixa' : 'Mostrar saldo em caixa'"
                    size="sm"
                    wire:click="toggleSaldoCaixa"
                />
            </x-slot>
        </x-ui.stat-card>
        <x-ui.stat-card
            label="Faturamento"
            :value="'R$ '.number_format($faturamento, 2, ',', '.')"
            icon="wallet"
            :subtitle="$currentPeriod->label"
        />
        <x-ui.stat-card
            label="Vendas"
            :value="$vendasCount"
            icon="shopping-bag"
            :subtitle="$currentPeriod->label"
        />
        <x-ui.stat-card
            label="Ticket médio"
            :value="'R$ '.number_format($ticketMedio, 2, ',', '.')"
            icon="bar-chart-3"
            :subtitle="$currentPeriod->label"
        />
        <x-ui.stat-card
            label="Lucro estimado"
            :value="'R$ '.number_format($lucroEstimado, 2, ',', '.')"
            icon="trending-up"
            subtitle="Baseado em custos históricos"
        />
        <x-ui.stat-card
            label="Clientes cadastrados"
            :value="$clientesCadastrados"
            icon="users"
        />
        <x-ui.stat-card
            label="A receber (pendente)"
            :value="'R$ '.number_format($valorPendente, 2, ',', '.')"
            icon="wallet"
        />
        <x-ui.stat-card
            label="Estoque baixo"
            :value="$materiaisEstoqueBaixoCount"
            icon="archive"
            subtitle="materiais no ou abaixo do mínimo"
        />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
        {{-- ======================================================
             GRAFICO DE VENDAS
             ====================================================== --}}
        <x-ui.card class="lg:col-span-2">
            <h2 class="font-semibold text-brand-text mb-3">Faturamento — {{ $currentPeriod->label }}</h2>

            @if ($vendasCount > 0)
                <div
                    wire:ignore
                    x-data="salesChart(@js(['categories' => $chartCategories, 'values' => $chartValues]))"
                    x-on:dashboard-chart-updated.window="update($event.detail)"
                    class="overflow-x-auto"
                >
                    <div data-chart-canvas class="min-w-[280px]"></div>
                </div>
            @else
                <x-ui.empty-state icon="bar-chart-3" title="Sem vendas no período" description="Assim que houver vendas registradas, o gráfico aparecerá aqui." />
            @endif
        </x-ui.card>

        {{-- ======================================================
             PRODUTOS MAIS VENDIDOS
             ====================================================== --}}
        <x-ui.card>
            <h2 class="font-semibold text-brand-text mb-3">Produtos mais vendidos</h2>

            @if ($produtosMaisVendidos->isEmpty())
                <x-ui.empty-state icon="gift" title="Nenhum produto vendido" description="Ainda não há vendas no período selecionado." />
            @else
                <ul class="space-y-3">
                    @foreach ($produtosMaisVendidos as $produto)
                        <li class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-brand-text truncate">{{ $produto->nome }}</p>
                                <p class="text-xs text-brand-text-muted">{{ (int) $produto->quantidade }} un.</p>
                            </div>
                            <p class="text-sm font-semibold text-brand-text shrink-0">R$ {{ number_format($produto->faturamento, 2, ',', '.') }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
        {{-- ======================================================
             ESTOQUE BAIXO
             ====================================================== --}}
        <x-ui.card>
            <h2 class="font-semibold text-brand-text mb-3">Materiais com estoque baixo</h2>

            @if ($materiaisEstoqueBaixo->isEmpty())
                <x-ui.empty-state icon="check-circle" title="Estoque em dia" description="Nenhum material está no limite mínimo no momento." />
            @else
                <ul class="space-y-3">
                    @foreach ($materiaisEstoqueBaixo as $material)
                        <li class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-brand-text truncate">{{ $material->nome }}</p>
                            <x-ui.badge variant="warning">{{ rtrim(rtrim(number_format((float) $material->estoque, 3, ',', '.'), '0'), ',') }} {{ $material->unidade_base }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        {{-- ======================================================
             VENDAS RECENTES
             ====================================================== --}}
        <x-ui.card class="lg:col-span-2" :padded="false">
            <div class="p-4 sm:p-5 pb-0">
                <h2 class="font-semibold text-brand-text mb-3">Vendas recentes</h2>
            </div>

            @if ($vendasRecentes->isEmpty())
                <div class="p-4 sm:p-5 pt-0">
                    <x-ui.empty-state icon="shopping-bag" title="Nenhuma venda registrada" description="As vendas mais recentes aparecerão aqui." />
                </div>
            @else
                <div class="px-4 sm:px-5 pb-4 sm:pb-5 space-y-2">
                    @foreach ($vendasRecentes as $venda)
                        <x-ui.mobile-list-card
                            :title="$venda->cliente->nome ?? 'Sem cliente'"
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
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Itens</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Pagamento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border/20">
                        @foreach ($vendasRecentes as $venda)
                            <tr>
                                <td class="px-4 py-3 text-brand-text">{{ $venda->cliente->nome ?? 'Sem cliente' }}</td>
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
</div>
