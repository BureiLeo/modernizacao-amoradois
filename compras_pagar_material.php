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

  if ($materialId <= 0) {
    $qs = http_build_query(['err' => 'Material inválido']);
    header('Location: materiais.php?'.$qs);
    exit;
  }

  $st = $pdo->prepare("
    SELECT c.id, m.nome
    FROM compras c
    JOIN materiais m ON m.id = c.material_id
    WHERE c.material_id = ? AND c.pago = 0
    ORDER BY c.data ASC, c.id ASC
  ");
  $st->execute([$materialId]);
  $rows = $st->fetchAll();

  if (!$rows) {
    $qs = http_build_query(['err' => 'Nenhuma compra em aberto para este material']);
    header('Location: materiais.php?'.$qs);
    exit;
  }

  foreach ($rows as $r) {
    $pdo->prepare("UPDATE compras SET forma_pagamento = ? WHERE id = ?")
        ->execute([$fpag, (int)$r['id']]);
    atualizar_status_pagamento_compra((int)$r['id'], 1);
  }

  $qs = http_build_query(['msg' => 'Pagas '.count($rows).' compras do material: '.$rows[0]['nome']]);
  header('Location: materiais.php?'.$qs);
  exit;

} catch (Throwable $e) {
  $qs = http_build_query(['err' => 'Erro: '.$e->getMessage()]);
  header('Location: materiais.php?'.$qs);
  exit;
}
