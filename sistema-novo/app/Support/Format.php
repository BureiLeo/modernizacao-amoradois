<?php

namespace App\Support;

/**
 * Formatacao de numeros para exibicao.
 *
 * Quantidades sao guardadas em decimal(12,3) porque existem materiais
 * fracionados (ml de tinta, metros de fita). Mas a maioria dos itens e
 * contada em unidades inteiras, e "12,000 un" polui a leitura. Aqui as
 * casas decimais so aparecem quando realmente existem: 12 -> "12",
 * 12,5 -> "12,5", 0,125 -> "0,125".
 */
class Format
{
    public static function quantidade(float|int|string|null $valor, int $casas = 3): string
    {
        $numero = (float) ($valor ?? 0);

        $formatado = number_format($numero, $casas, ',', '.');

        if (! str_contains($formatado, ',')) {
            return $formatado;
        }

        return rtrim(rtrim($formatado, '0'), ',');
    }

    public static function dinheiro(float|int|string|null $valor, int $casas = 2): string
    {
        return number_format((float) ($valor ?? 0), $casas, ',', '.');
    }
}
