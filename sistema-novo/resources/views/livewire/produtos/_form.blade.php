{{--
    Formulario compartilhado entre Create e Edit de Produtos.
    Espera $form (ProdutoForm), $categorias e $produto opcional (edicao).
--}}

{{-- Informacoes --}}
<x-ui.card>
    <h2 class="font-semibold text-brand-text mb-4">Informações</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <x-ui.input name="nome" label="Nome do produto *" wire:model="form.nome" autofocus />
        </div>

        <x-ui.input name="sku" label="SKU" wire:model="form.sku" placeholder="Ex: 0001" helper="Opcional, mas deve ser único." />

        <x-ui.select name="categoria_id" label="Categoria" wire:model="form.categoria_id" placeholder="Sem categoria">
            @foreach ($categorias as $categoria)
                <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
            @endforeach
        </x-ui.select>
    </div>
</x-ui.card>

{{-- Preco --}}
<x-ui.card class="mt-4">
    <h2 class="font-semibold text-brand-text mb-1">Preço e custo</h2>
    <p class="text-xs text-brand-text-muted mb-4">
        O custo de referência é apenas informativo — não altera o custo já registrado em vendas antigas.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-ui.input name="preco_venda" label="Preço de venda (R$) *" wire:model="form.preco_venda" inputmode="decimal" placeholder="0,00" />
        <x-ui.input name="custo_referencia" label="Custo de referência (R$)" wire:model="form.custo_referencia" inputmode="decimal" placeholder="0,00" />
        <x-ui.input name="estoque_minimo" label="Estoque mínimo (opcional)" wire:model="form.estoque_minimo" inputmode="decimal" placeholder="0" />
    </div>
</x-ui.card>

{{-- Imagem --}}
<x-ui.card class="mt-4" x-data="{ preview: null }">
    <h2 class="font-semibold text-brand-text mb-4">Imagem</h2>

    <div class="flex items-start gap-4">
        <div class="w-24 h-24 rounded-brand-sm bg-brand-soft/30 overflow-hidden flex items-center justify-center shrink-0">
            <template x-if="preview">
                <img :src="preview" class="w-full h-full object-cover">
            </template>
            <template x-if="!preview">
                <div>
                    @if (isset($produto) && $produto->imagem)
                        <img src="{{ Storage::url($produto->imagem) }}" class="w-full h-full object-cover" alt="{{ $produto->nome }}">
                    @else
                        <x-icon name="gift" class="w-8 h-8 text-brand-primary/40" />
                    @endif
                </div>
            </template>
        </div>

        <div class="flex-1">
            <input
                type="file"
                wire:model="form.novaImagem"
                accept="image/jpeg,image/png,image/webp"
                x-on:change="preview = $event.target.files.length ? URL.createObjectURL($event.target.files[0]) : null"
                class="block w-full text-sm text-brand-text file:me-3 file:py-2 file:px-4 file:rounded-brand-sm file:border-0 file:bg-brand-primary/10 file:text-brand-primary file:font-medium hover:file:bg-brand-primary/20"
            >
            <p class="mt-1.5 text-xs text-brand-text-muted">JPG, PNG ou WEBP, até 2MB.</p>

            <div wire:loading wire:target="form.novaImagem" class="mt-2">
                <x-ui.skeleton :lines="1" class="max-w-xs" />
            </div>

            @error('form.novaImagem')
                <p class="mt-1.5 text-xs text-brand-danger">{{ $message }}</p>
            @enderror

            @if (isset($produto) && $produto->imagem)
                <div class="mt-2">
                    <x-ui.button type="button" variant="ghost" size="sm" wire:click="removerImagem" wire:confirm="Remover a imagem atual deste produto?">
                        Remover imagem atual
                    </x-ui.button>
                </div>
            @endif
        </div>
    </div>
</x-ui.card>

{{-- Status --}}
<x-ui.card class="mt-4">
    <h2 class="font-semibold text-brand-text mb-4">Status</h2>
    <label class="flex items-center gap-2">
        <input type="checkbox" wire:model="form.ativo" class="rounded border-brand-border/70 text-brand-primary focus:ring-brand-primary">
        <span class="text-sm text-brand-text">Produto ativo</span>
    </label>
</x-ui.card>

<div class="flex justify-end gap-2 mt-5 pb-4">
    <x-ui.button variant="secondary" :href="isset($produto) ? route('produtos.show', $produto) : route('produtos.index')" wire:navigate>
        Cancelar
    </x-ui.button>
    <x-ui.button type="submit" variant="primary">Salvar</x-ui.button>
</div>
