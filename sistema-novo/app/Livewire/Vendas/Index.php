<?php

namespace App\Livewire\Vendas;

use App\Models\Venda;
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
    public string $filtro = 'todas';

    #[Url]
    public string $ordenar = 'recentes';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFiltro(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $vendas = Venda::query()
            ->with(['cliente:id,nome,telefone', 'pagamentos:id,venda_id,status'])
            ->when($this->search !== '', function ($query) {
                $termo = trim($this->search);

                $query->where(function ($sub) use ($termo) {
                    $sub->where('id', 'like', "%{$termo}%")
                        ->orWhereHas('cliente', function ($clienteQuery) use ($termo) {
                            $clienteQuery->where('nome', 'like', "%{$termo}%")
                                ->orWhere('telefone', 'like', "%{$termo}%");
                        });
                });
            })
            ->when($this->filtro === 'pagas', fn ($query) => $query->where('pago', true))
            ->when($this->filtro === 'pendentes', fn ($query) => $query->where('pago', false))
            ->when($this->ordenar === 'recentes', fn ($query) => $query->orderByDesc('data'))
            ->when($this->ordenar === 'maior', fn ($query) => $query->orderByDesc('total'))
            ->when($this->ordenar === 'menor', fn ($query) => $query->orderBy('total'))
            ->paginate(12);

        return view('livewire.vendas.index', compact('vendas'));
    }
}
