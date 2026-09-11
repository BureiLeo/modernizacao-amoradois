<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/helpers.php';
auth_require();

$pdo = db();
$msg = $err = null;

$material_id = isset($_GET['material_id']) ? (int)$_GET['material_id'] : null;
$status = $_GET['status'] ?? 'todos'; // todos | aberto | pago

// Ações: marcar como pago/abrir
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acao']) && $_POST['acao']==='toggle_pago') {
  try {
    $id = (int)$_POST['compra_id'];
    $novo_pago = (int)$_POST['novo_pago']; // 1=pago, 0=aberto
    $fpag = $_POST['forma_pagamento'] ?: null;

    // salva forma de pagamento (opcional) antes de marcar pago
    $st = $pdo->prepare("UPDATE compras SET forma_pagamento = ? WHERE id = ?");
    $st->execute([$fpag, $id]);

    // dispara lógica de caixa (saída) e atualiza flag pago
    atualizar_status_pagamento_compra($id, $novo_pago);

    $q = [];
    if ($material_id) $q[] = 'material_id='.$material_id;
    if ($status && $status!=='todos') $q[] = 'status='.$status;
    $redir = 'compras.php'.($q ? '?'.implode('&',$q) : '');
    header('Location: '.$redir.'&msg='.($novo_pago? 'Compra marcada como paga' : 'Compra reaberta'));
    exit;
  } catch (Throwable $e) { $err = $e->getMessage(); }
}

// filtros
$where = [];
$params = [];
if ($material_id) { $where[] = 'c.material_id = ?'; $params[] = $material_id; }
if ($status === 'aberto') { $where[] = 'c.pago = 0'; }
elseif ($status === 'pago') { $where[] = 'c.pago = 1'; }

$sql = "
  SELECT c.id, c.data, c.material_id, m.nome AS material,
         c.qtd_embalagem, c.qtd_base,
         c.custo_total, IFNULL(c.frete,0) AS frete,
         c.custo_unit, c.forma_pagamento, c.pago
  FROM compras c
  JOIN materiais m ON m.id = c.material_id
  ".($where ? 'WHERE '.implode(' AND ', $where) : '')."
  ORDER BY c.data DESC, c.id DESC
  LIMIT 200
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// totais
$tot_aberto = 0.0; $tot_pago = 0.0;
foreach ($rows as $r) {
  $val = (float)$r['custo_total'] + (float)$r['frete'];
  if ((int)$r['pago']===1) $tot_pago += $val; else $tot_aberto += $val;
}

$title = 'Compras de materiais';
include __DIR__.'/layout_start.php';
?>
<?php if ($msg): ?><div class="ok"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if ($err): ?><div class="alert"><?=htmlspecialchars($err)?></div><?php endif; ?>

<div class="card" style="margin-bottom:14px">
  <form method="get" class="row-2" style="align-items:end">
    <div>
      <label>Filtrar por material</label>
      <select name="material_id">
        <option value="">— todos —</option>
        <?php
          $ms = $pdo->query("SELECT id, nome FROM materiais ORDER BY nome")->fetchAll();
          foreach ($ms as $m) {
            $sel = ($material_id===$m['id']) ? 'selected' : '';
            echo '<option value="'.$m['id'].'" '.$sel.'>'.htmlspecialchars($m['nome']).'</option>';
          }
        ?>
      </select>
    </div>
    <div>
      <label>Status</label>
      <select name="status">
        <option value="todos"  <?=$status==='todos'?'selected':''?>>Todos</option>
        <option value="aberto" <?=$status==='aberto'?'selected':''?>>Em aberto</option>
        <option value="pago"   <?=$status==='pago'?'selected':''?>>Pagos</option>
      </select>
    </div>
    <div>
      <button type="submit">Aplicar filtros</button>
    </div>
    <div>
      <a class="nav-btn" href="compras.php"><span class="label">Limpar</span></a>
    </div>
  </form>
</div>

<div class="card">
  <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:10px">
    <div><b>Total em aberto:</b> R$ <?=number_format($tot_aberto,2,',','.')?></div>
    <div><b>Total pago (lista):</b> R$ <?=number_format($tot_pago,2,',','.')?></div>
    <div style="margin-left:auto"><a href="compras_nova.php" class="nav-btn"><span class="label">➕ Registrar compra</span></a></div>
  </div>

  <div class="table-wrapper" style="overflow:auto">
    <table class="table">
      <thead>
        <tr>
          <th>Data</th>
          <th>Material</th>
          <th>Emb.</th>
          <th>Qtde base</th>
          <th>Custo total</th>
          <th>Frete</th>
          <th>Unit.</th>
          <th>Pagamento</th>
          <th>Status</th>
          <th style="width:185px">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): 
          $valor = (float)$r['custo_total'] + (float)$r['frete'];
          $isPago = (int)$r['pago'] === 1;
        ?>
        <tr>
          <td><?=htmlspecialchars(date('d/m/Y', strtotime($r['data'])))?></td>
          <td>
            <a href="compras.php?material_id=<?=$r['material_id']?>"><?=htmlspecialchars($r['material'])?></a>
          </td>
          <td style="text-align:right"><?= (int)$r['qtd_embalagem'] ?></td>
          <td style="text-align:right"><?= (int)$r['qtd_base'] ?></td>
          <td>R$ <?=number_format((float)$r['custo_total'],2,',','.')?></td>
          <td>R$ <?=number_format((float)$r['frete'],2,',','.')?></td>
          <td>R$ <?=number_format((float)$r['custo_unit'],4,',','.')?></td>
          <td>
            <form method="post" style="display:flex;gap:6px;align-items:center">
              <input type="hidden" name="acao" value="toggle_pago">
              <input type="hidden" name="compra_id" value="<?=$r['id']?>">
              <input type="hidden" name="novo_pago" value="<?= $isPago ? 0 : 1 ?>">
              <select name="forma_pagamento" style="min-width:120px">
                <?php
                  $ops = ['','PIX','Dinheiro','Débito','Crédito','Transferência','Outro'];
                  foreach ($ops as $op) {
                    $sel = ($r['forma_pagamento']===$op) ? 'selected':'';
                    $label = $op ?: '—';
                    echo "<option value=\"".htmlspecialchars($op)."\" $sel>$label</option>";
                  }
                ?>
              </select>
              <button type="submit"><?= $isPago ? 'Reabrir' : 'Marcar pago' ?></button>
            </form>
          </td>
          <td>
            <?php if($isPago): ?>
              <span class="status pago">Pago</span>
            <?php else: ?>
              <span class="status aberto">Em aberto</span>
            <?php endif; ?>
          </td>
          <td>
            <!-- Espaço para ações futuras: editar/excluir compra -->
            <span class="small">R$ <?=number_format($valor,2,',','.')?></span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?>
        <tr><td colspan="10" class="small">Nenhuma compra encontrada.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__.'/layout_end.php'; ?>
