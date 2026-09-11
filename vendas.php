<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

$msg = $_GET['msg'] ?? null;
$filtro = $_GET['filtro'] ?? 'ultimas50';
$mes = $_GET['mes'] ?? null;

// Construir query com filtros
$where = "1=1";
$params = [];
$limit = "";

if ($filtro === 'ultimas50') {
  $limit = "LIMIT 50";
} elseif ($filtro === 'ultimas100') {
  $limit = "LIMIT 100";
} elseif ($filtro === 'pendentes') {
  $where = "v.pago = 0";
} elseif ($filtro === 'mes' && $mes) {
  $where = "DATE_FORMAT(v.data, '%Y-%m') = ?";
  $params[] = $mes;
} elseif ($filtro === 'mesatual') {
  $where = "DATE_FORMAT(v.data, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
}
// 'todas' não tem limite nem filtro

$sql = "
  SELECT v.id, v.data, v.obs, v.forma_pagamento, v.pago, v.cliente_id,
         c.codigo AS cliente_codigo, c.nome AS cliente_nome,
         (SELECT SUM(qtd * preco_unit) FROM venda_itens WHERE venda_id = v.id) AS total,
         (SELECT COUNT(*) FROM venda_itens WHERE venda_id = v.id) AS qtd_itens
  FROM vendas v
  LEFT JOIN clientes c ON c.id = v.cliente_id
  WHERE $where
  ORDER BY v.data DESC
  $limit
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Calcular total geral
$totalGeral = 0;
$totalPago = 0;
$totalAberto = 0;
foreach($rows as $r) {
  $valor = (float)($r['total'] ?? 0);
  $totalGeral += $valor;
  if ($r['pago']) {
    $totalPago += $valor;
  } else {
    $totalAberto += $valor;
  }
}

// Buscar meses disponíveis para o filtro
$meses = $pdo->query("
  SELECT DISTINCT DATE_FORMAT(data, '%Y-%m') AS mes
  FROM vendas
  ORDER BY mes DESC
  LIMIT 24
")->fetchAll(PDO::FETCH_COLUMN);

$title = 'Vendas';
include __DIR__.'/layout_start.php';
?>

<style>
.vendas-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
  gap: 15px;
}

.vendas-header h2 {
  margin: 0;
  font-size: 28px;
  background: linear-gradient(135deg, #5d4e99, #7d6eb9);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 10px;
}

.vendas-stats {
  font-size: 14px;
  color: #666;
  margin-top: 5px;
  font-weight: normal;
}

.btn-nova-venda {
  padding: 10px 20px;
  font-size: 15px;
  background: #5d4e99;
  color: white;
  border: none;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}

.btn-nova-venda:hover {
  background: #4a3d7a;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(93, 78, 153, 0.3);
}

.venda-card {
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  padding: 4px 8px;
  margin-bottom: 2px;
  display: grid;
  grid-template-columns: 50px 1fr 120px 100px 120px !important;
  gap: 12px;
  align-items: center;
  transition: all 0.2s;
}

.venda-card:hover {
  border-color: #5d4e99;
  box-shadow: 0 2px 6px rgba(93, 78, 153, 0.1);
}

.venda-id {
  background: linear-gradient(135deg, #5d4e99, #7d6eb9);
  color: white;
  font-weight: bold;
  font-size: 11px;
  padding: 4px 8px;
  border-radius: 4px;
  text-align: center;
}

.venda-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.venda-cliente {
  font-size: 17px;
  font-weight: 600;
  color: #333;
}

.venda-detalhes {
  display: flex;
  gap: 12px;
  font-size: 14px;
  color: #666;
}

.venda-valor {
  font-size: 21px;
  font-weight: bold;
  color: #2d7a3e;
  text-align: right;
}

.venda-pagamento {
  font-size: 14px;
  color: #666;
  text-align: center;
}

.venda-status {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: center;
  flex-direction: row;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 14px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  height: auto;
  line-height: 1.2;
}

.status-pago {
  background: #d4edda;
  color: #155724;
}

.status-aberto {
  background: #fff3cd;
  color: #856404;
}

.venda-acoes {
  text-align: right;
}

.filtros-vendas {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 6px;
  margin-bottom: 15px;
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.filtros-vendas label {
  font-weight: 600;
  font-size: 14px;
  color: #333;
  margin-right: 5px;
}

.filtro-btn {
  padding: 8px 16px;
  border: 2px solid #ddd;
  background: white;
  color: #333;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}

.filtro-btn:hover {
  border-color: #5d4e99;
  background: #f8f7fc;
}

.filtro-btn.active {
  border-color: #5d4e99;
  background: #5d4e99;
  color: white;
}

.filtros-vendas select {
  padding: 8px 12px;
  border: 2px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  background: white;
  cursor: pointer;
}

@media (max-width: 768px) {
  .venda-card {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  
  .vendas-header {
    flex-direction: column;
    align-items: stretch;
  }
  
  .filtros-vendas {
    flex-direction: column;
    align-items: stretch;
  }
  
  .filtro-btn {
    width: 100%;
    text-align: center;
  }
}
</style>

<?php if($msg): ?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div class="vendas-header">
  <div>
    <h2>🛍️ Vendas</h2>
    <div class="vendas-stats">
      📊 <?=count($rows)?> venda(s) • 
      💰 Total: <strong>R$ <?=number_format($totalGeral, 2, ',', '.')?></strong>
      <?php if($totalPago > 0): ?>
        • ✅ Pago: R$ <?=number_format($totalPago, 2, ',', '.')?>
      <?php endif; ?>
      <?php if($totalAberto > 0): ?>
        • ⏱ Em aberto: R$ <?=number_format($totalAberto, 2, ',', '.')?>
      <?php endif; ?>
    </div>
  </div>
  <a class="btn-nova-venda" href="vendas_nova_v3.php">➕ Nova Venda</a>
</div>

<!-- Filtros -->
<div class="filtros-vendas">
  <label>🔍 Filtrar:</label>
  
  <a href="?filtro=ultimas50" class="filtro-btn <?=$filtro==='ultimas50'?'active':''?>">
    Últimas 50
  </a>
  
  <a href="?filtro=ultimas100" class="filtro-btn <?=$filtro==='ultimas100'?'active':''?>">
    Últimas 100
  </a>
  
  <a href="?filtro=mesatual" class="filtro-btn <?=$filtro==='mesatual'?'active':''?>">
    Mês atual
  </a>
  
  <a href="?filtro=pendentes" class="filtro-btn <?=$filtro==='pendentes'?'active':''?>" style="background: <?=$filtro==='pendentes'?'#ff9800':'#fff3cd'?>; border-color: <?=$filtro==='pendentes'?'#ff9800':'#ffc107'?>; color: <?=$filtro==='pendentes'?'white':'#856404'?>; font-weight: 600;">
    ⏱ Pendentes
  </a>
  
  <a href="?filtro=todas" class="filtro-btn <?=$filtro==='todas'?'active':''?>">
    Todas
  </a>
  
  <div style="display: flex; align-items: center; gap: 8px;">
    <span style="font-size: 14px; color: #666;">ou por mês:</span>
    <select onchange="if(this.value) window.location.href='?filtro=mes&mes='+this.value" style="min-width: 150px;">
      <option value="">— Selecione —</option>
      <?php foreach($meses as $m): 
        $mesFormatado = DateTime::createFromFormat('Y-m', $m)->format('m/Y');
      ?>
        <option value="<?=$m?>" <?=($filtro==='mes' && $mes===$m)?'selected':''?>>
          <?=$mesFormatado?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="card">
  <?php if(!$rows): ?>
    <div style="text-align: center; padding: 40px; color: #999;">
      <div style="font-size: 4em; margin-bottom: 15px;">🛒</div>
      <p style="font-size: 18px; margin-bottom: 10px;">Nenhuma venda registrada</p>
      <p style="font-size: 14px;">Registre sua primeira venda para começar!</p>
    </div>
  <?php else: 
    foreach($rows as $r):
      $statusClass = $r['pago'] ? 'status-pago' : 'status-aberto';
      $statusTxt = $r['pago'] ? '✓ Pago' : '⏱ Em aberto';
      $total = (float)$r['total'];
      $clienteNome = $r['cliente_nome'] ?: 'Cliente não informado';
      $clienteCodigo = $r['cliente_codigo'] ? '#'.$r['cliente_codigo'] : '';
  ?>
    <div class="venda-card">
      <div class="venda-id">
        #<?=$r['id']?>
      </div>
      
      <div class="venda-info">
        <div class="venda-cliente">
          <?php if($clienteCodigo): ?>
            <span style="color: #5d4e99;"><?=$clienteCodigo?></span>
          <?php endif; ?>
          <?=htmlspecialchars($clienteNome)?>
        </div>
        <div class="venda-detalhes">
          <span>📅 <?=date('d/m/Y H:i', strtotime($r['data']))?></span>
          <span>📦 <?=$r['qtd_itens']?> <?=$r['qtd_itens']==1?'item':'itens'?></span>
          <?php if($r['obs']): ?>
            <span>💬 <?=htmlspecialchars($r['obs'])?></span>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="venda-pagamento">
        <?=htmlspecialchars($r['forma_pagamento'] ?? '—')?>
      </div>
      
      <div class="venda-valor">
        R$ <?=number_format($total, 2, ',', '.')?>
      </div>
      
      <div class="venda-status">
        <span class="status-badge <?=$statusClass?>"><?=$statusTxt?></span>
        <a href="vendas_editar.php?id=<?=$r['id']?>" class="btn btn-sm btn-secondary" style="display: inline-flex !important; align-items: center !important; height: 32px !important; padding: 6px 12px !important; margin: 0 !important;">✏️</a>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php include __DIR__.'/layout_end.php'; ?>