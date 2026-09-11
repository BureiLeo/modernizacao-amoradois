<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\User;

/**
 * Regra simples e documentada (Etapa 6/7 #56 - ainda sem sistema
 * completo de permissoes, que fica para a etapa de Usuarios):
 *
 * ADMIN     : visualizar, criar, editar, inativar/reativar
 * VENDEDOR  : visualizar, criar, editar (nao pode inativar/reativar)
 * demais    : somente visualizar
 */
class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Vendedor], true);
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Vendedor], true);
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function restore(User $user, Cliente $cliente): bool
    {
        return $user->role === UserRole::Admin;
    }
}
