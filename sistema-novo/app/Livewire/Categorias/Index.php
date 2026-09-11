<?php

namespace App\Livewire\Categorias;

use App\Livewire\Forms\CategoriaForm;
use App\Models\Categoria;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url]
    public string $filtro = 'todos'; // todos | ativos | inativos

    public bool $showModal = false;

    public CategoriaForm $form;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFiltro(): void
    {
        $this->resetPage();
    }

    public function novaCategoria(): void
    {
        $this->authorize('create', Categoria::class);

        $this->form->reset();
        $this->showModal = true;
    }

    public function editar(int $categoriaId): void
    {
        $categoria = Categoria::findOrFail($categoriaId);
        $this->authorize('update', $categoria);

        $this->form->setCategoria($categoria);
        $this->showModal = true;
    }

    public function salvar(): void
    {
        if ($this->form->categoria) {
            $this->authorize('update', $this->form->categoria);
        } else {
            $this->authorize('create', Categoria::class);
        }

        $editando = (bool) $this->form->categoria;
        $this->form->save();
        $this->showModal = false;
        $this->form->reset();

        session()->flash('success', $editando ? 'Categoria atualizada com sucesso.' : 'Categoria cadastrada com sucesso.');
    }

    public function alternarAtivo(int $categoriaId): void
    {
        $categoria = Categoria::findOrFail($categoriaId);
        $this->authorize('update', $categoria);

        $categoria->update(['ativo' => ! $categoria->ativo]);

        session()->flash('success', $categoria->ativo ? 'Categoria ativada.' : 'Categoria inativada.');
    }

    public function fecharModal(): void
    {
        $this->showModal = false;
        $this->form->reset();
    }

    public function render(): View
    {
        $categorias = Categoria::query()
            ->when($this->search !== '', fn ($q) => $q->where('nome', 'like', '%'.$this->search.'%'))
            ->when($this->filtro === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($this->filtro === 'inativos', fn ($q) => $q->where('ativo', false))
            ->withCount('produtos')
            ->orderBy('nome')
            ->paginate(10);

        return view('livewire.categorias.index', compact('categorias'));
    }
}
