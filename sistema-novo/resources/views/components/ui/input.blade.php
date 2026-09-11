@props([
    'label' => null,
    'name',
    'error' => null,
    'helper' => null,
    'type' => 'text',
])

@php
    // Se houver wire:model (ex.: "form.preco_venda"), o Livewire aninha
    // o erro sob essa mesma chave - nao apenas o "name" puro. Sem isso,
    // erros de formularios que usam Livewire\Form (ClienteForm,
    // ProdutoForm, CategoriaForm) ficavam silenciosamente invisiveis
    // (bug real encontrado e corrigido na Etapa 6/7).
    $errorKey = $attributes->wire('model')->value() ?: $name;
    $errorMessage = $error ?? ($errors->has($errorKey) ? $errors->first($errorKey) : null);
@endphp

<div>
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-brand-text mb-1.5">{{ $label }}</label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->merge(['class' =>
            'block w-full rounded-brand-sm border-brand-border/70 bg-brand-surface text-brand-text placeholder:text-brand-text-muted/70 '
            .'focus:border-brand-primary focus:ring-brand-primary min-h-[44px] '
            .($errorMessage ? 'border-brand-danger focus:border-brand-danger focus:ring-brand-danger' : '')
        ]) }}
        @if($errorMessage) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
    />

    @if ($helper && ! $errorMessage)
        <p class="mt-1.5 text-xs text-brand-text-muted">{{ $helper }}</p>
    @endif

    @if ($errorMessage)
        <p id="{{ $name }}-error" class="mt-1.5 text-xs text-brand-danger">{{ $errorMessage }}</p>
    @endif
</div>
