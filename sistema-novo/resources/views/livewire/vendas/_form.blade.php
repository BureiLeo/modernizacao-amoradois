<div class="pb-40 lg:pb-8">
    <x-ui.page-header :title="$titulo" :subtitle="$subtitulo">
        <x-slot name="actions">
            <x-ui.button variant="outline" :href="$cancelarUrl" wire:navigate>Cancelar</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    @if ($erro)
        <x-ui.alert variant="danger" class="mb-4">
            <div class="whitespace-pre-line">{{ $erro }}</div>
        </x-ui.alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start">

        {{-- ============ CATÁLOGO ============ --}}
        <div class="space-y-4">
            <x-ui.search-input wire:model.live.debounce.300ms="buscaProduto" placeholder="Buscar produto por nome ou SKU..." />

            @if ($produtos->isEmpty())
                <x-ui.card>
                    <x-ui.empty-state
                        icon="gift"
                        title="Nenhum produto encontrado"
                        :description="$buscaProduto !== '' ? 'Tente outro termo de busca.' : 'Cadastre um produto para começar a vender.'"
                    />
                </x-ui.card>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach ($produtos as $produto)
                        @php
                            $chave = 'p:'.$produto->id;
                            $noCarrinho = $carrinho[$chave] ?? null;
                            $semEstoque = $produto->disponivel !== null && $produto->disponivel <= 0;
                        @endphp

                        <div @class([
                            'group relative overflow-hidden rounded-2xl border bg-brand-surface transition',
                            'border-brand-primary ring-2 ring-brand-primary/30' => $noCarrinho,
                            'border-brand-border/70 hover:border-brand-primary/60 hover:shadow-sm' => ! $noCarrinho && ! $semEstoque,
                            'border-brand-border/40' => $semEstoque,
                        ])>
                            <button
                                type="button"
                                @unless ($semEstoque) wire:click="adicionar({{ $produto->id }})" @endunless
                                @class([
                                    'block w-full text-left',
                                    'cursor-not-allowed' => $semEstoque,
                                ])
                                @disabled($semEstoque)
                            >
                                <div @class([
                                    'relative aspect-square w-full overflow-hidden bg-brand-soft/40',
                                    'grayscale opacity-50' => $semEstoque,
                                ])>
                                    @if ($produto->imagem)
                                        <img src="{{ Storage::url($produto->imagem) }}" alt="{{ $produto->nome }}" class="h-full w-full object-cover transition group-hover:scale-105">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-brand-text-muted/60">
                                            <x-icon name="gift" class="h-10 w-10" />
                                        </div>
                                    @endif

                                    @if ($noCarrinho)
                                        <span class="absolute right-2 top-2 flex h-7 min-w-7 items-center justify-center rounded-full bg-brand-primary px-2 text-xs font-bold text-white shadow">
                                            {{ $noCarrinho['quantidade'] }}
                                        </span>
                                    @endif

                                    @if ($semEstoque)
                                        <span class="absolute inset-x-0 bottom-0 bg-black/70 px-2 py-1 text-center text-[11px] font-semibold uppercase tracking-wide text-white">
                                            Sem estoque
                                        </span>
                                    @endif
                                </div>

                                <div @class(['p-2.5', 'opacity-50' => $semEstoque])>
                                    <div class="line-clamp-2 min-h-[2.5rem] text-sm font-medium leading-tight text-brand-text">{{ $produto->nome }}</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-primary">R$ {{ \App\Support\Format::dinheiro($produto->preco_venda) }}</div>
                                </div>
                            </button>

                            @if ($noCarrinho)
                                <div class="flex items-center justify-between gap-2 border-t border-brand-border/60 bg-brand-soft/20 px-2 py-1.5">
                                    <button type="button" wire:click="diminuir('{{ $chave }}')" aria-label="Diminuir quantidade de {{ $produto->nome }}"
                                        class="flex h-8 w-8 items-center justify-center rounded-full border border-brand-border/70 bg-brand-surface text-lg font-bold leading-none text-brand-text">−</button>
                                    <span class="text-sm font-semibold text-brand-text">{{ $noCarrinho['quantidade'] }}</span>
                                    <button type="button" wire:click="aumentar('{{ $chave }}')" aria-label="Aumentar quantidade de {{ $produto->nome }}"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-primary text-lg font-bold leading-none text-white">+</button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- ============ OUTROS CUSTOS ============ --}}
            <div class="rounded-2xl border border-dashed border-brand-border bg-brand-surface p-4">
                @if (! $mostrarOutros)
                    <button type="button" wire:click="$set('mostrarOutros', true)" class="flex w-full items-center gap-3 text-left">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-soft/50 text-brand-primary">
                            <x-icon name="plus" class="h-5 w-5" />
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-brand-text">Outros</span>
                            <span class="block text-xs text-brand-text-muted">Adicionar um custo manual (taxa, personalização, embalagem...)</span>
                        </span>
                    </button>
                @else
                    <div class="space-y-3">
                        <div class="text-sm font-semibold text-brand-text">Outro custo</div>

                        <x-ui.input name="outroDescricao" label="Descrição" wire:model="outroDescricao" placeholder="Ex.: Taxa de personalização" />

                        <div class="grid grid-cols-2 gap-3">
                            <x-ui.input name="outroValor" label="Valor unitário (R$)" wire:model="outroValor" inputmode="decimal" placeholder="0,00" />
                            <x-ui.input name="outroQuantidade" type="number" min="1" label="Quantidade" wire:model="outroQuantidade" />
                        </div>

                        <div class="flex gap-2">
                            <x-ui.button type="button" wire:click="adicionarOutro">Adicionar</x-ui.button>
                            <x-ui.button type="button" variant="ghost" wire:click="$set('mostrarOutros', false)">Cancelar</x-ui.button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ============ CARRINHO ============ --}}
        <div class="space-y-4 lg:sticky lg:top-4">
            <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-brand-text">
                        Carrinho
                        @if ($this->totalItens() > 0)
                            <span class="ml-1 text-brand-text-muted">({{ $this->totalItens() }} {{ $this->totalItens() === 1 ? 'item' : 'itens' }})</span>
                        @endif
                    </div>
                    @if ($carrinho !== [])
                        <button type="button" wire:click="limpar" class="text-xs font-medium text-brand-danger">Limpar</button>
                    @endif
                </div>

                @if ($carrinho === [])
                    <div class="py-6 text-center text-sm text-brand-text-muted">
                        Nenhum item ainda.<br>Toque em um produto para adicionar.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($carrinho as $chave => $linha)
                            <div class="flex items-start gap-3 border-b border-brand-border/50 pb-3 last:border-0 last:pb-0" wire:key="cart-{{ $chave }}">
                                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-brand-soft/40">
                                    @if ($linha['imagem'])
                                        <img src="{{ Storage::url($linha['imagem']) }}" alt="" class="h-full w-full object-cover">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-brand-text-muted/60">
                                            <x-icon name="{{ $linha['produto_id'] ? 'gift' : 'wallet' }}" class="h-5 w-5" />
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-brand-text">{{ $linha['nome'] }}</div>
                                    @unless ($linha['produto_id'])
                                        <div class="text-[11px] uppercase tracking-wide text-brand-text-muted">Outro custo</div>
                                    @endunless

                                    <div class="mt-2 flex items-center gap-2">
                                        <button type="button" wire:click="diminuir('{{ $chave }}')" aria-label="Diminuir"
                                            class="flex h-7 w-7 items-center justify-center rounded-full border border-brand-border/70 text-base font-bold leading-none text-brand-text">−</button>
                                        <input type="number" min="1" wire:model.live.debounce.500ms="carrinho.{{ $chave }}.quantidade" aria-label="Quantidade"
                                            class="h-7 w-14 rounded-brand-sm border-brand-border/70 bg-brand-surface px-1 text-center text-sm text-brand-text focus:border-brand-primary focus:ring-brand-primary">
                                        <button type="button" wire:click="aumentar('{{ $chave }}')" aria-label="Aumentar"
                                            class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-primary text-base font-bold leading-none text-white">+</button>

                                        <div class="ml-auto flex items-center gap-1 text-xs text-brand-text-muted">
                                            <span>R$</span>
                                            <input type="text" inputmode="decimal" wire:model.live.debounce.600ms="carrinho.{{ $chave }}.preco_unitario" aria-label="Preço unitário"
                                                class="h-7 w-20 rounded-brand-sm border-brand-border/70 bg-brand-surface px-1 text-right text-sm text-brand-text focus:border-brand-primary focus:ring-brand-primary">
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col items-end gap-2">
                                    <div class="whitespace-nowrap text-sm font-semibold text-brand-text">
                                        R$ {{ \App\Support\Format::dinheiro((float) $linha['preco_unitario'] * (int) $linha['quantidade']) }}
                                    </div>
                                    <button type="button" wire:click="remover('{{ $chave }}')" aria-label="Remover item" class="text-brand-text-muted hover:text-brand-danger">
                                        <x-icon name="x" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ============ DADOS DA VENDA ============ --}}
            <div class="space-y-3 rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
                <div>
                    <span class="mb-1.5 block text-sm font-medium text-brand-text">Cliente (opcional)</span>

                    @if ($clienteSelecionado)
                        <div class="flex items-center justify-between gap-2 rounded-brand-sm border border-brand-border/70 bg-brand-soft/40 px-3 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-brand-text">{{ $clienteSelecionado->nome }}</p>
                                @if ($clienteSelecionado->telefone)
                                    <p class="truncate text-xs text-brand-text-muted">{{ $clienteSelecionado->telefone }}</p>
                                @endif
                            </div>
                            <button type="button" wire:click="limparCliente" class="shrink-0 rounded-brand-sm px-2 py-1 text-xs font-medium text-brand-text-muted hover:text-brand-danger">
                                Trocar
                            </button>
                        </div>
                    @else
                        <div class="relative">
                            <x-ui.input
                                name="buscaCliente"
                                wire:model.live.debounce.400ms="buscaCliente"
                                placeholder="Buscar por nome ou telefone... (ex.: fer)"
                            />

                            @if ($buscaCliente !== '')
                                <div class="mt-1 max-h-56 overflow-y-auto rounded-brand-sm border border-brand-border/70 bg-brand-surface shadow-brand-md">
                                    @forelse ($clientes as $cliente)
                                        <button
                                            type="button"
                                            wire:click="selecionarCliente({{ $cliente->id }})"
                                            wire:key="cliente-sugestao-{{ $cliente->id }}"
                                            class="flex w-full items-center justify-between gap-2 border-b border-brand-border/40 px-3 py-2.5 text-left last:border-b-0 hover:bg-brand-soft/40"
                                        >
                                            <span class="truncate text-sm text-brand-text">{{ $cliente->nome }}</span>
                                            @if ($cliente->telefone)
                                                <span class="shrink-0 text-xs text-brand-text-muted">{{ $cliente->telefone }}</span>
                                            @endif
                                        </button>
                                    @empty
                                        <p class="px-3 py-2.5 text-sm text-brand-text-muted">Nenhum cliente encontrado.</p>
                                    @endforelse

                                    <button
                                        type="button"
                                        wire:click="abrirNovoCliente"
                                        class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm font-semibold text-brand-primary hover:bg-brand-soft/40"
                                    >
                                        <x-icon name="plus" class="h-4 w-4" />
                                        Cadastrar novo cliente
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input name="frete" label="Frete (R$)" wire:model.live.debounce.600ms="frete" inputmode="decimal" placeholder="0,00" />
                    <x-ui.input name="desconto" label="Desconto (R$)" wire:model.live.debounce.600ms="desconto" inputmode="decimal" placeholder="0,00" />
                </div>

                <div>
                    <span class="mb-1.5 block text-sm font-medium text-brand-text">Pagamento</span>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="$set('pago', true)" @class([
                            'rounded-brand-sm border px-3 py-2.5 text-sm font-semibold transition',
                            'border-brand-primary bg-brand-primary text-white' => $pago,
                            'border-brand-border/70 bg-brand-surface text-brand-text' => ! $pago,
                        ])>Pago</button>
                        <button type="button" wire:click="$set('pago', false)" @class([
                            'rounded-brand-sm border px-3 py-2.5 text-sm font-semibold transition',
                            'border-brand-warning bg-brand-warning/10 text-brand-warning' => ! $pago,
                            'border-brand-border/70 bg-brand-surface text-brand-text' => $pago,
                        ])>Pendente</button>
                    </div>
                </div>

                @if ($pago)
                    <x-ui.select name="forma_pagamento" label="Forma de pagamento" wire:model="forma_pagamento">
                        <option value="pix">PIX</option>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="debito">Débito</option>
                        <option value="credito">Crédito</option>
                        <option value="transferencia">Transferência</option>
                        <option value="outro">Outro</option>
                    </x-ui.select>
                @endif

                <x-ui.textarea name="observacoes" label="Observações" rows="2" wire:model="observacoes" placeholder="Opcional" />
            </div>

            {{-- ============ RESUMO (desktop) ============ --}}
            <div class="hidden rounded-2xl border border-brand-border/70 bg-brand-surface p-4 lg:block">
                @include('livewire.vendas._resumo')

                @if ($confirmando)
                    <x-ui.button type="button" class="mt-3 w-full justify-center" wire:click="salvar" wire:loading.attr="disabled" wire:target="salvar">
                        <span wire:loading.remove wire:target="salvar">{{ $confirmarLabel }}</span>
                        <span wire:loading wire:target="salvar">Salvando...</span>
                    </x-ui.button>
                    <x-ui.button type="button" variant="ghost" class="mt-2 w-full justify-center" wire:click="$set('confirmando', false)">Voltar</x-ui.button>
                @else
                    <x-ui.button type="button" class="mt-3 w-full justify-center" wire:click="revisar" :disabled="empty($carrinho)">
                        {{ $finalizarLabel }}
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>

    {{-- ============ BARRA FIXA (mobile) ============ --}}
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-brand-border/70 bg-brand-surface/95 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] shadow-[0_-4px_16px_rgba(0,0,0,0.06)] backdrop-blur lg:hidden">
        <div class="mb-2 flex items-end justify-between">
            <div class="text-xs text-brand-text-muted">
                {{ $this->totalItens() }} {{ $this->totalItens() === 1 ? 'item' : 'itens' }}
                @if ($this->freteValor() > 0) · frete R$ {{ \App\Support\Format::dinheiro($this->freteValor()) }} @endif
                @if ($this->descontoValor() > 0) · desc. R$ {{ \App\Support\Format::dinheiro($this->descontoValor()) }} @endif
                <span class="block">{{ $pago ? strtoupper($forma_pagamento) : 'Pendente' }}</span>
            </div>
            <div class="text-right">
                <div class="text-[11px] text-brand-text-muted">Total</div>
                <div class="text-xl font-bold text-brand-text">R$ {{ \App\Support\Format::dinheiro($this->total()) }}</div>
            </div>
        </div>

        @if ($confirmando)
            <div class="flex gap-2">
                <x-ui.button type="button" variant="outline" wire:click="$set('confirmando', false)">Voltar</x-ui.button>
                <x-ui.button type="button" class="flex-1 justify-center" wire:click="salvar" wire:loading.attr="disabled" wire:target="salvar">
                    <span wire:loading.remove wire:target="salvar">{{ $confirmarLabel }}</span>
                    <span wire:loading wire:target="salvar">Salvando...</span>
                </x-ui.button>
            </div>
        @else
            <x-ui.button type="button" class="w-full justify-center" wire:click="revisar" :disabled="empty($carrinho)">
                {{ $finalizarLabel }}
            </x-ui.button>
        @endif
    </div>

    {{-- ============ MODAL: NOVO CLIENTE RÁPIDO ============ --}}
    <x-ui.modal wire-model="mostrarNovoCliente" title="Novo cliente" max-width="sm" close-method="fecharNovoCliente">
        <form wire:submit="criarClienteRapido" class="p-6 space-y-4">
            <h2 class="text-lg font-semibold text-brand-text">Cadastrar novo cliente</h2>

            <x-ui.input name="novoClienteNome" label="Nome *" wire:model="novoClienteNome" autofocus placeholder="Nome do cliente" />
            <x-ui.input name="novoClienteTelefone" label="Telefone" wire:model="novoClienteTelefone" placeholder="(00) 00000-0000" />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" variant="secondary" wire:click="fecharNovoCliente">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="criarClienteRapido">
                    Cadastrar e selecionar
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
