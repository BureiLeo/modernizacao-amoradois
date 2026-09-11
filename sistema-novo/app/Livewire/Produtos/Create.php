<?php

namespace App\Livewire\Produtos;

use App\Livewire\Forms\ProdutoForm;
use App\Models\Categoria;
use App\Models\Produto;
use App\Services\Produtos\ProductImageService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Create extends Component
{
    use WithFileUploads;

    public ProdutoForm $form;

    public function mount(): void
    {
        $this->authorize('create', Produto::class);
    }

    public function salvar(ProductImageService $imageService): void
    {
        $this->authorize('create', Produto::class);

        $this->form->validate();

        $dados = $this->form->dadosNormalizados();

        if ($this->form->novaImagem) {
            $dados['imagem'] = $imageService->store($this->form->novaImagem);
        }

        $produto = Produto::create($dados);

        session()->flash('success', 'Produto cadastrado com sucesso.');

        $this->redirectRoute('produtos.show', $produto, navigate: true);
    }

    public function render(): View
    {
        $categorias = Categoria::where('ativo', true)->orderBy('nome')->get(['id', 'nome']);

        return view('livewire.produtos.create', compact('categorias'));
    }
}
