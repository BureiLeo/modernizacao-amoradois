<?php

namespace App\Livewire\Forms;

use App\Models\Produto;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ProdutoForm extends Form
{
    public ?Produto $produto = null;

    public ?int $categoria_id = null;

    public string $nome = '';

    public ?string $sku = null;

    public string $preco_venda = '';

    public ?string $custo_referencia = null;

    public ?string $estoque_minimo = null;

    /** @var UploadedFile|null Nova imagem selecionada nesta sessao de edicao. */
    public $novaImagem = null;

    public bool $ativo = true;

    protected function rules(): array
    {
        return [
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'nome' => ['required', 'string', 'max:120'],
            'sku' => [
                'nullable', 'string', 'max:60',
                Rule::unique('produtos', 'sku')->ignore($this->produto?->id),
            ],
            // Aceita tanto "18.90" quanto "18,90" (formato brasileiro)
            // digitado pelo usuario - a rule "numeric" padrao do Laravel
            // rejeita virgula, o que causava falha de validacao
            // silenciosa (bug real encontrado e corrigido nesta etapa).
            'preco_venda' => ['required', 'regex:/^\d+([.,]\d{1,2})?$/'],
            'custo_referencia' => ['nullable', 'regex:/^\d+([.,]\d{1,2})?$/'],
            'estoque_minimo' => ['nullable', 'regex:/^\d+([.,]\d{1,3})?$/'],
            'novaImagem' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'ativo' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'preco_venda.required' => 'Informe o preço de venda.',
            'preco_venda.regex' => 'Informe um preço válido (ex: 29,90).',
            'custo_referencia.regex' => 'Informe um valor válido (ex: 12,50).',
            'estoque_minimo.regex' => 'Informe uma quantidade válida (ex: 5).',
            'sku.unique' => 'Este SKU já está em uso por outro produto.',
        ];
    }

    public function setProduto(Produto $produto): void
    {
        $this->produto = $produto;
        $this->categoria_id = $produto->categoria_id;
        $this->nome = $produto->nome;
        $this->sku = $produto->sku;
        $this->preco_venda = (string) $produto->preco_venda;
        $this->custo_referencia = $produto->custo_referencia !== null ? (string) $produto->custo_referencia : null;
        $this->estoque_minimo = $produto->estoque_minimo !== null ? (string) $produto->estoque_minimo : null;
        $this->ativo = $produto->ativo;
    }

    /**
     * @return array<string, mixed>
     */
    public function dadosNormalizados(): array
    {
        return [
            'categoria_id' => $this->categoria_id ?: null,
            'nome' => trim($this->nome),
            'sku' => $this->sku ? trim($this->sku) : null,
            'preco_venda' => str_replace(',', '.', (string) $this->preco_venda),
            'custo_referencia' => $this->custo_referencia !== null && $this->custo_referencia !== ''
                ? str_replace(',', '.', (string) $this->custo_referencia)
                : null,
            'estoque_minimo' => $this->estoque_minimo !== null && $this->estoque_minimo !== ''
                ? str_replace(',', '.', (string) $this->estoque_minimo)
                : null,
            'ativo' => $this->ativo,
        ];
    }
}
