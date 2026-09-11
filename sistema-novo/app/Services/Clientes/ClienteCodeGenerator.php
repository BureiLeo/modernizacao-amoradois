<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

/**
 * Gera o proximo `codigo` de cliente de forma segura (Etapa 6 #10).
 *
 * O sistema legado fazia MAX(codigo)+1 sem nenhuma protecao, com risco
 * real de corrida (dois cadastros simultaneos podiam gerar o mesmo
 * codigo). Aqui, o calculo do proximo numero e a insercao do cliente
 * acontecem dentro da MESMA transacao com lock (`lockForUpdate`), o que
 * serializa criacoes concorrentes e garante que o codigo nunca se repita.
 *
 * `withTrashed()` e usado de proposito: um cliente inativado (soft
 * delete) continua "ocupando" seu codigo para sempre - nunca reciclado.
 *
 * Uso:
 *   $cliente = app(ClienteCodeGenerator::class)->create([...dados...]);
 */
class ClienteCodeGenerator
{
    /**
     * @param  array<string, mixed>  $atributos  Dados do cliente, SEM 'codigo'
     *                                           (o codigo e calculado aqui dentro).
     */
    public function create(array $atributos): Cliente
    {
        return DB::transaction(function () use ($atributos) {
            $proximoCodigo = (int) (Cliente::withTrashed()->lockForUpdate()->max('codigo') ?? 0) + 1;

            return Cliente::create(array_merge($atributos, ['codigo' => $proximoCodigo]));
        });
    }
}
