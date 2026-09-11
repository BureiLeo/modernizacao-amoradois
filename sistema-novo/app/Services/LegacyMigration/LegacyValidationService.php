<?php

namespace App\Services\LegacyMigration;

use Illuminate\Support\Facades\DB;

/**
 * Compara origem (legacy) e destino (novo) apos a migracao.
 *
 * Cada metodo retorna um array simples [ok(bool), linhas(array<string>)]
 * para o comando `legacy:validate` imprimir.
 */
class LegacyValidationService
{
    /** @return array{ok: bool, lines: array<int, string>} */
    public function contagens(): array
    {
        $pares = [
            'clientes' => ['clientes', 'clientes'],
            'produtos' => ['produtos', 'produtos'],
            'materiais' => ['materiais', 'materiais'],
            'produto_bom' => ['produto_bom', 'produto_bom'],
            'compras' => ['compras', 'compras'],
            'vendas' => ['vendas', 'vendas'],
            'venda_itens' => ['venda_itens', 'venda_itens'],
            'perdas' => ['perdas', 'perdas'],
            'investimentos' => ['investimentos', 'investimentos'],
            'caixa_movimentos' => ['caixa_movimentos', 'caixa_movimentos'],
        ];

        $ok = true;
        $lines = [];
        foreach ($pares as $label => [$legacyTable, $novaTable]) {
            $antigo = DB::connection('legacy')->table($legacyTable)->count();
            $novo = DB::table($novaTable)->count();
            $bate = $antigo === $novo;
            $ok = $ok && $bate;
            $lines[] = sprintf('%-20s antigo=%-6d novo=%-6d %s', $label, $antigo, $novo, $bate ? 'OK' : 'DIVERGENTE');
        }

        return ['ok' => $ok, 'lines' => $lines];
    }

    /** @return array{ok: bool, lines: array<int, string>} */
    public function stockMovements(): array
    {
        $vendaEsperado = DB::connection('legacy')->table('venda_consumos')->count();
        $perdaEsperado = DB::connection('legacy')->table('perda_consumos')->count();
        $ajusteEsperado = DB::connection('legacy')->table('ajustes_estoque')->count();

        $vendaEncontrado = DB::table('stock_movements')->where('type', 'venda')->count();
        $perdaEncontrado = DB::table('stock_movements')->where('type', 'perda')->count();
        $ajusteEncontrado = DB::table('stock_movements')->where('type', 'ajuste')->count();

        $ok = $vendaEsperado === $vendaEncontrado
            && $perdaEsperado === $perdaEncontrado
            && $ajusteEsperado === $ajusteEncontrado;

        return [
            'ok' => $ok,
            'lines' => [
                "VENDA esperado = {$vendaEsperado} / encontrado = {$vendaEncontrado}",
                "PERDA esperado = {$perdaEsperado} / encontrado = {$perdaEncontrado}",
                "AJUSTE esperado = {$ajusteEsperado} / encontrado = {$ajusteEncontrado}",
            ],
        ];
    }

    /** @return array{ok: bool, lines: array<int, string>} */
    public function pagamentos(): array
    {
        $vendasCount = DB::table('vendas')->count();
        $pagamentosCount = DB::table('pagamentos')->count();

        $somaTotalVendas = (float) DB::table('vendas')->sum('total');
        $somaPagamentos = (float) DB::table('pagamentos')->sum('valor');

        $diff = round($somaTotalVendas - $somaPagamentos, 2);
        $ok = $vendasCount === $pagamentosCount && abs($diff) < 0.01;

        return [
            'ok' => $ok,
            'lines' => [
                "pagamentos gerados = {$pagamentosCount} (esperado = {$vendasCount}, 1 por venda)",
                'SUM(vendas.total) = '.number_format($somaTotalVendas, 2, ',', '.').
                ' vs SUM(pagamentos.valor) = '.number_format($somaPagamentos, 2, ',', '.').
                ' | diferença = '.number_format($diff, 2, ',', '.'),
            ],
        ];
    }

    /** @return array{ok: bool, lines: array<int, string>} */
    public function financeiro(): array
    {
        $legadoTotal = (float) DB::connection('legacy')->table('venda_itens')
            ->selectRaw('SUM(qtd * preco_unit) t')->value('t');
        $novoTotal = (float) DB::table('vendas')->sum('total');
        $novoItensTotal = (float) DB::table('venda_itens')
            ->selectRaw('SUM(quantidade * preco_unitario) t')->value('t');

        $legadoCusto = (float) DB::connection('legacy')->table('venda_itens')
            ->selectRaw('SUM(qtd * custo_unit_calculado) t')->value('t');
        $novoCusto = (float) DB::table('vendas')->sum('custo_total');
        $novoItensCusto = (float) DB::table('venda_itens')
            ->selectRaw('SUM(quantidade * custo_unitario_calculado) t')->value('t');

        // Comparacao "bruta" (item a item, sem arredondamento por venda):
        // esta e a verificacao real de integridade dos dados - tem que
        // bater exatamente, senao algo foi perdido/alterado na migracao.
        $diffBrutoTotal = round($legadoTotal - $novoItensTotal, 2);
        $diffBrutoCusto = round($legadoCusto - $novoItensCusto, 2);

        // vendas.total/custo_total sao PERSISTIDOS arredondados por
        // venda (2 casas), por decisao de arquitetura (Etapa 3/4) - a
        // soma de 197 valores ja arredondados individualmente pode
        // divergir da soma bruta por uma fracao de centavo por venda.
        // Isso e esperado e NAO indica perda de dado (o item bruto bate
        // exatamente, ver acima). Tolerancia: 0,005 por venda migrada.
        $numVendas = DB::table('vendas')->count();
        $tolerancia = round(0.005 * max($numVendas, 1), 2);
        $diffPersistidoTotal = round($legadoTotal - $novoTotal, 2);
        $diffPersistidoCusto = round($legadoCusto - $novoCusto, 2);

        $ok = abs($diffBrutoTotal) < 0.01
            && abs($diffBrutoCusto) < 0.01
            && abs($diffPersistidoTotal) <= $tolerancia
            && abs($diffPersistidoCusto) <= $tolerancia;

        return [
            'ok' => $ok,
            'lines' => [
                '[bruto/integridade] SUM(qtd*preco_unit) legado = '.number_format($legadoTotal, 2, ',', '.').
                ' vs SUM(venda_itens novo) = '.number_format($novoItensTotal, 2, ',', '.').
                ' | diferença = '.number_format($diffBrutoTotal, 2, ',', '.').' (deve ser 0,00)',
                '[bruto/integridade] SUM(custo histórico) legado = '.number_format($legadoCusto, 2, ',', '.').
                ' vs SUM(venda_itens.custo novo) = '.number_format($novoItensCusto, 2, ',', '.').
                ' | diferença = '.number_format($diffBrutoCusto, 2, ',', '.').' (deve ser 0,00)',
                '[persistido/vendas.total] legado = '.number_format($legadoTotal, 2, ',', '.').
                ' vs novo = '.number_format($novoTotal, 2, ',', '.').
                ' | diferença = '.number_format($diffPersistidoTotal, 2, ',', '.').
                ' (tolerância de arredondamento por venda: '.number_format($tolerancia, 2, ',', '.').')',
                '[persistido/vendas.custo_total] legado = '.number_format($legadoCusto, 2, ',', '.').
                ' vs novo = '.number_format($novoCusto, 2, ',', '.').
                ' | diferença = '.number_format($diffPersistidoCusto, 2, ',', '.').
                ' (tolerância de arredondamento por venda: '.number_format($tolerancia, 2, ',', '.').')',
            ],
        ];
    }

    /** @return array{ok: bool, lines: array<int, string>} */
    public function caixa(): array
    {
        // Regra real do legado (helpers.php: caixa_lancar grava valor
        // ja com o sinal correto - entrada positiva, saida negativa).
        // O "saldo" e simplesmente a soma de todos os valores.
        $saldoAntigo = (float) DB::connection('legacy')->table('caixa_movimentos')->sum('valor');
        $saldoNovo = (float) DB::table('caixa_movimentos')->sum('valor');

        $diff = round($saldoAntigo - $saldoNovo, 2);
        $ok = abs($diff) < 0.01;

        return [
            'ok' => $ok,
            'lines' => [
                'saldo antigo = '.number_format($saldoAntigo, 2, ',', '.').
                ' | saldo novo = '.number_format($saldoNovo, 2, ',', '.').
                ' | diferença = '.number_format($diff, 2, ',', '.'),
            ],
        ];
    }

    /** @return array{ok: bool, lines: array<int, string>} */
    public function estoque(): array
    {
        $legado = DB::connection('legacy')->table('materiais')->orderBy('id')->get(['id', 'nome', 'estoque']);
        $ok = true;
        $lines = [];

        foreach ($legado as $m) {
            $novo = DB::table('materiais')->where('id', $m->id)->value('estoque');
            $diff = round((float) $m->estoque - (float) $novo, 3);
            $bate = abs($diff) < 0.001;
            $ok = $ok && $bate;
            if (! $bate) {
                $lines[] = sprintf('#%d %-30s antigo=%s novo=%s diff=%s DIVERGENTE', $m->id, $m->nome, $m->estoque, $novo, $diff);
            }
        }

        if ($ok) {
            $lines[] = 'Todos os '.$legado->count().' materiais conferem (diferença = 0 em todos).';
        }

        return ['ok' => $ok, 'lines' => $lines];
    }

    /** @return array{ok: bool, lines: array<int, string>} */
    public function ids(): array
    {
        $amostras = [
            ['clientes', 1],
            ['produtos', 1],
            ['vendas', 100],
        ];
        $ok = true;
        $lines = [];
        foreach ($amostras as [$tabela, $id]) {
            $existeLegado = DB::connection('legacy')->table($tabela)->where('id', $id)->exists();
            $existeNovo = DB::table($tabela)->where('id', $id)->exists();
            $bate = $existeLegado === $existeNovo;
            $ok = $ok && $bate;
            $lines[] = "{$tabela} #{$id}: legado=".($existeLegado ? 'existe' : 'nao existe').' novo='.($existeNovo ? 'existe' : 'nao existe').($bate ? ' OK' : ' DIVERGENTE');
        }

        return ['ok' => $ok, 'lines' => $lines];
    }

    /** @return array{ok: bool, lines: array<int, string>} */
    public function orfaos(): array
    {
        $lines = [];
        $ok = true;

        $checks = [
            'vendas com cliente_id inexistente' => DB::table('vendas')
                ->whereNotNull('cliente_id')
                ->whereNotIn('cliente_id', DB::table('clientes')->pluck('id'))
                ->count(),
            'venda_itens sem produto' => DB::table('venda_itens')
                ->whereNotIn('produto_id', DB::table('produtos')->pluck('id'))
                ->count(),
            'venda_itens sem venda' => DB::table('venda_itens')
                ->whereNotIn('venda_id', DB::table('vendas')->pluck('id'))
                ->count(),
            'produto_bom sem material' => DB::table('produto_bom')
                ->whereNotIn('material_id', DB::table('materiais')->pluck('id'))
                ->count(),
            'produto_bom sem produto' => DB::table('produto_bom')
                ->whereNotIn('produto_id', DB::table('produtos')->pluck('id'))
                ->count(),
            'compras sem material' => DB::table('compras')
                ->whereNotIn('material_id', DB::table('materiais')->pluck('id'))
                ->count(),
            'stock_movements sem material' => DB::table('stock_movements')
                ->whereNotIn('material_id', DB::table('materiais')->pluck('id'))
                ->count(),
        ];

        foreach ($checks as $label => $count) {
            $bate = $count === 0;
            $ok = $ok && $bate;
            $lines[] = sprintf('%-40s %d %s', $label, $count, $bate ? 'OK' : 'ENCONTRADO(S)');
        }

        return ['ok' => $ok, 'lines' => $lines];
    }

    /** @return array<int, string> */
    public function anomaliasConhecidas(): array
    {
        $semCliente = DB::table('vendas')->whereNull('cliente_id')->count();
        $precoZero = DB::table('venda_itens')->where('preco_unitario', 0)->count();
        $telDuplicado = DB::table('clientes')
            ->select('telefone')
            ->whereNotNull('telefone')
            ->where('telefone', '!=', '')
            ->groupBy('telefone')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        $ajusteFracionado = DB::table('stock_movements')
            ->where('type', 'ajuste')
            ->get(['quantity'])
            ->filter(fn ($r) => (float) $r->quantity != (int) $r->quantity)
            ->count();

        $caixaTipoInvestimentoBug = DB::table('caixa_movimentos')
            ->where('ref_tabela', 'investimentos')
            ->where('tipo', 'outro')
            ->count();

        return [
            ($semCliente > 0 ? '✓' : '✗')." vendas sem cliente ({$semCliente} encontradas)",
            ($precoZero > 0 ? '✓' : '✗')." preço zero em item de venda ({$precoZero} encontrado(s))",
            ($telDuplicado > 0 ? '✓' : '✗')." telefone duplicado entre clientes ({$telDuplicado} grupo(s))",
            ($ajusteFracionado > 0 ? '✓' : '✗')." ajustes de estoque fracionados ({$ajusteFracionado} encontrado(s))",
            ($caixaTipoInvestimentoBug > 0 ? '✓' : '✗')." bug legado: caixa_movimentos.tipo vazio em lançamentos de investimento ({$caixaTipoInvestimentoBug} encontrado(s), mapeados para 'outro')",
        ];
    }
}
