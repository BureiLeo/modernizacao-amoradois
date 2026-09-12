<?php

namespace App\Services\Stock;

use App\Enums\StockMovementType;
use App\Models\Material;
use App\Models\Produto;
use App\Models\StockMovement;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class StockService
{
    /**
     * @return array{available: bool, required: float, available_quantity: float, missing: float, requirements?: array<int, array{material: Material, quantity: float, available: float, missing: float}>}
     */
    public function checkAvailability(Produto|Material $entity, float|int|string $quantity = 1): array
    {
        if ($entity instanceof Produto) {
            $requirements = [];
            $missingValue = 0.0;

            if (! $entity->hasBom()) {
                throw new RuntimeException("O produto {$entity->nome} não possui materiais configurados.");
            }

            foreach ($entity->materialRequirements((int) $quantity) as $requirement) {
                $material = $requirement['material'];
                $needed = (float) $requirement['quantidade'];
                $available = (float) ($material->estoque ?? 0);
                $missing = max($needed - $available, 0.0);
                $essencial = (bool) ($material->essencial ?? true);

                $requirements[] = [
                    'material' => $material,
                    'quantity' => $needed,
                    'available' => $available,
                    'missing' => $missing,
                    'essencial' => $essencial,
                ];

                // Materiais de apoio/embalagem (nao essenciais) nao bloqueiam
                // a venda mesmo faltando - so os essenciais (ex.: a caneca).
                if ($essencial) {
                    $missingValue += $missing;
                }
            }

            return [
                'available' => $missingValue === 0.0,
                'required' => $missingValue,
                'available_quantity' => 0.0,
                'missing' => $missingValue,
                'requirements' => $requirements,
            ];
        }

        $required = (float) $quantity;
        $available = (float) ($entity->estoque ?? 0);

        return [
            'available' => $available >= $required,
            'required' => $required,
            'available_quantity' => $available,
            'missing' => max($required - $available, 0.0),
            'material' => $entity,
        ];
    }

    public function consumeForSale(Venda $venda, ?int $userId = null): void
    {
        foreach ($venda->itens()->with('produto.bom.material')->get() as $item) {
            $produto = $item->produto;

            if (! $produto) {
                continue;
            }

            $this->ensureBomForProduto($produto);

            foreach ($produto->materialRequirements((int) $item->quantidade) as $requirement) {
                $material = $requirement['material'];
                $quantity = (float) $requirement['quantidade'];
                // Materiais de apoio/embalagem (nao essenciais) podem ficar
                // negativos no estoque - eles nao bloqueiam a venda, so
                // servem de alerta para reposicao.
                $allowNegative = ! ($material->essencial ?? true);
                $this->changeMaterialStock($material, -$quantity, StockMovementType::Venda, $produto, $venda, $userId, 'Venda #'.$venda->id, $allowNegative);
            }
        }
    }

    public function reverseSale(Venda $venda, ?int $userId = null): void
    {
        foreach ($venda->itens()->with('produto.bom.material')->get() as $item) {
            $produto = $item->produto;

            if (! $produto) {
                continue;
            }

            foreach ($produto->materialRequirements((int) $item->quantidade) as $requirement) {
                $material = $requirement['material'];
                $quantity = (float) $requirement['quantidade'];
                $this->changeMaterialStock($material, $quantity, StockMovementType::Estorno, $produto, $venda, $userId, 'Estorno da venda #'.$venda->id);
            }
        }
    }

    public function adjust(Material $material, float|int|string $quantity, ?string $motivo = null, ?string $observacoes = null, ?int $userId = null): StockMovement
    {
        $delta = (float) $quantity;

        return DB::transaction(function () use ($material, $delta, $motivo, $observacoes, $userId) {
            $locked = Material::query()->whereKey($material->getKey())->lockForUpdate()->firstOrFail();
            $before = (float) ($locked->estoque ?? 0);
            $after = $before + $delta;

            if ($after < 0) {
                throw new InvalidArgumentException('Estoque não pode ficar negativo.');
            }

            $locked->update(['estoque' => $after]);

            return $this->recordMovement(
                $locked,
                $delta,
                $before,
                $after,
                StockMovementType::Ajuste,
                $userId,
                $motivo ?? 'ajuste',
                $observacoes,
                null,
                null,
            );
        });
    }

    public function registerLoss(Material $material, float|int|string $quantity, ?string $motivo = null, ?string $observacoes = null, ?int $userId = null): StockMovement
    {
        $delta = (float) $quantity;

        if ($delta <= 0) {
            throw new InvalidArgumentException('A perda deve reduzir o estoque.');
        }

        return DB::transaction(function () use ($material, $delta, $motivo, $observacoes, $userId) {
            $locked = Material::query()->whereKey($material->getKey())->lockForUpdate()->firstOrFail();
            $before = (float) ($locked->estoque ?? 0);
            $after = $before - $delta;

            if ($after < 0) {
                throw new InvalidArgumentException('Não foi possível registrar a perda: estoque insuficiente.');
            }

            $locked->update(['estoque' => $after]);

            return $this->recordMovement(
                $locked,
                -$delta,
                $before,
                $after,
                StockMovementType::Perda,
                $userId,
                $motivo ?? 'perda',
                $observacoes,
                null,
                null,
            );
        });
    }

    /**
     * Entrada de estoque vinda de compra. Alem de somar a quantidade,
     * recalcula o custo medio ponderado do material - por isso nao usa
     * o changeMaterialStock generico.
     */
    public function registerPurchase(
        Material $material,
        float|int|string $quantity,
        float|int|string $custoUnitario,
        ?int $compraId = null,
        ?int $userId = null,
        ?string $observacoes = null,
    ): StockMovement {
        $delta = (float) $quantity;
        $custo = (float) $custoUnitario;

        if ($delta <= 0) {
            throw new InvalidArgumentException('A quantidade da compra deve ser maior que zero.');
        }

        if ($custo < 0) {
            throw new InvalidArgumentException('O custo unitário não pode ser negativo.');
        }

        return DB::transaction(function () use ($material, $delta, $custo, $compraId, $userId, $observacoes) {
            $locked = Material::query()->whereKey($material->getKey())->lockForUpdate()->firstOrFail();
            $before = (float) ($locked->estoque ?? 0);
            $after = $before + $delta;

            // Estoque negativo (herdado de dados legados) nao deve puxar
            // a media ponderada para baixo/cima de forma absurda.
            $baseMedia = max($before, 0.0);
            $custoMedioAtual = (float) ($locked->custo_medio ?? 0);
            $custoMedio = ($baseMedia + $delta) > 0
                ? (($baseMedia * $custoMedioAtual) + ($delta * $custo)) / ($baseMedia + $delta)
                : $custo;

            $locked->update([
                'estoque' => $after,
                'custo_medio' => round($custoMedio, 6),
            ]);

            return $this->recordMovement(
                $locked,
                $delta,
                $before,
                $after,
                StockMovementType::Compra,
                $userId,
                'compra',
                $observacoes,
                null,
                $compraId,
            );
        });
    }

    protected function ensureBomForProduto(Produto $produto): void
    {
        if (! $produto->hasBom()) {
            throw new RuntimeException("O produto {$produto->nome} não possui materiais configurados.");
        }
    }

    protected function changeMaterialStock(Material $material, float $quantityDelta, StockMovementType $type, ?Produto $produto = null, ?Venda $venda = null, ?int $userId = null, ?string $sourceLabel = null, bool $allowNegative = false): StockMovement
    {
        return DB::transaction(function () use ($material, $quantityDelta, $type, $produto, $venda, $userId, $sourceLabel, $allowNegative) {
            $locked = Material::query()->whereKey($material->getKey())->lockForUpdate()->firstOrFail();
            $before = (float) ($locked->estoque ?? 0);
            $after = $before + $quantityDelta;

            if ($after < 0 && ! $allowNegative) {
                throw new InvalidArgumentException('Não foi possível concluir a operação: quantidade indisponível em estoque.');
            }

            $locked->update(['estoque' => $after]);

            return $this->recordMovement(
                $locked,
                $quantityDelta,
                $before,
                $after,
                $type,
                $userId,
                $sourceLabel ?? 'movimentacao',
                null,
                $produto?->id,
                $venda?->id,
            );
        });
    }

    protected function recordMovement(
        Material $material,
        float $quantityDelta,
        float $before,
        float $after,
        StockMovementType $type,
        ?int $userId,
        ?string $sourceLabel,
        ?string $observacoes,
        ?int $produtoId,
        ?int $sourceId,
    ): StockMovement {
        return StockMovement::query()->create([
            'material_id' => $material->getKey(),
            'produto_id' => $produtoId,
            'produto_variacao_id' => null,
            'user_id' => $userId,
            'type' => $type->value,
            'quantity' => $quantityDelta,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'source_type' => $sourceLabel,
            'source_id' => $sourceId,
            'observacoes' => $observacoes ?? $sourceLabel,
        ]);
    }
}
