<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

$msg = $err = null;

/* Ações: alternar quitado (POST) */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='toggle_quitado') {
  try {
    require_once __DIR__.'/helpers.php';
    $id = (int)$_POST['id'];
    
    // Buscar dados do investimento
    $st = $pdo->prepare("SELECT quitado, valor, data, nome FROM investimentos WHERE id = ?");
    $st->execute([$id]);
    $inv = $st->fetch();
    if (!$inv) throw new Exception('Investimento não encontrado');
    
    $novoQuitado = 1 - (int)$inv['quitado']; // Alterna 0->1 ou 1->0
    $valor = -1 * (float)$inv['valor']; // Negativo porque é saída
    $data = new DateTime($inv['data']);
    
    $pdo->beginTransaction();
    
    // Atualiza o status
    $pdo->prepare("UPDATE investimentos SET quitado = ? WHERE id = ?")->execute([$novoQuitado, $id]);
    
    // Se está quitando (0->1), lança no caixa
    if ($novoQuitado == 1) {
      caixa_lancar($data, 'INVESTIMENTO', 'investimentos', $id, $valor, 'Investimento: '.substr($inv['nome'], 0, 50));
    } else {
      // Se está desquitando (1->0), remove do caixa
      caixa_apagar_por_ref('INVESTIMENTO', 'investimentos', $id);
    }
    
    $pdo->commit();
    header('Location: investimentos.php?msg=Status atualizado e lançado no caixa');
    exit;
  } catch (Throwable $e) { 
    if ($pdo->inTransaction()) $pdo->rollBack();
    $err = $e->getMessage(); 
  }
}

/* Filtro simples: all | abertos | quitados */
$filtro = $_GET['f'] ?? 'all';
$where  = $filtro==='abertos' ? 'WHERE quitado=0' : ($filtro==='quitados' ? 'WHERE quitado=1' : '');
$rows = $pdo->query("SELECT * FROM investimentos $where ORDER BY data DESC, id DESC")->fetchAll();

/* KPIs */
$totalGeral   = (float)($pdo->query("SELECT IFNULL(SUM(valor),0) v FROM investimentos")->fetch()['v'] ?? 0);
$totalAberto  = (float)($pdo->query("SELECT IFNULL(SUM(valor),0) v FROM investimentos WHERE quitado=0")->fetch()['v'] ?? 0);
$lucro30      = (float)($pdo->query("
  SELECT IFNULL(SUM(vi.lucro_unit * vi.qtd),0) v
  FROM venda_itens vi JOIN vendas v ON v.id=vi.venda_id
  WHERE v.data >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch()['v'] ?? 0);

/* Payback estimado (em aberto): dias = totalAberto / (lucro30/30) */
$paybackTxt = '—';
if ($lucro30 > 0 && $totalAberto > 0) {
  $dias = (int)ceil($totalAberto / max(0.01, ($lucro30/30)));
  $meses = $dias/30;
  $paybackTxt = $dias.' dias (~'.number_format($meses,1,',','.').' meses)';
}

$title = 'Investimentos';
include __DIR__.'/layout_start.php';
?>
<?php if($msg): ?><div class="ok"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="alert"><?=htmlspecialchars($err)?></div><?php endif; ?>

<section class="grid">
  <div class="card">
    <h3>Total investido (todos)</h3>
    <div class="big">R$ <?=number_format($totalGeral,2,',','.')?></div>
  </div>
  <div class="card">
    <h3>Em aberto (a pagar)</h3>
    <div class="big">R$ <?=number_format($totalAberto,2,',','.')?></div>
  </div>
  <div class="card">
    <h3>Payback estimado</h3>
    <div class="big"><?=$paybackTxt?></div>
    <small class="small">Base: lucro dos últimos 30 dias</small>
  </div>
</section>

<div class="card" style="margin-top:16px; display:flex; gap:10px; align-items:center;">
  <a class="quick" href="investimentos_novo.php" style="display:inline-block">➕ Novo investimento</a>
  <div class="small">Filtro:
    <a href="investimentos.php?f=all">Todos</a> ·
    <a href="investimentos.php?f=abertos">Abertos</a> ·
    <a href="investimentos.php?f=quitados">Quitados</a>
  </div>
</div>

<div class="card" style="margin-top:12px; overflow:auto">
  <h3>Lista</h3>
  <table class="table">
    <thead>
      <tr><th>Data</th><th>Nome</th><th>Categoria</th><th>Valor</th><th>Status</th><th>Ações</th></tr>
    </thead>
    <tbody>
      <?php if(!$rows): ?>
        <tr><td colspan="6">Nenhum investimento.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><?=date('d/m/Y', strtotime($r['data']))?></td>
          <td><?=htmlspecialchars($r['nome'])?></td>
          <td><?=htmlspecialchars($r['categoria'] ?? '')?></td>
          <td>R$ <?=number_format((float)$r['valor'],2,',','.')?></td>
          <td><?= $r['quitado'] ? 'Quitado' : 'Em aberto' ?></td>
          <td>
            <form method="post" style="display:inline" onsubmit="return confirm('Alterar status?');">
              <input type="hidden" name="action" value="toggle_quitado">
              <input type="hidden" name="id" value="<?=$r['id']?>">
              <button type="submit"><?= $r['quitado'] ? 'Marcar em aberto' : 'Marcar quitado' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__.'/layout_end.php'; ?>
