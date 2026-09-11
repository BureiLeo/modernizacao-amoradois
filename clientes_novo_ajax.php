<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();

header('Content-Type: application/json');

try {
  $pdo = db();
  
  $nome = trim($_POST['nome'] ?? '');
  $telefone = trim($_POST['telefone'] ?? '');
  
  if (!$nome) {
    throw new Exception('Nome é obrigatório');
  }
  
  // Pegar próximo código
  $maxCodigo = $pdo->query("SELECT IFNULL(MAX(codigo), 0) + 1 AS proximo FROM clientes")->fetch();
  $codigo = (int)$maxCodigo['proximo'];
  
  $st = $pdo->prepare("
    INSERT INTO clientes (codigo, nome, telefone)
    VALUES (?, ?, ?)
  ");
  $st->execute([$codigo, $nome, $telefone]);
  
  $id = (int)$pdo->lastInsertId();
  
  echo json_encode([
    'success' => true,
    'id' => $id,
    'codigo' => $codigo,
    'nome' => $nome,
    'telefone' => $telefone
  ]);
  
} catch (Throwable $e) {
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}
?>
