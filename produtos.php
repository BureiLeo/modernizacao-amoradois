<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

$rows = $pdo->query("
  SELECT p.*,
         IFNULL((
           SELECT SUM(pb.qtd_por_unidade * m.custo_medio)
           FROM produto_bom pb
           JOIN materiais m ON m.id = pb.material_id
           WHERE pb.produto_id = p.id
         ), 0) AS custo_unit
  FROM produtos p
  ORDER BY p.nome
")->fetchAll();

$msg = $_GET['msg'] ?? null;

$title = 'Produtos';
include __DIR__.'/layout_start.php';
?>

<style>
.produtos-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  gap: 15px;
}

.produtos-header h2 {
  margin: 0;
  font-size: 28px;
  font-weight: 800;
  background: linear-gradient(135deg, #5d4e99, #7d6eb9);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.produtos-stats {
  font-size: 13px;
  color: #666;
  margin-top: 5px;
}

.produto-card {
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  padding: 3px 0;
  margin-bottom: 1px;
  display: grid;
  grid-template-columns: 1fr 120px 120px 120px 120px auto;
  gap: 6px;
  align-items: center;
  transition: all 0.2s;
}

.produto-card:nth-child(even) {
  background: #f5f3ff;
}

.produto-card:hover {
  border-color: #5d4e99;
  box-shadow: 0 2px 6px rgba(93, 78, 153, 0.1);
}

.produto-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 0 10px;
}

.produto-nome {
  font-size: 13px;
  font-weight: 600;
  color: #333;
  line-height: 1.2;
}

.produto-sku {
  font-size: 10px;
  color: #666;
  line-height: 1.2;
}

.produto-valor {
  font-size: 13px;
  font-weight: 600;
  color: #333;
  padding: 0 10px;
}

.produto-valor.custo {
  font-size: 11px;
  color: #666;
}

.produto-valor.margem {
  color: #16a34a;
}

.produto-acoes {
  display: flex;
  gap: 6px;
  padding: 0 10px;
  align-items: center;
  justify-content: center;
}

.produto-acoes .btn {
  font-size: 11px;
  padding: 4px 8px 20px 8px;
  white-space: nowrap;
}

@media (max-width: 768px) {
  .produto-card {
    grid-template-columns: 1fr;
    gap: 8px;
    padding: 10px;
  }
  
  .produtos-header {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>

<?php if($msg): ?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div class="produtos-header">
  <div>
    <h2>🎁 Produtos</h2>
    <div class="produtos-stats">
      📊 Total: <?=count($rows)?> produtos cadastrados
    </div>
  </div>
  <a class="btn btn-primary" href="produtos_novo.php">➕ Novo Produto</a>
</div>

<div style="margin-bottom: 15px;">
  <input 
    type="text" 
    id="searchProdutos" 
    placeholder="🔍 Buscar por nome ou SKU..." 
    style="width: 100%; padding: 10px 15px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px;"
  >
</div>

<div class="card">
  <?php if(!$rows): ?>
    <div style="text-align: center; padding: 40px; color: #999;">
      <div style="font-size: 4em; margin-bottom: 15px;">🎁</div>
      <p style="font-size: 18px; margin-bottom: 10px;">Nenhum produto cadastrado</p>
      <p style="font-size: 14px;">Cadastre seu primeiro produto para começar!</p>
    </div>
  <?php else: ?>
    <!-- Cabeçalho das colunas -->
    <div style="display: grid; grid-template-columns: 1fr 120px 120px 120px 120px auto; gap: 6px; padding: 8px 0; border-bottom: 2px solid #e0e0e0; margin-bottom: 5px; font-weight: 600; font-size: 11px; color: #666; text-transform: uppercase;">
      <div style="padding: 0 10px;">Produto</div>
      <div style="padding: 0 10px;">Preço Sug.</div>
      <div style="padding: 0 10px;">Custo Unit.</div>
      <div style="padding: 0 10px;">Margem</div>
      <div style="padding: 0 10px;"></div>
      <div style="padding: 0 10px; text-align: center;">Ações</div>
    </div>
    
    <?php foreach($rows as $r):
      $margem = (float)$r['preco_sugerido'] - (float)$r['custo_unit'];
    ?>
    <div class="produto-card">
      <div class="produto-info">
        <div class="produto-nome"><?=htmlspecialchars($r['nome'])?></div>
        <div class="produto-sku">SKU: <?=htmlspecialchars($r['sku'])?></div>
      </div>
      
      <div class="produto-valor">
        R$ <?=number_format((float)$r['preco_sugerido'],2,',','.')?>
      </div>
      
      <div class="produto-valor custo">
        R$ <?=number_format((float)$r['custo_unit'],2,',','.')?>
      </div>
      
      <div class="produto-valor margem">
        R$ <?=number_format($margem,2,',','.')?>
      </div>
      
      <div class="produto-acoes">
        <a href="produtos_editar.php?id=<?=$r['id']?>" class="btn btn-sm btn-secondary">✏️ Editar</a>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<script>
document.getElementById('searchProdutos').addEventListener('input', function(e) {
  const searchTerm = e.target.value.toLowerCase().trim();
  const cards = document.querySelectorAll('.produto-card');
  
  cards.forEach(card => {
    const nome = card.querySelector('.produto-nome')?.textContent.toLowerCase() || '';
    const sku = card.querySelector('.produto-sku')?.textContent.toLowerCase() || '';
    
    if (nome.includes(searchTerm) || sku.includes(searchTerm)) {
      card.style.display = 'grid';
    } else {
      card.style.display = 'none';
    }
  });
});
</script>

<?php include __DIR__.'/layout_end.php'; ?>
