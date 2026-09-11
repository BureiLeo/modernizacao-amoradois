<?php

namespace App\Services\LegacyMigration;

use App\Enums\StockMovementType;
use Illuminate\Support\Facades\DB;

/**
 * Converte o historico de estoque legado (3 tabelas distintas) para o
 * ledger unificado `stock_movements`. Isto e CONVERSAO DE HISTORICO -
 * em nenhum momento este servico aplica a movimentacao no estoque
 * (materiais.estoque ja foi migrado direto por MaterialMigrationService,
 * com o valor atual do legado).
 *
 * Fontes convertidas:
 * - venda_consumos   -> type=venda,  quantity negativo (saida)
 * - perda_consumos   -> type=perda,  quantity negativo (saida)
 * - ajustes_estoque  -> type=ajuste, quantity com sinal conforme o
 *                       campo `tipo` do legado ('adicionar' => positivo,
 *                       'remover' => negativo) - CONFIRMADO lendo
 *                       materiais_ajuste.php (Etapa 2/4), nao assumido.
 *
 * quantity_before/quantity_after: SOMENTE preenchidos quando o legado
 * realmente guardava esses valores (e o caso de ajustes_estoque, que
 * tem estoque_anterior/estoque_novo). Para venda_consumos/perda_consumos
 * o legado NAO guarda before/after por linha - ficam null (nao inventado).
 *
 * IDEMPOTENCIA: como stock_movements e nova (sem PK compartilhada com o
 * legado), a idempotencia usa a combinacao (source_type, source_id,
 * material_id) como identificador logico do registro migrado.
 */
class StockMovementMigrationService implements MigratesLegacyModule
{
    public function key(): string
    {
        return 'stock_movements';
    }

    public function run(bool $dryRun): ModuleMigrationResult
    {
        $vendaConsumos = DB::connection('legacy')->table('venda_consumos')->orderBy('id')->get();
        $perdaConsumos = DB::connection('legacy')->table('perda_consumos')->orderBy('id')->get();
        $ajustes = DB::connection('legacy')->table('ajustes_estoque')->orderBy('id')->get();

        $materialIds = DB::connection('legacy')->table('materiais')->pluck('id')->all();

        $warnings = [];
        $rowsToInsert = [];

        foreach ($vendaConsumos as $c) {
            if (! in_array($c->material_id, $materialIds, true)) {
                $warnings[] = "venda_consumos #{$c->id}: material_id {$c->material_id} nao existe - ignorado";

                continue;
            }
            $rowsToInsert[] = [
                'material_id' => $c->material_id,
                'produto_id' => null,
                'produto_variacao_id' => null,
                'user_id' => null,
                'type' => StockMovementType::Venda->value,
                'quantity' => -abs($c->qtd_consumida),
                'quantity_before' => null,
                'quantity_after' => null,
                'source_type' => 'venda',
                'source_id' => $c->venda_id,
                'observacoes' => 'Convertido de venda_consumos (legado) #'.$c->id,
                'created_at' => $c->created_at,
                'updated_at' => $c->created_at,
            ];
        }

        foreach ($perdaConsumos as $c) {
            if (! in_array($c->material_id, $materialIds, true)) {
                $warnings[] = "perda_consumos #{$c->id}: material_id {$c->material_id} nao existe - ignorado";

                continue;
            }
            $rowsToInsert[] = [
                'material_id' => $c->material_id,
                'produto_id' => null,
                'produto_variacao_id' => null,
                'user_id' => null,
                'type' => StockMovementType::Perda->value,
                'quantity' => -abs($c->qtd_consumida),
                'quantity_before' => null,
                'quantity_after' => null,
                'source_type' => 'perda',
                'source_id' => $c->perda_id,
                'observacoes' => 'Convertido de perda_consumos (legado) #'.$c->id,
                'created_at' => $c->created_at,
                'updated_at' => $c->created_at,
            ];
        }

        $fracionados = 0;
        foreach ($ajustes as $a) {
            if (! in_array($a->material_id, $materialIds, true)) {
                $warnings[] = "ajustes_estoque #{$a->id}: material_id {$a->material_id} nao existe - ignorado";

                continue;
            }

            // Sinal confirmado em materiais_ajuste.php (legado): tipo
            // 'adicionar' soma, 'remover' subtrai do estoque.
            $sinal = $a->tipo === 'adicionar' ? 1 : -1;
            $quantidade = $sinal * abs((float) $a->quantidade);

            if ($quantidade != (int) $quantidade) {
                $fracionados++;
            }

            $rowsToInsert[] = [
                'material_id' => $a->material_id,
                'produto_id' => null,
                'produto_variacao_id' => null,
                'user_id' => $a->usuario_id,
                'type' => StockMovementType::Ajuste->value,
                'quantity' => $quantidade,
                'quantity_before' => $a->estoque_anterior,
                'quantity_after' => $a->estoque_novo,
                'source_type' => 'ajuste_estoque',
                'source_id' => $a->id,
                // Inclui o ID original no final para garantir chave de
                // idempotencia unica (varios ajustes legados compartilham
                // o mesmo texto de motivo, ex. "correcao").
                'observacoes' => trim(($a->motivo ?? '').' (ajustes_estoque legado #'.$a->id.')'),
                'created_at' => $a->data_ajuste,
                'updated_at' => $a->data_ajuste,
            ];
        }

        if ($fracionados > 0) {
            $warnings[] = "ANOMALIA CONHECIDA PRESERVADA: {$fracionados} ajuste(s) de estoque com quantidade fracionada migrados com precisao decimal exata";
        }

        $migrated = 0;

        if (! $dryRun) {
            DB::transaction(function () use ($rowsToInsert, &$migrated) {
                foreach ($rowsToInsert as $data) {
                    // Idempotencia: usa 'observacoes' como chave logica,
                    // pois carrega o ID original e unico da linha legada
                    // (ex.: "venda_consumos (legado) #123"). NAO usar
                    // source_type+source_id+material_id+type sozinhos:
                    // o legado tem pares (venda_id, material_id)
                    // legitimamente duplicados (vendas editadas mais de
                    // uma vez), e usar so essas colunas colapsaria linhas
                    // de historico distintas em uma so, perdendo dados.
                    $existente = DB::table('stock_movements')
                        ->where('observacoes', $data['observacoes'])
                        ->first();

                    if ($existente) {
                        DB::table('stock_movements')->where('id', $existente->id)->update($data);
                    } else {
                        DB::table('stock_movements')->insert($data);
                    }
                    $migrated++;
                }
            });
        } else {
            $migrated = count($rowsToInsert);
        }

        $expected = $vendaConsumos->count() + $perdaConsumos->count() + $ajustes->count();

        $warnings[] = "detalhe: venda={$vendaConsumos->count()}, perda={$perdaConsumos->count()}, ajuste={$ajustes->count()}";

        return new ModuleMigrationResult(
            module: $this->key(),
            found: $expected,
            migrated: $migrated,
            warnings: $warnings,
            expected: $expected,
        );
    }
}
