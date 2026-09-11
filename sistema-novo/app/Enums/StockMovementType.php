<?php

namespace App\Enums;

/**
 * Tipo de movimentacao no ledger unificado de estoque (stock_movements).
 * A direcao (entrada/saida) e dada pelo sinal de "quantity", nao pelo tipo.
 */
enum StockMovementType: string
{
    case Compra = 'compra';
    case Venda = 'venda';
    case Perda = 'perda';
    case Ajuste = 'ajuste';
    case Devolucao = 'devolucao';
    case Estorno = 'estorno';

    public function label(): string
    {
        return match ($this) {
            self::Compra => 'Compra',
            self::Venda => 'Venda',
            self::Perda => 'Perda',
            self::Ajuste => 'Ajuste manual',
            self::Devolucao => 'Devolução',
            self::Estorno => 'Estorno',
        };
    }
}
