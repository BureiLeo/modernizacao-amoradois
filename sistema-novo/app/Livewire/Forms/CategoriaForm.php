<?php

namespace App\Livewire\Forms;

use App\Models\Categoria;
use Livewire\Form;

class CategoriaForm extends Form
{
    public ?Categoria $categoria = null;

    public string $nome = '';

    public ?string $descricao = null;

    public bool $ativo = true;

    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:500'],
            'ativo' => ['boolean'],
        ];
    }

    public function setCategoria(Categoria $categoria): void
    {
        $this->categoria = $categoria;
        $this->nome = $categoria->nome;
        $this->descricao = $categoria->descricao;
        $this->ativo = $categoria->ativo;
    }

    public function reset(...$properties): void
    {
        parent::reset(...$properties);
        $this->categoria = null;
        $this->ativo = true;
    }

    public function save(): Categoria
    {
        $this->validate();

        if ($this->categoria) {
            $this->categoria->update([
                'nome' => $this->nome,
                'descricao' => $this->descricao,
                'ativo' => $this->ativo,
            ]);

            return $this->categoria;
        }

        return Categoria::create([
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'ativo' => $this->ativo,
        ]);
    }
}
