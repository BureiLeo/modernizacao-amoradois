<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/helpers.php';
auth_require();
$pdo = db();

$err = null;

// materiais para o select
$materiais = $pdo->query("SELECT id, nome, fator_embalagem FROM materiais ORDER BY nome")->fetchAll();
$material_sel = isset($_GET['material_id']) ? (int)$_GET['material_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $materialId   = (int)$_POST['material_id'];
    $qtdEmb       = max(1, (int)$_POST['qtd_embalagem']);
    $fator        = max(1, (int)$_POST['fator']);
    $custoTotal   = (float)str_replace(',', '.', trim($_POST['custo_total']));
    $frete        = $_POST['frete'] === '' ? null : (float)str_replace(',', '.', trim($_POST['frete']));
    $fpag         = $_POST['forma_pagamento'] ?: null;
    $pago         = isset($_POST['pago']) ? (int)$_POST['pago'] : 0;

    if ($materialId <= 0) throw new Exception('Selecione um material');
    if ($custoTotal <= 0) throw new Exception('Custo total deve ser maior que zero');

    // registra compra (atualiza estoque/custo médio)
    $compraId = registrar_compra($materialId, $qtdEmb, $fator, $custoTotal, $frete);

    // persiste forma de pagamento (opcional)
    if ($fpag !== null) {
      $st = $pdo->prepare("UPDATE compras SET forma_pagamento = ? WHERE id = ?");
      $st->execute([$fpag, $compraId]);
    }

    // status de pagamento e lançamento no caixa
    if ($pago === 1 || $pago === 0) {
      atualizar_status_pagamento_compra($compraId, $pago);
    }

    header('Location: compras.php?msg=Compra registrada&material_id='.$materialId);
    exit;
  } catch (Throwable $e) { $err = $e->getMessage(); }
}

$title = 'Registrar compra';
include __DIR__.'/layout_start.php';
?>
<?php if($err): ?><div class="alert"><?=htmlspecialchars($err)?></div><?php endif; ?>

<form class="card" method="post" autocomplete="off">
  <label>Material</label>
  <select name="material_id" required>
    <option value="" disabled <?= $material_sel ? '' : 'selected' ?>>Selecione</option>
    <?php foreach($materiais as $m): ?>
      <option value="<?=$m['id']?>" <?= $material_sel==$m['id']?'selected':'' ?>>
        <?=htmlspecialchars($m['nome'])?>
      </option>
    <?php endforeach; ?>
  </select>

  <div class="row-3">
    <div>
      <label>Qtd por embalagem</label>
      <input type="number" name="fator" value="<?=htmlspecialchars((string)($materiais && $material_sel ? ((int)array_values(array_filter($materiais, fn($x)=>$x['id']==$material_sel))[0]['fator_embalagem']) : 1))?>" min="1" required>
    </div>
    <div>
      <label>Nº de embalagens</label>
      <input type="number" name="qtd_embalagem" value="1" min="1" required>
    </div>
    <div>
      <label>Custo total (R$)</label>
      <input name="custo_total" placeholder="ex: 199,90" required>
    </div>
  </div>

  <label>Frete (R$) — opcional</label>
  <input name="frete" placeholder="ex: 25,00">

  <div class="row-2">
    <div>
      <label>Forma de pagamento</label>
      <select name="forma_pagamento">
        <option value="">—</option>
        <option>PIX</option><option>Dinheiro</option><option>Débito</option>
        <option>Crédito</option><option>Transferência</option><option>Outro</option>
      </select>
    </div>
    <div>
      <label>Status do pagamento</label>
      <select name="pago">
        <option value="1">Pago</option>
        <option value="0" selected>Em aberto</option>
      </select>
    </div>
  </div>

  <button type="submit">Salvar compra</button>
</form>

<?php include __DIR__.'/layout_end.php'; ?>
