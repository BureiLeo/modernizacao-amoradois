<?php

namespace App\Services\LegacyMigration\Support;

/**
 * Ponto UNICO de mapeamento de valores livres do legado para os
 * valores dos PHP Enums do sistema novo (App\Enums\*). Usa os mapas
 * centralizados em config/legacy_migration.php.
 *
 * Qualquer valor nao mapeado cai num fallback seguro E gera um aviso
 * (nunca falha silenciosamente, nunca perde o dado original de vista -
 * quem chamar deve registrar o warning retornado).
 */
class LegacyValueMapper
{
    /**
     * @return array{0: ?string, 1: ?string} [valor_mapeado, warning|null]
     */
    public static function formaPagamento(?string $legacyValue): array
    {
        if ($legacyValue === null || trim($legacyValue) === '') {
            return [null, null];
        }

        $key = mb_strtolower(trim($legacyValue));
        $map = config('legacy_migration.forma_pagamento_map');

        if (isset($map[$key])) {
            return [$map[$key], null];
        }

        return [
            'outro',
            "forma_pagamento '{$legacyValue}' desconhecida - mapeada para 'outro' (verificar manualmente)",
        ];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    public static function caixaTipo(string $legacyValue): array
    {
        $key = mb_strtolower(trim($legacyValue));
        $map = config('legacy_migration.caixa_tipo_map');

        if (isset($map[$key])) {
            return [$map[$key], null];
        }

        return ['outro', "caixa_movimentos.tipo '{$legacyValue}' desconhecido - mapeado para 'outro'"];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    public static function perdaTipo(string $legacyValue): array
    {
        $key = mb_strtolower(trim($legacyValue));
        $map = config('legacy_migration.perda_tipo_map');

        if (isset($map[$key])) {
            return [$map[$key], null];
        }

        return ['material', "perdas.tipo '{$legacyValue}' desconhecido - mapeado para 'material'"];
    }
}
