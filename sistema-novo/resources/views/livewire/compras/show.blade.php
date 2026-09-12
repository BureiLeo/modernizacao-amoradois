<div class="space-y-4">
    <x-ui.page-header
        :title="$nota->numero_nota ? 'NF '.$nota->numero_nota : 'Compra #'.$nota->id"
        :subtitle="\Illuminate\Support\Carbon::parse($nota->data)->format('d/m/Y').' · '.$compras->count().' item(ns)'"
    >
        <x-slot name="actions">
            <x-ui.button variant="outline" :href="route('compras.index')" wire:navigate>Voltar</x-ui.button>
            @if ($nota->lote_id && ! $nota->pago)
                <x-ui.button wire:click="$set('confirmandoPagamento', true)">Marcar como paga</x-ui.button>
            @endif
            @if ($nota->lote_id)
                <x-ui.button variant="danger" wire:click="$set('confirmandoEstorno', true)">Estornar</x-ui.button>
            @endif
        </x-slot>
    </x-ui.page-header>

    @if (session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif

    @error('estorno') <x-ui.alert variant="danger">{{ $message }}</x-ui.alert> @enderror
    @error('pagamento') <x-ui.alert variant="danger">{{ $message }}</x-ui.alert> @enderror

    @if ($confirmandoPagamento)
        <x-ui.alert variant="info">
            <div class="space-y-3">
                <p>Confirmar o pagamento de <strong>R$ {{ \App\Support\Format::dinheiro($total) }}</strong>? O valor será lançado como saída no caixa.</p>
                <select wire:model="formaPagamento" class="w-full rounded-brand-sm border border-brand-border/70 bg-brand-surface px-3 py-2 text-brand-text sm:max-w-xs">
                    <option value="">Manter forma atual</option>
                    <option value="PIX">PIX</option>
                    <option value="Dinheiro">Dinheiro</option>
                    <option value="Débito">Débito</option>
                    <option value="Crédito">Crédito</option>
                    <option value="Transferência">Transferência</option>
                    <option value="Boleto">Boleto</option>
                    <option value="Outro">Outro</option>
                </select>
                <div class="flex gap-2">
                    <x-ui.button size="sm" wire:click="marcarComoPaga">Confirmar pagamento</x-ui.button>
                    <x-ui.button variant="outline" size="sm" wire:click="$set('confirmandoPagamento', false)">Cancelar</x-ui.button>
                </div>
            </div>
        </x-ui.alert>
    @endif

    @if ($confirmandoEstorno)
        <x-ui.alert variant="warning">
            <div class="space-y-3">
                <p>Estornar esta compra remove os itens lançados e devolve o estoque de cada material. Confirma?</p>
                <div class="flex gap-2">
                    <x-ui.button variant="danger" size="sm" wire:click="estornar">Sim, estornar</x-ui.button>
                    <x-ui.button variant="outline" size="sm" wire:click="$set('confirmandoEstorno', false)">Cancelar</x-ui.button>
                </div>
            </div>
        </x-ui.alert>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4 md:col-span-2">
            <div class="text-sm font-semibold text-brand-text">Dados da nota</div>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-brand-text-muted">Fornecedor</dt>
                    <dd class="font-medium text-brand-text">{{ $nota->fornecedor?->nome ?? 'Sem fornecedor' }}</dd>
                </div>
                <div>
                    <dt class="text-brand-text-muted">Forma de pagamento</dt>
                    <dd class="font-medium text-brand-text">{{ $nota->forma_pagamento ?: 'A definir' }}</dd>
                </div>
                <div>
                    <dt class="text-brand-text-muted">Status</dt>
                    <dd><x-ui.status-badge :status="$nota->pago ? 'pago' : 'pendente'" /></dd>
                </div>
                <div>
                    <dt class="text-brand-text-muted">Chave da NF-e</dt>
                    <dd class="break-all font-medium text-brand-text">{{ $nota->chave_nfe ?: '—' }}</dd>
                </div>
                @if ($nota->observacoes)
                    <div class="sm:col-span-2">
                        <dt class="text-brand-text-muted">Observações</dt>
                        <dd class="text-brand-text">{{ $nota->observacoes }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
            <div class="text-sm font-semibold text-brand-text">Valores</div>
            <dl class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between text-brand-text-muted">
                    <dt>Subtotal</dt>
                    <dd>R$ {{ number_format($subtotal, 2, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between text-brand-text-muted">
                    <dt>Frete</dt>
                    <dd>R$ {{ number_format($frete, 2, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between text-brand-text-muted">
                    <dt>Desconto</dt>
                    <dd>- R$ {{ number_format($desconto, 2, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between border-t border-brand-border/70 pt-2 text-base font-semibold text-brand-text">
                    <dt>Total</dt>
                    <dd>R$ {{ number_format($total, 2, ',', '.') }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-brand-text-muted">
                {{ $movimentos }} movimentação(ões) de estoque geradas por esta compra.
                @if ($nota->pago)
                    Saída de R$ {{ \App\Support\Format::dinheiro($total) }} lançada no caixa.
                @else
                    Ainda não lançada no caixa.
                @endif
            </p>
        </div>
    </div>

    <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
        <div class="text-sm font-semibold text-brand-text">Itens</div>

        {{-- Mobile: cards --}}
        <div class="mt-3 space-y-3 md:hidden">
            @foreach ($compras as $compra)
                <div class="rounded-xl border border-brand-border/70 p-3">
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ route('materiais.show', $compra->material_id) }}" wire:navigate class="font-medium text-brand-text hover:text-brand-primary">
                            {{ $compra->material?->nome ?? 'Material removido' }}
                        </a>
                        <span class="text-sm font-semibold text-brand-text">R$ {{ number_format((float) $compra->valor, 2, ',', '.') }}</span>
                    </div>
                    <div class="mt-1 text-xs text-brand-text-muted">
                        {{ \App\Support\Format::quantidade($compra->quantidade) }} {{ $compra->material?->unidade_base }}
                        · custo un. R$ {{ \App\Support\Format::dinheiro($compra->custo_unitario, 4) }}
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Desktop: tabela --}}
        <div class="mt-3">
            <x-ui.table>
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-brand-text-muted">
                        <th class="px-4 py-3">Material</th>
                        <th class="px-4 py-3">Quantidade</th>
                        <th class="px-4 py-3">Valor</th>
                        <th class="px-4 py-3">Frete rateado</th>
                        <th class="px-4 py-3">Desconto rateado</th>
                        <th class="px-4 py-3">Custo unitário</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border/20">
                    @foreach ($compras as $compra)
                        <tr class="hover:bg-brand-soft/10">
                            <td class="px-4 py-3">
                                <a href="{{ route('materiais.show', $compra->material_id) }}" wire:navigate class="font-medium text-brand-text hover:text-brand-primary">
                                    {{ $compra->material?->nome ?? 'Material removido' }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-brand-text-muted">
                                {{ \App\Support\Format::quantidade($compra->quantidade) }} {{ $compra->material?->unidade_base }}
                            </td>
                            <td class="px-4 py-3 font-medium text-brand-text">R$ {{ number_format((float) $compra->valor, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-brand-text-muted">R$ {{ number_format((float) $compra->frete, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-brand-text-muted">R$ {{ number_format((float) $compra->desconto, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-brand-text-muted">R$ {{ number_format((float) $compra->custo_unitario, 4, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </div>
    </div>
</div>
