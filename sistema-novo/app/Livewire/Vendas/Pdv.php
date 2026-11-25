<?php

namespace App\Livewire\Vendas;

use App\Enums\FormaPagamento;
use App\Models\CaixaFeira;
use App\Models\Produto;
use App\Models\Venda;
use App\Services\Vendas\VendaService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts.app')]
class Pdv extends Component
{
    public string $busca = '';

    public string $forma = '';

    public string $recebido = '';

    #[Locked]
    public array $carrinho = [];

    #[Locked]
    public string $token;

    #[Locked]
    public ?int $vendaRegistrada = null;

    #[Locked]
    public ?int $caixaFeiraId = null;

    public function mount(): void
    {
        $this->token = (string) Str::uuid();
        $this->caixaFeiraId = CaixaFeira::query()->where('aberto', true)->value('id');
    }

    public function adicionar(int $produtoId): void
    {
        $this->resetErrorBag('carrinho');
        $produto = Produto::query()->where('ativo', true)
            ->with('bom.material:id,estoque,essencial')->find($produtoId);

        if (! $produto || $produto->bom->isEmpty()) {
            $this->addError('carrinho', 'Este produto está indisponível para venda.');

            return;
        }

        $quantidade = ($this->carrinho[$produtoId]['quantidade'] ?? 0) + 1;
        $disponivel = $produto->quantidadeDisponivel();

        if ($disponivel !== null && $quantidade > $disponivel) {
            $this->addError('carrinho', "Estoque insuficiente para {$produto->nome}.");

            return;
        }

        $this->vendaRegistrada = null;
        $this->carrinho[$produtoId] = [
            'produto_id' => $produto->id,
            'nome' => $produto->nome,
            'quantidade' => $quantidade,
            'preco_unitario' => $this->carrinho[$produtoId]['preco_unitario'] ?? $produto->preco_venda,
        ];
    }

    public function diminuir(int $produtoId): void
    {
        $this->resetErrorBag('carrinho');

        if (($this->carrinho[$produtoId]['quantidade'] ?? 0) <= 1) {
            unset($this->carrinho[$produtoId]);

            return;
        }

        $this->carrinho[$produtoId]['quantidade']--;
    }

    public function finalizar(VendaService $service): void
    {
        if ($this->carrinho === [] || $this->caixaFeiraId === null) {
            return;
        }

        $this->resetErrorBag();
        $vendas = Venda::query()->where('pdv_token', $this->token)->where('user_id', auth()->id());
        $venda = $vendas->first();

        if (! $venda) {
            $this->validate([
                'forma' => ['required', Rule::in(array_column($this->formas(), 'value'))],
            ], ['forma.required' => 'Selecione a forma de pagamento.', 'forma.in' => 'Selecione uma forma de pagamento válida.']);

            if ($this->forma === FormaPagamento::Dinheiro->value && trim($this->recebido) !== ''
                && ($this->valorRecebido() ?? -1) < $this->total()) {
                $this->addError('recebido', 'Informe um valor recebido válido e igual ou superior ao total.');

                return;
            }

            if (Produto::query()->where('ativo', true)->whereKey(array_keys($this->carrinho))->count() !== count($this->carrinho)) {
                $this->addError('carrinho', 'Retire do carrinho os produtos que ficaram indisponíveis.');

                return;
            }

            try {
                $venda = $service->create([
                    'itens' => array_values($this->carrinho),
                    'forma_pagamento' => $this->forma,
                    'pago' => true,
                    'canal' => 'feira',
                    'caixa_feira_id' => $this->caixaFeiraId,
                    'pdv_token' => $this->token,
                ]);
            } catch (QueryException $exception) {
                $venda = $vendas->first();

                if (! $venda) {
                    report($exception);
                    $this->addError('carrinho', 'Falha ao registrar a venda. Tente finalizar novamente.');

                    return;
                }
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $venda = $vendas->first();

                if (! $venda) {
                    $this->addError('carrinho', $exception->getMessage());

                    return;
                }
            }
        }

        $this->vendaRegistrada = $venda->id;
        $this->reset('carrinho', 'busca', 'forma', 'recebido');
        $this->token = (string) Str::uuid();
    }

    public function render(): View
    {
        $termo = trim($this->busca);
        $produtos = Produto::query()->where('ativo', true)
            ->when($termo !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('nome', "%{$termo}%")->orWhereLike('sku', "%{$termo}%")))
            ->with('bom.material:id,estoque,essencial')
            ->orderBy('nome')
            ->limit(48)
            ->get(['id', 'nome', 'sku', 'preco_venda', 'imagem', 'conta']);
        $total = $this->total();

        return view('livewire.vendas.pdv', [
            'caixaFeira' => CaixaFeira::query()->find($this->caixaFeiraId),
            'produtos' => $produtos,
            'formas' => $this->formas(),
            'total' => $total,
            'troco' => $this->forma === FormaPagamento::Dinheiro->value && $this->valorRecebido() !== null
                ? max(0, round($this->valorRecebido() - $total, 2))
                : null,
        ]);
    }

    private function formas(): array
    {
        return [FormaPagamento::Pix, FormaPagamento::Dinheiro, FormaPagamento::Debito, FormaPagamento::Credito];
    }

    private function total(): float
    {
        return round(array_sum(array_map(
            fn (array $item) => $item['quantidade'] * (float) $item['preco_unitario'],
            $this->carrinho,
        )), 2);
    }

    private function valorRecebido(): ?float
    {
        $valor = trim($this->recebido);

        return preg_match('/^\d{1,10}([,.]\d{1,2})?$/D', $valor)
            ? (float) str_replace(',', '.', $valor)
            : null;
    }
}
