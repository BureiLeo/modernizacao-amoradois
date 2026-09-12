<?php

namespace App\Livewire\Vendas;

use App\Models\Venda;
use App\Services\Vendas\VendaService;
use Livewire\Attributes\Layout;
use Throwable;

/**
 * Reaproveita o carrinho da tela de nova venda para permitir editar
 * uma venda com varios itens (inclusive os avulsos de "Outros").
 */
#[Layout('layouts.app')]
class Edit extends Create
{
    public Venda $venda;

    public function mount(Venda $venda): void
    {
        $this->venda = $venda;

        $this->cliente_id = $venda->cliente_id;
        $this->frete = (float) $venda->frete > 0 ? (string) (float) $venda->frete : '';
        $this->desconto = (float) $venda->desconto > 0 ? (string) (float) $venda->desconto : '';
        $this->forma_pagamento = $venda->forma_pagamento ?: 'pix';
        $this->pago = (bool) $venda->pago;
        $this->observacoes = (string) ($venda->observacoes ?? '');

        foreach ($venda->itens()->with('produto')->get() as $item) {
            $chave = $item->produto_id ? 'p:'.$item->produto_id : 'o:'.$item->id;

            $this->carrinho[$chave] = [
                'produto_id' => $item->produto_id,
                'descricao' => $item->descricao,
                'nome' => $item->nomeExibicao(),
                'imagem' => $item->produto?->imagem,
                'quantidade' => (int) $item->quantidade,
                'preco_unitario' => (float) $item->preco_unitario,
            ];
        }
    }

    public function salvar(VendaService $vendaService): void
    {
        if ($this->carrinho === []) {
            $this->erro = 'A venda precisa de pelo menos um item.';
            $this->confirmando = false;

            return;
        }

        $this->erro = null;

        $this->validate([
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'forma_pagamento' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $itens = [];

        foreach ($this->carrinho as $linha) {
            $itens[] = [
                'produto_id' => $linha['produto_id'],
                'descricao' => $linha['descricao'],
                'quantidade' => max((int) $linha['quantidade'], 1),
                'preco_unitario' => max((float) $linha['preco_unitario'], 0),
            ];
        }

        try {
            $vendaService->update($this->venda, [
                'cliente_id' => $this->cliente_id,
                'user_id' => auth()->id(),
                'itens' => $itens,
                'frete' => $this->freteValor(),
                'desconto' => $this->descontoValor(),
                'forma_pagamento' => $this->pago ? $this->forma_pagamento : null,
                'pago' => $this->pago,
                'observacoes' => $this->observacoes !== '' ? $this->observacoes : null,
            ]);
        } catch (Throwable $e) {
            $this->confirmando = false;
            $this->erro = $e->getMessage();

            return;
        }

        session()->flash('success', 'Venda atualizada com sucesso.');

        $this->redirectRoute('vendas.show', $this->venda, navigate: true);
    }

    protected function viewName(): string
    {
        return 'livewire.vendas.edit';
    }
}
