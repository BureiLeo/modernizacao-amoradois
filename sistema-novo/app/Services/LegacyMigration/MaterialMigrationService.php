<?php

namespace App\Services\LegacyMigration;

use App\Services\LegacyMigration\Support\AutoIncrementResetter;
use Illuminate\Support\Facades\DB;

/**
 * Migra `legacy`.materiais -> materiais.
 *
 * DECISAO DOCUMENTADA:
 * - `estoque` (int no legado) e copiado DIRETO para a coluna decimal
 *   nova, sem arredondar/recalcular - o valor atual em materiais.estoque
 *   e a referencia oficial da migracao (nao reprocessamos compras,
 *   vendas, perdas e ajustes para "reconstruir" o estoque).
 */
class MaterialMigrationService implements MigratesLegacyModule
{
    public function key(): string
    {
        return 'materiais';
    }

    public function run(bool $dryRun): ModuleMigrationResult
    {
        $rows = DB::connection('legacy')->table('materiais')->orderBy('id')->get();
        $migrated = 0;

        if (! $dryRun) {
            DB::transaction(function () use ($rows, &$migrated) {
                foreach ($rows as $row) {
                    DB::table('materiais')->updateOrInsert(
                        ['id' => $row->id],
                        [
                            'nome' => $row->nome,
                            'unidade_base' => $row->unidade_base,
                            'fator_embalagem' => $row->fator_embalagem,
                            'estoque' => $row->estoque,
                            'estoque_minimo' => $row->estoque_min,
                            'custo_medio' => $row->custo_medio,
                            'observacoes' => $row->obs,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->created_at,
                            'deleted_at' => null,
                        ]
                    );
                    $migrated++;
                }
            });

            AutoIncrementResetter::apply('materiais', $rows->max('id'));
        } else {
            $migrated = $rows->count();
        }

        return new ModuleMigrationResult(
            module: $this->key(),
            found: $rows->count(),
            migrated: $migrated,
            expected: config('legacy_migration.expected_counts.materiais'),
        );
    }
}
