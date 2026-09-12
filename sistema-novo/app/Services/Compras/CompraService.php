<?php

namespace App\Services\Compras;

use App\Enums\CaixaTipo;
use App\Models\CaixaMovimento;
use App\Models\Compra;
use App\Models\Material;
use App\Services\Stock\StockService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Lancamento de compra em lote (uma nota = varios materiais).
 *
 * Cada item vira uma linha em "compras" (mesmo formato do legado) mas
 * todas compartilham o mesmo lote_id, o que permite exibir e conferir a
 * nota inteira como um unico lancamento. A entrada de estoque e o
 * recalculo de custo medio ficam no StockService - aqui nao se mexe em
 * $material->estoque diretamente.
 */
class CompraService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Compra>
     */
    public function registerBatch(array $data, ?int $userId = null): Collection
    {
        $itens = array_values($data['itens'] ?? []);

        if ($itens === []) {
            throw new InvalidArgumentException('Adicione pelo menos um item à compra.');
        }

        $frete = round((float) ($data['frete'] ?? 0), 2);
        $desconto = round((float) ($data['desconto'] ?? 0), 2);

        if ($frete < 0 || $desconto < 0) {
            throw new InvalidArgumentException('Frete e desconto não podem ser negativos.');
        }

        $valores = [];

        foreach ($itens as $index => $item) {
            $quantidade = (float) ($item['quantidade'] ?? 0);
            $valorUnitario = (float) ($item['valor_unitario'] ?? 0);

            if ($quantidade <= 0) {
                throw new InvalidArgumentException('A quantidade do item '.($index + 1).' deve ser maior que zero.');
            }

            if ($valorUnitario < 0) {
                throw new InvalidArgumentException('O valor do item '.($index + 1).' não pode ser negativo.');
            }

            $valores[$index] = round($quantidade * $valorUnitario, 2);
        }

        $subtotal = round(array_sum($valores), 2);

        if ($desconto > $subtotal + $frete) {
            throw new InvalidArgumentException('O desconto não pode ser maior que o total da compra.');
        }

        $rateioFrete = $this->rateio($valores, $frete);
        $rateioDesconto = $this->rateio($valores, $desconto);

        return DB::transaction(function () use ($data, $itens, $valores, $rateioFrete, $rateioDesconto, $userId) {
            $loteId = (string) Str::uuid();
            $compras = collect();

            foreach ($itens as $index => $item) {
                $material = $this->resolveMaterial($item);

                $quantidade = round((float) $item['quantidade'], 3);
                $valorLinha = $valores[$index];
                $custoLinha = $valorLinha + $rateioFrete[$index] - $rateioDesconto[$index];
                $custoUnitario = $quantidade > 0 ? round($custoLinha / $quantidade, 6) : 0.0;

                $compra = Compra::query()->create([
                    'lote_id' => $loteId,
                    'data' => $data['data'] ?? now()->toDateString(),
                    'material_id' => $material->getKey(),
                    'fornecedor_id' => $data['fornecedor_id'] ?? null,
                    'numero_nota' => $data['numero_nota'] ?? null,
                    'chave_nfe' => $data['chave_nfe'] ?? null,
                    'quantidade_embalagem' => max((int) ($item['quantidade_embalagem'] ?? 1), 1),
                    'quantidade' => $quantidade,
                    'valor' => $valorLinha,
                    'custo_unitario' => max($custoUnitario, 0),
                    'frete' => $rateioFrete[$index] > 0 ? $rateioFrete[$index] : null,
                    'desconto' => $rateioDesconto[$index] > 0 ? $rateioDesconto[$index] : null,
                    'forma_pagamento' => $data['forma_pagamento'] ?? null,
                    'pago' => (bool) ($data['pago'] ?? false),
                    'observacoes' => $data['observacoes'] ?? null,
                ]);

                $this->stock->registerPurchase(
                    $material,
                    $quantidade,
                    max($custoUnitario, 0),
                    $compra->id,
                    $userId,
                    'Compra'.($data['numero_nota'] ?? null ? ' NF '.$data['numero_nota'] : '').' #'.$compra->id,
                );

                $compras->push($compra);
            }

            if ((bool) ($data['pago'] ?? false)) {
                $this->lancarNoCaixa($compras);
            }

            return $compras;
        });
    }

    /**
     * Compra lancada como pendente que depois foi paga: marca as linhas
     * do lote e so entao lanca a saida no caixa. O estoque nao e tocado
     * aqui - ele ja entrou na hora do lancamento.
     */
    public function markAsPaid(string $loteId, ?string $formaPagamento = null): int
    {
        return DB::transaction(function () use ($loteId, $formaPagamento) {
            $compras = Compra::query()->where('lote_id', $loteId)->lockForUpdate()->get();

            if ($compras->isEmpty()) {
                throw new InvalidArgumentException('Compra não encontrada.');
            }

            if ((bool) $compras->first()->pago) {
                return 0;
            }

            foreach ($compras as $compra) {
                $compra->update([
                    'pago' => true,
                    'forma_pagamento' => $formaPagamento ?: $compra->forma_pagamento,
                ]);
            }

            $this->lancarNoCaixa($compras);

            return $compras->count();
        });
    }

    /**
     * Estorna um lote inteiro: devolve o estoque e apaga as linhas da
     * compra. Usado quando a nota foi lancada errada.
     */
    public function reverseBatch(string $loteId, ?int $userId = null): int
    {
        return DB::transaction(function () use ($loteId, $userId) {
            $compras = Compra::query()->where('lote_id', $loteId)->get();

            if ($compras->isEmpty()) {
                throw new InvalidArgumentException('Compra não encontrada.');
            }

            foreach ($compras as $compra) {
                $material = Material::query()->find($compra->material_id);

                if ($material) {
                    $this->stock->adjust(
                        $material,
                        -(float) $compra->quantidade,
                        'estorno_compra',
                        'Estorno da compra #'.$compra->id,
                        $userId,
                    );
                }

                $compra->delete();
            }

            // Se a compra tinha saido do caixa, a devolucao entra como um
            // lancamento novo - o historico do caixa nunca e apagado.
            $caixa = CaixaMovimento::query()
                ->where('tipo', CaixaTipo::Compra->value)
                ->where('ref_tabela', 'compras')
                ->where('ref_id', $compras->first()->id)
                ->first();

            if ($caixa) {
                CaixaMovimento::query()->firstOrCreate(
                    [
                        'tipo' => CaixaTipo::Ajuste->value,
                        'ref_tabela' => 'compras',
                        'ref_id' => $compras->first()->id,
                    ],
                    [
                        'data' => now(),
                        'descricao' => 'Estorno da compra'.($compras->first()->numero_nota ? ' NF '.$compras->first()->numero_nota : ' #'.$compras->first()->id),
                        'valor' => abs((float) $caixa->valor),
                    ]
                );
            }

            return $compras->count();
        });
    }

    /**
     * Uma nota = varias linhas em "compras", mas um unico lancamento de
     * saida no caixa, ancorado no id da primeira linha do lote. O indice
     * unico (tipo, ref_tabela, ref_id) garante que pagar duas vezes nao
     * tira o dinheiro do caixa duas vezes.
     *
     * @param  Collection<int, Compra>|EloquentCollection<int, Compra>  $compras
     */
    protected function lancarNoCaixa(Collection|EloquentCollection $compras): void
    {
        $primeira = $compras->first();

        $total = round(
            $compras->sum(fn (Compra $c) => (float) $c->valor)
            + $compras->sum(fn (Compra $c) => (float) $c->frete)
            - $compras->sum(fn (Compra $c) => (float) $c->desconto),
            2
        );

        if ($total <= 0) {
            return;
        }

        CaixaMovimento::query()->firstOrCreate(
            [
                'tipo' => CaixaTipo::Compra->value,
                'ref_tabela' => 'compras',
                'ref_id' => $primeira->id,
            ],
            [
                'data' => now(),
                'descricao' => 'Compra'.($primeira->numero_nota ? ' NF '.$primeira->numero_nota : ' #'.$primeira->id),
                // Valor com sinal: saida de caixa e negativa.
                'valor' => -$total,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveMaterial(array $item): Material
    {
        $materialId = $item['material_id'] ?? null;

        if (! empty($materialId)) {
            return Material::query()->findOrFail((int) $materialId);
        }

        $nome = trim((string) ($item['nome'] ?? ''));

        if ($nome === '') {
            throw new InvalidArgumentException('Informe o material ou o nome do novo material.');
        }

        return Material::query()->create([
            'nome' => $nome,
            'unidade_base' => trim((string) ($item['unidade_base'] ?? 'un')) ?: 'un',
            'fator_embalagem' => max((int) ($item['fator_embalagem'] ?? 1), 1),
            'estoque' => 0,
            'estoque_minimo' => (float) ($item['estoque_minimo'] ?? 0),
            'custo_medio' => 0,
        ]);
    }

    /**
     * Rateio proporcional ao valor de cada linha, com a sobra de
     * centavos indo para o ultimo item - assim a soma do rateio bate
     * exatamente com o valor informado (sem centavo perdido).
     *
     * @param  array<int, float>  $valores
     * @return array<int, float>
     */
    protected function rateio(array $valores, float $total): array
    {
        $rateio = array_fill_keys(array_keys($valores), 0.0);

        if ($total <= 0 || $valores === []) {
            return $rateio;
        }

        $base = array_sum($valores);
        $keys = array_keys($valores);
        $ultimo = end($keys);
        $acumulado = 0.0;

        foreach ($keys as $key) {
            if ($key === $ultimo) {
                $rateio[$key] = round($total - $acumulado, 2);

                continue;
            }

            $parte = $base > 0
                ? round($total * ($valores[$key] / $base), 2)
                : round($total / count($keys), 2);

            $rateio[$key] = $parte;
            $acumulado += $parte;
        }

        return $rateio;
    }
}
