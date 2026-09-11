<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

$msg = $_GET['msg'] ?? null;

$perdas = $pdo->query("
  SELECT p.id, p.data, p.tipo, p.qtd, p.motivo, p.custo_total,
         prd.nome AS produto, mat.nome AS material
  FROM perdas p
  LEFT JOIN produtos prd ON prd.id = p.produto_id
  LEFT JOIN materiais mat ON mat.id = p.material_id
  ORDER BY p.data DESC
  LIMIT 100
")->fetchAll();

$perdas30 = (float)($pdo->query("SELECT IFNULL(SUM(custo_total),0) v FROM perdas WHERE data >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['v'] ?? 0);

$title = 'Perdas';
include __DIR__.'/layout_start.php';
?>
<?php if($msg): ?><div class="ok"><?=htmlspecialchars($msg)?></div><?php endif; ?>

<section class="grid">
  <div class="card">
    <h3>Custo de perdas (30d)</h3>
    <div class="big">R$ <?=number_format($perdas30,2,',','.')?></div>
  </div>
  <div class="card">
    <h3>Registrar</h3>
    <div><a class="quick" href="perdas_novo.php">➕ Nova perda</a></div>
  </div>
</section>

<div class="card" style="margin-top:16px; overflow:auto">
  <h3>Últimas perdas</h3>
  <table class="table">
    <thead>
      <tr>
        <th>Data</th><th>Tipo</th><th>Item</th><th>Qtd</th><th>Motivo</th><th>Custo total</th>
      </tr>
    </thead>
    <tbody>
      <?php if(!$perdas): ?>
        <tr><td colspan="6">Nada registrado ainda.</td></tr>
      <?php else: foreach($perdas as $p): ?>
        <tr>
          <td><?=date('d/m/Y H:i', strtotime($p['data']))?></td>
          <td><?=htmlspecialchars($p['tipo'])?></td>
          <td><?=htmlspecialchars($p['tipo']==='PRODUTO' ? ($p['produto'] ?? '') : ($p['material'] ?? ''))?></td>
          <td><?=$p['qtd']?></td>
          <td><?=htmlspecialchars($p['motivo'] ?? '')?></td>
          <td>R$ <?=number_format((float)$p['custo_total'],2,',','.')?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__.'/layout_end.php'; ?>
