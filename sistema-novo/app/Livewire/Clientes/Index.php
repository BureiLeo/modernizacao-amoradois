<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
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
    public string $ordenar = 'nome'; // nome | codigo | recentes

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFiltro(): void
    {
        $this->resetPage();
    }

    public function alternarAtivo(int $clienteId): void
    {
        $cliente = Cliente::findOrFail($clienteId);

        if ($cliente->ativo) {
            $this->authorize('delete', $cliente);
        } else {
            $this->authorize('restore', $cliente);
        }

        $cliente->update(['ativo' => ! $cliente->ativo]);

        session()->flash('success', $cliente->ativo ? 'Cliente reativado.' : 'Cliente inativado.');
    }

    public function render(): View
    {
        // Busca tolerante a formatacao de telefone (Etapa 6 #6): remove
        // parenteses/traco/espaco tanto do termo buscado quanto da
        // coluna telefone antes de comparar. REPLACE() e suportado de
        // forma identica por MySQL/MariaDB e SQLite (usado nos testes),
        // sem precisar de coluna normalizada nem migration nova.
        $termoBuscado = trim($this->search);
        $termoDigits = preg_replace('/\D/', '', $termoBuscado);

        $clientes = Cliente::query()
            ->when($termoBuscado !== '', function ($query) use ($termoBuscado, $termoDigits) {
                $query->where(function ($q) use ($termoBuscado, $termoDigits) {
                    $q->where('nome', 'like', "%{$termoBuscado}%")
                        ->orWhere('email', 'like', "%{$termoBuscado}%")
                        ->orWhere('codigo', 'like', "%{$termoBuscado}%");

                    if ($termoDigits !== '') {
                        $q->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') like ?",
                            ["%{$termoDigits}%"]
                        )
                            ->orWhereRaw(
                                "REPLACE(REPLACE(cpf, '.', ''), '-', '') like ?",
                                ["%{$termoDigits}%"]
                            )
                            ->orWhereRaw(
                                "REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') like ?",
                                ["%{$termoDigits}%"]
                            );
                    }
                });
            })
            ->when($this->filtro === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($this->filtro === 'inativos', fn ($q) => $q->where('ativo', false))
            ->withCount('vendas')
            ->withSum('vendas', 'total')
            ->when($this->ordenar === 'nome', fn ($q) => $q->orderBy('nome'))
            ->when($this->ordenar === 'codigo', fn ($q) => $q->orderBy('codigo'))
            ->when($this->ordenar === 'recentes', fn ($q) => $q->orderByDesc('created_at'))
            ->paginate(12);

        return view('livewire.clientes.index', compact('clientes'));
    }
}
