<?php

namespace App\Enums;

enum VendaStatus: string
{
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
        };
    }
}
