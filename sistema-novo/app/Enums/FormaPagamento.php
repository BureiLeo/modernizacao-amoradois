<?php

namespace App\Enums;

/**
 * Forma de pagamento. Guardada como string (nao ENUM do banco) para
 * poder evoluir sem migration. O sistema legado usava valores livres
 * ("PIX", "Dinheiro", "Outro"...); os valores aqui sao os usados pelo
 * sistema novo a partir de agora.
 */
enum FormaPagamento: string
{
    case Pix = 'pix';
    case Dinheiro = 'dinheiro';
    case Debito = 'debito';
    case Credito = 'credito';
    case Transferencia = 'transferencia';
    case Outro = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'PIX',
            self::Dinheiro => 'Dinheiro',
            self::Debito => 'Débito',
            self::Credito => 'Crédito',
            self::Transferencia => 'Transferência',
            self::Outro => 'Outro',
        };
    }
}
