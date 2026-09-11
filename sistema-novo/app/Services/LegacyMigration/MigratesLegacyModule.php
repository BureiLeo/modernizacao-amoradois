<?php

namespace App\Services\LegacyMigration;

interface MigratesLegacyModule
{
    /**
     * Nome curto do modulo (chave usada em --only= e nos relatorios).
     */
    public function key(): string;

    /**
     * Executa a migracao deste modulo.
     *
     * Quando $dryRun = true, NADA pode ser gravado no banco de destino -
     * apenas contagens/anomalias sao calculadas e retornadas.
     */
    public function run(bool $dryRun): ModuleMigrationResult;
}
