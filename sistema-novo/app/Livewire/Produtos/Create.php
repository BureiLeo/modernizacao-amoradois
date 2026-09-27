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
class Create extends Component
{
    use GerenciaBom, WithFileUploads;

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

        $produto = DB::transaction(function () use ($dados): Produto {
            $produto = Produto::create($dados);
            $this->salvarBom($produto);

            return $produto;
        });

        session()->flash('success', 'Produto cadastrado com sucesso.');

        $this->redirectRoute('produtos.show', $produto, navigate: true);
    }

    public function render(): View
    {
        $categorias = Categoria::where('ativo', true)->orderBy('nome')->get(['id', 'nome']);
        $materiais = $this->materiaisDisponiveis();

        return view('livewire.produtos.create', [
            'categorias' => $categorias,
            'materiais' => $materiais,
            ...$this->resumoPreco($materiais),
        ]);
    }
}
