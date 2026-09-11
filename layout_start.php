<?php
require_once __DIR__.'/auth.php';
auth_require();
$title = $title ?? 'Lojinha de Canecas';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="color-scheme" content="light dark">
  <title><?=htmlspecialchars($title)?></title>
  
  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- CSS inline para garantir layout desktop -->
  <style>
    /* Garante que em desktop (>1024px) o layout seja mantido */
    @media (min-width: 1024px) {
      .layout {
        display: grid !important;
        grid-template-columns: 280px 1fr !important;
      }
      .side {
        position: sticky !important;
        top: 0 !important;
        height: 100vh !important;
      }
      /* Grids mantêm colunas lado a lado em desktop */
      .grid {
        grid-template-columns: repeat(3, 1fr) !important;
      }
      .grid-2 {
        grid-template-columns: repeat(2, 1fr) !important;
      }
      .grid-3 {
        grid-template-columns: repeat(3, 1fr) !important;
      }
      .grid-4 {
        grid-template-columns: repeat(4, 1fr) !important;
      }
    }
    /* Tablet mantém algumas colunas */
    @media (min-width: 769px) and (max-width: 1023px) {
      .layout {
        grid-template-columns: 240px 1fr !important;
      }
      .grid, .grid-2 {
        grid-template-columns: repeat(2, 1fr) !important;
      }
      .grid-3, .grid-4 {
        grid-template-columns: repeat(2, 1fr) !important;
      }
    }
  </style>
  
  <!-- Custom CSS - carrega por último para sobrescrever Bootstrap -->
  <link rel="stylesheet" href="app.css?v=20">
  <script>
    // Força tema claro sempre
    (function(){
      try{
        document.documentElement.classList.add('theme-light');
        document.documentElement.setAttribute('data-bs-theme', 'light');
        localStorage.setItem('theme', 'light');
      }catch(e){}
    })();
  </script>
</head>
  <body>
<?php include __DIR__.'/icons_sprite.php'; ?>

<div class="layout">
  <aside class="side" id="sidebar">
    <?php include __DIR__.'/menu.php'; ?>
  </aside>
  <!-- Área principal restaurada -->
  <main class="main">
    <div class="page-top">
      <h1><?=htmlspecialchars($title)?></h1>
      <div class="pill">Feito com Amor!</div>
    </div>
    <div class="page-content">
