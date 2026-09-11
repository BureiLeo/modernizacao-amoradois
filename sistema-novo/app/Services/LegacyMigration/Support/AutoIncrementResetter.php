<?php

namespace App\Services\LegacyMigration\Support;

use Illuminate\Support\Facades\DB;

/**
 * Ajusta o proximo AUTO_INCREMENT de uma tabela do banco NOVO depois de
 * inserir registros com ID explicito (preservado do legado).
 *
 * So atua no MySQL/MariaDB (producao/homologacao real). No SQLite (usado
 * pelos testes automatizados via phpunit.xml) o auto-increment e apenas
 * MAX(id)+1 automaticamente, entao a operacao vira um no-op seguro.
 *
 * NUNCA executa nada na conexao "legacy" - so na conexao padrao (destino).
 */
class AutoIncrementResetter
{
    public static function apply(string $table, ?int $maxId): void
    {
        if ($maxId === null) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            // sqlite/pgsql/outros: nao precisa de ajuste manual aqui.
            return;
        }

        $next = $maxId + 1;
        DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}");
    }
}
