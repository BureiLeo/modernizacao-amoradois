<?php
require_once __DIR__.'/db.php';

if (session_status() === PHP_SESSION_NONE) {
  // Se precisar, crie uma pasta /sessions e descomente a linha abaixo:
  // ini_set('session.save_path', __DIR__ . '/sessions');
  session_start();
}

function auth_require() {
  if (empty($_SESSION['user'])) {
    header('Location: /index.php?auth=required');
    exit;
  }
}

function auth_login(string $username, string $password): bool {
  $pdo = db();
  $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');
  $st->execute([$username]);
  $u = $st->fetch();
  if ($u && password_verify($password, $u['password_hash'])) {
    $_SESSION['user'] = ['id'=>$u['id'], 'nome'=>$u['nome'], 'username'=>$u['username']];
    return true;
  }
  return false;
}

function auth_logout() {
  $_SESSION = [];
  if (session_id()) { session_destroy(); }
  header('Location: /index.php?logout=1');
  exit;
}

function auth_user() {
  return $_SESSION['user'] ?? null;
}
