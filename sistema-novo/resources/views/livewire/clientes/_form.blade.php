{{--
    Formulario compartilhado entre Create e Edit (Etapa 6 #50: nao
    duplicar regra/markup entre criar/editar). Espera uma variavel
    $form (ClienteForm) e $cliente opcional (quando em modo edicao).
--}}

{{-- Dados principais --}}
<x-ui.card>
    <h2 class="font-semibold text-brand-text mb-4">Dados principais</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @if (isset($cliente))
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-brand-text mb-1.5">Código</label>
                <input type="text" value="#{{ $cliente->codigo }}" disabled class="block w-full rounded-brand-sm border-brand-border/40 bg-brand-soft/20 text-brand-text-muted min-h-[44px]">
                <p class="mt-1.5 text-xs text-brand-text-muted">O código é gerado automaticamente e não pode ser alterado.</p>
            </div>
        @endif

        <div class="sm:col-span-2">
            <x-ui.input name="nome" label="Nome / Nome fantasia *" wire:model="form.nome" autofocus />
        </div>

        <x-ui.input name="telefone" label="Telefone" wire:model="form.telefone" inputmode="tel" placeholder="(00) 00000-0000" x-mask="(99) 99999-9999" />

        <x-ui.input name="email" label="E-mail" type="email" wire:model="form.email" inputmode="email" placeholder="cliente@email.com" />
    </div>
</x-ui.card>

{{-- Documentos --}}
<x-ui.card class="mt-4">
    <h2 class="font-semibold text-brand-text mb-1">Documentos</h2>
    <p class="text-xs text-brand-text-muted mb-4">Opcional — preencha somente se necessário.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-ui.input name="cpf" label="CPF" wire:model="form.cpf" inputmode="numeric" placeholder="000.000.000-00" x-mask="999.999.999-99" />
        <x-ui.input name="rg" label="RG" wire:model="form.rg" />
        <x-ui.input name="cnpj" label="CNPJ" wire:model="form.cnpj" inputmode="numeric" placeholder="00.000.000/0000-00" x-mask="99.999.999/9999-99" />
        <x-ui.input name="razao_social" label="Razão social" wire:model="form.razao_social" />
    </div>
</x-ui.card>

{{-- Endereco --}}
<x-ui.card class="mt-4">
    <h2 class="font-semibold text-brand-text mb-4">Endereço</h2>
    <x-ui.textarea name="endereco" label="Endereço completo" wire:model="form.endereco" rows="2" placeholder="Rua, número, bairro, cidade..." />
</x-ui.card>

{{-- Observacoes --}}
<x-ui.card class="mt-4">
    <h2 class="font-semibold text-brand-text mb-4">Observações</h2>
    <x-ui.textarea name="observacoes" label="Observações internas" wire:model="form.observacoes" rows="3" />

    <label class="flex items-center gap-2 mt-4">
        <input type="checkbox" wire:model="form.ativo" class="rounded border-brand-border/70 text-brand-primary focus:ring-brand-primary">
        <span class="text-sm text-brand-text">Cliente ativo</span>
    </label>
</x-ui.card>

<div class="flex justify-end gap-2 mt-5 pb-4">
    <x-ui.button variant="secondary" :href="isset($cliente) ? route('clientes.show', $cliente) : route('clientes.index')" wire:navigate>
        Cancelar
    </x-ui.button>
    <x-ui.button type="submit" variant="primary">Salvar</x-ui.button>
</div>
