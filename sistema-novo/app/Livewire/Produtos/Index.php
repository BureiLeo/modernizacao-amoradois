<?php

namespace App\Livewire\Produtos;

use App\Models\Categoria;
use App\Models\Produto;
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

    #[Url]
    public string $categoriaFiltro = ''; // '' = todas

    #[Url]
    public string $ordenar = 'nome'; // nome | preco | recentes

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFiltro(): void
    {
        $this->resetPage();
    }

    public function updatingCategoriaFiltro(): void
    {
        $this->resetPage();
    }

    public function alternarAtivo(int $produtoId): void
    {
        $produto = Produto::findOrFail($produtoId);

        if ($produto->ativo) {
            $this->authorize('delete', $produto);
        } else {
            $this->authorize('restore', $produto);
        }

        $produto->update(['ativo' => ! $produto->ativo]);

        session()->flash('success', $produto->ativo ? 'Produto reativado.' : 'Produto inativado.');
    }

    public function render(): View
    {
        $produtos = Produto::query()
            ->with('categoria:id,nome')
            ->when($this->search !== '', function ($q) {
                $termo = trim($this->search);
                $q->where(function ($sub) use ($termo) {
                    $sub->where('nome', 'like', "%{$termo}%")
                        ->orWhere('sku', 'like', "%{$termo}%");
                });
            })
            ->when($this->filtro === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($this->filtro === 'inativos', fn ($q) => $q->where('ativo', false))
            ->when($this->categoriaFiltro !== '', fn ($q) => $q->where('categoria_id', $this->categoriaFiltro))
            ->when($this->ordenar === 'nome', fn ($q) => $q->orderBy('nome'))
            ->when($this->ordenar === 'preco', fn ($q) => $q->orderBy('preco_venda'))
            ->when($this->ordenar === 'recentes', fn ($q) => $q->orderByDesc('created_at'))
            ->paginate(12);

        $categorias = Categoria::where('ativo', true)->orderBy('nome')->get(['id', 'nome']);

        return view('livewire.produtos.index', compact('produtos', 'categorias'));
    }
}
