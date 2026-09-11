<?php

namespace App\Services\LegacyMigration;

use App\Services\LegacyMigration\Support\AutoIncrementResetter;
use App\Services\LegacyMigration\Support\LegacyValueMapper;
use Illuminate\Support\Facades\DB;

/**
 * Migra `legacy`.perdas -> perdas.
 *
 * user_id fica null (o legado nao registrava quem lancou a perda).
 */
class PerdaMigrationService implements MigratesLegacyModule
{
    public function key(): string
    {
        return 'perdas';
    }

    public function run(bool $dryRun): ModuleMigrationResult
    {
        $rows = DB::connection('legacy')->table('perdas')->orderBy('id')->get();
        $warnings = [];
        $migrated = 0;

        $prepared = [];
        foreach ($rows as $row) {
            [$tipo, $warning] = LegacyValueMapper::perdaTipo($row->tipo);
            if ($warning) {
                $warnings[] = "perda #{$row->id}: {$warning}";
            }
            $prepared[] = [
                'id' => $row->id,
                'data' => $row->data,
                'tipo' => $tipo,
                'produto_id' => $row->produto_id,
                'material_id' => $row->material_id,
                'quantidade' => $row->qtd,
                'motivo' => $row->motivo,
                'observacoes' => $row->obs,
                'custo_total' => $row->custo_total,
                'user_id' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ];
        }

        if (! $dryRun) {
            DB::transaction(function () use ($prepared, &$migrated) {
                foreach ($prepared as $data) {
                    $id = $data['id'];
                    unset($data['id']);
                    DB::table('perdas')->updateOrInsert(['id' => $id], $data);
                    $migrated++;
                }
            });

            AutoIncrementResetter::apply('perdas', $rows->max('id'));
        } else {
            $migrated = count($prepared);
        }

        return new ModuleMigrationResult(
            module: $this->key(),
            found: $rows->count(),
            migrated: $migrated,
            warnings: $warnings,
            expected: config('legacy_migration.expected_counts.perdas'),
        );
    }
}
