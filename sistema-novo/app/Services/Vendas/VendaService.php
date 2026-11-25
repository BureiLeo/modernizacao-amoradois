<?php

namespace App\Services\Vendas;

use App\Enums\ContaCaixa;
use App\Enums\FormaPagamento;
use App\Enums\StatusPagamento;
use App\Enums\VendaStatus;
use App\Models\CaixaFeira;
use App\Models\CaixaMovimento;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class VendaService
{
    public function __construct(protected StockService $stockService) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): Venda
    {
        $itens = $payload['itens'] ?? [];

        if ($itens === []) {
            throw new InvalidArgumentException('A venda precisa de pelo menos um item.');
        }

        return DB::transaction(function () use ($payload, $itens) {
            if (isset($payload['caixa_feira_id'])) {
                $caixa = CaixaFeira::query()->lockForUpdate()->findOrFail($payload['caixa_feira_id']);

                if (! $caixa->aberto) {
                    throw new InvalidArgumentException('Este caixa foi fechado. Abra o PDV do caixa atual para registrar a venda.');
                }
            }

            ['subtotal' => $subtotal, 'linhas' => $linhas] = $this->montarLinhas($itens);

            $desconto = (float) ($payload['desconto'] ?? 0);
            $frete = max(0.0, round((float) ($payload['frete'] ?? 0), 2));
            $subtotal = round($subtotal, 2);

            if ($desconto > $subtotal + $frete) {
                throw new InvalidArgumentException('O desconto não pode deixar o total negativo.');
            }

            $total = max(0.0, round($subtotal + $frete - $desconto, 2));
            $status = $payload['status'] ?? 'pendente';
            $pago = (bool) ($payload['pago'] ?? ($status === 'paga' || $status === 'concluida'));

            $dados = [
                'data' => now(),
                'cliente_id' => $payload['cliente_id'] ?? null,
                'user_id' => $payload['user_id'] ?? auth()->id(),
                'subtotal' => $subtotal,
                'desconto' => $desconto,
                'frete' => $frete,
                'total' => $total,
                'custo_total' => 0,
                'lucro_total' => 0,
                'forma_pagamento' => $payload['forma_pagamento'] ?? null,
                'pago' => $pago,
                'canal' => $payload['canal'] ?? null,
                'caixa_feira_id' => $payload['caixa_feira_id'] ?? null,
                'observacoes' => $payload['observacoes'] ?? null,
                'status' => ($pago ? VendaStatus::Paga : VendaStatus::Pendente)->value,
            ];

            if (isset($payload['pdv_token'])) {
                $dados['pdv_token'] = $payload['pdv_token'];
            }

            $venda = Venda::query()->create($dados);

            foreach ($linhas as $linha) {
                VendaItem::query()->create([
                    'venda_id' => $venda->id,
                    'produto_id' => $linha['produto']?->id,
                    'conta' => $linha['produto']?->conta ?? ContaCaixa::Canecas->value,
                    'produto_variacao_id' => null,
                    'descricao' => $linha['descricao'],
                    'quantidade' => $linha['quantidade'],
                    'preco_unitario' => $linha['preco_unitario'],
                    'custo_unitario_calculado' => 0,
                    'lucro_unitario' => 0,
                    'subtotal' => $linha['subtotal'],
                ]);
            }

            $venda->refresh();
            $this->stockService->consumeForSale($venda, $payload['user_id'] ?? auth()->id());

            if ($pago) {
                $this->markAsPaid($venda, $payload['forma_pagamento'] ?? FormaPagamento::Pix->value, $payload['user_id'] ?? auth()->id());
            } else {
                Pagamento::query()->create([
                    'venda_id' => $venda->id,
                    'forma' => $payload['forma_pagamento'] ?? FormaPagamento::Pix->value,
                    'valor' => $total,
                    'status' => StatusPagamento::Pendente->value,
                    'pago_em' => null,
                    'observacoes' => 'Pagamento pendente',
                ]);
            }

            $venda->refresh();

            return $venda;
        });
    }

    /**
     * Substitui os itens/valores de uma venda existente.
     *
     * Estorna o estoque dos itens antigos antes de consumir o dos novos,
     * para que a edicao nunca deixe o estoque inconsistente.
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(Venda $venda, array $payload): Venda
    {
        return DB::transaction(function () use ($venda, $payload) {
            $venda = Venda::query()->lockForUpdate()->findOrFail($venda->id);
            $conta3d = ContaCaixa::Impressao3d->value;

            if ($venda->caixa_feira_id || ($venda->pago && (
                $venda->itens()->where('conta', $conta3d)->exists()
                || Produto::query()->where('conta', $conta3d)->whereKey(array_filter(array_column($payload['itens'] ?? [], 'produto_id')))->exists()
            ))) {
                throw new InvalidArgumentException('Esta venda já foi registrada no caixa e seus valores estão vinculados às contas.');
            }

            $userId = $payload['user_id'] ?? auth()->id();

            if (array_key_exists('itens', $payload)) {
                $itens = $payload['itens'];

                if ($itens === []) {
                    throw new InvalidArgumentException('A venda precisa de pelo menos um item.');
                }

                // Devolve o estoque atual antes de revalidar a nova composicao.
                $this->stockService->reverseSale($venda, $userId);
                $venda->itens()->delete();
                $venda->refresh();

                ['subtotal' => $subtotal, 'linhas' => $linhas] = $this->montarLinhas($itens);

                foreach ($linhas as $linha) {
                    VendaItem::query()->create([
                        'venda_id' => $venda->id,
                        'produto_id' => $linha['produto']?->id,
                        'conta' => $linha['produto']?->conta ?? ContaCaixa::Canecas->value,
                        'produto_variacao_id' => null,
                        'descricao' => $linha['descricao'],
                        'quantidade' => $linha['quantidade'],
                        'preco_unitario' => $linha['preco_unitario'],
                        'custo_unitario_calculado' => 0,
                        'lucro_unitario' => 0,
                        'subtotal' => $linha['subtotal'],
                    ]);
                }

                $venda->refresh();
                $this->stockService->consumeForSale($venda, $userId);
            } else {
                $subtotal = (float) $venda->subtotal;
            }

            $desconto = (float) ($payload['desconto'] ?? $venda->desconto);
            $frete = max(0.0, round((float) ($payload['frete'] ?? $venda->frete), 2));
            $subtotal = round($subtotal, 2);

            if ($desconto > $subtotal + $frete) {
                throw new InvalidArgumentException('O desconto não pode deixar o total negativo.');
            }

            $total = max(0.0, round($subtotal + $frete - $desconto, 2));
            $pago = array_key_exists('pago', $payload) ? (bool) $payload['pago'] : (bool) $venda->pago;
            $jaEstavaPago = (bool) $venda->pago;
            $formaPagamento = $payload['forma_pagamento'] ?? $venda->forma_pagamento;

            $venda->update([
                'cliente_id' => array_key_exists('cliente_id', $payload) ? $payload['cliente_id'] : $venda->cliente_id,
                'subtotal' => $subtotal,
                'desconto' => $desconto,
                'frete' => $frete,
                'total' => $total,
                'forma_pagamento' => $pago ? ($formaPagamento ?? FormaPagamento::Pix->value) : $formaPagamento,
                'observacoes' => array_key_exists('observacoes', $payload) ? $payload['observacoes'] : $venda->observacoes,
                'pago' => $pago,
                'status' => ($pago ? VendaStatus::Paga : VendaStatus::Pendente)->value,
            ]);

            $venda->refresh();

            // Mantem o valor do pagamento pendente alinhado ao novo total.
            $venda->pagamentos()
                ->where('status', StatusPagamento::Pendente->value)
                ->update(['valor' => $total]);

            // Pendente -> paga: so agora entra no caixa.
            if ($pago && ! $jaEstavaPago) {
                $this->markAsPaid($venda, $formaPagamento, $userId);
                $venda->refresh();
            }

            return $venda;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $itens
     * @return array{subtotal: float, linhas: array<int, array<string, mixed>>}
     */
    protected function montarLinhas(array $itens): array
    {
        $subtotal = 0.0;
        $linhas = [];

        foreach ($itens as $item) {
            $quantidade = max((int) ($item['quantidade'] ?? 1), 1);
            $produtoId = $item['produto_id'] ?? null;

            // Item avulso ("Outros"): custo manual, sem produto e sem baixa de estoque.
            if (! $produtoId) {
                $descricao = trim((string) ($item['descricao'] ?? ''));

                if ($descricao === '') {
                    throw new InvalidArgumentException('Informe a descrição do item avulso.');
                }

                $precoUnitario = round((float) ($item['preco_unitario'] ?? 0), 2);
                $subtotal += $precoUnitario * $quantidade;

                $linhas[] = [
                    'produto' => null,
                    'descricao' => $descricao,
                    'quantidade' => $quantidade,
                    'preco_unitario' => $precoUnitario,
                    'subtotal' => $precoUnitario * $quantidade,
                ];

                continue;
            }

            $produto = Produto::query()->with('bom.material')->findOrFail($produtoId);
            $precoUnitario = (float) ($item['preco_unitario'] ?? $produto->preco_venda ?? 0);

            if (! $produto->hasBom()) {
                throw new RuntimeException("O produto {$produto->nome} não possui materiais configurados.");
            }

            $availability = $this->stockService->checkAvailability($produto, $quantidade);
            if (! $availability['available']) {
                $firstMissing = collect($availability['requirements'])
                    ->first(fn (array $r) => ($r['essencial'] ?? true) && $r['missing'] > 0);
                $material = $firstMissing['material'] ?? null;
                $needed = $firstMissing['quantity'] ?? 0;
                $available = $firstMissing['available'] ?? 0;

                throw new RuntimeException('Não foi possível finalizar. Material: '.($material?->nome ?? 'material')."\nDisponível: {$available}\nNecessário: {$needed}");
            }

            $subtotal += $precoUnitario * $quantidade;
            $linhas[] = [
                'produto' => $produto,
                'descricao' => null,
                'quantidade' => $quantidade,
                'preco_unitario' => $precoUnitario,
                'subtotal' => $precoUnitario * $quantidade,
            ];
        }

        return ['subtotal' => $subtotal, 'linhas' => $linhas];
    }

    public function markAsPaid(Venda $venda, ?string $formaPagamento = null, ?int $userId = null): void
    {
        $formaPagamento ??= FormaPagamento::Pix->value;

        DB::transaction(function () use ($venda, $formaPagamento) {
            $venda = Venda::query()->lockForUpdate()->findOrFail($venda->id);

            if ($venda->pagamentos()->where('status', StatusPagamento::Pago->value)->exists()) {
                return;
            }

            Pagamento::query()->create([
                'venda_id' => $venda->id,
                'forma' => $formaPagamento,
                'valor' => $venda->total,
                'status' => StatusPagamento::Pago->value,
                'pago_em' => now(),
                'observacoes' => 'Pagamento registrado via ação de recebimento',
            ]);

            $venda->update([
                'pago' => true,
                'forma_pagamento' => $formaPagamento,
                'status' => VendaStatus::Paga->value,
            ]);

            $dados = [
                'data' => now(),
                'tipo' => 'venda',
                'ref_tabela' => 'vendas',
                'ref_id' => $venda->id,
                'descricao' => 'Entrada de venda #'.$venda->id,
            ];

            if ($venda->caixa_feira_id) {
                CaixaMovimento::query()->create($dados + ['conta' => ContaCaixa::Feira->value, 'valor' => $venda->total]);

                return;
            }

            $itens3d = $venda->itens()->where('conta', ContaCaixa::Impressao3d->value)->orderBy('id')->get();
            $total = (float) $venda->total;
            $valor3d = 0.0;

            if ($itens3d->isNotEmpty()) {
                $valor3d = (float) $venda->subtotal > 0
                    ? round($total * (float) $itens3d->sum('subtotal') / (float) $venda->subtotal, 2)
                    : ($venda->itens()->where('conta', ContaCaixa::Canecas->value)->exists() ? 0.0 : $total);

                if ($valor3d > 0) {
                    CaixaMovimento::query()->create(array_replace($dados, [
                        'ref_tabela' => 'venda_itens',
                        'ref_id' => $itens3d->first()->id,
                        'conta' => ContaCaixa::Impressao3d->value,
                        'valor' => $valor3d,
                    ]));
                }
            }

            if ($valor3d < $total || $itens3d->isEmpty()) {
                CaixaMovimento::query()->create($dados + [
                    'conta' => ContaCaixa::Canecas->value,
                    'valor' => round($total - $valor3d, 2),
                ]);
            }
        });
    }
}
