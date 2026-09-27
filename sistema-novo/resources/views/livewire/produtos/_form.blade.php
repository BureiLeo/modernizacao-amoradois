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

{{-- Receita / BOM --}}
<x-ui.card class="mt-4">
    <div class="flex items-start justify-between gap-3 mb-4">
        <div>
            <h2 class="font-semibold text-brand-text mb-1">Materiais utilizados (BOM)</h2>
            <p class="text-xs text-brand-text-muted">
                Informe quanto de cada material é usado para produzir uma unidade deste produto.
            </p>
        </div>
        <x-ui.button type="button" variant="outline" size="sm" wire:click="adicionarMaterial">
            <x-icon name="plus" class="w-4 h-4" />
            Material
        </x-ui.button>
    </div>

    @if ($materiais->isEmpty())
        <x-ui.empty-state
            icon="archive"
            title="Nenhum material cadastrado"
            description="Cadastre um material no estoque antes de montar a receita."
        />
    @elseif ($form->bom === [])
        <x-ui.empty-state
            icon="archive"
            title="Sem materiais na receita"
            description="Clique em Material para começar a montar o BOM deste produto."
        />
    @else
        <div class="space-y-3">
            @foreach ($form->bom as $index => $item)
                @php
                    $materialSelecionado = $materiais->firstWhere('id', (int) ($item['material_id'] ?? 0));
                    $quantidade = (float) str_replace(',', '.', (string) ($item['quantidade'] ?? 0));
                    $subtotalMaterial = $materialSelecionado
                        ? $quantidade * (float) $materialSelecionado->custo_medio
                        : 0;
                @endphp
                <div wire:key="bom-item-{{ $index }}" class="rounded-brand-sm border border-brand-border/50 p-3">
                    <div class="grid grid-cols-1 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto] gap-3 items-start">
                        <x-ui.select
                            name="bom_{{ $index }}_material_id"
                            label="Material *"
                            wire:model.live="form.bom.{{ $index }}.material_id"
                            placeholder="Selecione"
                        >
                            @foreach ($materiais as $material)
                                <option value="{{ $material->id }}">
                                    {{ $material->nome }} — R$ {{ number_format((float) $material->custo_medio, 4, ',', '.') }}/{{ $material->unidade_base }}
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.input
                            name="bom_{{ $index }}_quantidade"
                            label="Quantidade *"
                            wire:model.live.debounce.300ms="form.bom.{{ $index }}.quantidade"
                            inputmode="decimal"
                            placeholder="1"
                            :helper="$materialSelecionado ? 'Em '.$materialSelecionado->unidade_base : null"
                        />

                        <button
                            type="button"
                            wire:click="removerMaterial({{ $index }})"
                            class="md:mt-8 min-h-[44px] px-2 text-sm font-medium text-brand-danger hover:underline"
                        >
                            Remover
                        </button>
                    </div>

                    @if ($materialSelecionado)
                        <p class="mt-2 text-xs text-brand-text-muted">
                            Custo neste produto: R$ {{ number_format($subtotalMaterial, 4, ',', '.') }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @error('form.bom')
        <p class="mt-3 text-xs text-brand-danger">{{ $message }}</p>
    @enderror
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

    <div class="mt-4 rounded-brand-sm border border-brand-border/50 bg-brand-soft/20 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            <div>
                <p class="text-xs text-brand-text-muted">Custo calculado da receita</p>
                <p class="mt-1 text-lg font-semibold text-brand-text">R$ {{ number_format($custoBom, 2, ',', '.') }}</p>
            </div>

            <x-ui.input
                name="margem_lucro"
                label="Porcentagem sobre o custo (%)"
                wire:model.live.debounce.300ms="form.margem_lucro"
                inputmode="decimal"
                placeholder="Ex: 30"
            />

            <div>
                <p class="text-xs text-brand-text-muted">Preço sugerido</p>
                <p class="mt-1 text-lg font-semibold text-brand-primary">
                    {{ $precoSugerido !== null ? 'R$ '.number_format($precoSugerido, 2, ',', '.') : '—' }}
                </p>
            </div>
        </div>

        <div class="mt-3 flex justify-end">
            <x-ui.button type="button" variant="outline" size="sm" wire:click="aplicarPrecoSugerido">
                Usar preço sugerido
            </x-ui.button>
        </div>
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
