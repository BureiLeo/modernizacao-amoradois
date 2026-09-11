<?php

namespace App\Services\Dashboard;

use App\Enums\StatusPagamento;
use App\Models\CaixaMovimento;
use App\Models\Cliente;
use App\Models\Material;
use App\Models\Pagamento;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Support\Collection;

/**
 * Consultas agregadas usadas pelo dashboard (Etapa 5 #26-#33).
 *
 * Mantido separado do componente Livewire (Etapa 5 #38) para que as
 * queries fiquem testáveis isoladamente e reutilizáveis por outras
 * telas/relatórios no futuro.
 *
 * IMPORTANTE (Etapa 5 #28/#29): nunca confundir
 *   faturamento  = total vendido no período (vendas.total), pago ou não
 *   lucro        = faturamento - custo histórico congelado (vendas.lucro_total),
 *                  sempre rotulado "estimado"
 *   saldo/caixa  = não é calculado aqui (pertence ao módulo Financeiro)
 */
class DashboardService
{
    /**
     * Faturamento do período: soma de vendas.total (independe de estar
     * pago ou não — isso é "faturado", nao "recebido").
     */
    public function faturamento(DashboardPeriod $period): float
    {
        return (float) Venda::query()
            ->whereBetween('data', [$period->start, $period->end])
            ->sum('total');
    }

    public function vendasCount(DashboardPeriod $period): int
    {
        return Venda::query()
            ->whereBetween('data', [$period->start, $period->end])
            ->count();
    }

    /**
     * Ticket médio = faturamento / número de vendas. Protegido contra
     * divisão por zero quando não há vendas no período (Etapa 5 #36).
     */
    public function ticketMedio(DashboardPeriod $period): float
    {
        $count = $this->vendasCount($period);

        if ($count === 0) {
            return 0.0;
        }

        return round($this->faturamento($period) / $count, 2);
    }

    /**
     * Lucro estimado do período, usando os custos históricos já
     * congelados em vendas.lucro_total (nunca recalculado pela BOM atual).
     */
    public function lucroEstimado(DashboardPeriod $period): float
    {
        return (float) Venda::query()
            ->whereBetween('data', [$period->start, $period->end])
            ->sum('lucro_total');
    }

    /**
     * Total de clientes cadastrados (base inteira, não filtrada por
     * período — é uma contagem de cadastro, não de atividade).
     */
    public function clientesCadastrados(): int
    {
        return Cliente::query()->count();
    }

    /**
     * Soma de pagamentos pendentes (contas a receber) — sempre "hoje",
     * não filtrado pelo período do dashboard (é uma pendência atual).
     */
    public function valorPendente(): float
    {
        return (float) Pagamento::query()
            ->where('status', StatusPagamento::Pendente->value)
            ->sum('valor');
    }

    /**
     * Saldo em caixa (histórico completo, não filtrado pelo período do
     * dashboard — é um saldo "atual", igual ao caixa.php do sistema
     * legado). Soma direta de caixa_movimentos.valor, que já é gravado
     * com o sinal correto (entrada positiva, saída negativa) pela regra
     * real do legado (helpers.php: caixa_lancar/caixa_apagar_por_ref).
     *
     * Este indicador é sensível (mostra a saúde financeira real do
     * negócio) — o dashboard permite ocultá-lo (ver #[Session] em
     * App\Livewire\Dashboard\Index::$showSaldoCaixa).
     */
    public function saldoCaixa(): float
    {
        return (float) CaixaMovimento::query()->sum('valor');
    }

    /**
     * Materiais com estoque no ou abaixo do mínimo configurado. Materiais
     * sem mínimo definido (0) não entram no alerta.
     */
    public function materiaisEstoqueBaixo(int $limit = 10): Collection
    {
        return Material::query()
            ->whereNotNull('estoque_minimo')
            ->where('estoque_minimo', '>', 0)
            ->whereColumn('estoque', '<=', 'estoque_minimo')
            ->orderBy('estoque')
            ->limit($limit)
            ->get(['id', 'nome', 'unidade_base', 'estoque', 'estoque_minimo']);
    }

    public function materiaisEstoqueBaixoCount(): int
    {
        return Material::query()
            ->whereNotNull('estoque_minimo')
            ->where('estoque_minimo', '>', 0)
            ->whereColumn('estoque', '<=', 'estoque_minimo')
            ->count();
    }

    /**
     * Série diária de faturamento no período, pronta para o gráfico
     * (Etapa 5 #30). Sempre retorna todos os dias do intervalo, mesmo
     * sem vendas (evita "buracos" no gráfico).
     */
    public function vendasPorDia(DashboardPeriod $period): Collection
    {
        $porDia = Venda::query()
            ->whereBetween('data', [$period->start, $period->end])
            ->selectRaw('DATE(data) as dia, SUM(total) as total, COUNT(*) as qtd')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $dias = collect();
        $cursor = $period->start->copy()->startOfDay();
        $fim = $period->end->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($fim)) {
            $chave = $cursor->format('Y-m-d');
            $dias->push([
                'data' => $cursor->format('d/m'),
                'total' => (float) ($porDia[$chave] ?? 0),
            ]);
            $cursor->addDay();
        }

        return $dias;
    }

    /**
     * Top produtos mais vendidos no período (Etapa 5 #31), limitado a 5.
     */
    public function produtosMaisVendidos(DashboardPeriod $period, int $limit = 5): Collection
    {
        return VendaItem::query()
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->join('produtos', 'produtos.id', '=', 'venda_itens.produto_id')
            ->whereBetween('vendas.data', [$period->start, $period->end])
            ->selectRaw('produtos.id, produtos.nome, SUM(venda_itens.quantidade) as quantidade, SUM(venda_itens.subtotal) as faturamento')
            ->groupBy('produtos.id', 'produtos.nome')
            ->orderByDesc('quantidade')
            ->limit($limit)
            ->get();
    }

    /**
     * Últimas vendas registradas (independente do período selecionado —
     * "recentes" é sempre cronológico, Etapa 5 #33).
     */
    public function vendasRecentes(int $limit = 8): Collection
    {
        return Venda::query()
            ->with(['cliente:id,nome', 'pagamentos:id,venda_id,status'])
            ->withCount('itens')
            ->orderByDesc('data')
            ->limit($limit)
            ->get();
    }
}
