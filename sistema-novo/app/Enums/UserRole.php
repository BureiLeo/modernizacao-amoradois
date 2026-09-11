<?php

namespace App\Enums;

/**
 * Papel do usuario no sistema. Ainda sem policies/permissoes finas
 * (isso fica para uma etapa futura) - por enquanto e so uma etiqueta.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Vendedor = 'vendedor';
    case Producao = 'producao';
    case Financeiro = 'financeiro';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Vendedor => 'Vendedor',
            self::Producao => 'Produção',
            self::Financeiro => 'Financeiro',
        };
    }
}
