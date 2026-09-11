<?php

use App\Livewire\Actions\Logout;
use App\Support\Navigation;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $navPrimary = Navigation::primary();
    $navSecondary = Navigation::secondary();
    $navBottom = Navigation::bottom();
    $primaryAction = Navigation::primaryAction();
    $user = auth()->user();
@endphp

<div x-data="{ moreOpen: false, userMenuOpen: false }">

    {{-- ==================================================================
         SIDEBAR — somente desktop (>= lg, 1024px)
         ================================================================== --}}
    <aside class="hidden lg:flex lg:flex-col lg:fixed lg:inset-y-0 lg:left-0 lg:w-64 lg:z-30 bg-gradient-to-b from-brand-vinho to-brand-ameixa">
        <div class="flex items-center gap-3 px-5 h-20 shrink-0">
            <img src="{{ asset('images/brand/icon-master.png') }}" alt="" class="w-9 h-9 object-contain" aria-hidden="true">
            <div class="min-w-0">
                <p class="text-brand-branco font-bold leading-tight truncate">Amor a Dois</p>
                <p class="text-brand-rosa-nude/80 text-[11px] leading-tight truncate">Personalizados</p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-1">
            @foreach ($navPrimary as $item)
                <a
                    href="{{ $item['href'] ?? '#' }}"
                    @if(! $item['enabled']) aria-disabled="true" tabindex="-1" @else wire:navigate @endif
                    @class([
                        'flex items-center gap-3 px-3 py-2.5 rounded-brand-sm text-sm font-medium transition-colors',
                        'bg-brand-branco/15 text-brand-branco' => $item['active'],
                        'text-brand-rosa-nude/90 hover:bg-brand-branco/10 hover:text-brand-branco' => ! $item['active'] && $item['enabled'],
                        'text-brand-rosa-nude/40 pointer-events-none' => ! $item['enabled'],
                    ])
                >
                    <x-icon :name="$item['icon']" class="w-5 h-5 shrink-0" />
                    <span class="truncate">{{ $item['label'] }}</span>
                    @if (! $item['enabled'])
                        <span class="ms-auto text-[10px] uppercase tracking-wide bg-brand-branco/10 px-1.5 py-0.5 rounded">Em breve</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="px-3 py-2 border-t border-brand-branco/10 space-y-1">
            @foreach ($navSecondary as $item)
                <a
                    href="{{ $item['href'] ?? '#' }}"
                    @if(! $item['enabled']) aria-disabled="true" tabindex="-1" @else wire:navigate @endif
                    @class([
                        'flex items-center gap-3 px-3 py-2.5 rounded-brand-sm text-sm font-medium transition-colors',
                        'bg-brand-branco/15 text-brand-branco' => $item['active'],
                        'text-brand-rosa-nude/90 hover:bg-brand-branco/10 hover:text-brand-branco' => ! $item['active'] && $item['enabled'],
                        'text-brand-rosa-nude/40 pointer-events-none' => ! $item['enabled'],
                    ])
                >
                    <x-icon :name="$item['icon']" class="w-5 h-5 shrink-0" />
                    <span class="truncate">{{ $item['label'] }}</span>
                    @if (! $item['enabled'])
                        <span class="ms-auto text-[10px] uppercase tracking-wide bg-brand-branco/10 px-1.5 py-0.5 rounded">Em breve</span>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- Usuario atual + sair --}}
        <div class="px-3 py-3 border-t border-brand-branco/10">
            <div class="flex items-center gap-3 px-2">
                <div class="w-9 h-9 rounded-full bg-brand-branco/15 text-brand-branco flex items-center justify-center font-semibold text-sm shrink-0">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-brand-branco text-sm font-medium truncate">{{ $user->name }}</p>
                    <p class="text-brand-rosa-nude/70 text-xs truncate">{{ $user->role->label() }}</p>
                </div>
                <button wire:click="logout" aria-label="Sair" class="shrink-0 text-brand-rosa-nude/80 hover:text-brand-branco">
                    <x-icon name="log-out" class="w-4 h-4" />
                </button>
            </div>
        </div>
    </aside>

    {{-- ==================================================================
         HEADER DESKTOP — somente >= lg
         ================================================================== --}}
    <header class="hidden lg:flex lg:fixed lg:top-0 lg:left-64 lg:right-0 lg:h-20 lg:z-20 items-center gap-4 px-6 bg-brand-surface border-b border-brand-border/30">
        <div class="flex-1 max-w-md">
            <x-ui.search-input placeholder="Buscar produtos, clientes ou pedidos..." disabled class="opacity-60" />
        </div>

        <div class="flex items-center gap-2 ms-auto">
            <x-ui.icon-button icon="bell" label="Notificações" />

            <div class="relative" x-on:click.outside="userMenuOpen = false">
                <button type="button" @click="userMenuOpen = ! userMenuOpen" class="flex items-center gap-2 pl-2 pr-3 py-1.5 rounded-brand-sm hover:bg-brand-soft/30">
                    <div class="w-8 h-8 rounded-full bg-brand-primary/10 text-brand-primary flex items-center justify-center font-semibold text-sm">
                        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                    </div>
                    <span class="text-sm text-brand-text font-medium">{{ Str::of($user->name)->before(' ') }}</span>
                    <x-icon name="chevron-down" class="w-4 h-4 text-brand-text-muted" />
                </button>

                <div
                    x-show="userMenuOpen" x-cloak
                    x-transition
                    class="absolute right-0 mt-2 w-48 bg-brand-surface border border-brand-border/40 rounded-brand-sm shadow-brand-md py-1 z-30"
                >
                    <p class="px-3 py-2 text-xs text-brand-text-muted border-b border-brand-border/30">{{ $user->role->label() }}</p>
                    <a href="{{ route('profile') }}" wire:navigate class="block px-3 py-2 text-sm text-brand-text hover:bg-brand-soft/30">Meu perfil</a>
                    <button wire:click="logout" class="w-full text-left px-3 py-2 text-sm text-brand-danger hover:bg-brand-danger-soft">Sair</button>
                </div>
            </div>
        </div>
    </header>

    {{-- ==================================================================
         HEADER MOBILE — somente < lg
         ================================================================== --}}
    <header class="lg:hidden sticky top-0 z-20 flex items-center justify-between gap-3 px-4 h-16 bg-brand-surface border-b border-brand-border/30">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 min-w-0">
            <img src="{{ asset('images/brand/icon-master.png') }}" alt="" aria-hidden="true" class="w-8 h-8 object-contain">
            <span class="font-bold text-brand-text truncate">Amor a Dois</span>
        </a>

        <div class="flex items-center gap-1 shrink-0">
            <x-ui.icon-button icon="bell" label="Notificações" />
            <div class="w-9 h-9 rounded-full bg-brand-primary/10 text-brand-primary flex items-center justify-center font-semibold text-sm">
                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
            </div>
        </div>
    </header>

    {{-- ==================================================================
         BOTTOM NAVIGATION — somente < lg
         ================================================================== --}}
    <nav
        class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-brand-surface border-t border-brand-border/30"
        style="padding-bottom: env(safe-area-inset-bottom);"
        aria-label="Navegação principal"
    >
        <div class="grid grid-cols-5 items-center h-16">
            @foreach ($navBottom as $i => $item)
                @if ($i === 2)
                    {{-- Slot central do botao "+" --}}
                    <div class="flex items-center justify-center">
                        <a
                            href="{{ $primaryAction['href'] ?? '#' }}"
                            @if(! $primaryAction['enabled']) aria-disabled="true" tabindex="-1" @else wire:navigate @endif
                            aria-label="{{ $primaryAction['label'] }}"
                            @class([
                                '-mt-6 w-14 h-14 rounded-full flex items-center justify-center shadow-brand-md text-brand-branco',
                                'bg-brand-primary' => $primaryAction['enabled'],
                                'bg-brand-border pointer-events-none' => ! $primaryAction['enabled'],
                            ])
                        >
                            <x-icon name="plus" class="w-6 h-6" />
                        </a>
                    </div>
                @endif

                <a
                    href="{{ $item['href'] ?? '#' }}"
                    @if(! $item['enabled']) aria-disabled="true" tabindex="-1" @else wire:navigate @endif
                    @class([
                        'flex flex-col items-center justify-center gap-0.5 h-full text-[11px] font-medium',
                        'text-brand-primary' => $item['active'],
                        'text-brand-text-muted' => ! $item['active'] && $item['enabled'],
                        'text-brand-text-muted/40 pointer-events-none' => ! $item['enabled'],
                    ])
                >
                    <x-icon :name="$item['icon']" class="w-5 h-5" />
                    {{ $item['label'] }}
                </a>
            @endforeach

            <button
                type="button"
                @click="moreOpen = true"
                class="flex flex-col items-center justify-center gap-0.5 h-full text-[11px] font-medium text-brand-text-muted"
            >
                <x-icon name="more-horizontal" class="w-5 h-5" />
                Mais
            </button>
        </div>
    </nav>

    {{-- Drawer "Mais" (mobile) --}}
    <x-ui.drawer x-model-open="moreOpen" title="Mais opções">
        <div class="space-y-1 pb-2">
            @foreach ($navPrimary as $item)
                @continue(in_array($item['key'], array_column($navBottom, 'key'), true))
                <a
                    href="{{ $item['href'] ?? '#' }}"
                    @if(! $item['enabled']) aria-disabled="true" tabindex="-1" @else wire:navigate @click="moreOpen = false" @endif
                    @class([
                        'flex items-center gap-3 px-3 py-3 rounded-brand-sm text-sm font-medium',
                        'text-brand-text hover:bg-brand-soft/30' => $item['enabled'],
                        'text-brand-text-muted/50 pointer-events-none' => ! $item['enabled'],
                    ])
                >
                    <x-icon :name="$item['icon']" class="w-5 h-5 shrink-0" />
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if (! $item['enabled'])
                        <span class="text-[10px] uppercase tracking-wide bg-brand-soft/60 text-brand-text-muted px-1.5 py-0.5 rounded">Em breve</span>
                    @endif
                </a>
            @endforeach

            <div class="border-t border-brand-border/30 my-2"></div>

            @foreach ($navSecondary as $item)
                <a
                    href="{{ $item['href'] ?? '#' }}"
                    @if(! $item['enabled']) aria-disabled="true" tabindex="-1" @else wire:navigate @click="moreOpen = false" @endif
                    @class([
                        'flex items-center gap-3 px-3 py-3 rounded-brand-sm text-sm font-medium',
                        'text-brand-text hover:bg-brand-soft/30' => $item['enabled'],
                        'text-brand-text-muted/50 pointer-events-none' => ! $item['enabled'],
                    ])
                >
                    <x-icon :name="$item['icon']" class="w-5 h-5 shrink-0" />
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if (! $item['enabled'])
                        <span class="text-[10px] uppercase tracking-wide bg-brand-soft/60 text-brand-text-muted px-1.5 py-0.5 rounded">Em breve</span>
                    @endif
                </a>
            @endforeach

            <button wire:click="logout" class="w-full flex items-center gap-3 px-3 py-3 rounded-brand-sm text-sm font-medium text-brand-danger hover:bg-brand-danger-soft">
                <x-icon name="log-out" class="w-5 h-5 shrink-0" />
                Sair
            </button>
        </div>
    </x-ui.drawer>
</div>
