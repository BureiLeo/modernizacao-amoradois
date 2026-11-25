<?php

namespace App\Enums;

enum ContaCaixa: string
{
    case Canecas = 'canecas';
    case Impressao3d = 'impressao_3d';
    case Feira = 'feira';

    public function label(): string
    {
        return match ($this) {
            self::Canecas => 'Canecas',
            self::Impressao3d => 'Impressão 3D',
            self::Feira => 'Conta da feira',
        };
    }

    public static function linhas(): array
    {
        return [self::Canecas, self::Impressao3d];
    }
}
