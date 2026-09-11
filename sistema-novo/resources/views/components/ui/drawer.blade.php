{{--
    Drawer generico (bottom-sheet no mobile), controlado via Alpine.
    Uso:
        <div x-data="{ open: false }">
            <button @click="open = true">Abrir</button>
            <x-ui.drawer x-model-open="open" title="Mais opções">
                ...conteudo...
            </x-ui.drawer>
        </div>
--}}
@props(['title' => null])

<div
    x-show="{{ $attributes->get('x-model-open') ?? 'open' }}"
    x-cloak
    class="fixed inset-0 z-50"
    role="dialog"
    aria-modal="true"
    @if($title) aria-label="{{ $title }}" @endif
>
    {{-- Backdrop --}}
    <div
        x-show="{{ $attributes->get('x-model-open') ?? 'open' }}"
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="{{ $attributes->get('x-model-open') ?? 'open' }} = false"
        class="absolute inset-0 bg-brand-marrom-cafe/50"
    ></div>

    {{-- Painel (sobe do fundo) --}}
    <div
        x-show="{{ $attributes->get('x-model-open') ?? 'open' }}"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        @click.outside="{{ $attributes->get('x-model-open') ?? 'open' }} = false"
        class="absolute bottom-0 inset-x-0 bg-brand-surface rounded-t-brand-lg shadow-brand-lg max-h-[80vh] overflow-y-auto"
        style="padding-bottom: max(1rem, env(safe-area-inset-bottom));"
    >
        <div class="flex items-center justify-center pt-2.5 pb-1">
            <span class="w-10 h-1.5 rounded-full bg-brand-border/50"></span>
        </div>

        @if ($title)
            <div class="px-5 pt-1 pb-3 flex items-center justify-between">
                <h2 class="font-semibold text-brand-text">{{ $title }}</h2>
                <x-ui.icon-button icon="x" label="Fechar" @click="{{ $attributes->get('x-model-open') ?? 'open' }} = false" />
            </div>
        @endif

        <div class="px-3 pb-3">
            {{ $slot }}
        </div>
    </div>
</div>
