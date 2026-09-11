<?php
if (!function_exists('is_active')) {
  function is_active(string $file): string {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
    return basename($path) === $file ? 'active' : '';
  }
}
?>
<nav class="side-nav">
  <!-- Dashboard -->
  <a class="nav-btn nav-btn-featured <?=is_active('dashboard.php')?>" href="dashboard.php">
    <span class="icon">📊</span>
    <span class="label">Dashboard</span>
  </a>

  <!-- Vendas & Caixa -->
  <div class="nav-title">💰 Vendas & Caixa</div>
  <a class="nav-btn <?=is_active('vendas_nova_v3.php')?>" href="vendas_nova_v3.php">
    <span class="icon">➕</span>
    <span class="label">Nova Venda</span>
  </a>
  <a class="nav-btn <?=is_active('vendas.php')?>" href="vendas.php">
    <span class="icon">🛍️</span>
    <span class="label">Todas as Vendas</span>
  </a>
  <a class="nav-btn <?=is_active('caixa.php')?>" href="caixa.php">
    <span class="icon">💵</span>
    <span class="label">Fluxo de Caixa</span>
  </a>

  <!-- Clientes -->
  <div class="nav-title">👥 Clientes</div>
  <a class="nav-btn <?=is_active('clientes.php')?>" href="clientes.php">
    <span class="icon">📋</span>
    <span class="label">Lista de Clientes</span>
  </a>
  <a class="nav-btn <?=is_active('clientes_novo.php')?>" href="clientes_novo.php">
    <span class="icon">👤</span>
    <span class="label">Novo Cliente</span>
  </a>

  <!-- Produtos & Estoque -->
  <div class="nav-title">📦 Produtos & Estoque</div>
  <a class="nav-btn <?=is_active('produtos.php')?>" href="produtos.php">
    <span class="icon">🎁</span>
    <span class="label">Produtos</span>
  </a>
  <a class="nav-btn <?=is_active('materiais.php')?>" href="materiais.php">
    <span class="icon">📦</span>
    <span class="label">Materiais</span>
  </a>

  <!-- Gestão -->
  <div class="nav-title">⚙️ Gestão</div>
  <a class="nav-btn <?=is_active('perdas.php')?>" href="perdas.php">
    <span class="icon">⚠️</span>
    <span class="label">Perdas</span>
  </a>
  <a class="nav-btn <?=is_active('investimentos.php')?>" href="investimentos.php">
    <span class="icon">💎</span>
    <span class="label">Investimentos</span>
  </a>
  <a class="nav-btn <?=is_active('relatorios.php')?>" href="relatorios.php">
    <span class="icon">📊</span>
    <span class="label">Relatórios</span>
  </a>

  <!-- Logout -->
  <div class="nav-section-bottom">
    <a class="nav-btn nav-btn-logout" href="index.php?logout=1">
      <span class="icon">🚪</span>
      <span class="label">Sair</span>
    </a>
  </div>
</nav>
