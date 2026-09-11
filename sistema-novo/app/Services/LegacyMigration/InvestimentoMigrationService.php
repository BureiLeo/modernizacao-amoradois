<?php

namespace App\Services\LegacyMigration;

use App\Services\LegacyMigration\Support\AutoIncrementResetter;
use Illuminate\Support\Facades\DB;

/**
 * Migra `legacy`.investimentos -> investimentos (copia direta, 1:1).
 */
class InvestimentoMigrationService implements MigratesLegacyModule
{
    public function key(): string
    {
        return 'investimentos';
    }

    public function run(bool $dryRun): ModuleMigrationResult
    {
        $rows = DB::connection('legacy')->table('investimentos')->orderBy('id')->get();
        $migrated = 0;

        if (! $dryRun) {
            DB::transaction(function () use ($rows, &$migrated) {
                foreach ($rows as $row) {
                    DB::table('investimentos')->updateOrInsert(
                        ['id' => $row->id],
                        [
                            'nome' => $row->nome,
                            'categoria' => $row->categoria,
                            'data' => $row->data,
                            'valor' => $row->valor,
                            'quitado' => (bool) $row->quitado,
                            'observacoes' => $row->obs,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->created_at,
                        ]
                    );
                    $migrated++;
                }
            });

            AutoIncrementResetter::apply('investimentos', $rows->max('id'));
        } else {
            $migrated = $rows->count();
        }

        return new ModuleMigrationResult(
            module: $this->key(),
            found: $rows->count(),
            migrated: $migrated,
            expected: config('legacy_migration.expected_counts.investimentos'),
        );
    }
}
