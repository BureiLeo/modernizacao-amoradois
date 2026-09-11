<?php

namespace App\Services\LegacyMigration;

use Illuminate\Support\Facades\Log;

/**
 * Orquestra a execucao dos modulos de migracao na ordem correta de
 * dependencia (Etapa 4, secao 15).
 *
 * Cada modulo roda em SUA PROPRIA transacao (nao uma unica transacao
 * gigante para tudo) - se um modulo falhar, os anteriores ja
 * commitados permanecem, e o comando informa exatamente onde parou.
 */
class LegacyMigrationService
{
    /**
     * @return array<string, MigratesLegacyModule>
     */
    public function modules(): array
    {
        return [
            'users' => new UserMigrationService,
            'clientes' => new ClienteMigrationService,
            'materiais' => new MaterialMigrationService,
            'produtos' => new ProdutoMigrationService,
            'produto_bom' => new ProdutoBomMigrationService,
            'compras' => new CompraMigrationService,
            'vendas' => new VendaMigrationService,
            'pagamentos' => new PagamentoMigrationService,
            'perdas' => new PerdaMigrationService,
            'stock_movements' => new StockMovementMigrationService,
            'investimentos' => new InvestimentoMigrationService,
            'caixa_movimentos' => new CaixaMigrationService,
        ];
    }

    /**
     * @param  array<int, string>|null  $only  chaves de modulo (ver modules())
     *                                         ou grupos de config('legacy_migration.groups').
     * @return array<int, ModuleMigrationResult>
     */
    public function run(bool $dryRun, ?array $only = null): array
    {
        $modules = $this->modules();
        $keys = $this->resolveKeys($only, array_keys($modules));

        $results = [];

        Log::channel('legacy_migration')->info('=== INICIO DA MIGRACAO ===', [
            'dry_run' => $dryRun,
            'modulos' => $keys,
        ]);

        foreach ($keys as $key) {
            $module = $modules[$key];

            try {
                $result = $module->run($dryRun);
                $results[] = $result;

                Log::channel('legacy_migration')->info("modulo={$key}", [
                    'found' => $result->found,
                    'migrated' => $result->migrated,
                    'expected' => $result->expected,
                    'warnings' => $result->warnings,
                ]);
            } catch (\Throwable $e) {
                Log::channel('legacy_migration')->error("FALHA no modulo={$key}: {$e->getMessage()}");
                throw $e;
            }
        }

        Log::channel('legacy_migration')->info('=== FIM DA MIGRACAO ===');

        return $results;
    }

    /**
     * @param  array<int, string>  $allKeys
     * @return array<int, string>
     */
    private function resolveKeys(?array $only, array $allKeys): array
    {
        if ($only === null || $only === []) {
            return $allKeys;
        }

        $groups = config('legacy_migration.groups');
        $resolved = [];

        foreach ($only as $item) {
            if (isset($groups[$item])) {
                foreach ($groups[$item] as $moduleKey) {
                    $resolved[$moduleKey] = true;
                }

                continue;
            }

            if (in_array($item, $allKeys, true)) {
                $resolved[$item] = true;
            }
        }

        // Preserva a ordem de dependencia original, filtrando so o que
        // foi pedido.
        return array_values(array_filter($allKeys, fn ($k) => isset($resolved[$k])));
    }
}
