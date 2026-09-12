<?php

namespace App\Livewire\Vendas;

use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Venda $venda;

    public function mount(Venda $venda): void
    {
        $this->venda = $venda;
    }

    public function excluir(): void
    {
        if ($this->venda->pagamentos()->exists()) {
            session()->flash('error', 'Não é possível excluir uma venda com pagamento registrado.');

            return;
        }

        VendaItem::query()->where('venda_id', $this->venda->id)->delete();
        $this->venda->delete();

        session()->flash('success', 'Venda removida com sucesso.');

        $this->redirectRoute('vendas.index', navigate: true);
    }

    public function render(): View
    {
        $this->venda->load(['cliente', 'itens.produto', 'pagamentos']);

        return view('livewire.vendas.show', ['venda' => $this->venda]);
    }
}
