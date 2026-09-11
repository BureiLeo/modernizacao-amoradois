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
class Edit extends Component
{
    use WithFileUploads;

    public Produto $produto;

    public ProdutoForm $form;

    public function mount(Produto $produto): void
    {
        $this->authorize('update', $produto);

        $this->produto = $produto;
        $this->form->setProduto($produto);
    }

    public function removerImagem(ProductImageService $imageService): void
    {
        $this->authorize('update', $this->produto);

        $imageService->delete($this->produto->imagem);
        $this->produto->update(['imagem' => null]);

        session()->flash('success', 'Imagem removida.');
    }

    public function salvar(ProductImageService $imageService): void
    {
        $this->authorize('update', $this->produto);

        $this->form->validate();

        $dados = $this->form->dadosNormalizados();

        if ($this->form->novaImagem) {
            $dados['imagem'] = $imageService->replace($this->form->novaImagem, $this->produto->imagem);
        }

        $this->produto->update($dados);

        session()->flash('success', 'Produto atualizado com sucesso.');

        $this->redirectRoute('produtos.show', $this->produto, navigate: true);
    }

    public function render(): View
    {
        $categorias = Categoria::where('ativo', true)->orderBy('nome')->get(['id', 'nome']);

        return view('livewire.produtos.edit', compact('categorias'));
    }
}
