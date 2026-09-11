<?php

namespace App\Enums;

enum StatusPagamento: string
{
    case Pendente = 'pendente';
    case Pago = 'pago';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Pago => 'Pago',
            self::Cancelado => 'Cancelado',
        };
    }
}
