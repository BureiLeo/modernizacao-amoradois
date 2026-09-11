{{--
    Modal centralizado, controlado via propriedade Livewire (usa $wire
    diretamente no Alpine, mais robusto que os eventos open-modal/
    close-modal do x-modal do Breeze para este caso de uso).

    Uso:
        <x-ui.modal wire-model="showModal" title="Novo cliente" max-width="lg">
            ...conteudo...
        </x-ui.modal>
--}}
@props(['wireModel' => 'showModal', 'title' => null, 'maxWidth' => 'md', 'closeMethod' => 'fecharModal'])

@php
    $widths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ];
@endphp

<div
    x-show="$wire.{{ $wireModel }}"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    @if($title) aria-label="{{ $title }}" @endif
>
    <div
        x-show="$wire.{{ $wireModel }}"
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-brand-marrom-cafe/50"
        wire:click="{{ $closeMethod }}"
    ></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div
            x-show="$wire.{{ $wireModel }}"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
            class="relative w-full {{ $widths[$maxWidth] ?? $widths['md'] }} bg-brand-surface rounded-brand-lg shadow-brand-lg"
        >
            {{ $slot }}
        </div>
    </div>
</div>
