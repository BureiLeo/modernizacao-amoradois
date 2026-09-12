<?php

namespace App\Enums;

enum VendaStatus: string
{
    case Pendente = 'pendente';
    case Paga = 'paga';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Paga => 'Paga',
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
        };
    }
}
