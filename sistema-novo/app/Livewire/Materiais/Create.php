<?php

namespace App\Livewire\Materiais;

use App\Models\Material;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $nome = '';

    public string $unidade_base = 'un';

    public int $fator_embalagem = 1;

    public float $estoque_minimo = 0;

    public float $custo_medio = 0;

    public string $observacoes = '';

    public function salvar(): void
    {
        $this->validate([
            'nome' => ['required', 'string', 'max:255'],
            'unidade_base' => ['required', 'string', 'max:16'],
            'fator_embalagem' => ['required', 'integer', 'min:1'],
            'estoque_minimo' => ['nullable', 'numeric'],
            'custo_medio' => ['nullable', 'numeric'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $material = Material::create([
            'nome' => trim($this->nome),
            'unidade_base' => trim($this->unidade_base),
            'fator_embalagem' => $this->fator_embalagem,
            'estoque' => 0,
            'estoque_minimo' => $this->estoque_minimo,
            'custo_medio' => $this->custo_medio,
            'observacoes' => $this->observacoes !== '' ? $this->observacoes : null,
        ]);

        session()->flash('success', 'Material cadastrado com sucesso.');

        $this->redirectRoute('materiais.show', $material, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.materiais.create');
    }
}
