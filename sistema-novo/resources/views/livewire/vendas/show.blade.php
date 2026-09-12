<div class="space-y-4">
    <x-ui.page-header title="Venda #{{ $venda->id }}" subtitle="Detalhes da operação." />

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('vendas.edit', $venda) }}" wire:navigate class="rounded-brand-sm bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white">Editar</a>
        <button type="button" wire:click="excluir" class="rounded-brand-sm border border-red-500/60 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-600">Excluir</button>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
            <div class="text-sm text-brand-text-muted">Cliente</div>
            <div class="mt-2 text-base font-semibold text-brand-text">{{ $venda->cliente?->nome ?? 'Sem cliente' }}</div>
            <div class="mt-4 text-sm text-brand-text-muted">Status</div>
            <div class="mt-2 text-base font-semibold text-brand-text">{{ $venda->status?->label() ?? ($venda->pago ? 'Paga' : 'Pendente') }}</div>
        </div>

        <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
            <div class="text-sm text-brand-text-muted">Valores</div>
            <div class="mt-2 text-sm">Subtotal: R$ {{ \App\Support\Format::dinheiro($venda->subtotal) }}</div>
            @if ((float) $venda->frete > 0)
                <div class="text-sm">Frete: R$ {{ \App\Support\Format::dinheiro($venda->frete) }}</div>
            @endif
            <div class="text-sm">Desconto: R$ {{ \App\Support\Format::dinheiro($venda->desconto) }}</div>
            <div class="text-base font-semibold text-brand-text">Total: R$ {{ \App\Support\Format::dinheiro($venda->total) }}</div>
            <div class="mt-3 text-sm text-brand-text-muted">Forma de pagamento</div>
            <div class="text-sm font-medium text-brand-text">{{ $venda->forma_pagamento ? strtoupper($venda->forma_pagamento) : 'A definir' }}</div>
        </div>
    </div>

    <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
        <div class="mb-3 text-sm font-medium text-brand-text-muted">Itens</div>
        @forelse ($venda->itens as $item)
            <div class="flex items-center justify-between border-b border-brand-border/50 py-2 last:border-0">
                <div>
                    <div class="font-medium text-brand-text">{{ $item->nomeExibicao() }}</div>
                    <div class="text-sm text-brand-text-muted">
                        Qtd: {{ $item->quantidade }} × R$ {{ \App\Support\Format::dinheiro($item->preco_unitario) }}
                        @unless ($item->produto_id)
                            <span class="ml-1 rounded bg-brand-soft/40 px-1.5 py-0.5 text-[11px] uppercase tracking-wide">Outro custo</span>
                        @endunless
                    </div>
                </div>
                <div class="font-medium text-brand-text">R$ {{ \App\Support\Format::dinheiro($item->subtotal) }}</div>
            </div>
        @empty
            <div class="text-sm text-brand-text-muted">Nenhum item registrado.</div>
        @endforelse
    </div>

    @if ($venda->observacoes)
        <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
            <div class="mb-2 text-sm font-medium text-brand-text-muted">Observações</div>
            <div class="whitespace-pre-line text-sm text-brand-text">{{ $venda->observacoes }}</div>
        </div>
    @endif
</div>
