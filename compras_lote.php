<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/helpers.php';
auth_require();
$pdo = db();

$err = null;
$msg = null;

// Buscar materiais
$materiais = $pdo->query("SELECT id, nome, unidade_base, fator_embalagem FROM materiais ORDER BY nome")->fetchAll();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $modo = $_POST['modo'] ?? 'individual';
    
    // COMPRA INDIVIDUAL
    if ($modo === 'individual') {
      $criarNovo = !empty($_POST['criar_novo']);
      $materialId = null;
      $fator = 1;
      
      if ($criarNovo) {
        $st = $pdo->prepare("INSERT INTO materiais (nome, unidade_base, fator_embalagem, estoque_min) VALUES (?, ?, ?, ?)");
        $st->execute([
          $_POST['novo_nome'],
          $_POST['novo_unidade'] ?: 'un',
          (int)($_POST['novo_fator'] ?: 1),
          0
        ]);
        $materialId = (int)$pdo->lastInsertId();
        $fator = (int)($_POST['novo_fator'] ?: 1);
      } else {
        $materialId = (int)($_POST['material_id'] ?? 0);
        $m = $pdo->prepare("SELECT fator_embalagem FROM materiais WHERE id = ?");
        $m->execute([$materialId]);
        $mData = $m->fetch();
        $fator = $mData ? (int)$mData['fator_embalagem'] : 1;
      }
      
      $qtdEmb = max(1, (int)($_POST['qtd_embalagem'] ?? 1));
      $custoTotal = (float)str_replace(',', '.', trim($_POST['custo_total'] ?? '0'));
      $frete = empty($_POST['frete']) ? null : (float)str_replace(',', '.', trim($_POST['frete']));
      
      $compraId = registrar_compra($materialId, $qtdEmb, $fator, $custoTotal, $frete);
      
      $fpag = $_POST['forma_pagamento'] ?: null;
      $pago = (int)($_POST['pago'] ?? 0);
      
      if ($fpag) {
        $pdo->prepare("UPDATE compras SET forma_pagamento = ? WHERE id = ?")->execute([$fpag, $compraId]);
      }
      
      atualizar_status_pagamento_compra($compraId, $pago);
      $msg = $criarNovo ? 'Material criado e compra registrada!' : 'Compra registrada!';
    }
    // LOTE
    else {
      $compras = $_POST['compras'] ?? [];
      $freteGlobal = !empty($_POST['frete_global']) ? (float)str_replace(',', '.', trim($_POST['frete_global'])) : 0;
      $descontoGlobal = !empty($_POST['desconto_global']) ? (float)str_replace(',', '.', trim($_POST['desconto_global'])) : 0;
      $formaGlobal = !empty($_POST['forma_global']) ? $_POST['forma_global'] : null;
      $pagoGlobal = (int)($_POST['pago_global'] ?? 0);
      
      // Primeiro calcular o total de custos para distribuir o frete e desconto proporcionalmente
      $totalCustos = 0;
      foreach ($compras as $c) {
        $custoItem = (float)str_replace(',', '.', trim($c['custo_total'] ?? '0'));
        if ($custoItem > 0) {
          $totalCustos += $custoItem;
        }
      }
      
      $criados = 0;
      $registradas = 0;
      
      foreach ($compras as $c) {
        // Verificar se é para criar novo ou usar existente
        $criarNovo = !empty($c['criar_novo']);
        $materialId = null;
        $fator = 1;
        
        if ($criarNovo) {
          // Criar novo material
          $nomeNovo = trim($c['material_nome'] ?? '');
          if (empty($nomeNovo)) continue; // Pular se não tiver nome
          
          $st = $pdo->prepare("INSERT INTO materiais (nome, unidade_base, fator_embalagem, estoque_min) VALUES (?, ?, ?, ?)");
          $st->execute([
            $nomeNovo,
            $c['unidade_base'] ?: 'un',
            (int)($c['fator_embalagem'] ?: 1),
            0
          ]);
          $materialId = (int)$pdo->lastInsertId();
          $fator = (int)($c['fator_embalagem'] ?: 1);
          $criados++;
        } else {
          // Usar material existente
          $materialId = (int)($c['material_id'] ?? 0);
          if ($materialId <= 0) continue; // Pular se não selecionou material
          
          // Validar se o material existe
          $m = $pdo->prepare("SELECT id FROM materiais WHERE id = ?");
          $m->execute([$materialId]);
          if (!$m->fetch()) continue; // Material não existe
          
          // Usar o fator que veio do formulário (pode ter sido ajustado pelo usuário)
          $fator = (int)($c['fator_embalagem'] ?: 1);
        }
        
        // Validar valores
        $qtdEmb = max(1, (int)($c['qtd_embalagem'] ?? 1));
        $custoTotal = (float)str_replace(',', '.', trim($c['custo_total'] ?? '0'));
        
        if ($custoTotal <= 0) continue; // Pular se não tem custo
        
        // Calcular frete e desconto proporcionais deste item
        $freteProporcional = null;
        $descontoProporcional = null;
        
        if ($totalCustos > 0) {
          $proporcao = $custoTotal / $totalCustos;
          
          if ($freteGlobal > 0) {
            $freteProporcional = $freteGlobal * $proporcao;
          }
          
          if ($descontoGlobal > 0) {
            $descontoProporcional = $descontoGlobal * $proporcao;
          }
        }
        
        // Registrar compra com frete e desconto proporcionais
        $compraId = registrar_compra($materialId, $qtdEmb, $fator, $custoTotal, $freteProporcional, $descontoProporcional);
        
        // Aplicar forma de pagamento e status globais
        if ($formaGlobal) {
          $pdo->prepare("UPDATE compras SET forma_pagamento = ? WHERE id = ?")->execute([$formaGlobal, $compraId]);
        }
        
        atualizar_status_pagamento_compra($compraId, $pagoGlobal);
        $registradas++;
      }
      
      $msgs = [];
      if ($criados > 0) $msgs[] = "✨ $criados material(is) criado(s)";
      if ($registradas > 0) $msgs[] = "✅ $registradas compra(s) registrada(s)";
      if ($freteGlobal > 0) $msgs[] = "🚚 Frete de R$ " . number_format($freteGlobal, 2, ',', '.') . " distribuído proporcionalmente";
      if ($descontoGlobal > 0) $msgs[] = "🏷️ Desconto de R$ " . number_format($descontoGlobal, 2, ',', '.') . " aplicado proporcionalmente";
      if ($formaGlobal) $msgs[] = "💳 Pagamento: " . $formaGlobal . " (" . ($pagoGlobal ? "Pago" : "Em aberto") . ")";
      $msg = $msgs ? implode(' • ', $msgs) : 'Nenhuma compra foi registrada';
    }
  } catch (Throwable $e) { 
    $err = $e->getMessage(); 
  }
}

$title = 'Lançar Compras';
include __DIR__.'/layout_start.php';
?>

<style>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.page-header h2 {
  margin: 0;
  font-size: 28px;
  font-weight: 800;
  background: linear-gradient(135deg, #5d4e99, #7d6eb9);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.btn-group {
  display: flex;
  gap: 10px;
}

.tabs {
  display: flex;
  gap: 15px;
  border-bottom: 2px solid #e0e0e0;
  margin-bottom: 30px;
}

.tab {
  padding: 14px 28px;
  border: none;
  background: transparent;
  color: #666;
  font-weight: 700;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  transition: all 0.2s;
  font-size: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.tab.active {
  color: #5d4e99;
  border-bottom-color: #5d4e99;
}

.tab:hover {
  color: #5d4e99;
  background: #f9f7ff;
}

.tab-content {
  display: none;
}

.tab-content.active {
  display: block;
}

.alert-success {
  padding: 15px 20px;
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  color: #065f46;
  border: 1px solid #34d399;
  border-radius: 8px;
  margin-bottom: 25px;
  font-weight: 600;
}

.alert {
  padding: 15px 20px;
  background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
  color: #991b1b;
  border: 1px solid #f87171;
  border-radius: 8px;
  margin-bottom: 25px;
  font-weight: 600;
}

.card {
  background: white;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  margin-bottom: 25px;
  border: 1px solid #e0e0e0;
}

.card-title {
  margin: 0 0 8px 0;
  color: #333;
  font-size: 20px;
  font-weight: 700;
}

.card-subtitle {
  color: #666;
  font-size: 14px;
  margin-bottom: 25px;
  line-height: 1.6;
}

.form-section {
  background: #f9fafb;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 20px;
  border: 1px solid #e5e7eb;
}

.form-section-title {
  font-size: 14px;
  font-weight: 700;
  color: #5d4e99;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 15px;
}

.card label {
  display: block;
  font-weight: 600;
  margin-bottom: 6px;
  color: #333;
  font-size: 14px;
}

.card input,
.card select {
  width: 100%;
  padding: 12px 14px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-size: 14px;
  margin-bottom: 18px;
  transition: all 0.2s;
}

.card input:focus,
.card select:focus {
  outline: none;
  border-color: #5d4e99;
  box-shadow: 0 0 0 3px rgba(93, 78, 153, 0.1);
}

.card input::placeholder {
  color: #9ca3af;
}

.card button[type="submit"] {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, #5d4e99 0%, #7d6eb9 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 4px 12px rgba(93, 78, 153, 0.3);
}

.card button[type="submit"]:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(93, 78, 153, 0.4);
}

.card button[type="submit"]:active {
  transform: translateY(0);
}

.row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.row-3 {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 20px;
}

.toggle-create-new {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 18px;
  background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
  border: 2px solid #86efac;
  border-radius: 8px;
  margin-bottom: 20px;
  cursor: pointer;
  transition: all 0.2s;
}

.toggle-create-new:hover {
  background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
  border-color: #4ade80;
}

.toggle-create-new input {
  margin: 0;
  cursor: pointer;
  width: auto;
}

.toggle-create-new label {
  margin: 0;
  font-weight: 700;
  color: #16a34a;
  cursor: pointer;
  font-size: 14px;
}

.help-text {
  font-size: 12px;
  color: #6b7280;
  margin-top: -12px;
  margin-bottom: 15px;
  line-height: 1.5;
}

@media (max-width: 768px) {
  .row-2,
  .row-3 {
    grid-template-columns: 1fr;
  }
  
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }
}
</style>

<?php if($msg): ?><div class="alert-success">✅ <?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="alert">❌ <?=htmlspecialchars($err)?></div><?php endif; ?>

<div class="page-header">
  <div>
    <h2>🛒 Lançar Compras</h2>
    <p style="color: #666; margin: 5px 0 0 0; font-size: 14px;">Registre suas compras de materiais individualmente ou em lote</p>
  </div>
  <div class="btn-group">
    <a href="materiais.php" class="btn btn-secondary">← Voltar</a>
  </div>
</div>

<div class="tabs">
  <button type="button" class="tab active" onclick="switchTab('individual')">
    <span style="font-size: 20px;">📝</span> Compra Individual
  </button>
  <button type="button" class="tab" onclick="switchTab('lote')">
    <span style="font-size: 20px;">📋</span> Lançamento em Lote
  </button>
</div>

<!-- TAB: INDIVIDUAL -->
<div id="tab-individual" class="tab-content active">
  <form class="card" method="post">
    <input type="hidden" name="modo" value="individual">
    
    <h3 class="card-title">Registrar Compra Individual</h3>
    <p class="card-subtitle">
      💡 <strong>Dica:</strong> Selecione um material existente ou crie um novo preenchendo todos os campos obrigatórios.
    </p>
    
    <!-- Opção Criar Novo -->
    <label class="toggle-create-new">
      <input type="checkbox" name="criar_novo" value="1" onchange="toggleNovo(this.checked)">
      <span>✨ Criar material novo ao invés de selecionar existente</span>
    </label>
    
    <!-- Seleção de Material Existente -->
    <div id="divExistente" class="form-section">
      <div class="form-section-title">📦 Selecionar Material</div>
      <label>Material *</label>
      <select name="material_id" id="selMat">
        <option value="">Selecione um material...</option>
        <?php foreach($materiais as $m): ?>
          <option value="<?=$m['id']?>" data-fator="<?=$m['fator_embalagem']?>">
            <?=htmlspecialchars($m['nome'])?> (<?=$m['unidade_base']?>)
          </option>
        <?php endforeach; ?>
      </select>
      <p class="help-text">💡 Escolha o material que você está comprando</p>
    </div>
    
    <!-- Criar Novo Material -->
    <div id="divNovo" style="display:none;" class="form-section">
      <div class="form-section-title">✨ Novo Material</div>
      <label>Nome do Material *</label>
      <input type="text" name="novo_nome" placeholder="Ex: Caneca branca 325ml">
      <p class="help-text">💡 Nome que identifica o material</p>
      
      <div class="row-2">
        <div>
          <label>Unidade Base *</label>
          <select name="novo_unidade">
            <option value="un">un (unidade)</option>
            <option value="pct">pct (pacote)</option>
            <option value="cx">cx (caixa)</option>
            <option value="folha">folha</option>
            <option value="rolo">rolo</option>
          </select>
          <p class="help-text">Unidade de medida</p>
        </div>
        <div>
          <label>Fator Embalagem *</label>
          <input type="number" name="novo_fator" id="novoFator" value="1" min="1" placeholder="1">
          <p class="help-text">Qtd por embalagem (ex: caixa c/ 36 = 36)</p>
        </div>
      </div>
    </div>
    
    <!-- Dados da Compra -->
    <div class="form-section">
      <div class="form-section-title">💰 Dados da Compra</div>
      <div class="row-3">
        <div>
          <label>Fator Embalagem</label>
          <input type="number" name="fator" id="fatorAtual" value="1" min="1" readonly style="background:#f3f4f6; border-color: #d1d5db;">
          <p class="help-text">Calculado automaticamente</p>
        </div>
        <div>
          <label>Qtd Embalagens *</label>
          <input type="number" name="qtd_embalagem" value="1" min="1" placeholder="1">
          <p class="help-text">Quantas embalagens comprou</p>
        </div>
        <div>
          <label>Custo Total (R$) *</label>
          <input name="custo_total" placeholder="199,90">
          <p class="help-text">Valor total pago</p>
        </div>
      </div>
      
      <label>Frete (R$)</label>
      <input name="frete" placeholder="25,00 (opcional)">
      <p class="help-text">💡 Deixe em branco se não houver frete</p>
    </div>
    
    <!-- Pagamento -->
    <div class="form-section">
      <div class="form-section-title">💳 Informações de Pagamento</div>
      <div class="row-2">
        <div>
          <label>Forma de Pagamento</label>
          <select name="forma_pagamento">
            <option value="">Não informado</option>
            <option>PIX</option>
            <option>Dinheiro</option>
            <option>Débito</option>
            <option>Crédito</option>
            <option>Transferência</option>
          </select>
        </div>
        <div>
          <label>Status do Pagamento *</label>
          <select name="pago">
            <option value="1">✅ Já está pago</option>
            <option value="0" selected>⏱️ Em aberto (pagar depois)</option>
          </select>
        </div>
      </div>
    </div>
    
    <button type="submit">💾 Salvar Compra</button>
  </form>
</div>

<!-- TAB: LOTE -->
<div id="tab-lote" class="tab-content">
  <form method="post" id="form-lote">
    <input type="hidden" name="modo" value="lote">
    
    <!-- FORMULÁRIO DE ADIÇÃO -->
    <div class="card" id="form-adicionar">
      <h3 class="card-title">➕ Adicionar Material ao Carrinho</h3>
      <p class="card-subtitle">Preencha os dados e clique em "Adicionar ao Carrinho" para incluir na lista</p>
      
      <!-- Opção Criar Novo -->
      <label class="toggle-create-new">
        <input type="checkbox" id="criar-novo-lote" onchange="toggleNovoLote(this.checked)">
        <span>✨ Criar material novo ao invés de selecionar existente</span>
      </label>
      
      <!-- Seleção de Material Existente -->
      <div id="divExistenteLote" class="form-section">
        <div class="form-section-title">📦 Selecionar Material</div>
        <label>Material *</label>
        <select id="material-lote">
          <option value="">Selecione um material...</option>
          <?php foreach($materiais as $m): ?>
            <option value="<?=$m['id']?>" data-fator="<?=$m['fator_embalagem']?>" data-nome="<?=htmlspecialchars($m['nome'])?>" data-unidade="<?=$m['unidade_base']?>">
              <?=htmlspecialchars($m['nome'])?> (<?=$m['unidade_base']?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <!-- Criar Novo Material -->
      <div id="divNovoLote" style="display:none;" class="form-section">
        <div class="form-section-title">✨ Novo Material</div>
        <label>Nome do Material *</label>
        <input type="text" id="novo-nome-lote" placeholder="Ex: Caneca branca 325ml">
        
        <div class="row-2">
          <div>
            <label>Unidade Base *</label>
            <select id="novo-unidade-lote">
              <option value="un">un (unidade)</option>
              <option value="pct">pct (pacote)</option>
              <option value="cx">cx (caixa)</option>
              <option value="folha">folha</option>
              <option value="rolo">rolo</option>
            </select>
          </div>
          <div>
            <label>Fator Embalagem *</label>
            <input type="number" id="novo-fator-lote" value="1" min="1" placeholder="1">
          </div>
        </div>
      </div>
      
      <!-- Dados da Compra -->
      <div class="form-section">
        <div class="form-section-title">💰 Dados da Compra</div>
        <div class="row-3">
          <div>
            <label>Fator Embalagem *</label>
            <input type="number" id="fator-lote" value="1" min="1" placeholder="1">
            <p class="help-text">💡 Ajuste se necessário (ex: cx 24, 36, 48)</p>
          </div>
          <div>
            <label>Qtd Embalagens *</label>
            <input type="number" id="qtd-lote" value="1" min="1" placeholder="1">
          </div>
          <div>
            <label>Custo Unitário (R$) *</label>
            <input type="text" id="custo-lote" placeholder="199,90">
            <p class="help-text">💡 Custo de cada embalagem</p>
          </div>
        </div>
      </div>
      

      
      <button type="button" onclick="adicionarAoCarrinho()" class="btn btn-primary">
        🛒 Adicionar ao Carrinho
      </button>
    </div>
    
    <!-- CARRINHO / TABELA -->
    <div class="card" id="carrinho-container" style="margin-top: 20px;">
      <h3 class="card-title">🛒 Carrinho de Compras</h3>
      <p class="card-subtitle">Lista de materiais que serão salvos quando você clicar em "Salvar Todas as Compras"</p>
      
      <div id="carrinho-vazio" style="text-align: center; padding: 40px 20px; color: #9ca3af;">
        <div style="font-size: 50px; margin-bottom: 10px; opacity: 0.5;">🛒</div>
        <p style="font-size: 15px; margin: 0; font-weight: 600;">Carrinho vazio</p>
        <p style="font-size: 13px; margin: 5px 0 0 0;">Adicione materiais usando o formulário acima</p>
      </div>
      
      <div id="carrinho-tabela" style="display: none;">
        <div style="overflow-x: auto;">
          <table class="carrinho-table">
            <thead>
              <tr>
                <th style="width: 40px;">#</th>
                <th>Material</th>
                <th style="width: 90px;">Fator</th>
                <th style="width: 100px;">Qtd Emb.</th>
                <th style="width: 140px;">Custo Unit.</th>
                <th style="width: 80px;">Ações</th>
              </tr>
            </thead>
            <tbody id="carrinho-items">
              <!-- Items serão adicionados aqui -->
            </tbody>
          </table>
        </div>
        
        <!-- Rodapé com Totais, Frete, Desconto e Pagamento -->
        <div style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-radius: 8px; border: 1px solid #e5e7eb; margin-top: 20px; padding: 20px 25px;">
          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
              <label style="font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 8px; display: block;">🚚 Frete Total (R$)</label>
              <input type="text" id="frete-global" placeholder="0,00 (opcional)" onchange="calcularTotais()" 
                     style="width: 100%; padding: 10px 12px; font-size: 14px; border: 2px solid #e5e7eb; border-radius: 8px;">
              <p style="font-size: 12px; color: #6b7280; margin: 5px 0 0 0;">💡 Frete único para todos</p>
            </div>
            <div>
              <label style="font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 8px; display: block;">🏷️ Desconto Total (R$)</label>
              <input type="text" id="desconto-global" placeholder="0,00 (opcional)" onchange="calcularTotais()" 
                     style="width: 100%; padding: 10px 12px; font-size: 14px; border: 2px solid #e5e7eb; border-radius: 8px;">
              <p style="font-size: 12px; color: #6b7280; margin: 5px 0 0 0;">💡 Desconto único no final</p>
            </div>
            <div>
              <label style="font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 8px; display: block;">💳 Forma de Pagamento</label>
              <select id="forma-global" style="width: 100%; padding: 10px 12px; font-size: 14px; border: 2px solid #e5e7eb; border-radius: 8px;">
                <option value="">Não informado</option>
                <option>PIX</option>
                <option>Dinheiro</option>
                <option>Débito</option>
                <option>Crédito</option>
                <option>Transferência</option>
              </select>
              <p style="font-size: 12px; color: #6b7280; margin: 5px 0 0 0;">💡 Mesma forma para todos</p>
            </div>
            <div>
              <label style="font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 8px; display: block;">✅ Status do Pagamento</label>
              <select id="pago-global" style="width: 100%; padding: 10px 12px; font-size: 14px; border: 2px solid #e5e7eb; border-radius: 8px;">
                <option value="1">✅ Já está pago</option>
                <option value="0" selected>⏱️ Em aberto (pagar depois)</option>
              </select>
              <p style="font-size: 12px; color: #6b7280; margin: 5px 0 0 0;">💡 Mesmo status para todos</p>
            </div>
          
          <div style="display: flex; justify-content: flex-end; align-items: center; margin-top: 20px;">
            <div style="display: flex; gap: 25px; align-items: center;">
              <div style="text-align: right;">
                <div style="font-size: 12px; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">Subtotal Materiais</div>
                <div style="font-size: 20px; font-weight: 700; color: #6b7280;" id="subtotal-materiais">R$ 0,00</div>
              </div>
              <div style="width: 2px; height: 40px; background: #e5e7eb;"></div>
              <div style="text-align: right;">
                <div style="font-size: 12px; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">Frete</div>
                <div style="font-size: 20px; font-weight: 700; color: #6b7280;" id="valor-frete">R$ 0,00</div>
              </div>
              <div style="width: 2px; height: 40px; background: #e5e7eb;"></div>
              <div style="text-align: right;">
                <div style="font-size: 12px; color: #ef4444; font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">Desconto</div>
                <div style="font-size: 20px; font-weight: 700; color: #ef4444;" id="valor-desconto">- R$ 0,00</div>
              </div>
              <div style="width: 2px; height: 40px; background: #e5e7eb;"></div>
              <div style="text-align: right;">
                <div style="font-size: 12px; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">Total Geral</div>
                <div style="font-size: 28px; font-weight: 800; color: #16a34a;" id="total-geral">R$ 0,00</div>
              </div>
            </div>
          </div>
          
          <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 2px solid #e5e7eb; margin-top: 20px;">
            <div>
              <span style="font-size: 14px; color: #6b7280;">Total de <strong style="color: #5d4e99; font-size: 18px;" id="total-itens">0</strong> materiais no carrinho</span>
            </div>
            <button type="submit" class="btn btn-primary" id="btn-salvar-lote" disabled style="width: auto; padding: 12px 32px;">
              💾 Salvar Todas as Compras
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<style>
.carrinho-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  overflow: hidden;
}

.carrinho-table thead {
  background: linear-gradient(135deg, #5d4e99 0%, #7c6bb8 100%);
  color: white;
}

.carrinho-table th {
  padding: 14px 12px;
  text-align: left;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.carrinho-table tbody tr {
  border-bottom: 2px solid #f3f4f6;
  transition: all 0.2s;
}

.carrinho-table tbody tr:hover {
  background: #fafbfc;
}

.carrinho-table td {
  padding: 12px;
  font-size: 13px;
  color: #374151;
}

.carrinho-table td:first-child {
  font-weight: 700;
  color: #5d4e99;
}

.item-nome {
  font-weight: 600;
  color: #1f2937;
}

.item-badge {
  display: inline-block;
  padding: 2px 8px;
  background: #f3f4f6;
  border-radius: 4px;
  font-size: 11px;
  color: #6b7280;
  margin-left: 6px;
}

.item-novo-badge {
  background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
  color: #15803d;
  font-weight: 700;
}

.btn-remover-item {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  border: none;
  border-radius: 6px;
  padding: 6px 12px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
  transition: all 0.2s;
}

.btn-remover-item:hover {
  background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
  transform: scale(1.05);
}

.subtotal-cell {
  font-weight: 700;
  color: #16a34a;
  font-size: 14px;
}

@media (max-width: 968px) {
  .carrinho-table {
    font-size: 12px;
  }
  
  .carrinho-table th,
  .carrinho-table td {
    padding: 8px 6px;
  }
}
</style>

<script>
let carrinhoCount = 0;
const materiais = <?=json_encode($materiais)?>;
let carrinho = [];

// Toggle criar novo material
function toggleNovoLote(criar) {
  const divExist = document.getElementById('divExistenteLote');
  const divNovo = document.getElementById('divNovoLote');
  const materialSelect = document.getElementById('material-lote');
  const fatorLote = document.getElementById('fator-lote');
  const novoFatorLote = document.getElementById('novo-fator-lote');
  
  if (criar) {
    divExist.style.display = 'none';
    divNovo.style.display = 'block';
    materialSelect.value = '';
    fatorLote.value = novoFatorLote.value;
  } else {
    divExist.style.display = 'block';
    divNovo.style.display = 'none';
    fatorLote.value = '1';
  }
}

// Atualizar fator quando selecionar material
document.getElementById('material-lote').addEventListener('change', function() {
  const option = this.options[this.selectedIndex];
  const fator = option.getAttribute('data-fator') || '1';
  document.getElementById('fator-lote').value = fator;
});

// Atualizar fator quando mudar fator do novo material
document.getElementById('novo-fator-lote').addEventListener('input', function() {
  if (document.getElementById('criar-novo-lote').checked) {
    document.getElementById('fator-lote').value = this.value;
  }
});

// Adicionar ao carrinho
function adicionarAoCarrinho() {
  const criarNovo = document.getElementById('criar-novo-lote').checked;
  let materialId = '', materialNome = '', unidadeBase = '', fatorEmbalagem = 1;
  
  // Validar material
  if (criarNovo) {
    materialNome = document.getElementById('novo-nome-lote').value.trim();
    unidadeBase = document.getElementById('novo-unidade-lote').value;
    
    if (!materialNome) {
      alert('❌ Preencha o nome do material novo!');
      return;
    }
  } else {
    const materialSelect = document.getElementById('material-lote');
    materialId = materialSelect.value;
    
    if (!materialId) {
      alert('❌ Selecione um material!');
      return;
    }
    
    const option = materialSelect.options[materialSelect.selectedIndex];
    materialNome = option.getAttribute('data-nome');
    unidadeBase = option.getAttribute('data-unidade');
  }
  
  // Pegar fator do campo editável (sempre, seja novo ou existente)
  fatorEmbalagem = parseInt(document.getElementById('fator-lote').value) || 1;
  
  // Validar campos obrigatórios
  const qtdEmbalagem = parseInt(document.getElementById('qtd-lote').value) || 0;
  const custoUnitario = document.getElementById('custo-lote').value.trim();
  
  if (qtdEmbalagem <= 0) {
    alert('❌ Quantidade de embalagens deve ser maior que zero!');
    return;
  }
  
  if (!custoUnitario) {
    alert('❌ Preencha o custo unitário!');
    return;
  }
  
  // Calcular valores: custo unitário × quantidade
  const custoUnitarioNum = parseFloat(custoUnitario.replace(',', '.')) || 0;
  const custoTotalCalculado = custoUnitarioNum * qtdEmbalagem;
  
  // Adicionar ao carrinho
  carrinhoCount++;
  carrinho.push({
    id: carrinhoCount,
    criarNovo: criarNovo,
    materialId: materialId,
    materialNome: materialNome,
    unidadeBase: unidadeBase,
    fatorEmbalagem: fatorEmbalagem,
    qtdEmbalagem: qtdEmbalagem,
    custoTotal: custoTotalCalculado
  });
  
  renderizarCarrinho();
  limparFormulario();
  
  // Scroll suave até o carrinho
  document.getElementById('carrinho-container').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Remover do carrinho
function removerDoCarrinho(id) {
  carrinho = carrinho.filter(item => item.id !== id);
  renderizarCarrinho();
}

// Renderizar carrinho
function renderizarCarrinho() {
  const carrinhoVazio = document.getElementById('carrinho-vazio');
  const carrinhoTabela = document.getElementById('carrinho-tabela');
  const carrinhoItems = document.getElementById('carrinho-items');
  
  if (carrinho.length === 0) {
    carrinhoVazio.style.display = 'block';
    carrinhoTabela.style.display = 'none';
    document.getElementById('btn-salvar-lote').disabled = true;
    return;
  }
  
  carrinhoVazio.style.display = 'none';
  carrinhoTabela.style.display = 'block';
  
  // Limpar tabela
  carrinhoItems.innerHTML = '';
  
  // Renderizar items
  carrinho.forEach((item, index) => {
    const tr = document.createElement('tr');
    
    const badgeNovo = item.criarNovo ? '<span class="item-badge item-novo-badge">✨ NOVO</span>' : '';
    
    tr.innerHTML = `
      <td>${index + 1}</td>
      <td>
        <div class="item-nome">${item.materialNome}</div>
        <span class="item-badge">${item.unidadeBase}</span>
        ${badgeNovo}
      </td>
      <td>${item.fatorEmbalagem}</td>
      <td>${item.qtdEmbalagem}</td>
      <td class="subtotal-cell">R$ ${item.custoTotal.toFixed(2).replace('.', ',')}</td>
      <td>
        <button type="button" class="btn-remover-item" onclick="removerDoCarrinho(${item.id})">
          🗑️
        </button>
      </td>
      <input type="hidden" name="compras[${item.id}][criar_novo]" value="${item.criarNovo ? '1' : ''}">
      <input type="hidden" name="compras[${item.id}][material_id]" value="${item.materialId}">
      <input type="hidden" name="compras[${item.id}][material_nome]" value="${item.materialNome}">
      <input type="hidden" name="compras[${item.id}][unidade_base]" value="${item.unidadeBase}">
      <input type="hidden" name="compras[${item.id}][fator_embalagem]" value="${item.fatorEmbalagem}">
      <input type="hidden" name="compras[${item.id}][fator]" value="${item.fatorEmbalagem}">
      <input type="hidden" name="compras[${item.id}][qtd_embalagem]" value="${item.qtdEmbalagem}">
      <input type="hidden" name="compras[${item.id}][custo_total]" value="${item.custoTotal.toFixed(2)}">
    `;
    
    carrinhoItems.appendChild(tr);
  });
  
  // Adicionar campo hidden para frete global
  const freteGlobalInput = document.getElementById('frete-global');
  const freteGlobal = freteGlobalInput ? freteGlobalInput.value : '0';
  
  // Criar input hidden para frete global se não existir
  let freteHidden = document.querySelector('input[name="frete_global"]');
  if (!freteHidden) {
    freteHidden = document.createElement('input');
    freteHidden.type = 'hidden';
    freteHidden.name = 'frete_global';
    document.getElementById('form-lote').appendChild(freteHidden);
    
    // Adicionar listener para atualizar o hidden quando o input mudar
    freteGlobalInput.addEventListener('input', function() {
      freteHidden.value = parseFloat(this.value.replace(',', '.') || 0).toFixed(2);
    });
  }
  freteHidden.value = parseFloat(freteGlobal.replace(',', '.') || 0).toFixed(2);
  
  // Adicionar campo hidden para desconto global
  const descontoGlobalInput = document.getElementById('desconto-global');
  const descontoGlobal = descontoGlobalInput ? descontoGlobalInput.value : '0';
  
  let descontoHidden = document.querySelector('input[name="desconto_global"]');
  if (!descontoHidden) {
    descontoHidden = document.createElement('input');
    descontoHidden.type = 'hidden';
    descontoHidden.name = 'desconto_global';
    document.getElementById('form-lote').appendChild(descontoHidden);
    
    // Adicionar listener para atualizar o hidden quando o input mudar
    descontoGlobalInput.addEventListener('input', function() {
      descontoHidden.value = parseFloat(this.value.replace(',', '.') || 0).toFixed(2);
    });
  }
  descontoHidden.value = parseFloat(descontoGlobal.replace(',', '.') || 0).toFixed(2);
  
  // Adicionar campos hidden para forma de pagamento e status
  const formaGlobalSelect = document.getElementById('forma-global');
  const pagoGlobalSelect = document.getElementById('pago-global');
  
  let formaHidden = document.querySelector('input[name="forma_global"]');
  if (!formaHidden) {
    formaHidden = document.createElement('input');
    formaHidden.type = 'hidden';
    formaHidden.name = 'forma_global';
    document.getElementById('form-lote').appendChild(formaHidden);
    
    formaGlobalSelect.addEventListener('change', function() {
      formaHidden.value = this.value;
    });
  }
  formaHidden.value = formaGlobalSelect.value;
  
  let pagoHidden = document.querySelector('input[name="pago_global"]');
  if (!pagoHidden) {
    pagoHidden = document.createElement('input');
    pagoHidden.type = 'hidden';
    pagoHidden.name = 'pago_global';
    document.getElementById('form-lote').appendChild(pagoHidden);
    
    pagoGlobalSelect.addEventListener('change', function() {
      pagoHidden.value = this.value;
    });
  }
  pagoHidden.value = pagoGlobalSelect.value;
  
  calcularTotais();
}

// Calcular totais
function calcularTotais() {
  let totalItens = carrinho.length;
  let subtotalMateriais = carrinho.reduce((sum, item) => sum + item.custoTotal, 0);
  
  // Obter frete global
  const freteGlobalInput = document.getElementById('frete-global');
  const freteGlobal = freteGlobalInput ? parseFloat(freteGlobalInput.value.replace(',', '.')) || 0 : 0;
  
  // Obter desconto global
  const descontoGlobalInput = document.getElementById('desconto-global');
  const descontoGlobal = descontoGlobalInput ? parseFloat(descontoGlobalInput.value.replace(',', '.')) || 0 : 0;
  
  // Calcular total geral: subtotal + frete - desconto
  let totalGeral = subtotalMateriais + freteGlobal - descontoGlobal;
  
  // Atualizar displays
  document.getElementById('total-itens').textContent = totalItens;
  document.getElementById('subtotal-materiais').textContent = 'R$ ' + subtotalMateriais.toFixed(2).replace('.', ',');
  document.getElementById('valor-frete').textContent = 'R$ ' + freteGlobal.toFixed(2).replace('.', ',');
  document.getElementById('valor-desconto').textContent = '- R$ ' + descontoGlobal.toFixed(2).replace('.', ',');
  document.getElementById('total-geral').textContent = 'R$ ' + totalGeral.toFixed(2).replace('.', ',');
  
  const btnSalvar = document.getElementById('btn-salvar-lote');
  if (totalItens > 0) {
    btnSalvar.disabled = false;
    btnSalvar.style.opacity = '1';
    btnSalvar.style.cursor = 'pointer';
  } else {
    btnSalvar.disabled = true;
    btnSalvar.style.opacity = '0.5';
    btnSalvar.style.cursor = 'not-allowed';
  }
}

// Limpar formulário
function limparFormulario() {
  document.getElementById('criar-novo-lote').checked = false;
  document.getElementById('material-lote').value = '';
  document.getElementById('novo-nome-lote').value = '';
  document.getElementById('novo-unidade-lote').value = 'un';
  document.getElementById('novo-fator-lote').value = '1';
  document.getElementById('fator-lote').value = '1';
  document.getElementById('qtd-lote').value = '1';
  document.getElementById('custo-lote').value = '';
  
  toggleNovoLote(false);
}
</script>

<script>
function switchTab(tab) {
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  
  // Ativar botão correto
  const tabs = document.querySelectorAll('.tab');
  tabs.forEach(t => {
    if ((tab === 'individual' && t.textContent.includes('Individual')) ||
        (tab === 'lote' && t.textContent.includes('Lote'))) {
      t.classList.add('active');
    }
  });
}

function toggleNovo(criar) {
  const divExist = document.getElementById('divExistente');
  const divNovo = document.getElementById('divNovo');
  const selMat = document.getElementById('selMat');
  const fatorAtual = document.getElementById('fatorAtual');
  const novoFator = document.getElementById('novoFator');
  
  if (criar) {
    divExist.style.display = 'none';
    divNovo.style.display = 'block';
    selMat.value = '';
    novoFator.addEventListener('input', function() {
      fatorAtual.value = this.value;
    });
  } else {
    divExist.style.display = 'block';
    divNovo.style.display = 'none';
    fatorAtual.value = '1';
  }
}

document.getElementById('selMat').addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  const fator = opt.getAttribute('data-fator');
  if (fator) {
    document.getElementById('fatorAtual').value = fator;
  }
});

// Abrir aba específica se vier na URL
window.addEventListener('DOMContentLoaded', function() {
  const params = new URLSearchParams(window.location.search);
  const tab = params.get('tab');
  if (tab === 'lote') {
    switchTab('lote');
  }
});
</script>

<?php include __DIR__.'/layout_end.php'; ?>
