<?php

namespace App\Livewire\Estoque;

use App\Models\Material;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'todos')]
    public string $filtro = 'todos';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFiltro(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $base = Material::query()
            ->when($this->search !== '', fn ($query) => $query->where('nome', 'like', '%'.$this->search.'%'));

        $materiais = (clone $base)
            ->when($this->filtro === 'sem_estoque', fn ($query) => $query->where('estoque', '<=', 0))
            ->when($this->filtro === 'baixo', fn ($query) => $query
                ->where('estoque', '>', 0)
                ->where('estoque_minimo', '>', 0)
                ->whereColumn('estoque', '<=', 'estoque_minimo'))
            ->orderBy('nome')
            ->paginate(24);

        return view('livewire.estoque.index', [
            'materiais' => $materiais,
            'totalSemEstoque' => (clone $base)->where('estoque', '<=', 0)->count(),
            'totalBaixo' => (clone $base)
                ->where('estoque', '>', 0)
                ->where('estoque_minimo', '>', 0)
                ->whereColumn('estoque', '<=', 'estoque_minimo')
                ->count(),
        ]);
    }
}
