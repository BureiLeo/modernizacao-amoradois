<?php

namespace App\Services\LegacyMigration;

use App\Enums\StatusPagamento;
use App\Services\LegacyMigration\Support\LegacyValueMapper;
use Illuminate\Support\Facades\DB;

/**
 * Deriva a tabela `pagamentos` (NOVA, nao existia no legado) a partir
 * do estado ja migrado de `vendas`.
 *
 * REGRA (documentada, Etapa 4 secoes 31-32):
 * - venda paga (pago=1)    -> pagamento status=pago,     valor=total,
 *                              pago_em = data da venda (INFERIDO, pois
 *                              o legado nao guardava uma data de
 *                              pagamento separada da data da venda).
 * - venda em aberto (pago=0) -> pagamento status=pendente, valor=total,
 *                                pago_em=null.
 *
 * IDEMPOTENCIA: como pagamentos nao tem uma coluna com o ID original do
 * legado (a tabela e inteiramente nova), a idempotencia e garantida por
 * "1 pagamento por venda_id migrada": se ja existe um pagamento para a
 * venda, ele e atualizado (nao duplicado) em vez de inserir um novo.
 * Limitacao conhecida: se no futuro o app permitir varios pagamentos
 * manuais por venda ANTES desta migracao ser re-executada, esse
 * mecanismo simples poderia sobrescrever um pagamento manual - por isso
 * so deve ser rodada em bases onde pagamentos e 100% derivado daqui.
 */
class PagamentoMigrationService implements MigratesLegacyModule
{
    public function key(): string
    {
        return 'pagamentos';
    }

    public function run(bool $dryRun): ModuleMigrationResult
    {
        $warnings = [
            'pagamentos e uma tabela nova (sem equivalente legado) - todos os registros aqui sao DERIVADOS do estado pago/total de vendas, nao migrados 1:1',
        ];

        if ($dryRun) {
            // Em dry-run, `vendas` (destino) ainda esta vazia porque
            // VendaMigrationService tambem nao grava nada - simulamos a
            // contagem/pendencias a partir do legado diretamente, sem
            // depender do destino.
            $legacyVendas = DB::connection('legacy')->table('vendas')->select('id', 'pago')->get();
            $found = $legacyVendas->count();
            $migrated = $found;
            $pendentes = $legacyVendas->where('pago', 0)->count();
        } else {
            $vendas = DB::table('vendas')->select('id', 'data', 'total', 'pago', 'forma_pagamento')->orderBy('id')->get();
            $found = $vendas->count();
            $migrated = 0;

            DB::transaction(function () use ($vendas, &$migrated) {
                foreach ($vendas as $venda) {
                    $pago = (bool) $venda->pago;
                    [$forma] = LegacyValueMapper::formaPagamento($venda->forma_pagamento);

                    $existente = DB::table('pagamentos')->where('venda_id', $venda->id)->first();

                    $dados = [
                        'venda_id' => $venda->id,
                        'forma' => $forma ?? 'outro',
                        'valor' => $venda->total,
                        'status' => $pago ? StatusPagamento::Pago->value : StatusPagamento::Pendente->value,
                        'pago_em' => $pago ? $venda->data : null,
                        'observacoes' => 'Pagamento derivado automaticamente da venda #'.$venda->id.' na migracao do legado (pago_em inferido a partir da data da venda).',
                        'updated_at' => now(),
                    ];

                    if ($existente) {
                        DB::table('pagamentos')->where('id', $existente->id)->update($dados);
                    } else {
                        $dados['created_at'] = now();
                        DB::table('pagamentos')->insert($dados);
                    }
                    $migrated++;
                }
            });

            $pendentes = $vendas->where('pago', false)->count();
        }

        if ($pendentes > 0) {
            $warnings[] = "{$pendentes} venda(s) em aberto -> pagamento(s) criados com status=pendente";
        }

        return new ModuleMigrationResult(
            module: $this->key(),
            found: $found,
            migrated: $migrated,
            warnings: $warnings,
            expected: $found,
        );
    }
}
