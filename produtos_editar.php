<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

$produto_id = (int)($_GET['id'] ?? 0);
if ($produto_id <= 0) { header('Location: produtos.php'); exit; }

$prod = $pdo->prepare("SELECT * FROM produtos WHERE id=?");
$prod->execute([$produto_id]);
$produto = $prod->fetch();
if (!$produto) { header('Location: produtos.php'); exit; }

$err = null;
$msg = null;

// Atualizar produto
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['atualizar'])) {
  try {
    $st = $pdo->prepare("UPDATE produtos SET nome=?, sku=?, preco_sugerido=? WHERE id=?");
    $st->execute([
      $_POST['nome'],
      $_POST['sku'] ?: null,
      (float)str_replace(',', '.', $_POST['preco_sugerido']),
      $produto_id
    ]);
    $msg = 'Produto atualizado com sucesso!';
    // Recarregar dados
    $prod->execute([$produto_id]);
    $produto = $prod->fetch();
  } catch (Throwable $e) { $err = $e->getMessage(); }
}

// Adicionar/atualizar insumo BOM (único)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_bom'])) {
  try {
    $st = $pdo->prepare("
      INSERT INTO produto_bom (produto_id, material_id, qtd_por_unidade)
      VALUES (?,?,?)
      ON DUPLICATE KEY UPDATE qtd_por_unidade = VALUES(qtd_por_unidade)
    ");
    $st->execute([
      $produto_id,
      (int)$_POST['material_id'],
      (float)str_replace(',', '.', $_POST['qtd'])
    ]);
    $msg = 'Insumo adicionado/atualizado!';
  } catch (Throwable $e){ $err = $e->getMessage(); }
}

// Adicionar múltiplos insumos BOM
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_bom_multi'])) {
  try {
    $materiais_selecionados = $_POST['materiais'] ?? [];
    $adicionados = 0;
    
    $st = $pdo->prepare("
      INSERT INTO produto_bom (produto_id, material_id, qtd_por_unidade)
      VALUES (?,?,?)
      ON DUPLICATE KEY UPDATE qtd_por_unidade = VALUES(qtd_por_unidade)
    ");
    
    foreach($materiais_selecionados as $material_id) {
      $qtd_key = 'qtd_' . $material_id;
      $qtd = $_POST[$qtd_key] ?? '';
      
      if ($qtd !== '' && $qtd !== null) {
        $qtd_float = (float)str_replace(',', '.', $qtd);
        if ($qtd_float > 0) {
          $st->execute([
            $produto_id,
            (int)$material_id,
            $qtd_float
          ]);
          $adicionados++;
        }
      }
    }
    
    if ($adicionados > 0) {
      $msg = $adicionados === 1 ? '1 material adicionado!' : "$adicionados materiais adicionados!";
    } else {
      $err = 'Selecione materiais e defina as quantidades.';
    }
  } catch (Throwable $e){ $err = $e->getMessage(); }
}

// Remover insumo BOM
if (isset($_GET['del_bom'])) {
  $del = (int)$_GET['del_bom'];
  $pdo->prepare("DELETE FROM produto_bom WHERE id=? AND produto_id=?")->execute([$del, $produto_id]);
  header("Location: produtos_editar.php?id=".$produto_id."&msg=Insumo removido");
  exit;
}

// Excluir produto
if (isset($_POST['excluir'])) {
  try {
    $pdo->prepare("DELETE FROM produto_bom WHERE produto_id=?")->execute([$produto_id]);
    $pdo->prepare("DELETE FROM produtos WHERE id=?")->execute([$produto_id]);
    header('Location: produtos.php?msg=Produto excluído');
    exit;
  } catch (Throwable $e) { $err = $e->getMessage(); }
}

// Buscar materiais disponíveis
$materiais = $pdo->query("SELECT id, nome FROM materiais ORDER BY nome")->fetchAll();

// Buscar itens do BOM
$itens = $pdo->prepare("
  SELECT pb.*, m.nome, m.custo_medio, (pb.qtd_por_unidade * m.custo_medio) as custo_parcial
  FROM produto_bom pb
  JOIN materiais m ON m.id = pb.material_id
  WHERE pb.produto_id = ?
  ORDER BY m.nome
");
$itens->execute([$produto_id]);
$rows = $itens->fetchAll();

$custo_unit = 0;
foreach ($rows as $r) $custo_unit += (float)$r['custo_parcial'];
$margem = (float)$produto['preco_sugerido'] - $custo_unit;

$title = 'Editar Produto: '.$produto['nome'];
include __DIR__.'/layout_start.php';
?>

<style>
.produto-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.produto-header h2 {
  margin: 0;
  font-size: 24px;
  font-weight: 700;
  color: #333;
}

.btn-group {
  display: flex;
  gap: 10px;
}

.tabs {
  display: flex;
  gap: 10px;
  border-bottom: 2px solid #e0e0e0;
  margin-bottom: 20px;
}

.tab {
  padding: 10px 20px;
  border: none;
  background: transparent;
  color: #666;
  font-weight: 600;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  transition: all 0.2s;
}

.tab.active {
  color: #5d4e99;
  border-bottom-color: #5d4e99;
}

.tab:hover {
  color: #5d4e99;
}

.tab-content {
  display: none;
}

.tab-content.active {
  display: block;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 15px;
  padding: 15px;
  background: #f9f9f9;
  border-radius: 8px;
  margin-bottom: 20px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.info-label {
  font-size: 11px;
  color: #666;
  text-transform: uppercase;
  font-weight: 600;
}

.info-value {
  font-size: 18px;
  font-weight: 700;
  color: #333;
}

.info-value.success {
  color: #16a34a;
}

.bom-item {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr auto;
  gap: 10px;
  padding: 4px 10px;
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  margin-bottom: 2px;
  align-items: center;
  font-size: 13px;
}

.bom-item:nth-child(even) {
  background: #f9f9f9;
}

.bom-header {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr auto;
  gap: 10px;
  padding: 6px 10px;
  font-weight: 600;
  font-size: 10px;
  color: #666;
  text-transform: uppercase;
  border-bottom: 2px solid #e0e0e0;
  margin-bottom: 5px;
}

.btn-danger {
  background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
  color: white;
}

.btn-danger:hover {
  background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
}
</style>

<?php if($err): ?><div class="alert alert-danger"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if($msg): ?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div class="produto-header">
  <h2>✏️ Editar Produto</h2>
  <div class="btn-group">
    <a href="produtos.php" class="btn btn-secondary">← Voltar</a>
  </div>
</div>

<!-- Resumo rápido -->
<div class="info-grid">
  <div class="info-item">
    <div class="info-label">💰 Preço Sugerido</div>
    <div class="info-value">R$ <?=number_format((float)$produto['preco_sugerido'],2,',','.')?></div>
  </div>
  <div class="info-item">
    <div class="info-label">📊 Custo Unitário</div>
    <div class="info-value">R$ <?=number_format($custo_unit,2,',','.')?></div>
  </div>
  <div class="info-item">
    <div class="info-label">💚 Margem</div>
    <div class="info-value success">R$ <?=number_format($margem,2,',','.')?></div>
  </div>
</div>

<!-- Tabs -->
<div class="tabs">
  <button class="tab active" onclick="switchTab('dados')">📝 Dados do Produto</button>
  <button class="tab" onclick="switchTab('bom')">📦 BOM - Materiais (<?=count($rows)?>)</button>
  <button class="tab" onclick="switchTab('perigo')">⚠️ Zona de Perigo</button>
</div>

<!-- Tab: Dados do Produto -->
<div id="tab-dados" class="tab-content active">
  <form class="card" method="post" autocomplete="off">
    <h3>Informações do Produto</h3>
    
    <label>Nome do Produto</label>
    <input name="nome" value="<?=htmlspecialchars($produto['nome'])?>" required>

    <label>SKU (Código)</label>
    <input name="sku" value="<?=htmlspecialchars($produto['sku'] ?? '')?>">

    <label>Preço Sugerido (R$)</label>
    <input name="preco_sugerido" value="<?=number_format((float)$produto['preco_sugerido'],2,',','.')?>" placeholder="ex: 29,90" required>

    <button type="submit" name="atualizar">💾 Salvar Alterações</button>
  </form>
</div>

<!-- Tab: BOM -->
<div id="tab-bom" class="tab-content">
  <div class="card">
    <h3>📦 Lista de Materiais (BOM)</h3>
    <p style="color: #666; font-size: 13px; margin-bottom: 20px;">
      Define quais materiais e em que quantidade são necessários para produzir uma unidade deste produto.
    </p>

    <?php if($rows): ?>
      <div class="bom-header">
        <div>Material</div>
        <div>Qtd/Unidade</div>
        <div>Custo Médio</div>
        <div>Custo Parcial</div>
        <div></div>
      </div>
      <?php foreach($rows as $r): ?>
        <div class="bom-item">
          <div style="font-weight: 600;"><?=htmlspecialchars($r['nome'])?></div>
          <div><?=number_format((float)$r['qtd_por_unidade'],4,',','.')?></div>
          <div>R$ <?=number_format((float)$r['custo_medio'],4,',','.')?></div>
          <div style="font-weight: 600;">R$ <?=number_format((float)$r['custo_parcial'],4,',','.')?></div>
          <div>
            <a href="produtos_editar.php?id=<?=$produto_id?>&del_bom=<?=$r['id']?>"
               onclick="return confirm('Remover este material do produto?')"
               class="btn btn-sm btn-danger">🗑️</a>
          </div>
        </div>
      <?php endforeach; ?>
      
      <div style="margin-top: 15px; padding: 15px; background: #f0fdf4; border-radius: 8px; border: 1px solid #86efac;">
        <strong>Total Custo Unitário:</strong> R$ <?=number_format($custo_unit,4,',','.')?>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 30px; color: #999;">
        <div style="font-size: 3em; margin-bottom: 10px;">📦</div>
        <p>Nenhum material vinculado ainda.</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Formulário adicionar materiais múltiplos -->
  <form class="card" method="post" autocomplete="off" id="formAddMateriais">
    <h3>➕ Adicionar Materiais</h3>
    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">
      Selecione um ou mais materiais e defina a quantidade de cada um.
    </p>
    
    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 8px; padding: 10px; background: #f9f9f9;">
      <?php foreach($materiais as $m): ?>
        <div style="display: grid; grid-template-columns: auto 2fr 1fr; gap: 10px; align-items: center; padding: 8px; background: white; border-radius: 4px; margin-bottom: 5px;">
          <input type="checkbox" name="materiais[]" value="<?=$m['id']?>" id="mat_<?=$m['id']?>" style="width: 18px; height: 18px;">
          <label for="mat_<?=$m['id']?>" style="margin: 0; cursor: pointer; font-weight: 500;">
            <?=htmlspecialchars($m['nome'])?>
          </label>
          <input type="text" name="qtd_<?=$m['id']?>" placeholder="Qtd" style="padding: 6px 10px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 13px;">
        </div>
      <?php endforeach; ?>
    </div>

    <button type="submit" name="add_bom_multi" style="margin-top: 15px;">➕ Adicionar Materiais Selecionados</button>
  </form>
</div>

<!-- Tab: Zona de Perigo -->
<div id="tab-perigo" class="tab-content">
  <div class="card" style="border: 2px solid #dc2626;">
    <h3 style="color: #dc2626;">⚠️ Zona de Perigo</h3>
    <p style="color: #666; margin-bottom: 20px;">
      Esta ação é <strong>irreversível</strong>. Ao excluir este produto, todos os materiais vinculados (BOM) também serão removidos.
    </p>
    
    <form method="post" onsubmit="return confirm('⚠️ ATENÇÃO!\n\nTem certeza que deseja EXCLUIR este produto?\n\nEsta ação NÃO pode ser desfeita!')">
      <button type="submit" name="excluir" class="btn-danger">🗑️ Excluir Produto Permanentemente</button>
    </form>
  </div>
</div>

<script>
function switchTab(tabName) {
  // Esconder todos os conteúdos
  document.querySelectorAll('.tab-content').forEach(content => {
    content.classList.remove('active');
  });
  
  // Remover active de todas as tabs
  document.querySelectorAll('.tab').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Mostrar conteúdo selecionado
  document.getElementById('tab-' + tabName).classList.add('active');
  
  // Ativar tab selecionada
  event.target.classList.add('active');
}
</script>

<?php include __DIR__.'/layout_end.php'; ?>
