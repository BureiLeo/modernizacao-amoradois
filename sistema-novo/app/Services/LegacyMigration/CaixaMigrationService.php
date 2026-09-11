<?php

namespace App\Services\LegacyMigration;

use App\Services\LegacyMigration\Support\AutoIncrementResetter;
use App\Services\LegacyMigration\Support\LegacyValueMapper;
use Illuminate\Support\Facades\DB;

/**
 * Migra `legacy`.caixa_movimentos -> caixa_movimentos (copia direta).
 *
 * NAO gera novamente o caixa a partir de vendas/compras/investimentos -
 * isso duplicaria os lancamentos (o legado ja tem o historico completo
 * nesta propria tabela). ref_tabela/ref_id sao preservados tal como
 * estao (continuam apontando para os MESMOS IDs, ja que vendas/compras/
 * investimentos preservam seus IDs originais na migracao).
 *
 * ANOMALIA REAL DO LEGADO DESCOBERTA NESTA ETAPA: investimentos.php
 * chama caixa_lancar(..., 'INVESTIMENTO', ...), mas a coluna
 * caixa_movimentos.tipo e um ENUM('VENDA','COMPRA','AJUSTE','OUTRO') -
 * NAO existe 'INVESTIMENTO' nesse ENUM. Em modo nao-estrito, o
 * MySQL/MariaDB aceitou o INSERT e silenciosamente gravou uma STRING
 * VAZIA no lugar do valor invalido, em vez de rejeitar. Os 5 lancamentos
 * de investimento do legado (ref_tabela='investimentos') tem
 * tipo='' por causa disso. Mapeamos para 'outro' (fallback seguro) e
 * reportamos como anomalia conhecida - o valor financeiro (coluna
 * `valor`) esta correto e intacto, so a categorizacao por `tipo` que
 * era invalida desde a origem.
 */
class CaixaMigrationService implements MigratesLegacyModule
{
    public function key(): string
    {
        return 'caixa_movimentos';
    }

    public function run(bool $dryRun): ModuleMigrationResult
    {
        $rows = DB::connection('legacy')->table('caixa_movimentos')->orderBy('id')->get();
        $warnings = [];
        $migrated = 0;
        $tipoVazioInvestimento = 0;

        $prepared = [];
        foreach ($rows as $row) {
            if (trim((string) $row->tipo) === '' && $row->ref_tabela === 'investimentos') {
                $tipoVazioInvestimento++;
                $tipo = 'outro';
            } else {
                [$tipo, $warning] = LegacyValueMapper::caixaTipo($row->tipo);
                if ($warning) {
                    $warnings[] = "caixa_movimentos #{$row->id}: {$warning}";
                }
            }
            $prepared[] = [
                'id' => $row->id,
                'data' => $row->data,
                'tipo' => $tipo,
                'ref_tabela' => $row->ref_tabela,
                'ref_id' => $row->ref_id,
                'descricao' => $row->descricao,
                'valor' => $row->valor,
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ];
        }

        if ($tipoVazioInvestimento > 0) {
            $warnings[] = "ANOMALIA REAL DO LEGADO PRESERVADA: {$tipoVazioInvestimento} lancamento(s) de investimento com caixa_movimentos.tipo='' (bug do legado: ENUM nao aceita 'INVESTIMENTO', string vazia foi gravada em modo nao-estrito) - mapeados para 'outro', valor financeiro preservado intacto";
        }

        if (! $dryRun) {
            DB::transaction(function () use ($prepared, &$migrated) {
                foreach ($prepared as $data) {
                    $id = $data['id'];
                    unset($data['id']);
                    DB::table('caixa_movimentos')->updateOrInsert(['id' => $id], $data);
                    $migrated++;
                }
            });

            AutoIncrementResetter::apply('caixa_movimentos', $rows->max('id'));
        } else {
            $migrated = count($prepared);
        }

        return new ModuleMigrationResult(
            module: $this->key(),
            found: $rows->count(),
            migrated: $migrated,
            warnings: $warnings,
            expected: config('legacy_migration.expected_counts.caixa_movimentos'),
        );
    }
}
