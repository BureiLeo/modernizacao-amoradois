<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/helpers.php';
auth_require();

$pdo = db();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: vendas.php'); exit; }

$err = null;

/* ====== AÇÃO: EXCLUIR ====== */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '') === 'delete') {
  try {
    excluir_venda($id);
    header('Location: vendas.php?msg=Venda excluída');
    exit;
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

/* ====== AÇÃO: SALVAR EDIÇÃO ====== */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '') === 'save') {
  try {
    $dataStr = $_POST['data'];
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $dataStr);
    if (!$dt) $dt = new DateTime($dataStr);

    $clienteId = (int)$_POST['cliente_id'] ?: null;
    $fpag = $_POST['forma_pagamento'] ?: null;
    
    // IMPORTANTE: garantir que o campo pago está sendo recebido corretamente
    $pago = isset($_POST['pago']) ? (int)$_POST['pago'] : 0;
    
    $canal = $_POST['canal'] ?: null;
    $obs = $_POST['obs'] ?: null;
    
    // DEBUG temporário - pode remover depois
    error_log("DEBUG VENDA #$id - Status recebido: pago=" . var_export($_POST['pago'] ?? 'NÃO ENVIADO', true) . " | Valor final: $pago");
    
    // Pegar itens do JSON
    $itensJson = $_POST['itens_json'] ?? '[]';
    $itens = json_decode($itensJson, true);
    
    if (!$itens || count($itens) === 0) {
      throw new Exception('Adicione pelo menos um item à venda');
    }

    // Verificar status de pagamento anterior
    $stOld = $pdo->prepare("SELECT pago FROM vendas WHERE id = ?");
    $stOld->execute([$id]);
    $vendaAnterior = $stOld->fetch();
    $pagoAnterior = (int)($vendaAnterior['pago'] ?? 0);

    $pdo->beginTransaction();

    // Atualizar dados da venda (COM o campo pago)
    $st = $pdo->prepare("
      UPDATE vendas 
      SET data = ?, cliente_id = ?, forma_pagamento = ?, canal = ?, obs = ?, pago = ?
      WHERE id = ?
    ");
    $result = $st->execute([$dt->format('Y-m-d H:i:s'), $clienteId, $fpag, $canal, $obs, $pago, $id]);
    
    // DEBUG: verificar se o UPDATE funcionou
    error_log("DEBUG VENDA #$id - UPDATE executado: " . ($result ? 'SIM' : 'NÃO') . " | Linhas afetadas: " . $st->rowCount());

    // Excluir itens antigos
    $pdo->prepare("DELETE FROM venda_itens WHERE venda_id = ?")->execute([$id]);
    
    // Inserir novos itens
    foreach ($itens as $item) {
      $tipo = $item['tipo'];
      $itemId = $item['id'];
      $qtd = max(1, (int)$item['qtd']);
      $preco = (float)$item['preco'];
      
      if ($tipo === 'produto') {
        $produtoId = (int)$itemId;
        $stItem = $pdo->prepare("
          INSERT INTO venda_itens (venda_id, produto_id, qtd, preco_unit, custo_unit_calculado, lucro_unit)
          VALUES (?, ?, ?, ?, 0, 0)
        ");
        $stItem->execute([$id, $produtoId, $qtd, $preco]);
      } else {
        $stItem = $pdo->prepare("
          INSERT INTO venda_itens (venda_id, produto_id, qtd, preco_unit, custo_unit_calculado, lucro_unit)
          VALUES (?, NULL, ?, ?, 0, ?)
        ");
        $stItem->execute([$id, $qtd, $preco, $preco]);
      }
    }

    // Calcular total da venda
    $stTotal = $pdo->prepare("SELECT IFNULL(SUM(qtd*preco_unit),0) t FROM venda_itens WHERE venda_id = ?");
    $stTotal->execute([$id]);
    $totalVenda = (float)($stTotal->fetch()['t'] ?? 0);
    
    // Atualizar caixa: sempre remover lançamento antigo primeiro
    caixa_apagar_por_ref('VENDA', 'vendas', $id);
    
    // Se está pago, adicionar novo lançamento com o valor atualizado
    if ($pago) {
      caixa_lancar($dt, 'VENDA', 'vendas', $id, $totalVenda, 'Venda #'.$id);
    }
    
    // DEBUG: Verificar se o pago está correto antes do commit
    $stVerify = $pdo->prepare("SELECT pago FROM vendas WHERE id = ?");
    $stVerify->execute([$id]);
    $vendaVerify = $stVerify->fetch();
    error_log("DEBUG VENDA #$id - Valor do campo 'pago' ANTES do commit: " . $vendaVerify['pago']);

    $pdo->commit();
    
    // DEBUG: Verificar após commit
    $stVerify2 = $pdo->prepare("SELECT pago FROM vendas WHERE id = ?");
    $stVerify2->execute([$id]);
    $vendaVerify2 = $stVerify2->fetch();
    error_log("DEBUG VENDA #$id - Valor do campo 'pago' APÓS commit: " . $vendaVerify2['pago'] . " | Esperado: $pago");

    header('Location: vendas.php?msg=' . urlencode('Venda atualizada com sucesso! Status: ' . ($pago ? 'PAGO' : 'EM ABERTO')));
    exit;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $err = $e->getMessage();
  }
}

/* ====== Carrega venda + itens para editar ====== */
$st = $pdo->prepare("
  SELECT v.id, v.data, v.canal, v.obs, v.forma_pagamento, v.pago, v.cliente_id
  FROM vendas v
  WHERE v.id = ?
");
$st->execute([$id]);
$venda = $st->fetch();
if (!$venda) { header('Location: vendas.php'); exit; }

// DEBUG: Verificar se cliente está sendo carregado
error_log("DEBUG VENDA #$id - Cliente ID carregado: " . ($venda['cliente_id'] ?? 'NULL'));

// Buscar itens da venda
$stItens = $pdo->prepare("
  SELECT vi.id, vi.produto_id, vi.qtd, vi.preco_unit,
         COALESCE(p.nome, 'Serviço') AS item_nome
  FROM venda_itens vi
  LEFT JOIN produtos p ON p.id = vi.produto_id
  WHERE vi.venda_id = ?
");
$stItens->execute([$id]);
$itens = $stItens->fetchAll();

// Calcular total
$total = 0;
$itensEditaveis = [];
foreach($itens as $item) {
  $total += $item['qtd'] * $item['preco_unit'];
  
  // Preparar formato para o JavaScript
  $itensEditaveis[] = [
    'tipo' => $item['produto_id'] ? 'produto' : 'servico',
    'id' => $item['produto_id'] ?: 'srv_' . $item['id'],
    'nome' => $item['item_nome'],
    'qtd' => (int)$item['qtd'],
    'preco' => (float)$item['preco_unit'],
    'total' => $item['qtd'] * $item['preco_unit']
  ];
}

// Buscar clientes
$clientes = $pdo->query("SELECT id, codigo, nome FROM clientes ORDER BY codigo")->fetchAll();

// Buscar produtos
$produtos = $pdo->query("SELECT id, nome, preco_sugerido FROM produtos ORDER BY nome")->fetchAll();

// Serviços
$servicos = [
  ['id' => 'srv_personalizacao', 'nome' => 'Personalização de caixinha', 'preco' => 15.00],
  ['id' => 'srv_embalagem', 'nome' => 'Embalagem especial', 'preco' => 8.00],
  ['id' => 'srv_entrega', 'nome' => 'Entrega local', 'preco' => 10.00],
  ['id' => 'srv_outro', 'nome' => 'Outro serviço', 'preco' => 0.00],
];

$title = 'Editar venda #'.$id;
include __DIR__.'/layout_start.php';
?>

<style>
/* Reutilizar estilos da página vendas_nova_v3.php */
.venda-edit-container {
  max-width: 1400px;
  margin: 0 auto;
}

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

.tab-content {
  display: none;
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
  border-left: 3px solid transparent;
}

.item-option:hover {
  background: #f8f7fc;
  border-left-color: #5d4e99;
}

.item-option:last-child {
  border-bottom: none;
}

.dropdown-empty {
  padding: 20px;
  text-align: center;
  color: #999;
  font-size: 0.9em;
}

.cart-item {
  display: flex;
  gap: 8px;
  padding: 10px;
  margin-bottom: 8px;
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  align-items: flex-start;
  font-size: 13px;
}

.cart-item input {
  font-size: 13px;
}

.cart-item-remove {
  background: #e53e3e;
  color: white;
  border: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  cursor: pointer;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}

.danger-zone {
  background: #fff5f5;
  border: 2px solid #fc8181;
  padding: 20px;
  border-radius: 8px;
  margin-top: 30px;
}

.btn-danger {
  background: #e53e3e !important;
  color: white !important;
  border: none !important;
  padding: 10px 20px !important;
  border-radius: 6px !important;
  cursor: pointer !important;
  font-weight: 600 !important;
}
</style>

<?php if($err): ?><div class="alert alert-danger"><?=htmlspecialchars($err)?></div><?php endif; ?>

<div class="venda-edit-container">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
      <h2 style="margin: 0;">✏️ Editar Venda #<?=$id?></h2>
      <p style="margin: 5px 0 0 0; color: #666;">Altere itens, cliente e dados da venda</p>
      <!-- DEBUG: Cliente ID = <?=$venda['cliente_id'] ?? 'NULL'?> -->
    </div>
    <a href="vendas.php" class="btn btn-secondary">← Voltar</a>
  </div>

  <div class="venda-cart">
    <!-- Seção de Adicionar Itens -->
    <div class="add-item-section">
      <h3>Editar Itens da Venda</h3>
      
      <div class="item-selector">
        <button type="button" onclick="switchTab('produtos', event)" class="active">🛍️ Produtos</button>
        <button type="button" onclick="switchTab('servicos', event)">⚙️ Serviços</button>
      </div>

      <div id="tab-produtos" class="tab-content" style="display:block;">
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
              <div class="item-option" 
                   data-tipo="produto" 
                   data-id="<?=$p['id']?>" 
                   data-nome="<?=htmlspecialchars($p['nome'])?>"
                   data-preco="<?=$p['preco_sugerido']?>">
                <strong><?=htmlspecialchars($p['nome'])?></strong>
                <div style="font-size:12px;color:#666;">Sug: R$ <?=number_format((float)$p['preco_sugerido'],2,',','.')?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div id="tab-servicos" class="tab-content">
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
              <div class="item-option"
                   data-tipo="servico"
                   data-id="<?=$s['id']?>"
                   data-nome="<?=htmlspecialchars($s['nome'])?>"
                   data-preco="<?=$s['preco']?>">
                <strong><?=htmlspecialchars($s['nome'])?></strong>
                <div style="font-size:12px;color:#666;">R$ <?=number_format($s['preco'],2,',','.')?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      <div id="selected-info" style="margin-top:15px;padding:10px;background:#f0edf7;border-radius:6px;display:none;">
        <div id="selected-name" style="margin-bottom:8px;font-weight:600;"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr !important;gap:10px;">
          <div>
            <label style="font-size:12px;">Quantidade</label>
            <input type="number" id="item-qtd" value="1" min="1" style="width:100%;">
          </div>
          <div>
            <label style="font-size:12px;">Preço (R$)</label>
            <input type="text" id="item-preco" placeholder="0,00" style="width:100%;">
          </div>
        </div>
        <button type="button" onclick="addToCart()" style="width:100%;margin-top:10px;padding:10px;background:#5d4e99;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">
          ➕ Adicionar ao Carrinho
        </button>
      </div>
    </div>

    <!-- Carrinho -->
    <div class="cart-section">
      <h3>🛒 Carrinho</h3>
      <div id="cart-items"></div>
      <div id="cart-total-section" style="margin-top:15px;padding-top:15px;border-top:2px solid #ddd;">
        <div style="display:flex;justify-content:space-between;font-size:18px;font-weight:bold;">
          <span>Total:</span>
          <span id="cart-total-value">R$ 0,00</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Formulário de Finalização -->
  <form method="post" id="form-checkout" autocomplete="off">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="itens_json" id="itens-json">

    <div class="card" style="margin-bottom:20px;">
      <h3>📝 Dados da Venda</h3>
      <div style="display:grid;grid-template-columns:repeat(2,1fr) !important;gap:15px;">
        <div style="grid-column: 1 / -1;">
          <label>Cliente *</label>
          
          <!-- Aviso se cliente não estiver selecionado -->
          <div id="aviso-sem-cliente" style="display: none; background: #fee2e2; border: 2px solid #ef4444; padding: 12px; border-radius: 8px; margin-bottom: 10px; color: #991b1b; font-weight: 600; text-align: center;">
            ⚠️ ATENÇÃO: Você precisa selecionar um cliente antes de salvar!
          </div>
          
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
          
          <select name="cliente_id" id="cliente-select" class="form-select" style="display:none;">
            <option value="">— Selecione —</option>
            <?php foreach($clientes as $c): ?>
              <option value="<?=$c['id']?>" data-codigo="<?=$c['codigo']?>" data-nome="<?=htmlspecialchars($c['nome'])?>" <?=$c['id']==$venda['cliente_id']?'selected':''?>>
                #<?=$c['codigo']?> - <?=htmlspecialchars($c['nome'])?>
              </option>
            <?php endforeach; ?>
          </select>
          
          <div id="cliente-resultado" style="margin-top: 8px; font-size: 14px; min-height: 24px;">
            <span id="cliente-selecionado" style="color: #999;"></span>
          </div>
          
          <small class="text-muted">
            Não encontrou? 
            <a href="#" onclick="abrirNovoCliente(); return false;" style="color: #5d4e99; font-weight: 600;">
              ➕ Cadastrar novo cliente
            </a>
          </small>
        </div>

        <div>
          <label>Data e hora *</label>
          <input type="datetime-local" name="data" value="<?=date('Y-m-d\TH:i',strtotime($venda['data']))?>" required>
        </div>

        <div>
          <label>Forma de pagamento *</label>
          <select name="forma_pagamento" required>
            <option value="">— selecione —</option>
            <?php
              $ops = ['PIX','Dinheiro','Débito','Crédito','Transferência','Outro'];
              foreach($ops as $o){
                $sel = ($venda['forma_pagamento']===$o)?'selected':'';
                echo "<option $sel>$o</option>";
              }
            ?>
          </select>
        </div>

        <div>
          <label style="font-weight: 700; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 18px;">💰</span> Status de Pagamento *
          </label>
          <select name="pago" id="status-pago-select" required style="font-size: 15px; padding: 12px; font-weight: 600;">
            <option value="1" <?=$venda['pago']?'selected':''?>>✅ Pago</option>
            <option value="0" <?=!$venda['pago']?'selected':''?>>⏱️ Em aberto</option>
          </select>
          <small class="text-muted" style="display: block; margin-top: 5px;">
            <span id="status-pago-info"></span>
          </small>
        </div>

        <div>
          <label>Canal</label>
          <select name="canal">
            <option value="">—</option>
            <?php
              $opsC = ['Instagram','WhatsApp','Local','Outro'];
              foreach($opsC as $o){
                $sel = ($venda['canal']===$o)?'selected':'';
                echo "<option $sel>$o</option>";
              }
            ?>
          </select>
        </div>
      </div>

      <div style="margin-top:15px;">
        <label>Observações</label>
        <textarea name="obs" rows="2" placeholder="Observações opcionais"><?=htmlspecialchars($venda['obs']??'')?></textarea>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
        <a href="vendas.php" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary" id="btn-salvar">💾 Salvar Alterações</button>
      </div>
      
      <!-- Aviso de mudança de status -->
      <div id="aviso-mudanca-status" style="display: none; margin-top: 15px; padding: 15px; border-radius: 8px; border: 2px solid; text-align: center; font-weight: 600;">
      </div>
    </div>
  </form>

  <!-- Zona de Perigo -->
  <div class="danger-zone">
    <h3 style="color:#c53030;margin:0 0 10px 0;">⚠️ Excluir Venda</h3>
    <p style="margin:0 0 15px 0;color:#666;">
      Esta ação é irreversível e irá estornar o estoque dos materiais.
    </p>
    <form method="post" onsubmit="return confirm('⚠️ ATENÇÃO!\n\nEsta ação irá:\n- Excluir a venda permanentemente\n- Estornar o estoque\n- Não pode ser desfeita\n\nConfirmar exclusão?');" style="margin:0;">
      <input type="hidden" name="action" value="delete">
      <button type="submit" class="btn-danger">🗑️ Excluir Venda</button>
    </form>
  </div>
</div>

<script>
(function() {
  'use strict';
  
  // Carregar itens existentes
  let cart = <?=json_encode($itensEditaveis)?>;
  let selectedItem = null;

  window.switchTab = function(tab, evt) {
    document.querySelectorAll('.item-selector button').forEach(btn => btn.classList.remove('active'));
    if (evt && evt.target) evt.target.classList.add('active');
    document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
    const tabEl = document.getElementById('tab-' + tab);
    if (tabEl) tabEl.style.display = 'block';
    clearSelection();
  };

  function selectItem(elemento) {
    const tipo = elemento.getAttribute('data-tipo');
    const id = elemento.getAttribute('data-id');
    const nome = elemento.getAttribute('data-nome');
    const preco = parseFloat(elemento.getAttribute('data-preco')) || 0;
    
    selectedItem = { tipo, id, nome, preco };
    
    document.querySelectorAll('.item-option').forEach(el => el.classList.remove('selected'));
    elemento.classList.add('selected');
    
    document.getElementById('item-preco').value = preco.toFixed(2).replace('.', ',');
    document.getElementById('item-qtd').value = '1';
    document.getElementById('selected-info').style.display = 'block';
    document.getElementById('selected-name').innerHTML = '✓ ' + escapeHtml(nome);
  }

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
        allItems.forEach(item => item.style.display = 'block');
      }
    });
    
    // Buscar ao digitar
    input.addEventListener('input', function() {
      const termo = this.value.toLowerCase().trim();
      dropdown.classList.add('show');
      
      if (!termo) {
        // Mostrar todos os itens
        allItems.forEach(item => item.style.display = 'block');
        return;
      }
      
      let encontrou = false;
      allItems.forEach(item => {
        const nome = (item.getAttribute('data-nome') || '').toLowerCase();
        if (nome.indexOf(termo) >= 0) {
          item.style.display = 'block';
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
    
    // Atualizar aviso
    if (typeof verificarCliente === 'function') {
      verificarCliente();
    }
    
    console.log('✅ Cliente selecionado:', texto, '(ID:', id + ')');
  };
  
  if (clienteBuscaInput && clienteSelect && clienteResultados) {
    const avisoSemCliente = document.getElementById('aviso-sem-cliente');
    
    // Função GLOBAL para verificar e atualizar aviso
    window.verificarCliente = function() {
      const temCliente = clienteSelect.value && clienteSelect.value !== '' && clienteSelect.value !== '0';
      
      if (avisoSemCliente) {
        if (!temCliente) {
          avisoSemCliente.style.display = 'block';
          console.warn('⚠️ Mostrando aviso: cliente não selecionado');
        } else {
          avisoSemCliente.style.display = 'none';
        }
      }
      
      return temCliente;
    };
    
    // Mostrar cliente pré-selecionado ao carregar
    if (clienteSelect.value) {
      const option = clienteSelect.options[clienteSelect.selectedIndex];
      if (option && option.value) {
        clienteSelecionado.innerHTML = '<strong style="color: #5d4e99;">✓ ' + escapeHtml(option.text) + '</strong>';
        console.log('✅ Cliente pré-selecionado carregado:', option.text, '(ID:', option.value + ')');
      }
    } else {
      console.warn('⚠️ ATENÇÃO: Nenhum cliente pré-selecionado! Select vazio.');
      clienteSelecionado.innerHTML = '<span style="color: #e53e3e; font-weight: 600;">⚠️ SELECIONE UM CLIENTE</span>';
    }
    
    // Verificar ao carregar
    window.verificarCliente();
    
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
    document.querySelectorAll('.item-option').forEach(el => el.classList.remove('selected'));
    document.getElementById('item-preco').value = '';
    document.getElementById('item-qtd').value = '1';
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
    renderCart();
    clearSelection();
  };

  window.removeFromCart = function(index) {
    cart.splice(index, 1);
    renderCart();
  };

  function renderCart() {
    const container = document.getElementById('cart-items');
    const totalValue = document.getElementById('cart-total-value');
    const btnSalvar = document.getElementById('btn-salvar');

    if (cart.length === 0) {
      container.innerHTML = '<div style="text-align:center;padding:40px;color:#999;">Carrinho vazio</div>';
      totalValue.textContent = 'R$ 0,00';
      if (btnSalvar) {
        btnSalvar.disabled = true;
        btnSalvar.style.opacity = '0.5';
        btnSalvar.style.cursor = 'not-allowed';
      }
      console.warn('⚠️ Carrinho vazio - botão salvar DESABILITADO');
      return;
    }

    let total = 0;
    let html = '';

    cart.forEach((item, index) => {
      total += item.total;
      html += `
        <div class="cart-item">
          <div style="flex:1;">
            <strong>${escapeHtml(item.nome)}</strong><br>
            <div style="display:flex;gap:8px;margin-top:4px;align-items:center;">
              <input type="number" value="${item.qtd}" min="1" 
                     onchange="updateCartItem(${index}, 'qtd', this.value)"
                     style="width:60px;padding:4px;border:1px solid #ddd;border-radius:4px;">
              <span style="font-size:12px;color:#666;">x</span>
              <input type="text" value="${formatMoney(item.preco)}" 
                     onchange="updateCartItem(${index}, 'preco', this.value)"
                     style="width:80px;padding:4px;border:1px solid #ddd;border-radius:4px;"
                     placeholder="0,00">
            </div>
          </div>
          <div style="text-align:right;font-weight:600;min-width:80px;">R$ ${formatMoney(item.total)}</div>
          <button type="button" class="cart-item-remove" onclick="removeFromCart(${index})">✕</button>
        </div>
      `;
    });

    container.innerHTML = html;
    totalValue.textContent = 'R$ ' + formatMoney(total);
    
    // GARANTIR que o botão está habilitado quando há itens
    if (btnSalvar) {
      btnSalvar.disabled = false;
      btnSalvar.style.opacity = '1';
      btnSalvar.style.cursor = 'pointer';
      console.log('✅ Carrinho com', cart.length, 'itens - botão salvar HABILITADO');
    }

    // Atualizar campo hidden com JSON
    document.getElementById('itens-json').value = JSON.stringify(cart);
  }

  window.updateCartItem = function(index, field, value) {
    if (field === 'qtd') {
      const qtd = parseInt(value) || 1;
      cart[index].qtd = Math.max(1, qtd);
    } else if (field === 'preco') {
      const preco = parseFloat(value.replace(',', '.')) || 0;
      cart[index].preco = Math.max(0, preco);
    }
    cart[index].total = cart[index].qtd * cart[index].preco;
    renderCart();
  };

  function formatMoney(val) {
    return val.toFixed(2).replace('.', ',');
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Adicionar eventos aos itens
  function attachItemEvents() {
    document.querySelectorAll('.item-option').forEach(el => {
      // Remover eventos antigos antes de adicionar novos
      el.replaceWith(el.cloneNode(true));
    });
    
    document.querySelectorAll('.item-option').forEach(el => {
      el.addEventListener('click', function() {
        selectItem(this);
      });
    });
  }
  
  attachItemEvents();

  // Renderizar carrinho inicial
  renderCart();
  
  // Verificar se o botão existe e adicionar listener de teste
  const btnSalvarTest = document.getElementById('btn-salvar');
  if (btnSalvarTest) {
    console.log('✅ Botão "Salvar Alterações" encontrado:', btnSalvarTest);
    console.log('   - Desabilitado?', btnSalvarTest.disabled);
    console.log('   - Type:', btnSalvarTest.type);
    
    btnSalvarTest.addEventListener('click', function(e) {
      console.log('🖱️ BOTÃO CLICADO!');
      console.log('   - Evento:', e);
      console.log('   - Botão desabilitado?', this.disabled);
      console.log('   - Carrinho tem', cart.length, 'itens');
    });
  } else {
    console.error('❌ ERRO: Botão "btn-salvar" NÃO encontrado!');
  }
  
  // Verificar se o formulário existe
  const formCheckout = document.getElementById('form-checkout');
  if (formCheckout) {
    console.log('✅ Formulário encontrado:', formCheckout);
    console.log('   - Method:', formCheckout.method);
    console.log('   - Action:', formCheckout.action || '(padrão)');
  } else {
    console.error('❌ ERRO: Formulário "form-checkout" NÃO encontrado!');
  }
  
  // Monitorar mudanças no status de pagamento
  const statusPagoSelect = document.getElementById('status-pago-select');
  const statusPagoInfo = document.getElementById('status-pago-info');
  const avisoMudancaStatus = document.getElementById('aviso-mudanca-status');
  
  if (statusPagoSelect && statusPagoInfo) {
    // Valor original ao carregar a página
    const statusOriginal = statusPagoSelect.value;
    
    function atualizarInfoStatus() {
      const valorAtual = statusPagoSelect.value;
      const mudou = valorAtual !== statusOriginal;
      
      if (valorAtual === '1') {
        statusPagoInfo.innerHTML = mudou 
          ? '<strong style="color: #16a34a;">⚠️ Será marcado como PAGO ao salvar</strong>' 
          : 'Venda já está marcada como paga';
        statusPagoSelect.style.borderColor = mudou ? '#16a34a' : '';
        statusPagoSelect.style.backgroundColor = mudou ? '#f0fdf4' : '';
      } else {
        statusPagoInfo.innerHTML = mudou 
          ? '<strong style="color: #f59e0b;">⚠️ Será marcado como EM ABERTO ao salvar</strong>' 
          : 'Venda está em aberto (não paga)';
        statusPagoSelect.style.borderColor = mudou ? '#f59e0b' : '';
        statusPagoSelect.style.backgroundColor = mudou ? '#fffbeb' : '';
      }
      
      // Mostrar/ocultar aviso de mudança
      if (mudou && avisoMudancaStatus) {
        const novaPago = valorAtual === '1';
        const statusAnterior = statusOriginal === '1' ? 'PAGO' : 'EM ABERTO';
        const statusNovo = novaPago ? 'PAGO' : 'EM ABERTO';
        
        avisoMudancaStatus.style.display = 'block';
        avisoMudancaStatus.style.borderColor = novaPago ? '#16a34a' : '#f59e0b';
        avisoMudancaStatus.style.backgroundColor = novaPago ? '#f0fdf4' : '#fffbeb';
        avisoMudancaStatus.style.color = novaPago ? '#16a34a' : '#f59e0b';
        avisoMudancaStatus.innerHTML = `
          📝 Alteração detectada: ${statusAnterior} → ${statusNovo}<br>
          <small style="font-weight: 400;">Clique em "Salvar Alterações" para confirmar</small>
        `;
      } else if (avisoMudancaStatus) {
        avisoMudancaStatus.style.display = 'none';
      }
    }
    
    // Atualizar ao carregar
    atualizarInfoStatus();
    
    // Atualizar ao mudar
    statusPagoSelect.addEventListener('change', function() {
      atualizarInfoStatus();
      console.log('Status de pagamento alterado para:', this.value === '1' ? 'Pago' : 'Em aberto');
    });
  }

  // Submit form
  document.getElementById('form-checkout').addEventListener('submit', function(e) {
    console.log('🔄 Formulário submetido! Iniciando validações...');
    console.log('📦 Carrinho tem', cart.length, 'itens');
    
    if (cart.length === 0) {
      e.preventDefault();
      console.error('❌ ERRO: Carrinho vazio!');
      alert('❌ Adicione pelo menos um item ao carrinho!');
      return false;
    }
    
    // Validar se cliente foi selecionado
    const clienteSelect = document.getElementById('cliente-select');
    console.log('👤 Cliente selecionado:', clienteSelect ? clienteSelect.value : 'campo não encontrado');
    
    if (!clienteSelect || !clienteSelect.value || clienteSelect.value === '' || clienteSelect.value === '0') {
      e.preventDefault();
      console.error('❌ ERRO: Cliente não selecionado!');
      alert('❌ Selecione um cliente para a venda!');
      
      // Tentar focar no campo de busca
      const clienteBusca = document.getElementById('cliente-busca');
      if (clienteBusca) {
        clienteBusca.focus();
        clienteBusca.style.borderColor = 'red';
        setTimeout(() => { clienteBusca.style.borderColor = ''; }, 2000);
      }
      
      return false;
    }
    
    // Garantir que o campo hidden está preenchido
    const itensJson = JSON.stringify(cart);
    document.getElementById('itens-json').value = itensJson;
    console.log('📝 JSON dos itens:', itensJson);
    
    // Verificar se o campo de status de pagamento está presente
    const pagoSelect = document.querySelector('select[name="pago"]');
    if (!pagoSelect) {
      console.error('❌ ERRO: Campo de status de pagamento não encontrado!');
      e.preventDefault();
      alert('❌ Erro no formulário: campo de status não encontrado. Atualize a página e tente novamente.');
      return false;
    }
    
    // Logs finais
    console.log('✅ Todas as validações passaram!');
    console.log('📤 Enviando venda com:');
    console.log('  - Itens:', cart);
    console.log('  - Cliente ID:', clienteSelect.value);
    console.log('  - Status de pagamento:', pagoSelect.value, pagoSelect.value === '1' ? '(Pago)' : '(Em aberto)');
    
    // Desabilitar botão para evitar cliques duplos
    const btnSalvar = document.getElementById('btn-salvar');
    if (btnSalvar) {
      btnSalvar.disabled = true;
      btnSalvar.textContent = '⏳ Salvando...';
      console.log('🔒 Botão desabilitado para evitar cliques duplos');
    }
    
    console.log('🚀 Enviando formulário para o servidor...');
    return true;
  });
  
  // Log de inicialização
  console.log('✅ Script de edição de venda carregado com sucesso!');
  console.log('📦 Carrinho inicial:', cart);
  
  // DEBUG: Verificar estado inicial do select de cliente
  const debugClienteSelect = document.getElementById('cliente-select');
  if (debugClienteSelect) {
    console.log('=== DEBUG: Estado inicial do select de cliente ===');
    console.log('Valor do select:', debugClienteSelect.value);
    console.log('Índice selecionado:', debugClienteSelect.selectedIndex);
    console.log('Total de opções:', debugClienteSelect.options.length);
    
    if (debugClienteSelect.selectedIndex >= 0) {
      const optSel = debugClienteSelect.options[debugClienteSelect.selectedIndex];
      console.log('Opção selecionada:', optSel ? optSel.text : 'nenhuma');
      console.log('Valor da opção:', optSel ? optSel.value : 'nenhum');
    }
    
    if (!debugClienteSelect.value || debugClienteSelect.value === '') {
      console.error('⚠️ PROBLEMA: Select de cliente está VAZIO ao carregar!');
      console.error('   Isso significa que o PHP não carregou o cliente_id corretamente');
      console.error('   OU o cliente_id no banco de dados é NULL/0 para esta venda');
    } else {
      console.log('✅ Select de cliente tem valor:', debugClienteSelect.value);
    }
  }
  
  // Função de teste global (simplificada)
  window.testarFormulario = function() {
    console.log('=== TESTE DE DEBUG INICIADO ===');
    
    const form = document.getElementById('form-checkout');
    const btnSalvar = document.getElementById('btn-salvar');
    const clienteSelect = document.getElementById('cliente-select');
    const itensJsonInput = document.getElementById('itens-json');
    const pagoSelect = document.querySelector('select[name="pago"]');
    
    let problemas = [];
    
    console.log('1️⃣ Verificando formulário...');
    if (!form) {
      problemas.push('❌ Formulário não encontrado!');
    } else {
      console.log('   ✅ Formulário OK - method:', form.method, 'action:', form.action || '(padrão)');
    }
    
    console.log('2️⃣ Verificando botão salvar...');
    if (!btnSalvar) {
      problemas.push('❌ Botão salvar não encontrado!');
    } else {
      console.log('   ✅ Botão OK - disabled:', btnSalvar.disabled, 'type:', btnSalvar.type);
      if (btnSalvar.disabled) {
        problemas.push('⚠️ Botão está DESABILITADO!');
      }
    }
    
    console.log('3️⃣ Verificando carrinho...');
    console.log('   Itens no carrinho:', cart.length);
    if (cart.length === 0) {
      problemas.push('⚠️ Carrinho está vazio!');
    } else {
      console.log('   ✅ Carrinho OK com', cart.length, 'itens');
    }
    
    console.log('4️⃣ Verificando cliente...');
    if (!clienteSelect) {
      problemas.push('❌ Campo cliente não encontrado!');
    } else {
      console.log('   Valor do select:', clienteSelect.value || '(vazio)');
      console.log('   Opção selecionada:', clienteSelect.selectedIndex);
      if (clienteSelect.selectedIndex >= 0) {
        const option = clienteSelect.options[clienteSelect.selectedIndex];
        console.log('   Texto da opção:', option ? option.text : '(sem opção)');
      }
      
      if (!clienteSelect.value || clienteSelect.value === '' || clienteSelect.value === '0') {
        problemas.push('⚠️ CRÍTICO: Nenhum cliente selecionado! O formulário não pode ser enviado.');
        console.error('   ❌ Cliente NÃO selecionado - isso está bloqueando o envio!');
      } else {
        console.log('   ✅ Cliente OK - ID:', clienteSelect.value);
      }
    }
    
    console.log('5️⃣ Verificando campo JSON de itens...');
    if (!itensJsonInput) {
      problemas.push('❌ Campo itens_json não encontrado!');
    } else {
      console.log('   Valor:', itensJsonInput.value || '(vazio)');
      console.log('   ✅ Campo OK');
    }
    
    console.log('6️⃣ Verificando campo de status de pagamento...');
    if (!pagoSelect) {
      problemas.push('❌ Campo de status não encontrado!');
    } else {
      console.log('   Status:', pagoSelect.value === '1' ? 'Pago' : 'Em aberto');
      console.log('   ✅ Campo OK');
    }
    
    console.log('=== RESULTADO DO TESTE ===');
    if (problemas.length === 0) {
      console.log('✅ TUDO OK! O formulário deveria funcionar.');
      alert('✅ Tudo parece estar correto!\n\n' + 
            'Carrinho: ' + cart.length + ' itens\n' +
            'Cliente: ' + (clienteSelect?.value ? 'Selecionado' : 'NÃO selecionado') + '\n' +
            'Botão: ' + (btnSalvar?.disabled ? 'DESABILITADO' : 'Habilitado') + '\n\n' +
            'Tente clicar em "Salvar Alterações" e veja o console (F12).');
    } else {
      console.error('❌ PROBLEMAS ENCONTRADOS:', problemas);
      alert('❌ Problemas encontrados:\n\n' + problemas.join('\n') + '\n\nVeja o console (F12) para mais detalhes.');
    }
  };
})();
</script>

<?php include __DIR__.'/layout_end.php'; ?>
