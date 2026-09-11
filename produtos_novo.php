<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

$err = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    $st = $pdo->prepare("INSERT INTO produtos (nome, sku, preco_sugerido, ativo) VALUES (?,?,?,1)");
    $st->execute([
      $_POST['nome'],
      $_POST['sku'] ?: null,
      (float)str_replace(',', '.', $_POST['preco_sugerido'])
    ]);
    header('Location: /produtos.php?msg=Produto cadastrado');
    exit;
  } catch (Throwable $e) { $err = $e->getMessage(); }
}

$title = 'Novo produto';
include __DIR__.'/layout_start.php';
?>
<?php if($err): ?><div class="alert"><?=htmlspecialchars($err)?></div><?php endif; ?>

<form class="card" method="post" autocomplete="off">
  <label>Nome</label>
  <input name="nome" required>

  <label>SKU (opcional)</label>
  <input name="sku">

  <label>Preço sugerido (R$)</label>
  <input name="preco_sugerido" placeholder="ex: 29,90" required>

  <button type="submit">Salvar</button>
</form>

<?php include __DIR__.'/layout_end.php'; ?>
