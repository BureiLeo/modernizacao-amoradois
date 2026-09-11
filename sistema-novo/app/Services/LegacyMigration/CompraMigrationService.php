<?php

namespace App\Services\LegacyMigration;

use App\Services\LegacyMigration\Support\AutoIncrementResetter;
use App\Services\LegacyMigration\Support\LegacyValueMapper;
use Illuminate\Support\Facades\DB;

/**
 * Migra `legacy`.compras -> compras.
 *
 * DECISOES DOCUMENTADAS:
 * - fornecedor_id = null (legado nao tem fornecedor estruturado).
 * - desconto = 0 (coluna nova, nao existia no legado).
 * - custo medio NAO e recalculado - a compra e importada como um fato
 *   historico, o custo_medio atual do material ja foi migrado direto
 *   (MaterialMigrationService), sem reprocessar a formula ponderada.
 */
class CompraMigrationService implements MigratesLegacyModule
{
    public function key(): string
    {
        return 'compras';
    }

    public function run(bool $dryRun): ModuleMigrationResult
    {
        $rows = DB::connection('legacy')->table('compras')->orderBy('id')->get();
        $warnings = [];
        $migrated = 0;

        $prepared = [];
        foreach ($rows as $row) {
            [$forma, $warning] = LegacyValueMapper::formaPagamento($row->forma_pagamento);
            if ($warning) {
                $warnings[] = "compra #{$row->id}: {$warning}";
            }
            $prepared[] = [
                'id' => $row->id,
                'data' => $row->data,
                'material_id' => $row->material_id,
                'fornecedor_id' => null,
                'quantidade_embalagem' => $row->qtd_embalagem,
                'quantidade' => $row->qtd_base,
                'valor' => $row->custo_total,
                'custo_unitario' => $row->custo_unit,
                'frete' => $row->frete,
                'desconto' => 0,
                'forma_pagamento' => $forma,
                'pago' => (bool) $row->pago,
                'anexo' => $row->anexo,
                'observacoes' => $row->obs,
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ];
        }

        if (! $dryRun) {
            DB::transaction(function () use ($prepared, &$migrated) {
                foreach ($prepared as $data) {
                    $id = $data['id'];
                    unset($data['id']);
                    DB::table('compras')->updateOrInsert(['id' => $id], $data);
                    $migrated++;
                }
            });

            AutoIncrementResetter::apply('compras', $rows->max('id'));
        } else {
            $migrated = count($prepared);
        }

        return new ModuleMigrationResult(
            module: $this->key(),
            found: $rows->count(),
            migrated: $migrated,
            warnings: $warnings,
            expected: config('legacy_migration.expected_counts.compras'),
        );
    }
}
