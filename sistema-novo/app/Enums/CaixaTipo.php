<?php

namespace App\Enums;

enum CaixaTipo: string
{
    case Venda = 'venda';
    case Compra = 'compra';
    case Ajuste = 'ajuste';
    case Outro = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::Venda => 'Venda',
            self::Compra => 'Compra',
            self::Ajuste => 'Ajuste de caixa',
            self::Outro => 'Outro',
        };
    }
}
