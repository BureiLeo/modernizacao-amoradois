<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

// DEBUG: confirme que o auth.php carregou
if (!function_exists('auth_login')) {
  die('ERRO: auth.php não foi carregado. Verifique o nome/caminho do arquivo.');
}

if (isset($_GET['logout'])) { auth_logout(); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ok = auth_login($_POST['username'] ?? '', $_POST['senha'] ?? '');
  if ($ok) { header('Location: dashboard.php'); exit; }
  $error = 'Usuário ou senha inválidos.';
}
?>
<!doctype html>
<html lang="pt-br" class="theme-dark">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="dark">
  <title>Login — Lojinha de Canecas</title>
  <link rel="stylesheet" href="app.css?v=9">
  <style>
    body.login {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: linear-gradient(135deg, #0b1220 0%, #1e293b 100%);
      padding: 20px;
    }
    
    .login-card {
      background: var(--card);
      border: 1px solid var(--borda);
      border-radius: 16px;
      padding: 40px;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }
    
    .login-card h1 {
      text-align: center;
      margin-bottom: 30px;
      color: var(--txt);
      font-size: 28px;
      font-weight: 700;
    }
    
    .brand-header {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .brand-header .badge {
      font-size: 48px;
      margin-bottom: 10px;
    }
    
    .brand-header h2 {
      color: var(--txt);
      margin: 0 0 5px 0;
      font-size: 24px;
      font-weight: 700;
    }
    
    .brand-header .subtitle {
      color: var(--muted);
      font-size: 14px;
      margin: 0;
    }
    
    .login-card label {
      display: block;
      margin-bottom: 8px;
      color: var(--txt);
      font-weight: 600;
      font-size: 14px;
    }
    
    .login-card input {
      width: 100%;
      padding: 14px 16px;
      margin-bottom: 20px;
      border: 2px solid var(--borda);
      border-radius: 12px;
      background: var(--field-bg);
      color: var(--txt);
      font-size: 16px;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    
    .login-card input:focus {
      outline: none;
      border-color: var(--azul);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
    }
    
    .login-card button {
      width: 100%;
      padding: 16px;
      background: var(--azul);
      border: none;
      border-radius: 12px;
      color: white;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 10px;
    }
    
    .login-card button:hover {
      background: var(--azul-esc);
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
    }
    
    .login-card button:active {
      transform: translateY(0);
    }
    
    .alert {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid var(--err);
      color: var(--err);
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      text-align: center;
    }
  </style>
</head>
<body class="login">
  <div class="login-card">
    <div class="brand-header">
      <div class="badge">☕</div>
      <h2>Lojinha de Canecas</h2>
      <p class="subtitle">Sistema de Gestão</p>
    </div>
    
    <form method="post" autocomplete="off">
      <h1>Entrar</h1>
      
      <?php if($error): ?>
        <div class="alert"><?=htmlspecialchars($error)?></div>
      <?php endif; ?>
      
      <label>Usuário</label>
      <input name="username" type="text" required autocomplete="username" placeholder="Digite seu usuário">
      
      <label>Senha</label>
      <input name="senha" type="password" required autocomplete="current-password" placeholder="Digite sua senha">
      
      <button type="submit">Entrar</button>
    </form>
  </div>
</body>
</html>
