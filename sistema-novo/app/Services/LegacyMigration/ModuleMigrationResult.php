<?php

namespace App\Services\LegacyMigration;

/**
 * Resultado padronizado de um modulo de migracao.
 *
 * Nao lanca excecao para anomalias de dados conhecidas - elas viram
 * "warnings" (documentadas), preservadas no banco novo. So erros reais
 * (ex.: falha de SQL) devem propagar como excecao.
 */
class ModuleMigrationResult
{
    /**
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly string $module,
        public readonly int $found,
        public readonly int $migrated,
        public readonly array $warnings = [],
        public readonly ?int $expected = null,
    ) {}

    public function withCounts(int $found, int $migrated): static
    {
        return new static($this->module, $found, $migrated, $this->warnings, $this->expected);
    }
}
