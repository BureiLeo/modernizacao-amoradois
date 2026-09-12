<?php

namespace App\Livewire\Materiais;

use App\Models\Material;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Material $material;

    public function mount(Material $material): void
    {
        $this->material = $material;
    }

    public function excluir(): void
    {
        $this->material->delete();

        session()->flash('success', 'Material removido com sucesso.');

        $this->redirectRoute('estoque.index', navigate: true);
    }

    public function render(): View
    {
        $movimentacoes = $this->material->stockMovements()->latest()->limit(10)->get();

        return view('livewire.materiais.show', compact('movimentacoes'));
    }
}
