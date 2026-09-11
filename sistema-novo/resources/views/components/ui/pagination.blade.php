@props(['paginator'])

@if ($paginator->hasPages())
    <nav {{ $attributes->merge(['class' => 'flex items-center justify-between gap-3 pt-3']) }} aria-label="Paginação">
        {{-- Mobile: so Anterior/Proxima + posicao --}}
        <div class="flex-1 flex items-center justify-between md:hidden">
            @if ($paginator->previousPageUrl())
                <x-ui.button variant="outline" size="sm" :href="$paginator->previousPageUrl()">Anterior</x-ui.button>
            @else
                <x-ui.button variant="outline" size="sm" class="opacity-40 pointer-events-none">Anterior</x-ui.button>
            @endif
            <span class="text-xs text-brand-text-muted">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
            @if ($paginator->nextPageUrl())
                <x-ui.button variant="outline" size="sm" :href="$paginator->nextPageUrl()">Próxima</x-ui.button>
            @else
                <x-ui.button variant="outline" size="sm" class="opacity-40 pointer-events-none">Próxima</x-ui.button>
            @endif
        </div>

        {{-- Desktop: numeros de pagina --}}
        <div class="hidden md:flex items-center gap-1">
            @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                <a
                    href="{{ $url }}"
                    @class([
                        'w-9 h-9 flex items-center justify-center rounded-brand-sm text-sm font-medium',
                        'bg-brand-primary text-brand-branco' => $page === $paginator->currentPage(),
                        'text-brand-text hover:bg-brand-soft/40' => $page !== $paginator->currentPage(),
                    ])
                >{{ $page }}</a>
            @endforeach
        </div>

        <p class="hidden md:block text-sm text-brand-text-muted">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </p>
    </nav>
@endif
