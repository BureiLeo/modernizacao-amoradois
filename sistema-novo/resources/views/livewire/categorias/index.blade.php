<div>
    <x-ui.page-header title="Categorias" subtitle="Organize seus produtos em categorias.">
        <x-slot name="actions">
            @can('create', \App\Models\Categoria::class)
                <x-ui.button wire:click="novaCategoria">
                    <x-icon name="plus" class="w-4 h-4" />
                    Nova categoria
                </x-ui.button>
            @endcan
        </x-slot>
    </x-ui.page-header>

    @if (session('success'))
        <x-ui.alert variant="success" class="mb-4">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="flex-1">
            <x-ui.search-input wire:model.live.debounce.400ms="search" placeholder="Buscar por nome..." />
        </div>

        <div class="flex gap-2 overflow-x-auto">
            @foreach (['todos' => 'Todas', 'ativos' => 'Ativas', 'inativos' => 'Inativas'] as $value => $label)
                <button
                    type="button"
                    wire:click="$set('filtro', '{{ $value }}')"
                    @class([
                        'px-3 py-2 rounded-brand-sm text-sm font-medium whitespace-nowrap transition-colors',
                        'bg-brand-primary text-brand-branco' => $filtro === $value,
                        'bg-brand-surface border border-brand-border/50 text-brand-text hover:bg-brand-soft/30' => $filtro !== $value,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @if ($categorias->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="inbox"
                title="Nenhuma categoria encontrada"
                description="{{ $search !== '' ? 'Tente ajustar sua busca.' : 'Cadastre a primeira categoria para organizar seus produtos.' }}"
            >
                @can('create', \App\Models\Categoria::class)
                    <x-slot name="actions">
                        <x-ui.button wire:click="novaCategoria" variant="outline">Nova categoria</x-ui.button>
                    </x-slot>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        {{-- Mobile: cards --}}
        <div class="space-y-2 md:hidden">
            @foreach ($categorias as $categoria)
                <x-ui.mobile-list-card
                    :title="$categoria->nome"
                    :subtitle="$categoria->produtos_count.' produto(s)'"
                >
                    <x-slot name="trailing">
                        <x-ui.status-badge :status="$categoria->ativo ? 'ativo' : 'inativo'" />
                        <div class="flex gap-1 mt-1">
                            @can('update', $categoria)
                                <x-ui.icon-button icon="settings" label="Editar categoria" size="sm" wire:click="editar({{ $categoria->id }})" />
                            @endcan
                        </div>
                    </x-slot>
                </x-ui.mobile-list-card>
            @endforeach
        </div>

        {{-- Desktop: tabela --}}
        <x-ui.table>
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-brand-text-muted">
                    <th class="px-4 py-3">Nome</th>
                    <th class="px-4 py-3">Produtos</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-border/20">
                @foreach ($categorias as $categoria)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium text-brand-text">{{ $categoria->nome }}</p>
                            @if ($categoria->descricao)
                                <p class="text-xs text-brand-text-muted">{{ Str::limit($categoria->descricao, 60) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-brand-text-muted">{{ $categoria->produtos_count }}</td>
                        <td class="px-4 py-3"><x-ui.status-badge :status="$categoria->ativo ? 'ativo' : 'inativo'" /></td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                @can('update', $categoria)
                                    <x-ui.button variant="ghost" size="sm" wire:click="editar({{ $categoria->id }})">Editar</x-ui.button>
                                    <x-ui.button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="alternarAtivo({{ $categoria->id }})"
                                        wire:confirm="{{ $categoria->ativo ? 'Inativar esta categoria? Produtos ja cadastrados nao serao afetados.' : 'Reativar esta categoria?' }}"
                                    >
                                        {{ $categoria->ativo ? 'Inativar' : 'Reativar' }}
                                    </x-ui.button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>

        <div class="mt-4">
            <x-ui.pagination :paginator="$categorias" />
        </div>
    @endif

    {{-- Modal de criar/editar --}}
    <x-ui.modal title="Categoria" max-width="md">
        <form wire:submit="salvar" class="p-6 space-y-4">
            <h2 class="text-lg font-semibold text-brand-text">
                {{ $form->categoria ? 'Editar categoria' : 'Nova categoria' }}
            </h2>

            <x-ui.input name="nome" label="Nome *" wire:model="form.nome" autofocus />

            <x-ui.textarea name="descricao" label="Descrição" wire:model="form.descricao" rows="3" />

            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="form.ativo" class="rounded border-brand-border/70 text-brand-primary focus:ring-brand-primary">
                <span class="text-sm text-brand-text">Categoria ativa</span>
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" variant="secondary" wire:click="fecharModal">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="primary">Salvar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
