<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();

$pdo = db();
$material_id = (int)($_GET['id'] ?? 0);

if ($material_id <= 0) { 
  header('Location: materiais.php'); 
  exit; 
}

// Buscar material
$stmt = $pdo->prepare("SELECT * FROM materiais WHERE id=?");
$stmt->execute([$material_id]);
$material = $stmt->fetch();

if (!$material) { 
  header('Location: materiais.php?err=Material não encontrado'); 
  exit; 
}

$msg = $err = null;

// Processar ajuste
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['tipo'])) {
  try {
    $tipo = $_POST['tipo']; // 'adicionar' ou 'remover'
    $quantidade = abs((float)$_POST['quantidade']);
    $motivo = trim($_POST['motivo']);
    
    if ($quantidade <= 0) {
      throw new Exception('Quantidade deve ser maior que zero');
    }
    
    if (empty($motivo)) {
      throw new Exception('Motivo do ajuste é obrigatório');
    }
    
    $pdo->beginTransaction();
    
    // Calcular novo estoque
    $estoque_atual = (float)$material['estoque'];
    $estoque_novo = $tipo === 'adicionar' 
      ? $estoque_atual + $quantidade 
      : $estoque_atual - $quantidade;
    
    if ($estoque_novo < 0) {
      throw new Exception('Estoque não pode ficar negativo');
    }
    
    // Atualizar estoque do material
    $stmt = $pdo->prepare("UPDATE materiais SET estoque = ? WHERE id = ?");
    $stmt->execute([$estoque_novo, $material_id]);
    
    // Registrar histórico de ajuste
    $stmt = $pdo->prepare("
      INSERT INTO ajustes_estoque 
        (material_id, tipo, quantidade, estoque_anterior, estoque_novo, motivo, usuario_id, data_ajuste)
      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
      $material_id,
      $tipo,
      $quantidade,
      $estoque_atual,
      $estoque_novo,
      $motivo,
      $_SESSION['user_id'] ?? null
    ]);
    
    $pdo->commit();
    
    header('Location: materiais_ajuste.php?id='.$material_id.'&msg=Ajuste realizado com sucesso!');
    exit;
    
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $err = 'Erro ao realizar ajuste: ' . $e->getMessage();
  }
}

// Verificar se a tabela ajustes_estoque existe
$tabela_existe = false;
try {
  $checkTable = $pdo->query("SHOW TABLES LIKE 'ajustes_estoque'")->fetch();
  $tabela_existe = !empty($checkTable);
} catch (Throwable $e) {
  $tabela_existe = false;
}

// Buscar histórico de ajustes
$historico = [];
if ($tabela_existe) {
  try {
    $stmt = $pdo->prepare("
      SELECT a.*, u.nome as usuario_nome
      FROM ajustes_estoque a
      LEFT JOIN usuarios u ON u.id = a.usuario_id
      WHERE a.material_id = ?
      ORDER BY a.data_ajuste DESC
      LIMIT 20
    ");
    $stmt->execute([$material_id]);
    $historico = $stmt->fetchAll();
  } catch (Throwable $e) {
    // Ignorar erro ao buscar histórico
  }
}

$title = 'Ajuste de Estoque: '.$material['nome'];
include __DIR__.'/layout_start.php';
?>

<style>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  gap: 15px;
}

.page-header h2 {
  margin: 0;
  font-size: 24px;
  font-weight: 700;
  color: #333;
}

.current-stock {
  background: linear-gradient(135deg, #5d4e99, #7d6eb9);
  color: white;
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 25px;
  box-shadow: 0 4px 12px rgba(93, 78, 153, 0.2);
}

.current-stock h3 {
  margin: 0 0 10px 0;
  font-size: 14px;
  font-weight: 600;
  opacity: 0.9;
}

.stock-value {
  font-size: 36px;
  font-weight: 800;
  margin: 0;
}

.stock-unit {
  font-size: 18px;
  opacity: 0.9;
  margin-left: 8px;
}

.ajuste-form {
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 12px;
  padding: 25px;
  margin-bottom: 30px;
}

.ajuste-form h3 {
  margin: 0 0 20px 0;
  font-size: 18px;
  font-weight: 700;
  color: #333;
}

.tipo-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
  margin-bottom: 20px;
}

.tipo-button {
  padding: 15px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  background: white;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  font-size: 16px;
  font-weight: 600;
}

.tipo-button:hover {
  border-color: #5d4e99;
  background: #f8f7fb;
}

.tipo-button.active {
  border-color: #5d4e99;
  background: linear-gradient(135deg, #5d4e99, #7d6eb9);
  color: white;
}

.tipo-button.adicionar.active {
  background: linear-gradient(135deg, #22c55e, #16a34a);
  border-color: #22c55e;
}

.tipo-button.remover.active {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  border-color: #ef4444;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  font-weight: 600;
  margin-bottom: 8px;
  color: #333;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  font-family: inherit;
}

.form-group textarea {
  min-height: 80px;
  resize: vertical;
}

.form-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.historico {
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 12px;
  padding: 25px;
}

.historico h3 {
  margin: 0 0 20px 0;
  font-size: 18px;
  font-weight: 700;
  color: #333;
}

.historico-item {
  padding: 15px;
  border-left: 4px solid #e0e0e0;
  background: #f9f9f9;
  margin-bottom: 12px;
  border-radius: 4px;
  font-size: 14px;
}

.historico-item.adicionar {
  border-left-color: #22c55e;
  background: #f0fdf4;
}

.historico-item.remover {
  border-left-color: #ef4444;
  background: #fef2f2;
}

.historico-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  font-weight: 600;
}

.historico-tipo {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.historico-tipo.adicionar {
  color: #16a34a;
}

.historico-tipo.remover {
  color: #dc2626;
}

.historico-data {
  font-size: 12px;
  color: #666;
}

.historico-detalhes {
  color: #666;
  line-height: 1.6;
}

.historico-motivo {
  margin-top: 8px;
  padding: 8px;
  background: white;
  border-radius: 4px;
  font-style: italic;
}

.empty-state {
  text-align: center;
  padding: 40px;
  color: #999;
}
</style>

<div class="page-header">
  <h2>📦 Ajuste de Estoque</h2>
  <a href="materiais.php" class="btn btn-sec">← Voltar</a>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<?php if (!$tabela_existe): ?>
  <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
    <h3 style="margin: 0 0 15px 0; color: #856404;">⚠️ Tabela de Ajustes Não Criada</h3>
    <p style="margin: 0 0 15px 0; color: #856404;">
      A tabela <code>ajustes_estoque</code> ainda não existe no banco de dados. 
      Execute o SQL abaixo no phpMyAdmin para criar a tabela:
    </p>
    <textarea readonly style="width: 100%; min-height: 150px; font-family: monospace; font-size: 12px; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f8f9fa;" onclick="this.select()">CREATE TABLE IF NOT EXISTS ajustes_estoque (
  id INT AUTO_INCREMENT PRIMARY KEY,
  material_id INT NOT NULL,
  tipo ENUM('adicionar', 'remover') NOT NULL,
  quantidade DECIMAL(10,2) NOT NULL,
  estoque_anterior DECIMAL(10,2) NOT NULL,
  estoque_novo DECIMAL(10,2) NOT NULL,
  motivo TEXT NOT NULL,
  usuario_id INT NULL,
  data_ajuste DATETIME NOT NULL,
  INDEX idx_material (material_id),
  INDEX idx_data (data_ajuste),
  INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</textarea>
    <p style="margin: 15px 0 0 0; color: #856404; font-size: 14px;">
      <strong>Como fazer:</strong> Copie o código acima (clique nele), vá ao phpMyAdmin, selecione seu banco de dados, 
      clique em "SQL" e cole o código. Depois clique em "Executar".
    </p>
  </div>
<?php endif; ?>

<div class="current-stock">
  <h3>Estoque Atual</h3>
  <div>
    <span class="stock-value"><?= number_format($material['estoque'], 2, ',', '.') ?></span>
    <span class="stock-unit"><?= htmlspecialchars($material['unidade_base']) ?></span>
  </div>
</div>

<div class="ajuste-form">
  <h3>Realizar Ajuste</h3>
  
  <?php if (!$tabela_existe): ?>
    <div style="padding: 30px; text-align: center; color: #999; background: #f8f9fa; border-radius: 8px;">
      <p style="font-size: 18px; margin: 0;">🔒 Formulário Desabilitado</p>
      <p style="font-size: 14px; margin: 10px 0 0 0;">Crie a tabela no banco de dados primeiro (veja instruções acima)</p>
    </div>
  <?php else: ?>
  
  <form method="post">
    <input type="hidden" name="tipo" id="tipo-input" value="">
    
    <div class="tipo-buttons">
      <div class="tipo-button adicionar" onclick="selectTipo('adicionar')">
        <span style="font-size: 24px;">➕</span>
        Adicionar ao Estoque
      </div>
      <div class="tipo-button remover" onclick="selectTipo('remover')">
        <span style="font-size: 24px;">➖</span>
        Remover do Estoque
      </div>
    </div>
    
    <div class="form-group">
      <label>Quantidade</label>
      <input type="number" name="quantidade" step="0.01" min="0.01" required 
             placeholder="Digite a quantidade">
    </div>
    
    <div class="form-group">
      <label>Motivo do Ajuste</label>
      <textarea name="motivo" required 
                placeholder="Ex: Correção de inventário, perda, devolução, etc."></textarea>
    </div>
    
    <div class="form-actions">
      <a href="materiais.php" class="btn btn-sec">Cancelar</a>
      <button type="submit" name="ajustar" class="btn btn-pri" id="btn-ajustar" disabled>
        Realizar Ajuste
      </button>
    </div>
  </form>
  
  <?php endif; ?>
</div>

<div class="historico">
  <h3>📋 Histórico de Ajustes</h3>
  
  <?php if (empty($historico)): ?>
    <div class="empty-state">
      <?php if (!$tabela_existe): ?>
        A tabela de histórico ainda não foi criada.
      <?php else: ?>
        Nenhum ajuste realizado ainda.
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php foreach ($historico as $h): ?>
      <div class="historico-item <?= $h['tipo'] ?>">
        <div class="historico-header">
          <div class="historico-tipo <?= $h['tipo'] ?>">
            <?php if ($h['tipo'] === 'adicionar'): ?>
              ➕ Adição de <?= number_format($h['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($material['unidade_base']) ?>
            <?php else: ?>
              ➖ Remoção de <?= number_format($h['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($material['unidade_base']) ?>
            <?php endif; ?>
          </div>
          <div class="historico-data">
            <?= date('d/m/Y H:i', strtotime($h['data_ajuste'])) ?>
          </div>
        </div>
        
        <div class="historico-detalhes">
          Estoque: <?= number_format($h['estoque_anterior'], 2, ',', '.') ?> 
          → <?= number_format($h['estoque_novo'], 2, ',', '.') ?> 
          <?= htmlspecialchars($material['unidade_base']) ?>
          <?php if ($h['usuario_nome']): ?>
            • Por: <?= htmlspecialchars($h['usuario_nome']) ?>
          <?php endif; ?>
        </div>
        
        <div class="historico-motivo">
          "<?= htmlspecialchars($h['motivo']) ?>"
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
<?php if ($tabela_existe): ?>
function selectTipo(tipo) {
  // Remove active de todos
  document.querySelectorAll('.tipo-button').forEach(btn => {
    btn.classList.remove('active');
  });
  
  // Adiciona active ao selecionado
  document.querySelector('.tipo-button.' + tipo).classList.add('active');
  
  // Atualiza input hidden
  document.getElementById('tipo-input').value = tipo;
  
  // Habilita botão de submit
  document.getElementById('btn-ajustar').disabled = false;
}

// Validação no submit
document.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', function(e) {
      const tipo = document.getElementById('tipo-input').value;
      
      if (!tipo) {
        e.preventDefault();
        alert('⚠️ Selecione se deseja ADICIONAR ou REMOVER do estoque');
        return false;
      }
    });
  }
});
<?php endif; ?>
</script>

<?php include __DIR__.'/layout_end.php'; ?>
