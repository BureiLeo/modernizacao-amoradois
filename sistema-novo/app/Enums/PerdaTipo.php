<?php

namespace App\Enums;

enum PerdaTipo: string
{
    case Produto = 'produto';
    case Material = 'material';

    public function label(): string
    {
        return match ($this) {
            self::Produto => 'Produto (via BOM)',
            self::Material => 'Material (direta)',
        };
    }
}
