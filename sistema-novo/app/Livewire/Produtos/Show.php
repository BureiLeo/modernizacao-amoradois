<?php

namespace App\Livewire\Produtos;

use App\Models\Produto;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Produto $produto;

    public function mount(Produto $produto): void
    {
        $this->authorize('view', $produto);

        $this->produto = $produto;
    }

    public function alternarAtivo(): void
    {
        if ($this->produto->ativo) {
            $this->authorize('delete', $this->produto);
        } else {
            $this->authorize('restore', $this->produto);
        }

        $this->produto->update(['ativo' => ! $this->produto->ativo]);

        session()->flash('success', $this->produto->ativo ? 'Produto reativado.' : 'Produto inativado.');
    }

    public function render(): View
    {
        // BOM em somente leitura (Etapa 6/7 #38) - a edicao completa
        // fica para a etapa de Materiais/Estoque.
        $bom = $this->produto->bom()->with('material:id,nome,unidade_base')->get();

        // Historico de vendas: agregados via query, sem N+1 (Etapa 6/7 #39/#62).
        $stats = $this->produto->vendaItens()
            ->selectRaw('IFNULL(SUM(quantidade),0) as total_vendido, IFNULL(SUM(subtotal),0) as faturamento_total')
            ->first();

        $vendasRecentes = $this->produto->vendaItens()
            ->with('venda:id,data,cliente_id')
            ->with('venda.cliente:id,nome')
            ->latest('id')
            ->limit(5)
            ->get();

        return view('livewire.produtos.show', [
            'bom' => $bom,
            'totalVendido' => (int) $stats->total_vendido,
            'faturamentoTotal' => (float) $stats->faturamento_total,
            'vendasRecentes' => $vendasRecentes,
        ]);
    }
}
