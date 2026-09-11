<?php

namespace App\Console\Commands;

use App\Services\LegacyMigration\LegacyMigrationService;
use App\Services\LegacyMigration\ModuleMigrationResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyData extends Command
{
    protected $signature = 'legacy:migrate
        {--dry-run : Nao grava nada no destino, so mostra o que seria feito}
        {--only=* : Migrar somente estes modulos/grupos (ex: --only=clientes --only=vendas)}
        {--force : Nao pedir confirmacao interativa}';

    protected $description = 'Migra os dados do sistema legado (conexao "legacy") para o banco novo, preservando IDs e sem reprocessar regras de negocio.';

    public function handle(LegacyMigrationService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = $this->option('only') ?: null;

        if (! $this->checkConnections()) {
            return self::FAILURE;
        }

        $this->info('Origem (legacy): '.config('database.connections.legacy.database').' @ '.config('database.connections.legacy.host'));
        $this->info('Destino (default): '.config('database.connections.'.config('database.default').'.database', config('database.default')));
        $this->info($dryRun ? 'Modo: DRY-RUN (nada sera gravado)' : 'Modo: MIGRACAO REAL');

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('Confirma a migracao de dados para o banco de destino acima?')) {
                $this->warn('Cancelado pelo usuario.');

                return self::SUCCESS;
            }
        }

        $modules = $service->modules();
        $total = count($only ? array_intersect(array_keys($modules), $this->expandOnly($only)) : $modules);
        $this->newLine();

        try {
            $results = $service->run($dryRun, $only);
        } catch (\Throwable $e) {
            $this->error('Migracao interrompida: '.$e->getMessage());
            $this->error('Consulte storage/logs/legacy-migration.log para detalhes.');

            return self::FAILURE;
        }

        $i = 0;
        foreach ($results as $result) {
            $i++;
            $this->line("[{$i}/{$total}] Migrando {$result->module}...");
            $suffix = $result->expected !== null ? " (esperado: {$result->expected})" : '';
            $this->line("  {$result->migrated} registro(s) migrado(s), {$result->found} encontrado(s){$suffix} ✓");
            foreach ($result->warnings as $warning) {
                $this->comment('  ⚠ '.$warning);
            }
            $this->newLine();
        }

        $this->renderSummary($results, $dryRun);

        return self::SUCCESS;
    }

    private function checkConnections(): bool
    {
        try {
            DB::connection('legacy')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Nao foi possivel conectar na conexao "legacy": '.$e->getMessage());
            $this->error('Configure LEGACY_DB_* no .env antes de rodar este comando.');

            return false;
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->error('Nao foi possivel conectar no banco de destino padrao: '.$e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @param  array<int, string>  $only
     * @return array<int, string>
     */
    private function expandOnly(array $only): array
    {
        $groups = config('legacy_migration.groups');
        $expanded = [];
        foreach ($only as $item) {
            if (isset($groups[$item])) {
                array_push($expanded, ...$groups[$item]);
            } else {
                $expanded[] = $item;
            }
        }

        return $expanded;
    }

    /**
     * @param  array<int, ModuleMigrationResult>  $results
     */
    private function renderSummary(array $results, bool $dryRun): void
    {
        $this->line('========================================');
        $this->line('MIGRAÇÃO LEGADO → NOVO'.($dryRun ? ' (DRY-RUN)' : ''));
        $this->line('========================================');
        foreach ($results as $result) {
            $expected = $result->expected !== null ? " / esperado {$result->expected}" : '';
            $this->line(str_pad(ucfirst($result->module).':', 20).$result->migrated.' / '.$result->found.$expected);
        }
        $this->line('========================================');
        $this->line($dryRun ? 'Resultado: DRY-RUN CONCLUÍDO (nada foi gravado)' : 'Resultado: MIGRAÇÃO CONCLUÍDA');
    }
}
