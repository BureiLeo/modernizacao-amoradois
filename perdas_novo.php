<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/helpers.php';
auth_require();

$pdo = db();

// Carrega listas
$produtos  = $pdo->query("SELECT id, nome FROM produtos WHERE ativo=1 ORDER BY nome")->fetchAll();
$materiais = $pdo->query("SELECT id, nome FROM materiais ORDER BY nome")->fetchAll();

$err = null;

// Prefills via GET (opcional)
$tipoPref     = $_GET['tipo'] ?? 'PRODUTO'; // PRODUTO ou MATERIAL
$produtoPref  = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : null;
$materialPref = isset($_GET['material_id']) ? (int)$_GET['material_id'] : null;
$qtdPref      = isset($_GET['qtd']) ? (int)$_GET['qtd'] : 1;
$motivoPref   = $_GET['motivo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $tipo   = $_POST['tipo'];
    $qtd    = max(1, (int)$_POST['qtd']);
    $motivo = $_POST['motivo'] ?: null;
    $obs    = $_POST['obs'] ?: null;

    if ($tipo === 'PRODUTO') {
      $produtoId = (int)$_POST['produto_id'];
      registrar_perda_produto(new DateTime(), $produtoId, $qtd, $motivo, $obs);
    } else {
      $materialId = (int)$_POST['material_id'];
      registrar_perda_material(new DateTime(), $materialId, $qtd, $motivo, $obs);
    }

    header('Location: perdas.php?msg=Perda registrada');
    exit;
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

$title = 'Registrar perda';
include __DIR__.'/layout_start.php';
?>
<?php if($err): ?><div class="alert"><?=htmlspecialchars($err)?></div><?php endif; ?>

<form class="card" method="post" autocomplete="off" id="form-perda">
  <label>Tipo de perda</label>
  <select name="tipo" id="tipo" onchange="toggleTipo()" required>
    <option value="PRODUTO" <?= $tipoPref==='PRODUTO'?'selected':'' ?>>Perda de produto (usa BOM)</option>
    <option value="MATERIAL" <?= $tipoPref==='MATERIAL'?'selected':'' ?>>Perda de material (direta)</option>
  </select>

  <div id="box-produto">
    <label>Produto</label>
    <select name="produto_id">
      <option value="">Selecione</option>
      <?php foreach($produtos as $p): ?>
        <option value="<?=$p['id']?>" <?= $produtoPref===$p['id'] ? 'selected':'' ?>>
          <?=htmlspecialchars($p['nome'])?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div id="box-material" style="display:none">
    <label>Material</label>
    <select name="material_id">
      <option value="">Selecione</option>
      <?php foreach($materiais as $m): ?>
        <option value="<?=$m['id']?>" <?= $materialPref===$m['id'] ? 'selected':'' ?>>
          <?=htmlspecialchars($m['nome'])?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <label>Quantidade</label>
  <input type="number" name="qtd" value="<?= (int)$qtdPref ?>" min="1" required>

  <label>Motivo (opcional)</label>
  <input name="motivo" value="<?=htmlspecialchars($motivoPref)?>" placeholder="Ex.: erro na sublimação, quebra, falha na prensa">

  <label>Observações</label>
  <input name="obs" placeholder="opcional">

  <button type="submit">Salvar perda</button>
  <p class="small">Dica: Se a perda aconteceu durante uma venda, você também pode lançar junto na tela “Nova venda”.</p>
</form>

<script>
function toggleTipo(){
  var t = document.getElementById('tipo').value;
  document.getElementById('box-produto').style.display = (t === 'PRODUTO') ? 'block' : 'none';
  document.getElementById('box-material').style.display = (t === 'MATERIAL') ? 'block' : 'none';
}
toggleTipo();
</script>

<?php include __DIR__.'/layout_end.php'; ?>
