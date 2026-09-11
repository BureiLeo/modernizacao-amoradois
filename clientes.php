<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

$msg = $_GET['msg'] ?? null;

// Buscar todos os clientes
$rows = $pdo->query("
  SELECT id, codigo, nome, telefone, email, cpf, cnpj, criado_em
  FROM clientes
  ORDER BY codigo
")->fetchAll();

$title = 'Clientes';
include __DIR__.'/layout_start.php';
?>

<style>
.clientes-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  gap: 15px;
}

.clientes-header h2 {
  margin: 0;
  font-size: 28px;
  font-weight: 800;
  background: linear-gradient(135deg, #5d4e99, #7d6eb9);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.clientes-header .btn-primary {
  padding: 10px 12px;
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  transition: all 0.2s;
}

.clientes-header .btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(93, 78, 153, 0.3);
}

.clientes-stats {
  font-size: 13px;
  color: #666;
  margin-top: 5px;
}

.cliente-card {
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  padding: 3px 0;
  margin-bottom: 1px;
  display: grid;
  grid-template-columns: 30px 1fr auto;
  gap: 6px;
  align-items: center;
  transition: all 0.2s;
}

.cliente-card:nth-child(even) {
  background: #f5f3ff;
}

.cliente-card:hover {
  border-color: #5d4e99;
  box-shadow: 0 2px 6px rgba(93, 78, 153, 0.1);
}

.cliente-codigo {
  background: #5d4e99;
  color: white;
  font-weight: bold;
  font-size: 11px;
  width: 28px;
  height: 28px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cliente-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 0;
}

.cliente-nome {
  font-size: 13px;
  font-weight: 600;
  color: #333;
  line-height: 1.2;
}

.cliente-detalhes {
  display: flex;
  gap: 10px;
  font-size: 10px;
  color: #666;
  line-height: 1.2;
}

.cliente-detalhes span {
  display: flex;
  align-items: center;
  gap: 3px;
}

.cliente-acoes {
  display: flex;
  gap: 6px;
  padding: 0 6px;
  align-items: center;
  justify-content: center;
}

.cliente-acoes .btn {
  font-size: 11px;
  padding: 4px 8px;
  white-space: nowrap;
}

@media (max-width: 768px) {
  .cliente-card {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  
  .clientes-header {
    flex-direction: column;
    align-items: stretch;
  }
  
  .cliente-detalhes {
    flex-direction: column;
    gap: 3px;
  }
}
</style>

<?php if($msg): ?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div class="clientes-header">
  <div>
    <h2>👥 Clientes</h2>
    <div class="clientes-stats">
      📊 Total: <?=count($rows)?> clientes cadastrados
    </div>
  </div>
  <a class="btn btn-primary" href="clientes_novo.php">➕ Novo Cliente</a>
</div>

<div style="margin-bottom: 15px;">
  <input 
    type="text" 
    id="searchClientes" 
    placeholder="🔍 Buscar por nome, telefone ou CPF..." 
    style="width: 100%; padding: 10px 15px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px;"
  >
</div>

<div class="card">
  <?php if(!$rows): ?>
    <div style="text-align: center; padding: 40px; color: #999;">
      <div style="font-size: 4em; margin-bottom: 15px;">📋</div>
      <p style="font-size: 18px; margin-bottom: 10px;">Nenhum cliente cadastrado</p>
      <p style="font-size: 14px;">Cadastre seu primeiro cliente para começar!</p>
    </div>
  <?php else: 
    foreach($rows as $r):
      $doc = $r['cpf'] ?: ($r['cnpj'] ?: '');
      $docLabel = $r['cpf'] ? '📄 CPF' : ($r['cnpj'] ? '🏢 CNPJ' : '');
  ?>
    <div class="cliente-card">
      <div class="cliente-codigo">#<?=$r['codigo']?></div>
      
      <div class="cliente-info">
        <div class="cliente-nome"><?=htmlspecialchars($r['nome'])?></div>
        <div class="cliente-detalhes">
          <?php if($doc): ?>
            <span><?=$docLabel?>: <?=htmlspecialchars($doc)?></span>
          <?php endif; ?>
          <?php if($r['telefone']): ?>
            <span>📱 <?=htmlspecialchars($r['telefone'])?></span>
          <?php endif; ?>
          <?php if($r['email']): ?>
            <span>✉️ <?=htmlspecialchars($r['email'])?></span>
          <?php endif; ?>
          <span>📅 <?=date('d/m/Y', strtotime($r['criado_em']))?></span>
        </div>
      </div>
      
      <div class="cliente-acoes">
        <a href="clientes_editar.php?id=<?=$r['id']?>" class="btn btn-sm btn-secondary">✏️ Editar</a>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<script>
document.getElementById('searchClientes').addEventListener('input', function(e) {
  const searchTerm = e.target.value.toLowerCase().trim();
  const cards = document.querySelectorAll('.cliente-card');
  
  cards.forEach(card => {
    const nome = card.querySelector('.cliente-nome')?.textContent.toLowerCase() || '';
    const detalhes = card.querySelector('.cliente-detalhes')?.textContent.toLowerCase() || '';
    
    if (nome.includes(searchTerm) || detalhes.includes(searchTerm)) {
      card.style.display = 'grid';
    } else {
      card.style.display = 'none';
    }
  });
});
</script>

<?php include __DIR__.'/layout_end.php'; ?>