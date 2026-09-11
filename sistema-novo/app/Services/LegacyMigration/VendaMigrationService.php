<?php

namespace App\Services\LegacyMigration;

use App\Enums\VendaStatus;
use App\Services\LegacyMigration\Support\AutoIncrementResetter;
use App\Services\LegacyMigration\Support\LegacyValueMapper;
use Illuminate\Support\Facades\DB;

/**
 * Migra `legacy`.vendas + `legacy`.venda_itens -> vendas + venda_itens.
 *
 * DECISOES DOCUMENTADAS:
 * - subtotal/total sao CALCULADOS a partir dos itens historicos
 *   (SUM(qtd*preco_unit) do legado) porque o legado nunca persistia
 *   um total no cabecalho da venda. desconto = 0 (legado nao tinha
 *   desconto de cabecalho).
 * - custo_total/lucro_total usam os valores JA CONGELADOS em
 *   venda_itens (custo_unit_calculado/lucro_unit do legado) - NAO
 *   recalculados pela BOM atual.
 * - status = 'concluida' para todas (o legado nao tinha conceito de
 *   cancelamento/estorno de venda).
 * - user_id = null (legado nao registrava qual usuario fez a venda).
 * - Item com preco_unit = 0 (ex.: venda_itens legado #110) e preservado
 *   exatamente e reportado como anomalia conhecida, nao como erro.
 * - venda_itens do legado nao tem timestamp proprio: herda o
 *   created_at da venda "pai".
 */
class VendaMigrationService implements MigratesLegacyModule
{
    public function key(): string
    {
        return 'vendas';
    }

    public function run(bool $dryRun): ModuleMigrationResult
    {
        $vendas = DB::connection('legacy')->table('vendas')->orderBy('id')->get();
        $itensPorVenda = DB::connection('legacy')->table('venda_itens')->orderBy('id')->get()->groupBy('venda_id');

        $warnings = [];
        $vendasMigradas = 0;
        $itensMigrados = 0;
        $totalItens = 0;

        $semCliente = $vendas->whereNull('cliente_id')->count();
        if ($semCliente > 0) {
            $warnings[] = "ANOMALIA CONHECIDA PRESERVADA: {$semCliente} vendas sem cliente_id (mantidas com cliente_id=null, nenhum cliente generico foi criado)";
        }

        foreach ($vendas as $venda) {
            $itens = $itensPorVenda->get($venda->id, collect());
            $totalItens += $itens->count();

            foreach ($itens as $item) {
                if ((float) $item->preco_unit === 0.0) {
                    $warnings[] = "ANOMALIA CONHECIDA PRESERVADA: venda_itens #{$item->id} (venda #{$venda->id}) com preco_unit = 0.00";
                }
            }
        }

        if (! $dryRun) {
            DB::transaction(function () use ($vendas, $itensPorVenda, &$vendasMigradas, &$itensMigrados, &$warnings) {
                foreach ($vendas as $venda) {
                    $itens = $itensPorVenda->get($venda->id, collect());

                    $subtotal = 0.0;
                    $custoTotal = 0.0;
                    $lucroTotal = 0.0;
                    foreach ($itens as $item) {
                        $subtotal += $item->qtd * $item->preco_unit;
                        $custoTotal += $item->qtd * $item->custo_unit_calculado;
                        $lucroTotal += $item->qtd * $item->lucro_unit;
                    }

                    [$forma, $warning] = LegacyValueMapper::formaPagamento($venda->forma_pagamento);
                    if ($warning) {
                        $warnings[] = "venda #{$venda->id}: {$warning}";
                    }

                    DB::table('vendas')->updateOrInsert(
                        ['id' => $venda->id],
                        [
                            'data' => $venda->data,
                            'cliente_id' => $venda->cliente_id,
                            'user_id' => null,
                            'subtotal' => round($subtotal, 2),
                            'desconto' => 0,
                            'total' => round($subtotal, 2),
                            'custo_total' => round($custoTotal, 2),
                            'lucro_total' => round($lucroTotal, 2),
                            'forma_pagamento' => $forma,
                            'pago' => (bool) $venda->pago,
                            'canal' => $venda->canal,
                            'observacoes' => $venda->obs,
                            'status' => VendaStatus::Concluida->value,
                            'created_at' => $venda->created_at,
                            'updated_at' => $venda->created_at,
                        ]
                    );
                    $vendasMigradas++;

                    foreach ($itens as $item) {
                        DB::table('venda_itens')->updateOrInsert(
                            ['id' => $item->id],
                            [
                                'venda_id' => $item->venda_id,
                                'produto_id' => $item->produto_id,
                                'produto_variacao_id' => null,
                                'quantidade' => $item->qtd,
                                'preco_unitario' => $item->preco_unit,
                                'custo_unitario_calculado' => $item->custo_unit_calculado,
                                'lucro_unitario' => $item->lucro_unit,
                                'subtotal' => round($item->qtd * $item->preco_unit, 2),
                                'created_at' => $venda->created_at,
                                'updated_at' => $venda->created_at,
                            ]
                        );
                        $itensMigrados++;
                    }
                }
            });

            AutoIncrementResetter::apply('vendas', $vendas->max('id'));
            AutoIncrementResetter::apply('venda_itens', $itensPorVenda->flatten(1)->max('id'));
        } else {
            $vendasMigradas = $vendas->count();
            $itensMigrados = $totalItens;
        }

        $warnings[] = "venda_itens migrados junto: {$itensMigrados}/{$totalItens}";

        return new ModuleMigrationResult(
            module: $this->key(),
            found: $vendas->count(),
            migrated: $vendasMigradas,
            warnings: $warnings,
            expected: config('legacy_migration.expected_counts.vendas'),
        );
    }
}
