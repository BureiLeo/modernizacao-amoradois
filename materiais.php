<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

$msg = $_GET['msg'] ?? null;
$err = $_GET['err'] ?? null;

$sql = "
SELECT m.id, m.nome, m.unidade_base, m.estoque, m.estoque_min, m.custo_medio,
       IFNULL(ab.aberto,0) AS aberto_val
FROM materiais m
LEFT JOIN (
  SELECT material_id, SUM(custo_total + IFNULL(frete,0)) AS aberto
  FROM compras
  WHERE pago = 0
  GROUP BY material_id
) ab ON ab.material_id = m.id
ORDER BY m.nome
";
$rows = $pdo->query($sql)->fetchAll();

$title = 'Materiais';
include __DIR__.'/layout_start.php';
?>

<style>
.materiais-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  gap: 15px;
}

.materiais-header h2 {
  margin: 0;
  font-size: 28px;
  font-weight: 800;
  background: linear-gradient(135deg, #5d4e99, #7d6eb9);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.materiais-stats {
  font-size: 13px;
  color: #666;
  margin-top: 5px;
}

.btn-group {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.material-card {
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 10px;
  display: grid;
  grid-template-columns: 2.5fr 150px 150px 180px auto;
  gap: 20px;
  align-items: center;
  transition: all 0.2s;
  font-size: 14px;
}

.material-card:hover {
  border-color: #5d4e99;
  box-shadow: 0 4px 12px rgba(93, 78, 153, 0.15);
  transform: translateY(-1px);
}

.material-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.material-nome {
  font-weight: 700;
  color: #333;
  line-height: 1.3;
  font-size: 16px;
}

.material-unidade {
  display: inline-block;
  font-size: 11px;
  color: #666;
  background: #f0f0f0;
  padding: 3px 8px;
  border-radius: 4px;
  width: fit-content;
  font-weight: 600;
}

.material-stat {
  display: flex;
  flex-direction: column;
  gap: 4px;
  text-align: center;
}

.material-stat-label {
  font-size: 10px;
  color: #999;
  text-transform: uppercase;
  font-weight: 600;
  letter-spacing: 0.5px;
}

.material-stat-value {
  font-size: 18px;
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.material-acoes .btn {
  font-size: 12px;
  padding: 8px 16px;
  white-space: nowrap;
  border-radius: 6px;
  font-weight: 7: uppercase;
  letter-spacing: 0.5px;
}

.status-ok { 
  background: #d1fae5;
  color: #065f46;
}

.status-baixo { 
  background: #fed7aa;
  color: #92400e;
}

.status-esgotado { 
  background: #fee2e2;
  color: #991b1b;
}

.material-acoes {
  padding: 0 10px;
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
}

.material-acoes .btn {
  font-size: 11px;
  padding: 5px 10px;
  white-space: nowrap;
  border-radius: 6px;
  font-weight: 600;
}

.header-row {
  display: grid;
  grid-template-columns: 2.5fr 150px 150px 180px auto;
  gap: 20px;
  padding: 12px 16px;
  background: #f9fafb;
  border-radius: 8px 8px 0 0;
  margin-bottom: 0px;
  font-weight: 700;
  font-size: 11px;
  color: #666;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stats-summary {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 15px;
  margin-bottom: 25px;
}

.stat-box {
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 15px;
  text-align: center;
}

.stat-box-value {
  font-size: 28px;
  font-weight: 800;
  color: #5d4e99;
  margin-bottom: 5px;
}

.stat-box-label {
  font-size: 12px;
  color: #666;
  text-transform: uppercase;
  font-weight: 600;
  letter-spacing: 0.5px;
}

@media (max-width: 1200px) {
  .material-card {
    grid-template-columns: 1fr;
    gap: 12px;
    padding: 16px;
  }
  .header-row {
    display: none;
  }
  .stats-summary {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>

<?php if($msg): ?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?=htmlspecialchars($err)?></div><?php endif; ?>

<div class="materiais-header">
  <div>
    <h2>📦 Materiais</h2>
    <div class="materiais-stats">
      Total de <?=count($rows)?> materiais cadastrados
    </div>
  </div>
  <div class="btn-group">
    <a class="btn btn-primary" href="materiais_novo.php">➕ Novo Material</a>
    <a class="btn btn-primary" href="compras_lote.php">🛒 Lançar Compras</a>
    <a class="btn btn-secondary" href="compras.php">📄 Ver Compras</a>
  </div>
</div>

<?php
// Calcular estatísticas
$total_estoque_valor = 0;
$materiais_baixo = 0;
$materiais_esgotados = 0;

foreach ($rows as $r) {
  $total_estoque_valor += (float)$r['estoque'] * (float)$r['custo_medio'];
  
  if ((int)$r['estoque'] <= 0) {
    $materiais_esgotados++;
  } elseif ((int)$r['estoque'] <= (int)$r['estoque_min']) {
    $materiais_baixo++;
  }
}
?>

<div class="stats-summary">
  <div class="stat-box">
    <div class="stat-box-value"><?=count($rows)?></div>
    <div class="stat-box-label">Total Materiais</div>
  </div>
  <div class="stat-box">
    <div class="stat-box-value" style="color: #f59e0b;"><?=$materiais_baixo?></div>
    <div class="stat-box-label">Estoque Baixo</div>
  </div>
  <div class="stat-box">
    <div class="stat-box-value" style="color: #dc2626;"><?=$materiais_esgotados?></div>
    <div class="stat-box-label">Esgotados</div>
  </div>
  <div class="stat-box">
    <div class="stat-box-value">R$ <?=number_format($total_estoque_valor, 2, ',', '.')?></div>
    <div class="stat-box-label">Valor em Estoque</div>
  </div>
</div>

<div style="margin-bottom: 20px;">
  <input 
    type="text" 
    id="searchMateriais" 
    placeholder="🔍 Buscar material por nome..." 
    style="width: 100%; padding: 12px 18px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;"
  >
</div>

<div class="card">
  <?php if(!$rows): ?>
    <div style="text-align: center; padding: 40px; color: #999;">
      <div style="font-size: 4em; margin-bottom: 15px;">📦</div>
      <p style="font-size: 18px; margin-bottom: 10px;">Nenhum material cadastrado</p>
      <p style="font-size: 14px;">Cadastre seu primeiro material para começar!</p>
    </div>
  <?php else: ?>
    <!-- Cabeçalho -->
    <div class="header-row">
      <div>Material</div>
      <div style="text-align: center;">Estoque</div>
      <div style="text-align: center;">Custo Médio</div>
      <div style="text-align: center;">Status</div>
      <div style="text-align: right;">Ações</div>
    </div>
    
    <?php foreach ($rows as $r): 
      $estoque = (int)$r['estoque'];
      $minimo = (int)$r['estoque_min'];
      
      if ($estoque <= 0) {
        $status = 'Esgotado';
        $statusClass = 'status-esgotado';
        $statusIcon = '🔴';
      } elseif ($estoque <= $minimo) {
        $status = 'Baixo';
        $statusClass = 'status-baixo';
        $statusIcon = '⚠️';
      } else {
        $status = 'OK';
        $statusClass = 'status-ok';
        $statusIcon = '✅';
      }
      
      $valor_total = (float)$r['estoque'] * (float)$r['custo_medio'];
    ?>
      <div class="material-card">
        <div class="material-info">
          <div class="material-nome"><?=htmlspecialchars($r['nome'])?></div>
          <div class="material-unidade"><?=htmlspecialchars($r['unidade_base'])?></div>
        </div>
        
        <div class="material-stat">
          <div class="material-stat-label">Estoque</div>
          <div class="material-stat-value"><?=$estoque?></div>
          <div style="font-size: 10px; color: #999; margin-top: 2px;">min: <?=$minimo?></div>
        </div>
        
        <div class="material-stat">
          <div class="material-stat-label">Custo Médio</div>
          <div class="material-stat-value" style="font-size: 16px;">R$ <?=number_format((float)$r['custo_medio'],2,',','.')?></div>
          <?php if($valor_total > 0): ?>
            <div style="font-size: 10px; color: #999; margin-top: 2px;">
              Total: R$ <?=number_format($valor_total,2,',','.')?>
            </div>
          <?php endif; ?>
        </div>
        
        <div style="display: flex; justify-content: center;">
          <span class="material-status <?=$statusClass?>">
            <?=$statusIcon?> <?=$status?>
          </span>
        </div>
        
        <div class="material-acoes">
          <a href="materiais_ajuste.php?id=<?=$r['id']?>" class="btn btn-sm btn-success" title="Ajustar estoque">⚖️ Ajustar</a>
          <a href="materiais_editar.php?id=<?=$r['id']?>" class="btn btn-sm btn-primary">Ver Detalhes</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
document.getElementById('searchMateriais').addEventListener('input', function(e) {
  const searchTerm = e.target.value.toLowerCase().trim();
  const cards = document.querySelectorAll('.material-card');
  
  cards.forEach(card => {
    const nome = card.querySelector('.material-nome')?.textContent.toLowerCase() || '';
    const unidade = card.querySelector('.material-unidade')?.textContent.toLowerCase() || '';
    
    if (nome.includes(searchTerm) || unidade.includes(searchTerm)) {
      card.style.display = 'grid';
    } else {
      card.style.display = 'none';
    }
  });
});
</script>

<?php include __DIR__.'/layout_end.php'; ?>
