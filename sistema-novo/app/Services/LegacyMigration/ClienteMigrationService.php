<?php

namespace App\Services\LegacyMigration;

use App\Services\LegacyMigration\Support\AutoIncrementResetter;
use Illuminate\Support\Facades\DB;

/**
 * Migra `legacy`.clientes -> clientes.
 *
 * DECISOES DOCUMENTADAS:
 * - `codigo` e preservado EXATAMENTE como veio do legado (nunca
 *   recalculado por MAX(codigo)+1 aqui - essa correcao de condicao de
 *   corrida fica para a etapa de aplicacao/UI, nao para a migracao).
 * - Telefone/e-mail NAO sao tratados como unicos - o telefone duplicado
 *   legitimo (dois cadastros com o mesmo numero) e preservado tal como
 *   esta, sem deduplicar.
 * - `ativo` nao existia no legado: todo cliente migrado entra como
 *   ativo=true (nenhum cliente era "inativo" no sistema antigo).
 */
class ClienteMigrationService implements MigratesLegacyModule
{
    public function key(): string
    {
        return 'clientes';
    }

    public function run(bool $dryRun): ModuleMigrationResult
    {
        $rows = DB::connection('legacy')->table('clientes')->orderBy('id')->get();
        $warnings = [];

        $telefones = [];
        foreach ($rows as $row) {
            if (! empty($row->telefone)) {
                $telefones[$row->telefone][] = $row->id;
            }
        }
        foreach ($telefones as $telefone => $ids) {
            if (count($ids) > 1) {
                $warnings[] = 'ANOMALIA CONHECIDA PRESERVADA: telefone '.$telefone.' duplicado entre clientes #'.implode(', #', $ids);
            }
        }

        $migrated = 0;

        if (! $dryRun) {
            DB::transaction(function () use ($rows, &$migrated) {
                foreach ($rows as $row) {
                    DB::table('clientes')->updateOrInsert(
                        ['id' => $row->id],
                        [
                            'codigo' => $row->codigo,
                            'nome' => $row->nome,
                            'telefone' => $row->telefone,
                            'email' => $row->email,
                            'cpf' => $row->cpf,
                            'rg' => $row->rg,
                            'cnpj' => $row->cnpj,
                            'razao_social' => $row->razao_social,
                            'endereco' => $row->endereco,
                            'observacoes' => $row->obs,
                            'ativo' => true,
                            'created_at' => $row->criado_em,
                            'updated_at' => $row->criado_em,
                            'deleted_at' => null,
                        ]
                    );
                    $migrated++;
                }
            });

            AutoIncrementResetter::apply('clientes', $rows->max('id'));
        } else {
            $migrated = $rows->count();
        }

        return new ModuleMigrationResult(
            module: $this->key(),
            found: $rows->count(),
            migrated: $migrated,
            warnings: $warnings,
            expected: config('legacy_migration.expected_counts.clientes'),
        );
    }
}
