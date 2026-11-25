<?php

namespace App\Enums;

enum CaixaTipo: string
{
    case TransferenciaSaida = 'transfer_saida';
    case TransferenciaEntrada = 'transfer_entrada';
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
            self::TransferenciaSaida => 'Repasse enviado',
            self::TransferenciaEntrada => 'Repasse recebido',
            self::Outro => 'Outro',
        };
    }
}
