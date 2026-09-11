<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

$msg = null; $err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $pdo = db();
    $st = $pdo->prepare('INSERT INTO users (nome, username, email, password_hash) VALUES (?,?,?,?)');
    $st->execute([
      $_POST['nome'], $_POST['username'], $_POST['email'],
      password_hash($_POST['senha'], PASSWORD_DEFAULT)
    ]);
    $msg = 'Usuário cadastrado! Você já pode fazer login.';
  } catch (Throwable $e) { $err = $e->getMessage(); }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Cadastrar — Lojinha</title>
  <link rel="stylesheet" href="app.css">
</head>
<body class="login">
  <form class="card" method="post">
    <h1>Novo usuário</h1>
    <?php if($msg): ?><div class="ok"><?=$msg?></div><?php endif; ?>
    <?php if($err): ?><div class="alert"><?=$err?></div><?php endif; ?>
    <label>Nome</label>
    <input name="nome" required>
    <label>Usuário</label>
    <input name="username" required>
    <label>E-mail</label>
    <input name="email" type="email">
    <label>Senha</label>
    <input name="senha" type="password" required>
    <button type="submit">Salvar</button>
  </form>
</body>
</html>
