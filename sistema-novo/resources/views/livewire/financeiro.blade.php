<div class="space-y-5">
    <x-ui.page-header title="Financeiro" subtitle="Saldos por conta e fechamento da feira." />

    <div class="grid gap-3 sm:grid-cols-3">
        @foreach ($contas as $conta)
            <x-ui.card>
                <p class="text-sm text-brand-text-muted">{{ $conta->label() }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">R$ {{ \App\Support\Format::dinheiro($saldos[$conta->value] ?? 0) }}</p>
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Caixa da feira</h2>
            @if ($aberto)
                <x-ui.button :href="route('vendas.pdv')" wire:navigate>Abrir PDV</x-ui.button>
            @endif
        </div>

        @if (! $aberto)
            <form wire:submit="abrir" class="grid items-start gap-3 sm:grid-cols-[1fr_12rem_auto]">
                <x-ui.input name="nome" label="Nome da feira" wire:model="nome" maxlength="100" />
                <x-ui.input name="troco" label="Troco inicial (R$)" wire:model="troco" inputmode="decimal" />
                <x-ui.button type="submit" class="sm:mt-6" wire:loading.attr="disabled">Abrir caixa</x-ui.button>
            </form>
        @endif

        @if ($caixas->isNotEmpty())
            <div class="{{ $aberto ? '' : 'mt-5' }}">
                <label for="caixa-feira" class="mb-1 block text-sm font-medium">Caixa para consultar</label>
                <select id="caixa-feira" wire:change="selecionar($event.target.value)" class="w-full rounded-brand-sm border-brand-border bg-brand-surface sm:max-w-lg">
                    @foreach ($caixas as $opcao)
                        <option value="{{ $opcao->id }}" @selected($caixa?->id === $opcao->id)>
                            {{ $opcao->nome }} · {{ $opcao->created_at->format('d/m/Y H:i') }} · {{ $opcao->aberto ? 'Aberto' : 'Fechado' }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
    </x-ui.card>

    @if ($caixa)
        <div class="grid gap-4 lg:grid-cols-2" wire:key="relatorio-feira-{{ $caixa->id }}">
            <x-ui.card>
                <h2 class="mb-3 text-lg font-semibold">{{ $caixa->aberto ? 'Conferência do caixa' : 'Relatório de fechamento' }}</h2>
                <dl class="space-y-2 text-sm">
                    @foreach (\App\Enums\FormaPagamento::cases() as $forma)
                        @if ($resumo['formas'][$forma->value] || in_array($forma->value, ['pix', 'dinheiro', 'debito', 'credito'], true))
                            <div class="flex justify-between gap-3">
                                <dt>{{ $forma->label() }}</dt>
                                <dd>R$ {{ \App\Support\Format::dinheiro($resumo['formas'][$forma->value] / 100) }}</dd>
                            </div>
                        @endif
                    @endforeach
                    <div class="flex justify-between gap-3 border-t border-brand-border/60 pt-2 font-semibold">
                        <dt>Total vendido</dt><dd>R$ {{ \App\Support\Format::dinheiro(array_sum($resumo['linhas']) / 100) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>Troco inicial</dt><dd>R$ {{ \App\Support\Format::dinheiro($caixa->troco_inicial) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>Dinheiro esperado na gaveta</dt><dd>R$ {{ \App\Support\Format::dinheiro($resumo['esperado'] / 100) }}</dd>
                    </div>
                    @if (! $caixa->aberto)
                        <div class="flex justify-between gap-3">
                            <dt>Dinheiro contado</dt><dd>R$ {{ \App\Support\Format::dinheiro($caixa->dinheiro_contado) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt>Diferença de caixa</dt><dd>R$ {{ \App\Support\Format::dinheiro((float) $caixa->dinheiro_contado - $resumo['esperado'] / 100) }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($caixa->aberto)
                    <form wire:submit="fechar" class="mt-4 space-y-3">
                        <x-ui.input name="dinheiroContado" label="Dinheiro contado na gaveta (R$)" wire:model="dinheiroContado" inputmode="decimal" />
                        <x-ui.button type="submit" wire:confirm="Fechar este caixa da feira?" wire:loading.attr="disabled">Fechar caixa</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card>
                <h2 class="mb-3 text-lg font-semibold">Vendas por linha e repasses</h2>
                <div class="space-y-3 text-sm">
                    @foreach (\App\Enums\ContaCaixa::linhas() as $linha)
                        <div class="rounded-brand-sm border border-brand-border/60 p-3">
                            <div class="flex flex-wrap justify-between gap-2 font-semibold">
                                <span>{{ $linha->label() }}</span>
                                <span>R$ {{ \App\Support\Format::dinheiro($resumo['linhas'][$linha->value] / 100) }} em vendas</span>
                            </div>
                            @foreach (\App\Enums\FormaPagamento::cases() as $forma)
                                @if ($resumo['formasPorLinha'][$linha->value][$forma->value])
                                    <div class="flex justify-between gap-2 text-brand-text-muted">
                                        <span>{{ $forma->label() }}</span>
                                        <span>R$ {{ \App\Support\Format::dinheiro($resumo['formasPorLinha'][$linha->value][$forma->value] / 100) }}</span>
                                    </div>
                                @endif
                            @endforeach
                            @if (! $caixa->aberto)
                                <p class="mt-1 text-brand-text-muted">
                                    R$ {{ \App\Support\Format::dinheiro($resumo['transferido'][$linha->value] / 100) }} repassados
                                    · R$ {{ \App\Support\Format::dinheiro($resumo['pendente'][$linha->value] / 100) }} a repassar
                                    @if ($resumo['taxas'][$linha->value])
                                        · R$ {{ \App\Support\Format::dinheiro($resumo['taxas'][$linha->value] / 100) }} em taxas
                                    @endif
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if (! $caixa->aberto && array_sum($resumo['pendente']) > 0)
                    <form wire:submit="transferir" class="mt-4 space-y-3">
                        <p class="text-sm text-brand-text-muted">Registre aqui o valor que você já transferiu. Considere o valor que caiu na conta da maquininha após as taxas.</p>
                        <x-ui.select name="destino" label="Conta de destino" wire:model="destino">
                            @foreach (\App\Enums\ContaCaixa::linhas() as $linha)
                                <option value="{{ $linha->value }}">{{ $linha->label() }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input name="valorRepasse" label="Valor transferido (R$)" wire:model="valorRepasse" inputmode="decimal" />
                        <x-ui.input name="taxaRepasse" label="Taxa descontada neste repasse (opcional)" wire:model="taxaRepasse" inputmode="decimal" />
                        <x-ui.button type="submit" wire:loading.attr="disabled">Registrar repasse</x-ui.button>
                    </form>
                @endif

                @if ($repasses->isNotEmpty())
                    <ul class="mt-4 divide-y divide-brand-border/50 text-sm">
                        @foreach ($repasses as $repasse)
                            <li class="flex justify-between gap-2 py-2">
                                <span>{{ $repasse->created_at->format('d/m H:i') }} · {{ \App\Enums\ContaCaixa::from($repasse->destino)->label() }}</span>
                                <span>R$ {{ \App\Support\Format::dinheiro($repasse->valor) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    @endif
</div>
