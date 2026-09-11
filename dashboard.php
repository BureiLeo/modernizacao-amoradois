<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

// Dados do mês atual
$receitaMes = (float)($pdo->query("
  SELECT IFNULL(SUM(vi.preco_unit * vi.qtd),0) v
  FROM venda_itens vi
  JOIN vendas v ON v.id = vi.venda_id
  WHERE YEAR(v.data) = YEAR(CURDATE()) AND MONTH(v.data) = MONTH(CURDATE())
")->fetch()['v'] ?? 0);

$lucroBrutoMes = (float)($pdo->query("
  SELECT IFNULL(SUM(vi.lucro_unit * vi.qtd),0) v
  FROM venda_itens vi
  JOIN vendas v ON v.id = vi.venda_id
  WHERE YEAR(v.data) = YEAR(CURDATE()) AND MONTH(v.data) = MONTH(CURDATE())
")->fetch()['v'] ?? 0);

$perdasMes = (float)($pdo->query("
  SELECT IFNULL(SUM(custo_total),0) v
  FROM perdas
  WHERE YEAR(data) = YEAR(CURDATE()) AND MONTH(data) = MONTH(CURDATE())
")->fetch()['v'] ?? 0);

$lucroLiquidoMes = $lucroBrutoMes - $perdasMes;

$valorCaixa = (float)($pdo->query("
  SELECT IFNULL(SUM(valor),0) v FROM caixa_movimentos
")->fetch()['v'] ?? 0);

// Busca estoque de materiais
$materiaisEstoque = $pdo->query("
  SELECT id, nome, unidade_base, estoque, estoque_min
  FROM materiais
  ORDER BY nome
")->fetchAll();

// Quantidade de vendas e produtos vendidos no mês
$numVendas = (int)($pdo->query("
  SELECT COUNT(DISTINCT id) v
  FROM vendas
  WHERE YEAR(data) = YEAR(CURDATE()) AND MONTH(data) = MONTH(CURDATE())
")->fetchColumn() ?? 0);

$totalProdutosVendidos = (int)($pdo->query("
  SELECT IFNULL(SUM(vi.qtd), 0) v
  FROM venda_itens vi
  JOIN vendas v ON v.id = vi.venda_id
  WHERE YEAR(v.data) = YEAR(CURDATE()) AND MONTH(v.data) = MONTH(CURDATE())
")->fetchColumn() ?? 0);

$title = 'Dashboard';
include __DIR__.'/layout_start.php';
?>
<?php
  $mesAtual = date('F/Y');
  $mesesPt = [
    'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
    'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
    'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
    'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
  ];
  $mesAtual = str_replace(array_keys($mesesPt), array_values($mesesPt), $mesAtual);
?>

<h2 style="margin-bottom:20px;color:#0f172a">📊 Dashboard - <?=$mesAtual?></h2>

<section class="grid">
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
  
  <div class="card card-metric metric-indigo">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">🏦 Caixa Total</div>
      </div>
      <div class="metric-icon">💳</div>
    </div>
    <div class="metric-value">R$ <?=number_format($valorCaixa,2,',','.')?></div>
    <div class="metric-subtitle">Saldo atual</div>
  </div>

  <div class="card card-metric metric-orange">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">🛍️ Vendas Realizadas</div>
      </div>
      <div class="metric-icon">🧾</div>
    </div>
    <div class="metric-value"><?=$numVendas?></div>
    <div class="metric-subtitle">No mês atual</div>
  </div>

  <div class="card card-metric metric-blue">
    <div class="metric-header">
      <div class="metric-info">
        <div class="metric-label">📦 Produtos Vendidos</div>
      </div>
      <div class="metric-icon">🎁</div>
    </div>
    <div class="metric-value"><?=$totalProdutosVendidos?></div>
    <div class="metric-subtitle">Unidades no mês</div>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <h3>📦 Estoque de Materiais</h3>
  <div class="table-wrapper" style="overflow:auto">
    <table class="table">
      <thead>
        <tr>
          <th>Material</th>
          <th>Unidade</th>
          <th style="text-align:center">Estoque</th>
          <th style="text-align:center">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($materiaisEstoque as $m): ?>
          <?php 
            $estoque = (int)$m['estoque'];
            $minimo = (int)$m['estoque_min'];
            $status = '';
            $statusBadge = '';
            $estoqueColor = '';
            
            if ($estoque <= 0) {
              $status = 'Esgotado';
              $statusBadge = 'background:#dc2626;color:#fff;padding:4px 12px;border-radius:20px;font-size:0.85rem;font-weight:600';
              $estoqueColor = 'color:#dc2626;font-weight:700;font-size:1.1rem';
            } elseif ($estoque <= $minimo) {
              $status = 'Baixo';
              $statusBadge = 'background:#f59e0b;color:#fff;padding:4px 12px;border-radius:20px;font-size:0.85rem;font-weight:600';
              $estoqueColor = 'color:#f59e0b;font-weight:700;font-size:1.1rem';
            } else {
              $status = 'Normal';
              $statusBadge = 'background:#10b981;color:#fff;padding:4px 12px;border-radius:20px;font-size:0.85rem;font-weight:600';
              $estoqueColor = 'color:#10b981;font-weight:600;font-size:1.1rem';
            }
          ?>
          <tr>
            <td><a href="compras.php?material_id=<?=$m['id']?>" style="font-weight:500"><?=htmlspecialchars($m['nome'])?></a></td>
            <td style="color:#64748b"><?=htmlspecialchars($m['unidade_base'])?></td>
            <td style="text-align:center;<?=$estoqueColor?>"><?=$estoque?></td>
            <td style="text-align:center"><span style="<?=$statusBadge?>"><?=$status?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$materiaisEstoque): ?>
          <tr><td colspan="4" class="small">Nenhum material cadastrado.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn btn-outline-primary" href="materiais.php">📦 Ver todos os materiais</a>
    <a class="btn btn-success" href="compras_nova.php">➕ Registrar compra</a>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <h3>Acesso rápido</h3>
  <div class="quick-grid quick">
    <a href="vendas_nova_v3.php">➕ Nova venda</a>
    <a href="vendas.php">💰 Vendas</a>
    <a href="compras.php">🧾 Compras</a>
    <a href="materiais.php">📦 Materiais</a>
    <a href="perdas_novo.php">➕ Registrar perda</a>
    <a href="investimentos.php">💼 Investimentos</a>
    <a href="relatorios.php">📊 Relatórios mensais</a>
  </div>
</section>
<?php include __DIR__.'/layout_end.php'; ?>
