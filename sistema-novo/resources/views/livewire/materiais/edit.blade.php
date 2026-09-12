<div>
    <x-ui.page-header title="Editar material" subtitle="Atualize os dados do insumo." />

    <form wire:submit="salvar" class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-text">Nome</label>
                <input type="text" wire:model="nome" class="w-full rounded-brand-sm border border-brand-border/70 bg-brand-surface px-3 py-2 text-brand-text focus:border-brand-primary focus:ring-brand-primary" />
                @error('nome') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-brand-text">Unidade</label>
                <input type="text" wire:model="unidade_base" class="w-full rounded-brand-sm border border-brand-border/70 bg-brand-surface px-3 py-2 text-brand-text focus:border-brand-primary focus:ring-brand-primary" />
                @error('unidade_base') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-brand-text">Fator embalagem</label>
                <input type="number" min="1" step="1" wire:model="fator_embalagem" class="w-full rounded-brand-sm border border-brand-border/70 bg-brand-surface px-3 py-2 text-brand-text focus:border-brand-primary focus:ring-brand-primary" />
                @error('fator_embalagem') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-brand-text">Estoque mínimo</label>
                <input type="number" min="0" step="0.001" wire:model="estoque_minimo" class="w-full rounded-brand-sm border border-brand-border/70 bg-brand-surface px-3 py-2 text-brand-text focus:border-brand-primary focus:ring-brand-primary" />
                @error('estoque_minimo') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-brand-text">Custo médio</label>
                <input type="number" min="0" step="0.01" wire:model="custo_medio" class="w-full rounded-brand-sm border border-brand-border/70 bg-brand-surface px-3 py-2 text-brand-text focus:border-brand-primary focus:ring-brand-primary" />
                @error('custo_medio') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-brand-text">Observações</label>
                <textarea wire:model="observacoes" rows="3" class="w-full rounded-brand-sm border border-brand-border/70 bg-brand-surface px-3 py-2 text-brand-text focus:border-brand-primary focus:ring-brand-primary"></textarea>
                @error('observacoes') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-brand-sm bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white">Salvar alterações</button>
            <a href="{{ route('materiais.show', $material) }}" wire:navigate class="rounded-brand-sm border border-brand-border/70 bg-brand-surface px-4 py-2.5 text-sm font-medium text-brand-text">Cancelar</a>
        </div>
    </form>
</div>
