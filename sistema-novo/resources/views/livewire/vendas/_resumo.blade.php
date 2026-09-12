<div class="space-y-2 text-sm">
    <div class="flex items-center justify-between text-brand-text-muted">
        <span>Subtotal</span>
        <span>R$ {{ \App\Support\Format::dinheiro($this->subtotal()) }}</span>
    </div>

    @if ($this->freteValor() > 0)
        <div class="flex items-center justify-between text-brand-text-muted">
            <span>Frete</span>
            <span>R$ {{ \App\Support\Format::dinheiro($this->freteValor()) }}</span>
        </div>
    @endif

    @if ($this->descontoValor() > 0)
        <div class="flex items-center justify-between text-brand-text-muted">
            <span>Desconto</span>
            <span>− R$ {{ \App\Support\Format::dinheiro($this->descontoValor()) }}</span>
        </div>
    @endif

    <div class="flex items-center justify-between border-t border-brand-border/60 pt-2 text-base font-bold text-brand-text">
        <span>Total</span>
        <span>R$ {{ \App\Support\Format::dinheiro($this->total()) }}</span>
    </div>

    <div class="text-xs text-brand-text-muted">
        {{ $this->totalItens() }} {{ $this->totalItens() === 1 ? 'item' : 'itens' }} ·
        {{ $pago ? 'Pagamento: '.strtoupper($forma_pagamento) : 'Pagamento: pendente' }}
    </div>
</div>
