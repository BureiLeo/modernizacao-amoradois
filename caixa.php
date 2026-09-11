<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/helpers.php';
auth_require();
$pdo = db();

$msg = $_GET['msg'] ?? null;
$err = $_GET['err'] ?? null;

// Processa ajuste de caixa (antes de qualquer saída)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $valor = (float)str_replace(',', '.', trim($_POST['valor'] ?? '0'));
    $desc  = $_POST['descricao'] ?: null;
    if ($valor == 0.0) throw new Exception('Informe um valor diferente de zero');
    caixa_ajuste(new DateTime(), $valor, $desc);
    header('Location: caixa.php?msg=Ajuste lançado');
    exit;
  } catch (Throwable $e) { $err = $e->getMessage(); }
}

// filtros
$tipo   = $_GET['tipo']   ?? 'todos'; // todos | VENDA | COMPRA | AJUSTE
$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fim    = $_GET['fim']    ?? date('Y-m-d');
$ordem  = $_GET['ordem']  ?? 'data_desc'; // data_desc, data_asc, valor_desc, valor_asc, tipo_asc, tipo_desc

// Define ordenação SQL
$orderSql = 'data DESC, id DESC';
switch($ordem) {
  case 'data_asc':   $orderSql = 'data ASC, id ASC'; break;
  case 'data_desc':  $orderSql = 'data DESC, id DESC'; break;
  case 'valor_asc':  $orderSql = 'valor ASC, data DESC'; break;
  case 'valor_desc': $orderSql = 'valor DESC, data DESC'; break;
  case 'tipo_asc':   $orderSql = 'tipo ASC, data DESC'; break;
  case 'tipo_desc':  $orderSql = 'tipo DESC, data DESC'; break;
}

$where = [];
$params = [];
$where[] = 'DATE(data) BETWEEN ? AND ?';
$params[] = $inicio; $params[] = $fim;
if ($tipo !== 'todos') { $where[] = 'tipo = ?'; $params[] = $tipo; }

$sql = "
  SELECT id, data, tipo, ref_tabela, ref_id, descricao, valor
  FROM caixa_movimentos
  WHERE ".implode(' AND ', $where)."
  ORDER BY $orderSql
  LIMIT 500
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$mapVendas = [];
$mapCompras = [];
if ($rows) {
  $vIds = [];
  $cIds = [];
  foreach ($rows as $r) {
    if (($r['tipo'] ?? '') === 'VENDA' && ($r['ref_tabela'] ?? '') === 'vendas' && (int)$r['ref_id'] > 0) {
      $vIds[] = (int)$r['ref_id'];
    } elseif (($r['tipo'] ?? '') === 'COMPRA' && ($r['ref_tabela'] ?? '') === 'compras' && (int)$r['ref_id'] > 0) {
      $cIds[] = (int)$r['ref_id'];
    }
  }
  if ($vIds) {
    $ph = implode(',', array_fill(0, count($vIds), '?'));
    $qv = $pdo->prepare("SELECT v.id,
                                v.canal, v.obs,
                                GROUP_CONCAT(CONCAT(p.nome,' x',vi.qtd) ORDER BY p.nome SEPARATOR ', ') AS itens
                           FROM vendas v
                      LEFT JOIN venda_itens vi ON vi.venda_id = v.id
                      LEFT JOIN produtos p ON p.id = vi.produto_id
                          WHERE v.id IN ($ph)
                       GROUP BY v.id");
    $qv->execute($vIds);
    foreach ($qv->fetchAll() as $vr) { $mapVendas[(int)$vr['id']] = $vr; }
  }
  if ($cIds) {
    $ph = implode(',', array_fill(0, count($cIds), '?'));
    $qc = $pdo->prepare("SELECT c.id, c.material_id, m.nome AS material,
                                c.qtd_embalagem, c.qtd_base, c.custo_total, IFNULL(c.frete,0) AS frete
                           FROM compras c
                           JOIN materiais m ON m.id = c.material_id
                          WHERE c.id IN ($ph)");
    $qc->execute($cIds);
    foreach ($qc->fetchAll() as $cr) { $mapCompras[(int)$cr['id']] = $cr; }
  }
}

$tot_entradas = 0.0; $tot_saidas = 0.0;
foreach ($rows as $r) {
  $v = (float)$r['valor'];
  if ($v >= 0) $tot_entradas += $v; else $tot_saidas += $v;
}
$saldo = $tot_entradas + $tot_saidas;

// Calcula o total em caixa (TUDO, sem filtros)
$sqlTotal = "SELECT SUM(valor) as total FROM caixa_movimentos";
$stTotal = $pdo->query($sqlTotal);
$totalCaixa = (float)($stTotal->fetchColumn() ?: 0);

$title = 'Fluxo de caixa';
include __DIR__.'/layout_start.php';
?>

<style>
.caixa-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
  gap: 15px;
}

.caixa-header h2 {
  margin: 0;
  font-size: 28px;
  background: linear-gradient(135deg, #10b981, #059669);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 10px;
}

.caixa-header .subtitle {
  margin: 5px 0 0 0;
  color: #666;
  font-size: 14px;
  font-weight: normal;
}

.btn-ajuste-small {
  padding: 8px 16px;
  font-size: 14px;
  background: #5d4e99;
  color: white;
  border: none;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}

.btn-ajuste-small:hover {
  background: #4a3d7a;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(93, 78, 153, 0.3);
}

.filtros-caixa {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin-bottom: 20px;
}

.filtros-caixa form {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 15px;
  align-items: end;
}

@media (max-width: 768px) {
  .filtros-caixa form {
    grid-template-columns: 1fr;
  }
}

.stats-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 15px;
  margin-bottom: 20px;
}

@media (max-width: 768px) {
  .stats-cards {
    grid-template-columns: 1fr;
  }
}

.stat-card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  border-left: 4px solid;
}

.stat-card.entradas {
  border-left-color: #10b981;
}

.stat-card.saidas {
  border-left-color: #ef4444;
}

.stat-card.saldo {
  border-left-color: #3b82f6;
}

.stat-card.total {
  border-left-color: #8b5cf6;
  background: linear-gradient(135deg, #f8f7fc 0%, #ffffff 100%);
}

.stat-label {
  font-size: 14px;
  color: #666;
  margin-bottom: 8px;
  font-weight: 500;
}

.stat-value {
  font-size: 28px;
  font-weight: bold;
  line-height: 1;
}

.stat-card.entradas .stat-value {
  color: #10b981;
}

.stat-card.saidas .stat-value {
  color: #ef4444;
}

.stat-card.saldo .stat-value {
  color: #3b82f6;
}

.stat-card.total .stat-value {
  color: #8b5cf6;
  font-size: 32px;
}

.movimentos-list {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  overflow: hidden;
}

.movimento-header {
  background: #f8f9fa;
  padding: 10px 12px;
  border-bottom: 2px solid #e0e0e0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.ordenar-links {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  font-size: 13px;
}

.ordenar-links a {
  color: #5d4e99;
  text-decoration: none;
  font-weight: 600;
  transition: color 0.2s;
}

.ordenar-links a:hover {
  color: #4a3d7a;
}

.movimento-table-header {
  display: grid;
  grid-template-columns: 110px 70px 60px 1fr 100px;
  gap: 10px;
  padding: 8px 12px;
  background: #5d4e99;
  color: white;
  font-size: 12px;
  font-weight: 600;
  border-bottom: 2px solid #4a3d7a;
}

.movimento-item {
  padding: 4px 12px;
  border-bottom: 1px solid #e8e8e8;
  display: grid;
  grid-template-columns: 110px 70px 60px 1fr 100px;
  gap: 10px;
  align-items: center;
  transition: background 0.05s;
  font-size: 13px;
  min-height: 32px;
}

.movimento-item:nth-child(even) {
  background: #fafafa;
}

.movimento-item:hover {
  background: #f0f0ff !important;
}

.movimento-item:last-child {
  border-bottom: none;
}

.movimento-data {
  font-size: 12px;
  color: #333;
  line-height: 1.2;
  white-space: nowrap;
}

.movimento-tipo {
  display: inline-block;
  padding: 2px 6px;
  border-radius: 3px;
  font-size: 10px;
  font-weight: 600;
  text-align: center;
  white-space: nowrap;
}

.movimento-tipo.VENDA {
  background: #d1fae5;
  color: #065f46;
}

.movimento-tipo.COMPRA {
  background: #fee2e2;
  color: #991b1b;
}

.movimento-tipo.AJUSTE {
  background: #dbeafe;
  color: #1e40af;
}

.movimento-id {
  font-size: 12px;
  color: #5d4e99;
  font-weight: 600;
  white-space: nowrap;
}

.movimento-id a {
  color: #5d4e99;
  text-decoration: none;
}

.movimento-id a:hover {
  text-decoration: underline;
}

.movimento-descricao {
  font-size: 13px;
  color: #333;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.movimento-valor {
  text-align: right;
  font-size: 14px;
  font-weight: bold;
  white-space: nowrap;
}

.movimento-valor.positivo {
  color: #10b981;
}

.movimento-valor.negativo {
  color: #ef4444;
}

.ajuste-form {
  background: white;
  padding: 25px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin-top: 20px;
  border: 2px solid #e9ecef;
}

.ajuste-form h3 {
  margin: 0 0 20px 0;
  color: #333;
  font-size: 20px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 15px;
  margin-bottom: 20px;
}

@media (max-width: 768px) {
  .movimento-table-header {
    display: none;
  }
  
  .movimento-item {
    grid-template-columns: 1fr;
    gap: 4px;
    padding: 8px 12px;
  }
  
  .movimento-data {
    font-size: 11px;
    font-weight: 600;
  }
  
  .movimento-descricao {
    white-space: normal;
  }
  
  .movimento-valor {
    text-align: left;
    font-size: 16px;
  }
  
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .ordenar-links {
    width: 100%;
    font-size: 11px;
  }
  
  .movimento-header {
    padding: 8px 10px;
  }
  
  .movimento-header h3 {
    font-size: 14px !important;
  }
}
</style>

<?php
// Função helper para gerar links de ordenação
function ordenar_link($campo, $ordemAtual, $tipo, $inicio, $fim) {
  $icone = '';
  $novaOrdem = $campo.'_desc';
  
  if ($ordemAtual === $campo.'_desc') {
    $icone = ' ▼';
    $novaOrdem = $campo.'_asc';
  } elseif ($ordemAtual === $campo.'_asc') {
    $icone = ' ▲';
    $novaOrdem = $campo.'_desc';
  }
  
  return "caixa.php?tipo=".urlencode($tipo)."&inicio=".urlencode($inicio)."&fim=".urlencode($fim)."&ordem=".urlencode($novaOrdem).$icone;
}
?>
<?php if($msg): ?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?=htmlspecialchars($err)?></div><?php endif; ?>

<div class="caixa-header">
  <div>
    <h2>💰 Fluxo de Caixa</h2>
    <p class="subtitle">Controle completo de entradas e saídas financeiras</p>
  </div>
  <a href="#ajuste" class="btn-ajuste-small">➕ Novo Ajuste</a>
</div>

<!-- Filtros -->
<div class="filtros-caixa">
  <form method="get">
    <div>
      <label>Data Início</label>
      <input type="date" name="inicio" value="<?=htmlspecialchars($inicio)?>" required>
    </div>
    <div>
      <label>Data Fim</label>
      <input type="date" name="fim" value="<?=htmlspecialchars($fim)?>" required>
    </div>
    <div>
      <label>Filtrar por Tipo</label>
      <select name="tipo">
        <option value="todos"  <?=$tipo==='todos'?'selected':''?>>📊 Todos</option>
        <option value="VENDA"  <?=$tipo==='VENDA'?'selected':''?>>📥 Vendas (entradas)</option>
        <option value="COMPRA" <?=$tipo==='COMPRA'?'selected':''?>>📤 Compras (saídas)</option>
        <option value="AJUSTE" <?=$tipo==='AJUSTE'?'selected':''?>>⚙️ Ajustes</option>
      </select>
    </div>
    <div>
      <button type="submit" class="btn btn-primary" style="width: 100%;">🔍 Aplicar Filtros</button>
    </div>
  </form>
</div>

<!-- Cards de Estatísticas -->
<div class="stats-cards">
  <div class="stat-card entradas">
    <div class="stat-label">📥 Entradas</div>
    <div class="stat-value">R$ <?=number_format($tot_entradas,2,',','.')?></div>
  </div>
  
  <div class="stat-card saidas">
    <div class="stat-label">📤 Saídas</div>
    <div class="stat-value">R$ <?=number_format(abs($tot_saidas),2,',','.')?></div>
  </div>
  
  <div class="stat-card saldo">
    <div class="stat-label">📊 Saldo do Período</div>
    <div class="stat-value">R$ <?=number_format($saldo,2,',','.')?></div>
  </div>
  
  <div class="stat-card total">
    <div class="stat-label">💎 Total em Caixa</div>
    <div class="stat-value">R$ <?=number_format($totalCaixa,2,',','.')?></div>
    <div style="font-size: 12px; color: #999; margin-top: 4px;">Saldo geral (todas as movimentações)</div>
  </div>
</div>

<!-- Lista de Movimentos -->
<div class="movimentos-list">
  <div class="movimento-header">
    <h3 style="margin: 0; font-size: 16px;">📋 <?=count($rows)?> movimentações</h3>
    <div class="ordenar-links">
      <span style="color: #666; font-size: 12px;">Ordenar:</span>
      <a href="<?=ordenar_link('data', $ordem, $tipo, $inicio, $fim)?>">
        Data<?php if(strpos($ordem,'data')!==false) echo (strpos($ordem,'desc')!==false?' ▼':' ▲'); ?>
      </a>
      <a href="<?=ordenar_link('tipo', $ordem, $tipo, $inicio, $fim)?>">
        Tipo<?php if(strpos($ordem,'tipo')!==false) echo (strpos($ordem,'desc')!==false?' ▼':' ▲'); ?>
      </a>
      <a href="<?=ordenar_link('valor', $ordem, $tipo, $inicio, $fim)?>">
        Valor<?php if(strpos($ordem,'valor')!==false) echo (strpos($ordem,'desc')!==false?' ▼':' ▲'); ?>
      </a>
    </div>
  </div>
  
  <?php if(!$rows): ?>
    <div style="text-align: center; padding: 40px 20px; color: #999;">
      <div style="font-size: 3em; margin-bottom: 10px;">📭</div>
      <p style="font-size: 15px; margin: 0;">Nenhuma movimentação encontrada</p>
    </div>
  <?php else: ?>
    <!-- Cabeçalho da tabela -->
    <div class="movimento-table-header">
      <div>DATA/HORA</div>
      <div>TIPO</div>
      <div>ID</div>
      <div>DESCRIÇÃO</div>
      <div style="text-align: right;">VALOR</div>
    </div>
    
    <?php foreach ($rows as $r): 
      $valor = (float)$r['valor'];
      $valorClass = $valor >= 0 ? 'positivo' : 'negativo';
      $tipoClass = strtoupper($r['tipo']);
      
      // Montar descrição resumida
      $descricao = '';
      if (($r['tipo'] ?? '')==='VENDA') {
        $vd = $mapVendas[(int)$r['ref_id']] ?? null;
        $descricao = $vd ? htmlspecialchars($vd['itens'] ?: 'Venda') : 'Venda';
      } elseif (($r['tipo'] ?? '')==='COMPRA') {
        $cd = $mapCompras[(int)$r['ref_id']] ?? null;
        $descricao = $cd ? htmlspecialchars($cd['material']) : 'Compra';
      } else {
        $descricao = htmlspecialchars($r['descricao'] ?? 'Ajuste');
      }
    ?>
      <div class="movimento-item">
        <div class="movimento-data">
          <?=date('d/m/Y H:i', strtotime($r['data']))?>
        </div>
        
        <div>
          <span class="movimento-tipo <?=$tipoClass?>">
            <?php
              if ($tipoClass === 'VENDA') echo '📥 VENDA';
              elseif ($tipoClass === 'COMPRA') echo '📤 COMPRA';
              elseif ($tipoClass === 'AJUSTE') echo '⚙️ AJUSTE';
              else echo htmlspecialchars($tipoClass);
            ?>
          </span>
        </div>
        
        <div class="movimento-id">
          <?php if ($r['ref_id']): ?>
            <?php if (($r['tipo'] ?? '')==='VENDA'): ?>
              <a href="vendas_editar.php?id=<?= (int)$r['ref_id'] ?>">#<?= (int)$r['ref_id'] ?></a>
            <?php elseif (($r['tipo'] ?? '')==='COMPRA'): ?>
              <?php $cd = $mapCompras[(int)$r['ref_id']] ?? null; ?>
              <?php if ($cd): ?>
                <a href="compras.php?material_id=<?= (int)$cd['material_id'] ?>">#<?= (int)$r['ref_id'] ?></a>
              <?php else: ?>
                #<?= (int)$r['ref_id'] ?>
              <?php endif; ?>
            <?php else: ?>
              #<?= (int)$r['ref_id'] ?>
            <?php endif; ?>
          <?php else: ?>
            —
          <?php endif; ?>
        </div>
        
        <div class="movimento-descricao" title="<?=$descricao?>">
          <?=$descricao?>
        </div>
        
        <div class="movimento-valor <?=$valorClass?>">
          <?=$valor >= 0 ? '+' : ''?>R$ <?=number_format(abs($valor),2,',','.')?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Formulário de Novo Ajuste -->
<a id="ajuste"></a>
<div class="ajuste-form">
  <h3>⚙️ Novo Ajuste de Caixa</h3>
  <?php if ($err): ?><div class="alert alert-danger"><?=htmlspecialchars($err)?></div><?php endif; ?>
  
  <form method="post">
    <div class="form-row">
      <div>
        <label>Valor *</label>
        <input type="text" name="valor" placeholder="Ex: 100,00 ou -50,00" required 
               style="font-size: 16px; padding: 12px;">
        <small style="display: block; margin-top: 5px; color: #666;">
          💡 Positivo = entrada | Negativo = saída
        </small>
      </div>
      <div>
        <label>Descrição</label>
        <input type="text" name="descricao" placeholder="Motivo do ajuste (opcional)" 
               style="padding: 12px;">
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px;">
      ✅ Lançar Ajuste
    </button>
  </form>
</div>

<?php include __DIR__.'/layout_end.php'; ?>
