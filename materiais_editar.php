<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/helpers.php';
auth_require();
$pdo = db();

$material_id = (int)($_GET['id'] ?? 0);
if ($material_id <= 0) { header('Location: materiais.php'); exit; }

// Buscar material
$stmt = $pdo->prepare("SELECT * FROM materiais WHERE id=?");
$stmt->execute([$material_id]);
$material = $stmt->fetch();
if (!$material) { header('Location: materiais.php'); exit; }

$err = null;
$msg = $_GET['msg'] ?? null;

// Atualizar material
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['nome'])) {
  try {
    $st = $pdo->prepare("UPDATE materiais SET nome=?, unidade_base=?, fator_embalagem=?, estoque_min=?, obs=? WHERE id=?");
    $result = $st->execute([
      $_POST['nome'],
      $_POST['unidade_base'],
      (int)$_POST['fator_embalagem'],
      (int)$_POST['estoque_min'],
      $_POST['obs'] ?: null,
      $material_id
    ]);
    
    if ($result) {
      header('Location: materiais_editar.php?id='.$material_id.'&msg=Material atualizado com sucesso!');
      exit;
    } else {
      $err = 'Erro ao atualizar material no banco de dados';
    }
  } catch (Throwable $e) { 
    $err = 'Erro: ' . $e->getMessage(); 
  }
}

// Excluir material
if (isset($_POST['excluir'])) {
  try {
    $pdo->prepare("DELETE FROM produto_bom WHERE material_id=?")->execute([$material_id]);
    $pdo->prepare("DELETE FROM compras WHERE material_id=?")->execute([$material_id]);
    $pdo->prepare("DELETE FROM materiais WHERE id=?")->execute([$material_id]);
    header('Location: materiais.php?msg=Material excluído');
    exit;
  } catch (Throwable $e) { $err = $e->getMessage(); }
}

$title = 'Editar Material: '.$material['nome'];
include __DIR__.'/layout_start.php';
?>

<style>
.material-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.material-header h2 {
  margin: 0;
  font-size: 24px;
  font-weight: 700;
  color: #333;
}

.btn-group {
  display: flex;
  gap: 10px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
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

.info-value.warning {
  color: #f59e0b;
}

.info-value.danger {
  color: #dc2626;
}

.btn-danger {
  background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
  color: white;
}

.btn-danger:hover {
  background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
}

.alert {
  padding: 12px 15px;
  border-radius: 6px;
  margin-bottom: 20px;
  font-size: 14px;
}

.alert-success {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #86efac;
}

.alert-danger {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fca5a5;
}
</style>

<?php if($err): ?><div class="alert alert-danger"><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if($msg): ?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div class="material-header">
  <h2>📦 Editar Material</h2>
  <div class="btn-group">
    <a href="materiais.php" class="btn btn-secondary">← Voltar</a>
    <a href="materiais_ajuste.php?id=<?=$material_id?>" class="btn btn-success">⚖️ Ajustar Estoque</a>
    <a href="compras_nova.php?material_id=<?=$material_id?>" class="btn btn-primary">➕ Nova Compra</a>
  </div>
</div>

<!-- Resumo rápido -->
<div class="info-grid">
  <div class="info-item">
    <div class="info-label">📦 Estoque Atual</div>
    <div class="info-value <?=(int)$material['estoque'] <= (int)$material['estoque_min'] ? 'danger' : 'success'?>">
      <?=(int)$material['estoque']?> <?=htmlspecialchars($material['unidade_base'])?>
    </div>
  </div>
  <div class="info-item">
    <div class="info-label">⚠️ Estoque Mínimo</div>
    <div class="info-value"><?=(int)$material['estoque_min']?> <?=htmlspecialchars($material['unidade_base'])?></div>
  </div>
  <div class="info-item">
    <div class="info-label">💰 Custo Médio</div>
    <div class="info-value">R$ <?=number_format((float)$material['custo_medio'],2,',','.')?></div>
  </div>
  <div class="info-item">
    <div class="info-label">🏭 Fator Embalagem</div>
    <div class="info-value"><?=(int)$material['fator_embalagem']?> un/emb</div>
  </div>
</div>

<!-- Tab: Dados do Material -->
<div id="tab-dados" class="tab-content active">
  <form class="card" method="post" action="" autocomplete="off">
    <h3 style="margin-top: 0;">📝 Informações do Material</h3>
    <p style="color: #666; font-size: 14px; margin-bottom: 25px;">
      Atualize os dados do material. O estoque é controlado automaticamente pelas compras e vendas.
    </p>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
      <div>
        <label>Nome do Material</label>
        <input name="nome" value="<?=htmlspecialchars($material['nome'])?>" required>
      </div>

      <div>
        <label>Unidade Base</label>
        <select name="unidade_base" required>
          <option value="un" <?=$material['unidade_base']=='un'?'selected':''?>>un (unidade)</option>
          <option value="pct" <?=$material['unidade_base']=='pct'?'selected':''?>>pct (pacote)</option>
          <option value="cx" <?=$material['unidade_base']=='cx'?'selected':''?>>cx (caixa)</option>
          <option value="folha" <?=$material['unidade_base']=='folha'?'selected':''?>>folha</option>
          <option value="rolo" <?=$material['unidade_base']=='rolo'?'selected':''?>>rolo</option>
        </select>
      </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
      <div>
        <label>Fator da Embalagem</label>
        <input type="number" name="fator_embalagem" value="<?=(int)$material['fator_embalagem']?>" min="1" required>
        <p style="font-size: 12px; color: #666; margin-top: 4px;">
          Quantas unidades base vêm na embalagem
        </p>
      </div>

      <div>
        <label>Estoque Mínimo (Alerta)</label>
        <input type="number" name="estoque_min" value="<?=(int)$material['estoque_min']?>" min="0" required>
        <p style="font-size: 12px; color: #666; margin-top: 4px;">
          Quantidade para alerta de reposição
        </p>
      </div>
    </div>

    <label>Observações</label>
    <textarea name="obs" rows="3" placeholder="Notas ou informações adicionais (opcional)"><?=htmlspecialchars($material['obs'] ?? '')?></textarea>

    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
      <button type="submit" name="atualizar" value="1">💾 Salvar Alterações</button>
    </div>
  </form>
</div>

<!-- Seção: Zona de Perigo -->
<div style="margin-top: 30px;">
  <div class="card" style="border: 2px solid #dc2626;">
    <h3 style="color: #dc2626;">⚠️ Zona de Perigo</h3>
    <p style="color: #666; margin-bottom: 20px;">
      Esta ação é <strong>irreversível</strong>. Ao excluir este material, todas as compras e vínculos com produtos (BOM) serão removidos.
    </p>
    
    <form method="post" onsubmit="return confirm('⚠️ ATENÇÃO!\n\nTem certeza que deseja EXCLUIR este material?\n\nEsta ação NÃO pode ser desfeita!')">
      <button type="submit" name="excluir" class="btn-danger">🗑️ Excluir Material Permanentemente</button>
    </form>
  </div>
</div>

<?php include __DIR__.'/layout_end.php'; ?>
