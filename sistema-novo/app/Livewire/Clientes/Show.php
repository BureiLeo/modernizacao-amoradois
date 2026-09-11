<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Cliente $cliente;

    public function mount(Cliente $cliente): void
    {
        $this->authorize('view', $cliente);

        $this->cliente = $cliente;
    }

    public function alternarAtivo(): void
    {
        if ($this->cliente->ativo) {
            $this->authorize('delete', $this->cliente);
        } else {
            $this->authorize('restore', $this->cliente);
        }

        $this->cliente->update(['ativo' => ! $this->cliente->ativo]);

        session()->flash('success', $this->cliente->ativo ? 'Cliente reativado.' : 'Cliente inativado.');
    }

    public function render(): View
    {
        // Agregados via query, sem carregar o historico inteiro em
        // memoria (Etapa 6 #62).
        $stats = $this->cliente->vendas()
            ->selectRaw('COUNT(*) as total_vendas, IFNULL(SUM(total),0) as total_comprado, MAX(data) as ultima_compra')
            ->first();

        $ticketMedio = $stats->total_vendas > 0
            ? round($stats->total_comprado / $stats->total_vendas, 2)
            : 0.0;

        // Ultimas 10 vendas (Etapa 6 #19), sem N+1.
        $vendasRecentes = $this->cliente->vendas()
            ->withCount('itens')
            ->orderByDesc('data')
            ->limit(10)
            ->get();

        return view('livewire.clientes.show', [
            'totalVendas' => (int) $stats->total_vendas,
            'totalComprado' => (float) $stats->total_comprado,
            'ticketMedio' => $ticketMedio,
            'ultimaCompra' => $stats->ultima_compra,
            'vendasRecentes' => $vendasRecentes,
        ]);
    }
}
