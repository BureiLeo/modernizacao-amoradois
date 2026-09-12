@php
    $inputClass = 'w-full rounded-brand-sm border border-brand-border/70 bg-brand-surface px-3 py-2 text-brand-text focus:border-brand-primary focus:ring-brand-primary';
@endphp

<div class="space-y-4">
    <x-ui.page-header title="Cadastrar compra" subtitle="Lance a nota do fornecedor e dê entrada em vários materiais de uma vez.">
        <x-slot name="actions">
            <x-ui.button variant="outline" :href="route('compras.index')" wire:navigate>Voltar</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    @if (session('import'))
        <x-ui.alert variant="success">{{ session('import') }}</x-ui.alert>
    @endif

    @if ($notaDuplicada)
        <x-ui.alert variant="warning">
            Já existe uma compra lançada com esta chave de NF-e. Confira antes de salvar para não dar entrada duplicada no estoque.
        </x-ui.alert>
    @endif

    @error('itens') <x-ui.alert variant="danger">{{ $message }}</x-ui.alert> @enderror

    <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
        <div class="text-sm font-semibold text-brand-text">Importar XML da nota</div>
        <p class="mt-1 text-xs text-brand-text-muted">
            Envie o XML da NF-e que o fornecedor mandou. Os itens são lidos automaticamente e os materiais já cadastrados são reconhecidos pelo nome.
        </p>

        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
            <input type="file" accept=".xml,text/xml,application/xml" wire:model="xml" class="{{ $inputClass }} file:mr-3 file:rounded-full file:border-0 file:bg-brand-soft file:px-3 file:py-1 file:text-xs file:font-semibold file:text-brand-primary" />
            <x-ui.button type="button" variant="secondary" wire:click="importarXml" wire:loading.attr="disabled" wire:target="importarXml,xml" class="shrink-0">
                <span wire:loading.remove wire:target="importarXml,xml">Ler XML</span>
                <span wire:loading wire:target="importarXml,xml">Lendo...</span>
            </x-ui.button>
        </div>
        @error('xml') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
    </div>

    <form wire:submit="salvar" class="space-y-4">
        <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
            <div class="text-sm font-semibold text-brand-text">Dados da nota</div>

            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-brand-text">Data</label>
                    <input type="date" wire:model="data" class="{{ $inputClass }}" />
                    @error('data') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-brand-text">Fornecedor</label>
                    <select wire:model="fornecedor_id" class="{{ $inputClass }}">
                        <option value="">Sem fornecedor</option>
                        @foreach ($fornecedores as $fornecedor)
                            <option value="{{ $fornecedor->id }}">{{ $fornecedor->nome }}</option>
                        @endforeach
                    </select>
                    @error('fornecedor_id') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-brand-text">Número da nota</label>
                    <input type="text" wire:model="numero_nota" class="{{ $inputClass }}" />
                    @error('numero_nota') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-brand-text">Chave da NF-e</label>
                    <input type="text" wire:model="chave_nfe" class="{{ $inputClass }}" />
                    @error('chave_nfe') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-brand-text">Forma de pagamento</label>
                    <select wire:model="forma_pagamento" class="{{ $inputClass }}">
                        <option value="">A definir</option>
                        <option value="PIX">PIX</option>
                        <option value="Dinheiro">Dinheiro</option>
                        <option value="Débito">Débito</option>
                        <option value="Crédito">Crédito</option>
                        <option value="Transferência">Transferência</option>
                        <option value="Boleto">Boleto</option>
                        <option value="Outro">Outro</option>
                    </select>
                    @error('forma_pagamento') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm font-medium text-brand-text">
                        <input type="checkbox" wire:model="pago" class="rounded border-brand-border/70 text-brand-primary focus:ring-brand-primary" />
                        Compra já paga
                    </label>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-brand-text">Observações</label>
                    <textarea wire:model="observacoes" rows="2" class="{{ $inputClass }}"></textarea>
                    @error('observacoes') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-brand-text">Itens da compra</div>
                    <p class="text-xs text-brand-text-muted">Deixe o material em branco para cadastrar um material novo.</p>
                </div>
                <x-ui.button type="button" variant="outline" size="sm" wire:click="adicionarItem">
                    <x-icon name="plus" class="w-4 h-4" />
                    Item
                </x-ui.button>
            </div>

            <div class="mt-3 space-y-3">
                @foreach ($itens as $index => $item)
                    <div wire:key="item-{{ $index }}" class="rounded-xl border border-brand-border/70 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-brand-text-muted">
                                Item {{ $index + 1 }}
                                @if (! empty($item['descricao_nota']))
                                    <span class="ml-1 normal-case text-brand-text-muted/80">· {{ $item['descricao_nota'] }}</span>
                                @endif
                            </div>
                            <button type="button" wire:click="removerItem({{ $index }})" class="text-xs font-semibold text-red-500 hover:underline">Remover</button>
                        </div>

                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-brand-text">Material</label>
                                <select wire:model.live="itens.{{ $index }}.material_id" class="{{ $inputClass }}">
                                    <option value="">+ Cadastrar novo material</option>
                                    @foreach ($materiais as $material)
                                        <option value="{{ $material->id }}">{{ $material->nome }} ({{ $material->unidade_base }})</option>
                                    @endforeach
                                </select>
                            </div>

                            @if (empty($item['material_id']))
                                <div class="md:col-span-2 rounded-lg bg-brand-soft/50 p-3">
                                    <div class="text-xs font-semibold text-brand-primary">Material novo</div>
                                    <div class="mt-2 grid gap-3 md:grid-cols-2">
                                        <div class="md:col-span-2">
                                            <label class="mb-1 block text-sm font-medium text-brand-text">Nome</label>
                                            <input type="text" wire:model="itens.{{ $index }}.nome" class="{{ $inputClass }}" />
                                            @error("itens.{$index}.nome") <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-brand-text">Unidade</label>
                                            <input type="text" wire:model="itens.{{ $index }}.unidade_base" class="{{ $inputClass }}" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-brand-text">Estoque mínimo</label>
                                            <input type="number" min="0" step="0.001" wire:model="itens.{{ $index }}.estoque_minimo" class="{{ $inputClass }}" />
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div>
                                <label class="mb-1 block text-sm font-medium text-brand-text">Quantidade</label>
                                <input type="number" min="0" step="0.001" wire:model.live.debounce.400ms="itens.{{ $index }}.quantidade" class="{{ $inputClass }}" />
                                @error("itens.{$index}.quantidade") <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-brand-text">Valor unitário (R$)</label>
                                <input type="number" min="0" step="0.0001" wire:model.live.debounce.400ms="itens.{{ $index }}.valor_unitario" class="{{ $inputClass }}" />
                                @error("itens.{$index}.valor_unitario") <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="mt-2 text-right text-sm text-brand-text-muted">
                            Total do item:
                            <span class="font-semibold text-brand-text">
                                R$ {{ number_format((float) str_replace(',', '.', (string) ($item['quantidade'] ?? 0)) * (float) str_replace(',', '.', (string) ($item['valor_unitario'] ?? 0)), 2, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-brand-text">Frete (R$)</label>
                    <input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="frete" class="{{ $inputClass }}" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-brand-text">Desconto (R$)</label>
                    <input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="desconto" class="{{ $inputClass }}" />
                </div>
            </div>

            <dl class="mt-4 space-y-1 border-t border-brand-border/70 pt-4 text-sm">
                <div class="flex justify-between text-brand-text-muted">
                    <dt>Subtotal</dt>
                    <dd>R$ {{ number_format($this->subtotal(), 2, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between text-brand-text-muted">
                    <dt>Frete</dt>
                    <dd>R$ {{ number_format((float) str_replace(',', '.', $frete), 2, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between text-brand-text-muted">
                    <dt>Desconto</dt>
                    <dd>- R$ {{ number_format((float) str_replace(',', '.', $desconto), 2, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between text-base font-semibold text-brand-text">
                    <dt>Total da nota</dt>
                    <dd>R$ {{ number_format($this->total(), 2, ',', '.') }}</dd>
                </div>
            </dl>

            <p class="mt-3 text-xs text-brand-text-muted">
                O frete e o desconto são rateados entre os itens proporcionalmente ao valor de cada um, e o custo médio de cada material é recalculado na entrada.
            </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <x-ui.button type="submit" class="sm:w-auto" wire:loading.attr="disabled" wire:target="salvar">
                <span wire:loading.remove wire:target="salvar">Lançar compra e dar entrada</span>
                <span wire:loading wire:target="salvar">Lançando...</span>
            </x-ui.button>
            <x-ui.button type="button" variant="outline" :href="route('estoque.index')" wire:navigate>Cancelar</x-ui.button>
        </div>
    </form>
</div>
