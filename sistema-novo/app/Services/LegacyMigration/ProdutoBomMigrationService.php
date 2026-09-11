<?php

namespace App\Services\LegacyMigration;

use App\Services\LegacyMigration\Support\AutoIncrementResetter;
use Illuminate\Support\Facades\DB;

/**
 * Migra `legacy`.produto_bom -> produto_bom.
 *
 * O legado nao tinha timestamps nesta tabela - usamos o instante da
 * migracao (now()) para created_at/updated_at, documentado aqui e no
 * warning retornado (nao inventamos uma data historica falsa).
 */
class ProdutoBomMigrationService implements MigratesLegacyModule
{
    public function key(): string
    {
        return 'produto_bom';
    }

    public function run(bool $dryRun): ModuleMigrationResult
    {
        $rows = DB::connection('legacy')->table('produto_bom')->orderBy('id')->get();

        $produtoIds = DB::connection('legacy')->table('produtos')->pluck('id')->all();
        $materialIds = DB::connection('legacy')->table('materiais')->pluck('id')->all();

        $warnings = ['sem timestamp no legado: created_at/updated_at = instante da migracao'];
        $valid = [];
        foreach ($rows as $row) {
            if (! in_array($row->produto_id, $produtoIds, true)) {
                $warnings[] = "produto_bom #{$row->id}: produto_id {$row->produto_id} nao existe - registro ignorado";

                continue;
            }
            if (! in_array($row->material_id, $materialIds, true)) {
                $warnings[] = "produto_bom #{$row->id}: material_id {$row->material_id} nao existe - registro ignorado";

                continue;
            }
            $valid[] = $row;
        }

        $migrated = 0;
        $now = now();

        if (! $dryRun) {
            DB::transaction(function () use ($valid, $now, &$migrated) {
                foreach ($valid as $row) {
                    DB::table('produto_bom')->updateOrInsert(
                        ['id' => $row->id],
                        [
                            'produto_id' => $row->produto_id,
                            'material_id' => $row->material_id,
                            'quantidade' => $row->qtd_por_unidade,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                    $migrated++;
                }
            });

            AutoIncrementResetter::apply('produto_bom', $rows->max('id'));
        } else {
            $migrated = count($valid);
        }

        return new ModuleMigrationResult(
            module: $this->key(),
            found: $rows->count(),
            migrated: $migrated,
            warnings: $warnings,
            expected: config('legacy_migration.expected_counts.produto_bom'),
        );
    }
}
