<?php

namespace App\Livewire\Materiais;

use App\Models\Material;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Material $material;

    public string $nome = '';

    public string $unidade_base = 'un';

    public int $fator_embalagem = 1;

    public float $estoque_minimo = 0;

    public float $custo_medio = 0;

    public string $observacoes = '';

    public bool $essencial = true;

    public function mount(Material $material): void
    {
        $this->material = $material;
        $this->nome = $material->nome;
        $this->unidade_base = $material->unidade_base;
        $this->fator_embalagem = (int) $material->fator_embalagem;
        $this->estoque_minimo = (float) ($material->estoque_minimo ?? 0);
        $this->custo_medio = (float) ($material->custo_medio ?? 0);
        $this->observacoes = (string) ($material->observacoes ?? '');
        $this->essencial = (bool) $material->essencial;
    }

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

        $this->material->update([
            'nome' => trim($this->nome),
            'unidade_base' => trim($this->unidade_base),
            'fator_embalagem' => $this->fator_embalagem,
            'estoque_minimo' => $this->estoque_minimo,
            'custo_medio' => $this->custo_medio,
            'observacoes' => $this->observacoes !== '' ? $this->observacoes : null,
            'essencial' => $this->essencial,
        ]);

        session()->flash('success', 'Material atualizado com sucesso.');

        $this->redirectRoute('materiais.show', $this->material, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.materiais.edit');
    }
}
