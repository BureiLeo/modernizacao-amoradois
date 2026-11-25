<div>
    <x-ui.page-header title="PDV da feira">
        <x-slot name="actions">
            <x-ui.button :href="route('financeiro.index')" wire:navigate variant="outline">Caixa da feira</x-ui.button>
            <x-ui.button :href="route('vendas.index', ['filtro' => 'feira'])" wire:navigate variant="outline">
                Vendas da feira
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>

    @if (! $caixaFeira?->aberto)
        <x-ui.alert variant="danger" class="mb-4">
            Abra o caixa da feira para registrar vendas.
            <a href="{{ route('financeiro.index') }}" wire:navigate class="font-semibold underline">Ir para o caixa</a>
        </x-ui.alert>
    @else
    <p class="mb-4 text-sm text-brand-text-muted">Caixa aberto · {{ $caixaFeira->nome }}</p>
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_23rem] lg:items-start">
        <section id="catalogo-pdv" aria-label="Catálogo de produtos" class="space-y-3">
            <div class="flex items-center gap-2">
                <div class="min-w-0 flex-1">
                    <x-ui.search-input wire:model.live.debounce.250ms="busca" aria-label="Buscar por nome ou SKU"
                        placeholder="Buscar por nome ou SKU" maxlength="120" />
                </div>
                <x-ui.button href="#carrinho-pdv" variant="outline" class="lg:hidden">
                    Carrinho ({{ array_sum(array_column($carrinho, 'quantidade')) }})
                </x-ui.button>
            </div>

            <div class="grid max-h-[55vh] grid-cols-2 gap-3 overflow-y-auto p-1 sm:grid-cols-3 lg:max-h-[70vh]">
                @forelse ($produtos as $produto)
                    @php
                        $quantidade = $carrinho[$produto->id]['quantidade'] ?? 0;
                        $saldo = $produto->quantidadeDisponivel();
                    @endphp
                    <button type="button" wire:key="pdv-produto-{{ $produto->id }}"
                        wire:click="adicionar({{ $produto->id }})" wire:loading.attr="disabled"
                        @disabled($produto->bom->isEmpty() || ($saldo !== null && $saldo <= $quantidade))
                        class="relative overflow-hidden rounded-2xl border border-brand-border/60 bg-brand-surface text-left focus-visible:ring-2 focus-visible:ring-brand-primary enabled:hover:border-brand-primary disabled:opacity-60">
                        @if ($produto->imagem)
                            <img src="{{ Storage::url($produto->imagem) }}" alt="" loading="lazy" class="aspect-[4/3] w-full object-cover">
                        @endif
                        @if ($quantidade)
                            <span class="absolute right-2 top-2 rounded-full bg-brand-primary px-2 text-xs font-bold text-white">{{ $quantidade }}</span>
                        @endif
                        <span class="block space-y-1 p-3">
                            <span class="block pr-4 text-sm font-semibold">{{ $produto->nome }}</span>
                            <span class="block text-xs text-brand-text-muted">{{ \App\Enums\ContaCaixa::from($produto->conta)->label() }}</span>
                            <span class="block text-sm font-bold text-brand-primary">R$ {{ \App\Support\Format::dinheiro($produto->preco_venda) }}</span>
                            <span class="block text-xs text-brand-text-muted">
                                @if ($produto->bom->isEmpty())
                                    Sem materiais configurados
                                @elseif ($saldo !== null)
                                    {{ max(0, $saldo - $quantidade) }} disponível(is)
                                @else
                                    {{ $produto->sku }}
                                @endif
                            </span>
                        </span>
                    </button>
                @empty
                    <p class="col-span-full rounded-2xl border border-brand-border/60 bg-brand-surface p-5 text-sm text-brand-text-muted">
                        Nenhum produto encontrado.
                    </p>
                @endforelse
            </div>
        </section>

        <section id="carrinho-pdv" aria-labelledby="titulo-carrinho-pdv"
            class="space-y-4 rounded-2xl border border-brand-border/60 bg-brand-surface p-4 lg:sticky lg:top-24">
            <h2 id="titulo-carrinho-pdv" class="text-lg font-semibold">Carrinho</h2>

            @if ($vendaRegistrada)
                <div role="status">
                    <x-ui.alert variant="success">
                        Venda #{{ $vendaRegistrada }} registrada.
                        <a href="{{ route('vendas.show', $vendaRegistrada) }}" wire:navigate class="font-semibold underline">Ver venda</a>
                    </x-ui.alert>
                    <a href="#catalogo-pdv" class="mt-2 inline-block font-semibold text-brand-primary underline lg:hidden">Voltar ao catálogo</a>
                </div>
            @endif

            @error('carrinho')
                <div role="alert"><x-ui.alert variant="danger">{{ $message }}</x-ui.alert></div>
            @enderror

            @forelse ($carrinho as $id => $item)
                <div wire:key="pdv-item-{{ $id }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-brand-border/40 pb-3">
                    <div class="min-w-0 flex-1">
                        <div class="break-words text-sm font-semibold">{{ $item['nome'] }}</div>
                        <div class="text-xs text-brand-text-muted">R$ {{ \App\Support\Format::dinheiro($item['preco_unitario']) }} / un.</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.button variant="outline" wire:click="diminuir({{ $id }})" wire:loading.attr="disabled"
                            aria-label="Diminuir quantidade de {{ $item['nome'] }}">−</x-ui.button>
                        <span class="w-5 text-center text-sm font-semibold tabular-nums">{{ $item['quantidade'] }}</span>
                        <x-ui.button variant="outline" wire:click="adicionar({{ $id }})" wire:loading.attr="disabled"
                            aria-label="Aumentar quantidade de {{ $item['nome'] }}">+</x-ui.button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-brand-text-muted">Toque em um produto para começar a venda.</p>
            @endforelse

            @if ($carrinho)
                <div class="flex items-baseline justify-between gap-2">
                    <span class="font-medium">Total</span>
                    <strong class="text-2xl tabular-nums">R$ {{ \App\Support\Format::dinheiro($total) }}</strong>
                </div>
                <form wire:submit="finalizar" class="space-y-3">
                    <fieldset class="space-y-2">
                        <legend class="text-sm font-semibold">Pagamento recebido</legend>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($formas as $opcao)
                                <label class="flex min-h-[44px] cursor-pointer items-center gap-2 rounded-brand-sm border border-brand-border/60 px-2 text-sm">
                                    <input type="radio" name="forma" value="{{ $opcao->value }}" wire:model.live="forma"
                                        class="border-brand-border text-brand-primary focus:ring-brand-primary">
                                    {{ $opcao->label() }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @error('forma') <p role="alert" class="text-sm text-brand-danger">{{ $message }}</p> @enderror

                    @if ($forma === 'dinheiro')
                        <x-ui.input name="recebido" label="Valor recebido (opcional)" wire:model.live.debounce.250ms="recebido"
                            inputmode="decimal" maxlength="13" placeholder="0,00" />
                        @if ($troco !== null)
                            <p class="flex justify-between text-sm"><span>Troco</span><strong>R$ {{ \App\Support\Format::dinheiro($troco) }}</strong></p>
                        @endif
                    @endif

                    <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="finalizar">Registrar venda paga</span>
                        <span wire:loading wire:target="finalizar">Registrando…</span>
                    </x-ui.button>
                </form>
            @endif
        </section>
    </div>
    @endif
</div>
