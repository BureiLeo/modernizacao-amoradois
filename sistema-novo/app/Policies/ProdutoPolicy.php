<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Produto;
use App\Models\User;

/**
 * Regra simples e documentada (Etapa 6/7 #56):
 *
 * ADMIN     : visualizar, criar, editar, inativar/reativar
 * VENDEDOR  : somente visualizar (consulta preco/estoque na venda)
 * demais    : somente visualizar
 */
class ProdutoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Produto $produto): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Produto $produto): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Produto $produto): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function restore(User $user, Produto $produto): bool
    {
        return $user->role === UserRole::Admin;
    }
}
