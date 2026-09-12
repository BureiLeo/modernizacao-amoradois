<div class="space-y-4">
    <x-ui.page-header title="Perdas" subtitle="Registro de perdas e movimentações." />

    <div class="space-y-3">
        @forelse ($perdas as $perda)
            <div class="rounded-2xl border border-brand-border/70 bg-brand-surface p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="font-medium text-brand-text">{{ $perda->material?->nome ?? 'Material' }}</div>
                    <span class="text-xs font-medium text-brand-primary">{{ $perda->tipo?->label() ?? $perda->tipo }}</span>
                </div>
                <div class="mt-2 text-sm text-brand-text-muted">Quantidade: {{ \App\Support\Format::quantidade($perda->quantidade) }}</div>
                <div class="text-sm text-brand-text-muted">Motivo: {{ $perda->motivo ?? 'Não informado' }}</div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-brand-border/80 bg-brand-surface p-6 text-sm text-brand-text-muted">
                Nenhuma perda registrada.
            </div>
        @endforelse
    </div>
</div>
