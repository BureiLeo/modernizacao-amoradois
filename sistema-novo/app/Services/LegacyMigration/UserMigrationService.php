<?php

namespace App\Services\LegacyMigration;

use App\Enums\UserRole;
use App\Services\LegacyMigration\Support\AutoIncrementResetter;
use Illuminate\Support\Facades\DB;

/**
 * Migra app\Models\User a partir de `legacy`.users.
 *
 * DECISOES DOCUMENTADAS:
 * - O legado usava `username` para login (nao existe no schema novo,
 *   que segue o padrao Laravel Breeze de login por e-mail). Todos os
 *   4 usuarios legados tem email='' (vazio) no dump - nao da para usar
 *   isso direto (colidiria com o UNIQUE de email). Sintetizamos um
 *   e-mail estavel a partir do username: "{username}@legado.amoradois.local".
 * - `password_hash` do legado ja e bcrypt (`$2y$10$...`), 100% compativel
 *   com o Hash::check do Laravel - copiado como esta, SEM redefinir senha.
 * - O legado nao tinha nenhum sistema de roles/permissoes (qualquer
 *   usuario logado tinha acesso total). Por isso, todo usuario migrado
 *   recebe role=admin - e a regra mais fiel ao comportamento real do
 *   sistema antigo. Nao inventamos papeis mais granulares aqui.
 * - email_verified_at = created_at (usuarios pre-existentes, nao passam
 *   pelo fluxo de verificacao do Breeze).
 */
class UserMigrationService implements MigratesLegacyModule
{
    public function key(): string
    {
        return 'users';
    }

    public function run(bool $dryRun): ModuleMigrationResult
    {
        $rows = DB::connection('legacy')->table('users')->orderBy('id')->get();
        $warnings = [];
        $migrated = 0;

        if (! $dryRun) {
            DB::transaction(function () use ($rows, &$migrated) {
                foreach ($rows as $row) {
                    $email = $row->email !== null && trim((string) $row->email) !== ''
                        ? $row->email
                        : $row->username.'@legado.amoradois.local';

                    DB::table('users')->updateOrInsert(
                        ['id' => $row->id],
                        [
                            'name' => $row->nome,
                            'email' => $email,
                            'email_verified_at' => $row->created_at,
                            'password' => $row->password_hash,
                            'role' => UserRole::Admin->value,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->created_at,
                        ]
                    );
                    $migrated++;
                }
            });

            $this->resetAutoIncrement($rows->max('id'));
        } else {
            $migrated = $rows->count();
        }

        foreach ($rows as $row) {
            if ($row->email === null || trim((string) $row->email) === '') {
                $warnings[] = "usuario #{$row->id} ({$row->username}): e-mail vazio no legado, sintetizado como {$row->username}@legado.amoradois.local";
            }
        }
        $warnings[] = 'todos os usuarios migrados receberam role=admin (legado nao tinha sistema de papeis)';

        return new ModuleMigrationResult(
            module: $this->key(),
            found: $rows->count(),
            migrated: $migrated,
            warnings: $warnings,
            expected: config('legacy_migration.expected_counts.users'),
        );
    }

    private function resetAutoIncrement(?int $maxId): void
    {
        AutoIncrementResetter::apply('users', $maxId);
    }
}
