<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();

$pdo = db();
$msg = $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $st = $pdo->prepare("INSERT INTO materiais
      (nome, unidade_base, fator_embalagem, estoque, estoque_min, custo_medio, obs)
      VALUES (?, ?, ?, 0, ?, 0, ?)");
    $st->execute([
      $_POST['nome'],
      $_POST['unidade_base'],
      (int)$_POST['fator_embalagem'],
      (int)$_POST['estoque_min'],
      $_POST['obs'] ?: null
    ]);
    header('Location: /materiais.php?msg=Material cadastrado');
    exit;
  } catch (Throwable $e) { $err = $e->getMessage(); }
}

$title = 'Novo material';
include __DIR__.'/layout_start.php'; // <-- abre HTML + sidebar + topo com título
?>

<style>
.form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.form-header h2 {
  margin: 0;
  font-size: 24px;
  font-weight: 700;
  color: #333;
}

.form-help {
  background: #f0f9ff;
  border-left: 4px solid #3b82f6;
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 4px;
}

.form-help strong {
  color: #1e40af;
  display: block;
  margin-bottom: 5px;
}

.form-help p {
  margin: 0;
  color: #666;
  font-size: 14px;
  line-height: 1.5;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.form-grid.full {
  grid-template-columns: 1fr;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  margin: 0;
  font-weight: 600;
  color: #333;
}

.form-group .help-text {
  font-size: 12px;
  color: #666;
  margin: 0;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<?php if($err): ?><div class="alert alert-danger"><?=htmlspecialchars($err)?></div><?php endif; ?>

<div class="form-header">
  <h2>📦 Cadastrar Novo Material</h2>
  <a href="materiais.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="form-help">
  <strong>💡 Dica</strong>
  <p>Cadastre materiais que você compra regularmente. O sistema vai calcular automaticamente o custo médio e controlar o estoque conforme você registra compras e vendas.</p>
</div>

<form class="card" method="post" autocomplete="off">
  <div class="form-grid full">
    <div class="form-group">
      <label>Nome do material *</label>
      <input name="nome" required placeholder="Ex: Papel Fotográfico A4, Cola Branca, etc.">
      <p class="help-text">Nome que identifica o material</p>
    </div>
  </div>

  <div class="form-grid">
    <div class="form-group">
      <label>Unidade base *</label>
      <select name="unidade_base" required>
        <option value="">Selecione...</option>
        <option value="un">un (unidade)</option>
        <option value="pct">pct (pacote)</option>
        <option value="cx">cx (caixa)</option>
        <option value="folha">folha</option>
        <option value="rolo">rolo</option>
      </select>
      <p class="help-text">Unidade em que você controla o estoque</p>
    </div>

    <div class="form-group">
      <label>Fator da embalagem *</label>
      <input type="number" name="fator_embalagem" value="1" min="1" required>
      <p class="help-text">Quantas unidades base tem na embalagem (ex: caixa com 36 unidades = 36)</p>
    </div>
  </div>

  <div class="form-grid">
    <div class="form-group">
      <label>Estoque mínimo *</label>
      <input type="number" name="estoque_min" value="0" min="0" required>
      <p class="help-text">Quantidade mínima para alerta de reposição</p>
    </div>

    <div class="form-group">
      <label>Observações</label>
      <input name="obs" placeholder="Informações adicionais (opcional)">
      <p class="help-text">Notas ou detalhes sobre o material</p>
    </div>
  </div>

  <div style="margin-top: 15px; padding-top: 20px; border-top: 1px solid #e0e0e0; display: flex; gap: 10px;">
    <button type="submit" style="flex: 1;">💾 Cadastrar Material</button>
    <a href="materiais.php" class="btn btn-secondary" style="flex: 0;">Cancelar</a>
  </div>
</form>

<?php include __DIR__.'/layout_end.php'; // <-- fecha o layout ?>
