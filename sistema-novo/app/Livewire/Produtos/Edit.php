<?php

namespace App\Livewire\Produtos;

use App\Livewire\Forms\ProdutoForm;
use App\Livewire\Produtos\Concerns\GerenciaBom;
use App\Models\Categoria;
use App\Models\Produto;
use App\Services\Produtos\ProductImageService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Edit extends Component
{
    use GerenciaBom, WithFileUploads;

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

        DB::transaction(function () use ($dados): void {
            $this->produto->update($dados);
            $this->salvarBom($this->produto);
        });

        session()->flash('success', 'Produto atualizado com sucesso.');

        $this->redirectRoute('produtos.show', $this->produto, navigate: true);
    }

    public function render(): View
    {
        $categorias = Categoria::where('ativo', true)->orderBy('nome')->get(['id', 'nome']);
        $materiais = $this->materiaisDisponiveis();

        return view('livewire.produtos.edit', [
            'categorias' => $categorias,
            'materiais' => $materiais,
            ...$this->resumoPreco($materiais),
        ]);
    }
}
