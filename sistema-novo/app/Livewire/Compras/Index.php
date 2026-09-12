<?php

namespace App\Livewire\Compras;

use App\Models\Compra;
use App\Models\Fornecedor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
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

    #[Url(except: 'todas')]
    public string $filtro = 'todas';

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
        // Uma nota vira varias linhas em "compras" (uma por material).
        // O agrupamento por lote_id devolve a nota como um lancamento
        // unico na listagem. Compras antigas/legadas nao tem lote_id e
        // por isso caem no COALESCE, aparecendo individualmente.
        $compras = Compra::query()
            ->select([
                DB::raw('COALESCE(lote_id, id) as grupo'),
                DB::raw('MIN(id) as id'),
                DB::raw('MIN(lote_id) as lote_id'),
                DB::raw('MIN(data) as data'),
                DB::raw('MIN(fornecedor_id) as fornecedor_id'),
                DB::raw('MIN(numero_nota) as numero_nota'),
                DB::raw('COUNT(*) as total_itens'),
                DB::raw('SUM(valor) as total_valor'),
                DB::raw('SUM(COALESCE(frete, 0)) as total_frete'),
                DB::raw('SUM(COALESCE(desconto, 0)) as total_desconto'),
                DB::raw('MIN(pago) as pago'),
            ])
            ->when($this->search !== '', function ($query) {
                $termo = '%'.$this->search.'%';

                $query->where(function ($sub) use ($termo) {
                    $sub->where('numero_nota', 'like', $termo)
                        ->orWhereHas('material', fn ($m) => $m->where('nome', 'like', $termo))
                        ->orWhereHas('fornecedor', fn ($f) => $f->where('nome', 'like', $termo));
                });
            })
            ->when($this->filtro === 'pagas', fn ($query) => $query->where('pago', true))
            ->when($this->filtro === 'pendentes', fn ($query) => $query->where('pago', false))
            ->when($this->filtro === 'mes', fn ($query) => $query->whereBetween('data', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ]))
            ->groupBy('grupo')
            ->orderByDesc(DB::raw('MIN(data)'))
            ->orderByDesc(DB::raw('MIN(id)'))
            ->paginate(15);

        $fornecedores = Fornecedor::query()
            ->whereIn('id', collect($compras->items())->pluck('fornecedor_id')->filter()->unique()->all())
            ->pluck('nome', 'id');

        return view('livewire.compras.index', compact('compras', 'fornecedores'));
    }
}
