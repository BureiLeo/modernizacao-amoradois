<?php

namespace App\Console\Commands;

use App\Services\LegacyMigration\LegacyValidationService;
use Illuminate\Console\Command;

class ValidateLegacyMigration extends Command
{
    protected $signature = 'legacy:validate';

    protected $description = 'Compara origem (legacy) e destino apos a migracao: contagens, financeiro, estoque, orfaos e anomalias conhecidas.';

    public function handle(LegacyValidationService $service): int
    {
        $tudoOk = true;

        $this->line('========================================');
        $this->line('VALIDAÇÃO DA MIGRAÇÃO');
        $this->line('========================================');

        $this->section('Contagens', $service->contagens(), $tudoOk);
        $this->section('Stock movements (convertidos)', $service->stockMovements(), $tudoOk);
        $this->section('Pagamentos derivados', $service->pagamentos(), $tudoOk);
        $this->section('Financeiro (vendas)', $service->financeiro(), $tudoOk);
        $this->section('Caixa (saldo)', $service->caixa(), $tudoOk);
        $this->section('Estoque (material por material)', $service->estoque(), $tudoOk);
        $this->section('IDs preservados (amostra)', $service->ids(), $tudoOk);
        $this->section('Relacionamentos órfãos', $service->orfaos(), $tudoOk);

        $this->newLine();
        $this->line('Anomalias conhecidas preservadas:');
        foreach ($service->anomaliasConhecidas() as $linha) {
            $this->line('  '.$linha);
        }

        $this->newLine();
        $this->line('========================================');
        $this->line('Resultado: '.($tudoOk ? 'VALIDAÇÃO APROVADA' : 'VALIDAÇÃO COM DIVERGÊNCIAS'));
        $this->line('========================================');

        return $tudoOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array{ok: bool, lines: array<int, string>}  $result
     */
    private function section(string $titulo, array $result, bool &$tudoOk): void
    {
        $this->newLine();
        $this->line($titulo.':');
        foreach ($result['lines'] as $linha) {
            $this->line('  '.$linha);
        }
        $tudoOk = $tudoOk && $result['ok'];
    }
}
