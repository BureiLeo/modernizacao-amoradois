<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

$err = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    require_once __DIR__.'/helpers.php';
    
    $nome = trim($_POST['nome']);
    $categoria = $_POST['categoria'] !== '' ? $_POST['categoria'] : null;
    $data = $_POST['data'] ?: date('Y-m-d');
    $valor = (float)str_replace(',', '.', $_POST['valor']);
    $quitado = isset($_POST['quitado']) ? 1 : 0;
    $obs = $_POST['obs'] !== '' ? $_POST['obs'] : null;

    $pdo->beginTransaction();
    
    $st = $pdo->prepare("INSERT INTO investimentos (nome, categoria, data, valor, quitado, obs) VALUES (?,?,?,?,?,?)");
    $st->execute([$nome, $categoria, $data, $valor, $quitado, $obs]);
    $investimentoId = (int)$pdo->lastInsertId();

    // Se foi cadastrado como quitado, lança no caixa
    if ($quitado == 1) {
      $dataObj = new DateTime($data);
      $valorCaixa = -1 * $valor; // Negativo porque é saída
      caixa_lancar($dataObj, 'INVESTIMENTO', 'investimentos', $investimentoId, $valorCaixa, 'Investimento: '.substr($nome, 0, 50));
    }
    
    $pdo->commit();
    header('Location: investimentos.php?msg=Investimento cadastrado');
    exit;
  } catch (Throwable $e) { 
    if ($pdo->inTransaction()) $pdo->rollBack();
    $err = $e->getMessage(); 
  }
}

$title = 'Novo investimento';
include __DIR__.'/layout_start.php';
?>
<?php if($err): ?><div class="alert"><?=htmlspecialchars($err)?></div><?php endif; ?>

<form class="card" method="post" autocomplete="off">
  <label>Nome</label>
  <input name="nome" required placeholder="Ex.: Prensa térmica 8x1">

  <label>Categoria (opcional)</label>
  <input name="categoria" placeholder="Ex.: Equipamento">

  <label>Data</label>
  <input type="date" name="data" value="<?=date('Y-m-d')?>" required>

  <label>Valor (R$)</label>
  <input name="valor" placeholder="ex: 1.850,00" required>

  <label><input type="checkbox" name="quitado"> Marcar como quitado</label>

  <label>Observações</label>
  <input name="obs" placeholder="opcional">

  <button type="submit">Salvar investimento</button>
</form>

<?php include __DIR__.'/layout_end.php'; ?>
