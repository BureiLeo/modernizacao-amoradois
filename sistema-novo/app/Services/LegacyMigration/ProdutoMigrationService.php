<?php

namespace App\Services\LegacyMigration;

use App\Services\LegacyMigration\Support\AutoIncrementResetter;
use Illuminate\Support\Facades\DB;

/**
 * Migra `legacy`.produtos -> produtos.
 *
 * DECISOES DOCUMENTADAS:
 * - categoria_id = null para todos (o legado nao tem categorias e NAO
 *   inventamos uma "Sem categoria" generica - o campo ja e nullable).
 * - custo_referencia fica NULL na migracao (o legado nunca persistia
 *   esse valor, sempre calculava on-the-fly via BOM+custo_medio na
 *   tela de produtos.php). Preencher esse cache fica para a aplicacao,
 *   nao para a migracao historica.
 * - estoque_minimo fica NULL (conceito e de materiais, nao de produto,
 *   no sistema legado).
 * - Nenhuma produto_variacao e criada aqui (Etapa 4, secao 21).
 */
class ProdutoMigrationService implements MigratesLegacyModule
{
    public function key(): string
    {
        return 'produtos';
    }

    public function run(bool $dryRun): ModuleMigrationResult
    {
        $rows = DB::connection('legacy')->table('produtos')->orderBy('id')->get();
        $migrated = 0;

        if (! $dryRun) {
            DB::transaction(function () use ($rows, &$migrated) {
                foreach ($rows as $row) {
                    DB::table('produtos')->updateOrInsert(
                        ['id' => $row->id],
                        [
                            'categoria_id' => null,
                            'nome' => $row->nome,
                            'sku' => $row->sku,
                            'preco_venda' => $row->preco_sugerido,
                            'custo_referencia' => null,
                            'estoque_minimo' => null,
                            'imagem' => null,
                            'ativo' => (bool) $row->ativo,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->created_at,
                            'deleted_at' => null,
                        ]
                    );
                    $migrated++;
                }
            });

            AutoIncrementResetter::apply('produtos', $rows->max('id'));
        } else {
            $migrated = $rows->count();
        }

        return new ModuleMigrationResult(
            module: $this->key(),
            found: $rows->count(),
            migrated: $migrated,
            warnings: ['produto_variacoes permanece vazia (legado nao possui equivalente)'],
            expected: config('legacy_migration.expected_counts.produtos'),
        );
    }
}
