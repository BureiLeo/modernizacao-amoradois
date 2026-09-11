<?php
declare(strict_types=1);

// DEBUG TEMPORÁRIO (remova depois de testar)
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/helpers.php';
auth_require();

$pdo = db();

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $qs = http_build_query(['err' => 'Requisição inválida (use POST)']);
    header('Location: materiais.php?'.$qs);
    exit;
  }

  $materialId = (int)($_POST['material_id'] ?? 0);
  $fpag = $_POST['forma_pagamento'] ?? null;
  $redirect = $_GET['redirect'] ?? 'materiais';
  $redirectId = (int)($_GET['id'] ?? 0);

  if ($materialId <= 0) {
    $qs = http_build_query(['err' => 'Material inválido']);
    $url = $redirect === 'materiais_editar' && $redirectId > 0 
      ? "materiais_editar.php?id={$redirectId}&{$qs}"
      : "materiais.php?{$qs}";
    header('Location: '.$url);
    exit;
  }

  // Última compra em aberto do material
  $st = $pdo->prepare("
    SELECT c.id, m.nome
    FROM compras c
    JOIN materiais m ON m.id = c.material_id
    WHERE c.material_id = ? AND c.pago = 0
    ORDER BY c.data DESC, c.id DESC
    LIMIT 1
  ");
  $st->execute([$materialId]);
  $row = $st->fetch();

  if (!$row) {
    $qs = http_build_query(['err' => 'Nenhuma compra em aberto para este material']);
    $url = $redirect === 'materiais_editar' && $redirectId > 0 
      ? "materiais_editar.php?id={$redirectId}&{$qs}"
      : "materiais.php?{$qs}";
    header('Location: '.$url);
    exit;
  }

  // Define forma de pagamento (opcional) e marca como pago (baixa do caixa)
  $pdo->prepare("UPDATE compras SET forma_pagamento = ? WHERE id = ?")
      ->execute([$fpag, (int)$row['id']]);

  atualizar_status_pagamento_compra((int)$row['id'], 1);

  $qs = http_build_query(['msg' => 'Compra paga do material: '.$row['nome']]);
  $url = $redirect === 'materiais_editar' && $redirectId > 0 
    ? "materiais_editar.php?id={$redirectId}&{$qs}"
    : "materiais.php?{$qs}";
  header('Location: '.$url);
  eredirect = $_GET['redirect'] ?? 'materiais';
  $redirectId = (int)($_GET['id'] ?? 0);
  $qs = http_build_query(['err' => 'Erro: '.$e->getMessage()]);
  $url = $redirect === 'materiais_editar' && $redirectId > 0 
    ? "materiais_editar.php?id={$redirectId}&{$qs}"
    : "materiais.php?{$qs}";
  header('Location: '.$url
} catch (Throwable $e) {
  $qs = http_build_query(['err' => 'Erro: '.$e->getMessage()]);
  header('Location: materiais.php?'.$qs);
  exit;
}
