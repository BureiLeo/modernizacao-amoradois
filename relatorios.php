<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

// Seleciona mês/ano
$mesAno = $_GET['mes'] ?? date('Y-m');
list($ano, $mes) = explode('-', $mesAno);

// Dados do mês selecionado
$receitaMes = (float)($pdo->query("
  SELECT IFNULL(SUM(vi.preco_unit * vi.qtd),0) v
  FROM venda_itens vi
  JOIN vendas v ON v.id = vi.venda_id
  WHERE YEAR(v.data) = $ano AND MONTH(v.data) = $mes
")->fetch()['v'] ?? 0);

$lucroBrutoMes = (float)($pdo->query("
  SELECT IFNULL(SUM(vi.lucro_unit * vi.qtd),0) v
  FROM venda_itens vi
  JOIN vendas v ON v.id = vi.venda_id
  WHERE YEAR(v.data) = $ano AND MONTH(v.data) = $mes
")->fetch()['v'] ?? 0);

$perdasMes = (float)($pdo->query("
  SELECT IFNULL(SUM(custo_total),0) v
  FROM perdas
  WHERE YEAR(data) = $ano AND MONTH(data) = $mes
")->fetch()['v'] ?? 0);

$comprasMes = (float)($pdo->query("
  SELECT IFNULL(SUM(custo_total),0) v
  FROM compras
  WHERE YEAR(data) = $ano AND MONTH(data) = $mes
")->fetch()['v'] ?? 0);

$investimentosMes = (float)($pdo->query("
  SELECT IFNULL(SUM(valor),0) v
  FROM investimentos
  WHERE YEAR(data) = $ano AND MONTH(data) = $mes
")->fetch()['v'] ?? 0);

$lucroLiquidoMes = $lucroBrutoMes - $perdasMes;

// Quantidade de vendas e produtos vendidos
$numVendas = (int)($pdo->query("
  SELECT COUNT(DISTINCT id) v
  FROM vendas
  WHERE YEAR(data) = $ano AND MONTH(data) = $mes
")->fetchColumn() ?? 0);

$totalProdutosVendidos = (int)($pdo->query("
  SELECT IFNULL(SUM(vi.qtd), 0) v
  FROM venda_itens vi
  JOIN vendas v ON v.id = vi.venda_id
  WHERE YEAR(v.data) = $ano AND MONTH(v.data) = $mes
")->fetchColumn() ?? 0);

// Top 5 produtos mais vendidos
$topProdutos = $pdo->query("
  SELECT 
    p.nome,
    SUM(vi.qtd) as total_qtd,
    SUM(vi.preco_unit * vi.qtd) as total_receita,
    SUM(vi.lucro_unit * vi.qtd) as total_lucro
  FROM venda_itens vi
  JOIN vendas v ON v.id = vi.venda_id
  JOIN produtos p ON p.id = vi.produto_id
  WHERE YEAR(v.data) = $ano AND MONTH(v.data) = $mes
  GROUP BY p.id, p.nome
  ORDER BY total_receita DESC
  LIMIT 5
")->fetchAll();

// Resumo de vendas por dia
$vendasDia = $pdo->query("
  SELECT 
    DATE(v.data) as dia,
    COUNT(DISTINCT v.id) as num_vendas,
    SUM(vi.preco_unit * vi.qtd) as receita,
    SUM(vi.lucro_unit * vi.qtd) as lucro
  FROM vendas v
  JOIN venda_itens vi ON vi.venda_id = v.id
  WHERE YEAR(v.data) = $ano AND MONTH(v.data) = $mes
  GROUP BY DATE(v.data)
  ORDER BY dia DESC
")->fetchAll();

$mesesPt = [
  '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',
  '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
  '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro',
  '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

$title = 'Relatórios Mensais';
include __DIR__.'/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>📊 Relatórios Mensais</h2>
  <form method="get" class="d-flex gap-2">
    <input type="month" name="mes" value="<?=$mesAno?>" class="form-control" style="width:auto">
    <button type="submit" class="btn btn-primary">Filtrar</button>
  </form>
</div>

<h4 style="margin-bottom:24px;color:#64748b;font-weight:500">📅 <?=$mesesPt[$mes]?> / <?=$ano?></h4>

<!-- Resumo Financeiro -->
<section class="grid" style="margin-bottom:24px">
  <div class="card card-metric metric-green">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">💰 Receita</div>
      </div>
      <div class="metric-icon">💵</div>
    </div>
    <div class="metric-value">R$ <?=number_format($receitaMes,2,',','.')?></div>
  </div>
  
  <div class="card card-metric metric-blue">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">📈 Lucro Bruto</div>
      </div>
      <div class="metric-icon">📊</div>
    </div>
    <div class="metric-value">R$ <?=number_format($lucroBrutoMes,2,',','.')?></div>
  </div>
  
  <div class="card card-metric metric-purple">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">💎 Lucro Líquido</div>
      </div>
      <div class="metric-icon">💰</div>
    </div>
    <div class="metric-value">R$ <?=number_format($lucroLiquidoMes,2,',','.')?></div>
    <div class="metric-subtitle">Bruto − perdas</div>
  </div>
  
  <div class="card card-metric metric-red">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">📉 Perdas</div>
      </div>
      <div class="metric-icon">⚠️</div>
    </div>
    <div class="metric-value">R$ <?=number_format($perdasMes,2,',','.')?></div>
  </div>

  <div class="card card-metric metric-orange">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">🛍️ Vendas Realizadas</div>
      </div>
      <div class="metric-icon">🧾</div>
    </div>
    <div class="metric-value"><?=$numVendas?></div>
    <div class="metric-subtitle">No período</div>
  </div>

  <div class="card card-metric metric-blue">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">📦 Produtos Vendidos</div>
      </div>
      <div class="metric-icon">🎁</div>
    </div>
    <div class="metric-value"><?=$totalProdutosVendidos?></div>
    <div class="metric-subtitle">Unidades vendidas</div>
  </div>
</section>

<section class="grid" style="margin-bottom:24px">
  <div class="card card-metric metric-orange">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">🛒 Compras</div>
      </div>
      <div class="metric-icon">🧾</div>
    </div>
    <div class="metric-value">R$ <?=number_format($comprasMes,2,',','.')?></div>
  </div>
  
  <div class="card card-metric metric-indigo">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">💼 Investimentos</div>
      </div>
      <div class="metric-icon">📊</div>
    </div>
    <div class="metric-value">R$ <?=number_format($investimentosMes,2,',','.')?></div>
  </div>
  
  <div class="card card-metric metric-red">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">💸 Total Saídas</div>
      </div>
      <div class="metric-icon">💳</div>
    </div>
    <div class="metric-value">R$ <?=number_format($comprasMes + $investimentosMes + $perdasMes,2,',','.')?></div>
    <div class="metric-subtitle">Compras + invest. + perdas</div>
  </div>
  
  <div class="card card-metric <?=$resultado >= 0 ? 'metric-green' : 'metric-red'?>">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">📊 Resultado</div>
      </div>
      <div class="metric-icon"><?=$resultado >= 0 ? '✅' : '❌'?></div>
    </div>
    <?php $resultado = $receitaMes - ($comprasMes + $investimentosMes + $perdasMes); ?>
    <div class="metric-value">R$ <?=number_format($resultado,2,',','.')?></div>
    <div class="metric-subtitle">Receita − saídas</div>
  </div>
</section>

<!-- Top 5 Produtos -->
<section class="card" style="margin-bottom:24px">
  <h3>🏆 Top 5 Produtos Mais Vendidos</h3>
  <div class="table-wrapper">
    <table class="table">
      <thead>
        <tr>
          <th>Produto</th>
          <th style="text-align:right">Quantidade</th>
          <th style="text-align:right">Receita</th>
          <th style="text-align:right">Lucro</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($topProdutos as $p): ?>
        <tr>
          <td style="font-weight:500"><?=htmlspecialchars($p['nome'])?></td>
          <td style="text-align:right"><?=(int)$p['total_qtd']?></td>
          <td style="text-align:right;color:#10b981;font-weight:600">R$ <?=number_format((float)$p['total_receita'],2,',','.')?></td>
          <td style="text-align:right;color:#3b82f6;font-weight:600">R$ <?=number_format((float)$p['total_lucro'],2,',','.')?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$topProdutos): ?>
        <tr><td colspan="4" class="small">Nenhuma venda registrada neste mês.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- Vendas por Dia -->
<section class="card">
  <h3>📅 Vendas por Dia</h3>
  <div class="table-wrapper">
    <table class="table">
      <thead>
        <tr>
          <th>Data</th>
          <th style="text-align:center">Nº Vendas</th>
          <th style="text-align:right">Receita</th>
          <th style="text-align:right">Lucro</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($vendasDia as $v): ?>
        <tr>
          <td><?=date('d/m/Y', strtotime($v['dia']))?></td>
          <td style="text-align:center;font-weight:600"><?=(int)$v['num_vendas']?></td>
          <td style="text-align:right;color:#10b981">R$ <?=number_format((float)$v['receita'],2,',','.')?></td>
          <td style="text-align:right;color:#3b82f6">R$ <?=number_format((float)$v['lucro'],2,',','.')?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$vendasDia): ?>
        <tr><td colspan="4" class="small">Nenhuma venda registrada neste mês.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include __DIR__.'/layout_end.php'; ?>
