<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/helpers.php';
auth_require();
$pdo = db();

$err = null;

// Verificar se a tabela clientes está configurada corretamente
try {
  $pdo->query("SELECT codigo FROM clientes LIMIT 1");
  $temCodigo = true;
} catch (PDOException $e) {
  $temCodigo = false;
}

if (!$temCodigo) {
  die('
    <h3>⚠️ Sistema de clientes não configurado</h3>
    <p>Execute o script de configuração primeiro:</p>
    <a href="clientes_setup.php" class="btn btn-primary">Configurar Sistema de Clientes</a>
  ');
}

// Buscar clientes
$clientes = $pdo->query("SELECT id, codigo, nome FROM clientes ORDER BY codigo")->fetchAll();
$cliente_padrao_id = null;
foreach($clientes as $c) {
  if ($c['nome'] === 'Consumidor Final') {
    $cliente_padrao_id = $c['id'];
    break;
  }
}

// Buscar produtos
$produtos = $pdo->query("SELECT id, nome, preco_sugerido FROM produtos ORDER BY nome")->fetchAll();

// Serviços/mão de obra (não baixa estoque)
$servicos = [
  ['id' => 'srv_personalizacao', 'nome' => 'Personalização de caixinha', 'preco' => 15.00],
  ['id' => 'srv_embalagem', 'nome' => 'Embalagem especial', 'preco' => 8.00],
  ['id' => 'srv_entrega', 'nome' => 'Entrega local', 'preco' => 10.00],
  ['id' => 'srv_outro', 'nome' => 'Outro serviço', 'preco' => 0.00],
];

if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    $itens_json = $_POST['itens_json'] ?? '[]';
    $itens = json_decode($itens_json, true);
    
    if (!$itens || count($itens) === 0) {
      throw new Exception('Adicione pelo menos um item à venda');
    }

    $cliente_id = (int)$_POST['cliente_id'] ?: null;
    $canal = $_POST['canal'] ?: null;
    $obs = $_POST['obs'] ?: null;
    $fpag = $_POST['forma_pagamento'] ?: null;
    $pago = isset($_POST['pago']) ? (int)$_POST['pago'] : 0;
    $data = new DateTime();

    $pdo->beginTransaction();

    // Criar cabeçalho da venda
    $stVenda = $pdo->prepare("
      INSERT INTO vendas (data, cliente_id, canal, obs, forma_pagamento, pago)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stVenda->execute([$data->format('Y-m-d H:i:s'), $cliente_id, $canal, $obs, $fpag, $pago]);
    $vendaId = (int)$pdo->lastInsertId();

    // Processar cada item
    foreach ($itens as $item) {
      $tipo = $item['tipo']; // 'produto' ou 'servico'
      $id = $item['id'];
      $qtd = max(1, (int)$item['qtd']);
      $preco = (float)$item['preco'];

      if ($tipo === 'produto') {
        // Produto: baixa estoque via BOM
        $produtoId = (int)$id;
        
        // Buscar BOM
        $stBom = $pdo->prepare("
          SELECT pb.material_id, pb.qtd_por_unidade, m.estoque, m.custo_medio
          FROM produto_bom pb
          JOIN materiais m ON m.id = pb.material_id
          WHERE pb.produto_id = ?
        ");
        $stBom->execute([$produtoId]);
        $bom = $stBom->fetchAll();

        $custo_unit = 0.0;
        $consumos = [];

        if ($bom) {
          foreach ($bom as $r) {
            $nec = (int)ceil($r['qtd_por_unidade'] * $qtd);
            if ((int)$r['estoque'] < $nec) {
              throw new Exception('Estoque insuficiente do material ID '.$r['material_id'].' para o produto ID '.$produtoId);
            }
            $custo_unit += (float)$r['qtd_por_unidade'] * (float)$r['custo_medio'];
            $consumos[] = ['mat'=>(int)$r['material_id'], 'qtd'=>$nec, 'cm'=>(float)$r['custo_medio']];
          }
        }

        $lucro_unit = $preco - $custo_unit;

        // Inserir item
        $stItem = $pdo->prepare("
          INSERT INTO venda_itens (venda_id, produto_id, qtd, preco_unit, custo_unit_calculado, lucro_unit)
          VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stItem->execute([$vendaId, $produtoId, $qtd, $preco, $custo_unit, $lucro_unit]);

        // Baixar estoque e gravar snapshot
        if ($consumos) {
          $stSnap = $pdo->prepare("
            INSERT INTO venda_consumos (venda_id, material_id, qtd_consumida, custo_unit_no_momento)
            VALUES (?, ?, ?, ?)
          ");
          foreach ($consumos as $c) {
            $pdo->prepare("UPDATE materiais SET estoque = estoque - ? WHERE id = ?")->execute([$c['qtd'], $c['mat']]);
            $stSnap->execute([$vendaId, $c['mat'], $c['qtd'], $c['cm']]);
          }
        }

      } else if ($tipo === 'servico') {
        // Serviço: não baixa estoque, insere com produto_id NULL
        $stItem = $pdo->prepare("
          INSERT INTO venda_itens (venda_id, produto_id, qtd, preco_unit, custo_unit_calculado, lucro_unit)
          VALUES (?, NULL, ?, ?, 0, ?)
        ");
        $stItem->execute([$vendaId, $qtd, $preco, $preco]);
      }
    }

    // Caixa: se pago na criação
    if ($pago) {
      $st = $pdo->prepare("SELECT IFNULL(SUM(qtd*preco_unit),0) t FROM venda_itens WHERE venda_id = ?");
      $st->execute([$vendaId]);
      $t = (float)($st->fetch()['t'] ?? 0);
      caixa_lancar($data, 'VENDA', 'vendas', $vendaId, $t, 'Venda #'.$vendaId);
    }

    $pdo->commit();
    header('Location: vendas.php?msg=Venda registrada com sucesso!&id='.$vendaId);
    exit;
  } catch (Throwable $e) { 
    if ($pdo->inTransaction()) $pdo->rollBack();
    $err = $e->getMessage(); 
  }
}

$title = 'Nova Venda (Múltiplos Itens)';
include __DIR__.'/layout_start.php';
?>

<style>
.venda-cart {
  display: grid;
  grid-template-columns: 1fr 400px !important;
  gap: 20px;
  margin-bottom: 20px;
}

@media (max-width: 768px) {
  .venda-cart {
    grid-template-columns: 1fr !important;
  }
  .item-controls {
    grid-template-columns: 1fr !important;
  }
  .form-grid {
    grid-template-columns: 1fr !important;
  }
}

.add-item-section {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.cart-section {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  position: sticky;
  top: 20px;
  max-height: calc(100vh - 40px);
  overflow-y: auto;
}

.item-selector {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.item-selector button {
  flex: 1;
  padding: 12px;
  border: 2px solid #ddd;
  background: white;
  color: #5d4e99;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s;
}

.item-selector button.active {
  border-color: #5d4e99;
  background: #5d4e99;
  color: white;
}

.item-selector button:hover {
  border-color: #5d4e99;
  background: #5d4e99;
  color: white;
}

.item-search-container {
  position: relative;
  margin-bottom: 15px;
}

.item-search-input {
  width: 100%;
  padding: 12px 40px 12px 12px;
  border: 2px solid #ddd;
  border-radius: 6px;
  font-size: 1em;
  transition: border-color 0.2s;
}

.item-search-input:focus {
  outline: none;
  border-color: #5d4e99;
}

.item-search-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #999;
  pointer-events: none;
}

.item-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  max-height: 300px;
  overflow-y: auto;
  background: white;
  border: 2px solid #5d4e99;
  border-radius: 6px;
  margin-top: 4px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  z-index: 1000;
  display: none;
}

.item-dropdown.show {
  display: block;
}

.item-option {
  padding: 12px;
  border-bottom: 1px solid #eee;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-left: 3px solid transparent;
}

.item-option:hover {
  background: #f8f7fc;
  border-left-color: #5d4e99;
}

.item-option:last-child {
  border-bottom: none;
}

.item-option-name {
  font-weight: 500;
}

.item-option-price {
  color: #5d4e99;
  font-weight: 600;
}

.dropdown-empty {
  padding: 20px;
  text-align: center;
  color: #999;
  font-size: 0.9em;
}

.item-controls {
  display: grid;
  grid-template-columns: 100px 1fr 120px !important;
  gap: 10px;
  margin-top: 15px;
}

.btn-add-cart {
  background: #5d4e99;
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: background 0.2s;
}

.btn-add-cart:hover {
  background: #4a3d7a;
}

.btn-add-cart:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.cart-items {
  margin-bottom: 20px;
  min-height: 100px;
}

.cart-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  border-bottom: 1px solid #eee;
  gap: 8px;
}

.cart-item-info {
  flex: 1;
  min-width: 0;
}

.cart-item-name {
  font-weight: 500;
  font-size: 0.9em;
  line-height: 1.3;
}

.cart-item-details {
  font-size: 0.8em;
  color: #666;
  margin-top: 2px;
}

.cart-item-price {
  font-weight: 600;
  color: #5d4e99;
  min-width: 70px;
  text-align: right;
  font-size: 0.9em;
  white-space: nowrap;
}

.cart-item-remove {
  background: #e74c3c;
  color: white;
  border: none;
  padding: 0;
  border-radius: 3px;
  cursor: pointer;
  font-size: 0.75em;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.cart-item-remove:hover {
  background: #c0392b;
}

.cart-empty {
  text-align: center;
  padding: 40px 20px;
  color: #999;
}

.cart-total {
  border-top: 2px solid #5d4e99;
  padding-top: 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 1.3em;
  font-weight: 600;
  margin-bottom: 20px;
}

.cart-total-value {
  color: #5d4e99;
}

.checkout-section {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr !important;
  gap: 15px;
  margin-bottom: 15px;
}

.form-full {
  grid-column: 1 / -1;
}

.badge {
  display: inline-block;
  padding: 2px 6px;
  border-radius: 3px;
  font-size: 0.7em;
  font-weight: 600;
  margin-left: 6px;
}

.badge-produto {
  background: #e8f5e9;
  color: #2e7d32;
}

.badge-servico {
  background: #e3f2fd;
  color: #1565c0;
}
</style>

<?php if($err): ?><div class="alert alert-danger"><?=htmlspecialchars($err)?></div><?php endif; ?>

<div class="venda-cart">
  <!-- Seção de adicionar itens -->
  <div class="add-item-section">
    <h3>Adicionar Itens</h3>
    
    <div class="item-selector">
      <button type="button" class="active" onclick="window.switchTab('produtos', event)">📦 Produtos</button>
      <button type="button" onclick="window.switchTab('servicos', event)">🛠️ Serviços</button>
    </div>

    <!-- Lista de Produtos -->
    <div id="tab-produtos" class="tab-content">
      <div class="item-search-container">
        <input 
          type="text" 
          class="item-search-input" 
          id="produto-search" 
          placeholder="🔍 Buscar produto..." 
          autocomplete="off"
        >
        <span class="item-search-icon">⌄</span>
        <div class="item-dropdown" id="produto-dropdown">
          <?php foreach($produtos as $p): ?>
            <div class="item-option" data-tipo="produto" data-id="<?=$p['id']?>" data-nome="<?=htmlspecialchars($p['nome'])?>" data-preco="<?=$p['preco_sugerido']?>">
              <span class="item-option-name"><?=htmlspecialchars($p['nome'])?></span>
              <span class="item-option-price">R$ <?=number_format((float)$p['preco_sugerido'],2,',','.')?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Lista de Serviços -->
    <div id="tab-servicos" class="tab-content" style="display:none;">
      <div class="item-search-container">
        <input 
          type="text" 
          class="item-search-input" 
          id="servico-search" 
          placeholder="🔍 Buscar serviço..." 
          autocomplete="off"
        >
        <span class="item-search-icon">⌄</span>
        <div class="item-dropdown" id="servico-dropdown">
          <?php foreach($servicos as $s): ?>
            <div class="item-option" data-tipo="servico" data-id="<?=htmlspecialchars($s['id'])?>" data-nome="<?=htmlspecialchars($s['nome'])?>" data-preco="<?=$s['preco']?>">
              <span class="item-option-name"><?=htmlspecialchars($s['nome'])?></span>
              <span class="item-option-price">R$ <?=number_format($s['preco'],2,',','.')?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="item-controls">
      <div>
        <label style="font-size: 0.9em; display: block; margin-bottom: 4px;">Qtd</label>
        <input type="number" id="item-qtd" value="1" min="1" style="width: 100%;">
      </div>
      <div>
        <label style="font-size: 0.9em; display: block; margin-bottom: 4px;">Preço Unit. (R$)</label>
        <input type="text" id="item-preco" placeholder="0,00" style="width: 100%;">
      </div>
      <div style="display: flex; align-items: flex-end;">
        <button type="button" class="btn-add-cart" onclick="window.addToCart()" id="btn-add" disabled>
          ➕ Adicionar
        </button>
      </div>
    </div>

    <div id="selected-info" style="margin-top: 15px; padding: 12px; background: #f0edff; border-left: 4px solid #5d4e99; border-radius: 6px; display: none;">
      <small style="color: #5d4e99; font-weight: 600;">✓ Item selecionado:</small>
      <div id="selected-name" style="font-weight: 600; margin-top: 4px; color: #333;"></div>
      <small style="color: #666; margin-top: 4px; display: block;">Ajuste quantidade e preço, depois clique em "Adicionar"</small>
    </div>
  </div>

  <!-- Carrinho -->
  <div class="cart-section">
    <h3>🛒 Carrinho</h3>
    
    <div class="cart-items" id="cart-items">
      <div class="cart-empty">
        <div style="font-size: 3em; margin-bottom: 10px;">🛒</div>
        Nenhum item adicionado
      </div>
    </div>

    <div class="cart-total" id="cart-total-section" style="display: none;">
      <span>Total:</span>
      <span class="cart-total-value" id="cart-total-value">R$ 0,00</span>
    </div>
  </div>
</div>

<!-- Formulário de finalização -->
<form method="post" id="form-checkout" class="checkout-section">
  <h3>Finalizar Venda</h3>
  
  <input type="hidden" name="itens_json" id="itens-json">

  <div class="form-grid">
    <div class="form-full">
      <label>Cliente *</label>
      <div style="position: relative;">
        <input 
          type="text" 
          id="cliente-busca" 
          placeholder="🔍 Digite o código ou nome do cliente..." 
          class="form-control"
          autocomplete="off">
        
        <div id="cliente-resultados" style="
          position: absolute;
          top: 100%;
          left: 0;
          right: 0;
          background: white;
          border: 1px solid #ddd;
          border-radius: 4px;
          max-height: 250px;
          overflow-y: auto;
          display: none;
          z-index: 1000;
          box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        "></div>
      </div>
      
      <select name="cliente_id" id="cliente-select" class="form-select" required style="display:none;">
        <option value="">— Selecione o cliente —</option>
        <?php foreach($clientes as $c): ?>
          <option value="<?=$c['id']?>" data-codigo="<?=$c['codigo']?>" data-nome="<?=htmlspecialchars($c['nome'])?>">
            #<?=$c['codigo']?> - <?=htmlspecialchars($c['nome'])?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <div id="cliente-resultado" style="margin-top: 8px; font-size: 14px; min-height: 24px;">
        <span id="cliente-selecionado" style="color: #999;">⚠️ Nenhum cliente selecionado</span>
      </div>
      
      <small class="text-muted">
        Não encontrou? 
        <a href="#" onclick="abrirNovoCliente(); return false;" style="color: #5d4e99; font-weight: 600;">
          ➕ Cadastrar novo cliente
        </a>
      </small>
    </div>
    
    <div>
      <label>Forma de pagamento *</label>
      <select name="forma_pagamento" required>
        <option value="">— selecione —</option>
        <option>PIX</option>
        <option>Dinheiro</option>
        <option>Débito</option>
        <option>Crédito</option>
        <option>Transferência</option>
        <option>Outro</option>
      </select>
    </div>

    <div>
      <label>Status do pagamento *</label>
      <select name="pago" required>
        <option value="1">Pago</option>
        <option value="0" selected>Em aberto</option>
      </select>
    </div>
  
    <div class="form-full">
      <label>Observações</label>
      <textarea name="obs" rows="3" placeholder="Informações adicionais sobre a venda..."></textarea>
    </div>
  </div>

  <button type="submit" class="btn-add-cart" style="width: 100%;" id="btn-finalizar" disabled>
    ✅ Finalizar Venda
  </button>
</form>

<script>
(function() {
  'use strict';
  
  let cart = [];
  let selectedItem = null;

  window.switchTab = function(tab, evt) {
    document.querySelectorAll('.item-selector button').forEach(btn => {
      btn.classList.remove('active');
    });
    if (evt && evt.target) {
      evt.target.classList.add('active');
    }

    document.querySelectorAll('.tab-content').forEach(content => {
      content.style.display = 'none';
    });
    const tabEl = document.getElementById('tab-' + tab);
    if (tabEl) {
      tabEl.style.display = 'block';
    }

    clearSelection();
  };

  function selectItem(elemento) {
    const tipo = elemento.getAttribute('data-tipo');
    const id = elemento.getAttribute('data-id');
    const nome = elemento.getAttribute('data-nome');
    const preco = parseFloat(elemento.getAttribute('data-preco')) || 0;
    
    selectedItem = { tipo, id, nome, preco };
    
    console.log('Item selecionado:', selectedItem);
    
    document.querySelectorAll('.item-option').forEach(el => {
      el.classList.remove('selected');
    });
    
    elemento.classList.add('selected');
    
    document.getElementById('item-preco').value = preco.toFixed(2).replace('.', ',');
    document.getElementById('item-qtd').value = '1';
    document.getElementById('btn-add').disabled = false;
    
    document.getElementById('selected-info').style.display = 'block';
    const badge = tipo === 'produto' 
      ? '<span class="badge badge-produto">Produto</span>' 
      : '<span class="badge badge-servico">Serviço</span>';
    
    document.getElementById('selected-name').innerHTML = '✓ ' + escapeHtml(nome) + badge;
  }

  // Adicionar eventos aos itens
  document.querySelectorAll('.item-option').forEach(el => {
    el.addEventListener('click', function() {
      selectItem(this);
    });
  });

  // Sistema de busca para produtos e serviços
  function setupItemSearch(inputId, dropdownId) {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    
    if (!input || !dropdown) return;
    
    const allItems = dropdown.querySelectorAll('.item-option');
    
    // Mostrar dropdown ao focar
    input.addEventListener('focus', function() {
      dropdown.classList.add('show');
      if (!this.value.trim()) {
        // Mostrar todos os itens
        allItems.forEach(item => item.style.display = 'flex');
      }
    });
    
    // Buscar ao digitar
    input.addEventListener('input', function() {
      const termo = this.value.toLowerCase().trim();
      dropdown.classList.add('show');
      
      if (!termo) {
        // Mostrar todos os itens
        allItems.forEach(item => item.style.display = 'flex');
        return;
      }
      
      let encontrou = false;
      allItems.forEach(item => {
        const nome = (item.getAttribute('data-nome') || '').toLowerCase();
        if (nome.indexOf(termo) >= 0) {
          item.style.display = 'flex';
          encontrou = true;
        } else {
          item.style.display = 'none';
        }
      });
      
      // Mostrar mensagem se não encontrou nada
      if (!encontrou) {
        let emptyMsg = dropdown.querySelector('.dropdown-empty');
        if (!emptyMsg) {
          emptyMsg = document.createElement('div');
          emptyMsg.className = 'dropdown-empty';
          dropdown.appendChild(emptyMsg);
        }
        emptyMsg.textContent = '❌ Nenhum item encontrado com "' + termo + '"';
        emptyMsg.style.display = 'block';
      } else {
        const emptyMsg = dropdown.querySelector('.dropdown-empty');
        if (emptyMsg) emptyMsg.style.display = 'none';
      }
    });
    
    // Selecionar item ao clicar
    allItems.forEach(item => {
      item.addEventListener('click', function() {
        selectItem(this);
        input.value = this.getAttribute('data-nome');
        dropdown.classList.remove('show');
      });
    });
    
    // Fechar dropdown ao clicar fora
    document.addEventListener('click', function(e) {
      if (!input.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('show');
      }
    });
  }
  
  // Configurar busca para produtos e serviços
  setupItemSearch('produto-search', 'produto-dropdown');
  setupItemSearch('servico-search', 'servico-dropdown');

  // Função para abrir popup de novo cliente
  window.abrirNovoCliente = function() {
    const nome = prompt('Nome do cliente:');
    if (!nome || !nome.trim()) return;
    
    const telefone = prompt('Telefone (opcional):') || '';
    
    // Enviar via AJAX
    fetch('clientes_novo_ajax.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'nome=' + encodeURIComponent(nome) + '&telefone=' + encodeURIComponent(telefone)
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        // Adicionar novo cliente ao select
        const select = document.getElementById('cliente-select');
        const option = document.createElement('option');
        option.value = data.id;
        option.setAttribute('data-codigo', data.codigo || '');
        option.setAttribute('data-nome', nome);
        option.text = '#' + (data.codigo || data.id) + ' - ' + nome;
        select.add(option);
        
        // Selecionar o novo cliente automaticamente
        selecionarCliente(data.id);
        
        alert('✅ Cliente cadastrado com sucesso! Código: #' + (data.codigo || data.id));
      } else {
        alert('❌ Erro: ' + data.error);
      }
    })
    .catch(e => alert('❌ Erro ao cadastrar cliente'));
  };

  // Busca inteligente de cliente com dropdown
  const clienteBuscaInput = document.getElementById('cliente-busca');
  const clienteSelect = document.getElementById('cliente-select');
  const clienteSelecionado = document.getElementById('cliente-selecionado');
  const clienteResultados = document.getElementById('cliente-resultados');
  
  // Função global para selecionar cliente
  window.selecionarCliente = function(id) {
    clienteSelect.value = id;
    
    // Pegar o texto da opção selecionada
    const option = clienteSelect.options[clienteSelect.selectedIndex];
    const texto = option ? option.text : '';
    
    clienteSelecionado.innerHTML = '<strong style="color: #5d4e99;">✓ ' + escapeHtml(texto) + '</strong>';
    clienteBuscaInput.value = '';
    clienteResultados.style.display = 'none';
    clienteResultados.innerHTML = '';
  };
  
  if (clienteBuscaInput && clienteSelect && clienteResultados) {
    clienteBuscaInput.addEventListener('input', function() {
      const termo = this.value.toLowerCase().trim();
      
      if (!termo) {
        clienteResultados.style.display = 'none';
        clienteResultados.innerHTML = '';
        return;
      }
      
      // Buscar no select e coletar resultados
      const resultados = [];
      for (let i = 0; i < clienteSelect.options.length; i++) {
        const option = clienteSelect.options[i];
        if (!option.value) continue; // pular opção vazia
        
        const codigo = (option.getAttribute('data-codigo') || '').toLowerCase();
        const nome = (option.getAttribute('data-nome') || '').toLowerCase();
        
        // Buscar por código (com ou sem #) ou nome
        if (codigo === termo || ('#' + codigo) === termo || nome.indexOf(termo) >= 0) {
          resultados.push({
            id: option.value,
            texto: option.text
          });
        }
      }
      
      // Mostrar resultados
      if (resultados.length > 0) {
        let html = '';
        resultados.forEach(r => {
          html += '<div class="cliente-resultado-item" data-id="' + r.id + '" style="padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0;">' + escapeHtml(r.texto) + '</div>';
        });
        clienteResultados.innerHTML = html;
        clienteResultados.style.display = 'block';
        
        // Adicionar evento de clique em cada resultado
        const items = clienteResultados.querySelectorAll('.cliente-resultado-item');
        items.forEach(item => {
          item.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f5f5f5';
          });
          item.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'white';
          });
          item.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            selecionarCliente(id);
          });
        });
      } else {
        clienteResultados.innerHTML = '<div style="padding: 15px; color: #999; text-align: center;">❌ Nenhum cliente encontrado com "' + escapeHtml(termo) + '"</div>';
        clienteResultados.style.display = 'block';
        clienteSelecionado.innerHTML = '<span style="color: #999;">⚠️ Nenhum cliente selecionado</span>';
        clienteSelect.value = '';
      }
    });
    
    // Fechar resultados ao clicar fora
    document.addEventListener('click', function(e) {
      if (!clienteBuscaInput.contains(e.target) && !clienteResultados.contains(e.target)) {
        clienteResultados.style.display = 'none';
      }
    });
    
    // Focar no input ao clicar
    clienteBuscaInput.addEventListener('focus', function() {
      if (this.value.trim()) {
        // Re-mostrar resultados se já tinha algo digitado
        this.dispatchEvent(new Event('input'));
      }
    });
  }

  function clearSelection() {
    selectedItem = null;
    document.querySelectorAll('.item-option').forEach(el => {
      el.classList.remove('selected');
    });
    document.getElementById('item-preco').value = '';
    document.getElementById('item-qtd').value = '1';
    document.getElementById('btn-add').disabled = true;
    document.getElementById('selected-info').style.display = 'none';
  }

  window.addToCart = function() {
    if (!selectedItem) {
      alert('Selecione um item primeiro!');
      return;
    }

    const qtd = parseInt(document.getElementById('item-qtd').value) || 1;
    const precoStr = document.getElementById('item-preco').value.replace(',', '.');
    const preco = parseFloat(precoStr) || 0;

    if (preco <= 0) {
      alert('Informe um preço válido');
      return;
    }

    const item = {
      tipo: selectedItem.tipo,
      id: selectedItem.id,
      nome: selectedItem.nome,
      qtd: qtd,
      preco: preco,
      total: qtd * preco
    };

    cart.push(item);
    console.log('Item adicionado ao carrinho:', item);
    renderCart();
    clearSelection();
  };

  window.removeFromCart = function(index) {
    cart.splice(index, 1);
    renderCart();
  };

  function renderCart() {
    const container = document.getElementById('cart-items');
    const totalSection = document.getElementById('cart-total-section');
    const totalValue = document.getElementById('cart-total-value');
    const btnFinalizar = document.getElementById('btn-finalizar');

    if (cart.length === 0) {
      container.innerHTML = `
        <div class="cart-empty">
          <div style="font-size: 3em; margin-bottom: 10px;">🛒</div>
          Nenhum item adicionado
        </div>
      `;
      totalSection.style.display = 'none';
      btnFinalizar.disabled = true;
      return;
    }

    let total = 0;
    let html = '';

    cart.forEach((item, index) => {
      total += item.total;
      
      html += `
        <div class="cart-item">
          <div class="cart-item-info">
            <div class="cart-item-name">${escapeHtml(item.nome)}</div>
            <div class="cart-item-details">
              ${item.qtd}x R$ ${formatMoney(item.preco)}
            </div>
          </div>
          <div class="cart-item-price">R$ ${formatMoney(item.total)}</div>
          <button type="button" class="cart-item-remove" onclick="window.removeFromCart(${index})">✕</button>
        </div>
      `;
    });

    container.innerHTML = html;
    totalSection.style.display = 'flex';
    totalValue.textContent = 'R$ ' + formatMoney(total);
    btnFinalizar.disabled = false;

    document.getElementById('itens-json').value = JSON.stringify(cart);
  }

  function formatMoney(value) {
    return value.toFixed(2).replace('.', ',');
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
    
    // Busca de cliente por código/nome/telefone
    const buscaInput = document.getElementById('cliente-busca');
    const select = document.getElementById('cliente-select');
    
    if (buscaInput && select) {
      buscaInput.addEventListener('input', function() {
        const termo = this.value.toLowerCase().trim();
        if (!termo) return;
        
        const options = select.querySelectorAll('option');
        
        for (let opt of options) {
          const codigo = opt.getAttribute('data-codigo') || '';
          const nome = opt.getAttribute('data-nome') || '';
          const telefone = opt.getAttribute('data-telefone') || '';
          const textoCompleto = codigo + ' ' + nom
          if (textoCompleto.toLowerCase().includes(termo) || 
              termo === codigo ||
              termo === '#' + codigo) {
            select.value = opt.value;
            break;
          }
        }
      });
    }
  }

  // Adicionar event listeners aos itens
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.item-option').forEach(el => {
      el.addEventListener('click', function() {
        selectItem(this);
      });
    });

    document.getElementById('form-checkout').addEventListener('submit', function(e) {
      if (cart.length === 0) {
        e.preventDefault();
        alert('Adicione pelo menos um item à venda');
      }
    });
  });
})();
</script>

<?php include __DIR__.'/layout_end.php'; ?>
