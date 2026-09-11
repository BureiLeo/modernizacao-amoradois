<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Categoria;
use App\Models\User;

/**
 * Categorias sao um recurso de catalogo/organizacao - gestao restrita
 * ao ADMIN (Etapa 6/7 #56).
 */
class CategoriaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Categoria $categoria): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Categoria $categoria): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Categoria $categoria): bool
    {
        return $user->role === UserRole::Admin;
    }
}
