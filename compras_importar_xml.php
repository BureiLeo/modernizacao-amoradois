<?php
/**
 * FUNCIONALIDADE FUTURA: Importação de XML de Nota Fiscal
 * 
 * Este arquivo será usado para importar automaticamente as compras
 * a partir do XML da nota fiscal eletrônica (NF-e).
 * 
 * O XML da NF-e contém:
 * - Dados do fornecedor
 * - Lista de produtos com descrição, quantidade e valores
 * - Informações de frete
 * - Forma de pagamento
 * 
 * TODO:
 * 1. Upload do arquivo XML
 * 2. Parser do XML (usar SimpleXML ou DOMDocument)
 * 3. Extração dos itens da nota
 * 4. Matching automático com materiais existentes
 * 5. Sugestão de criação de novos materiais não encontrados
 * 6. Pré-preenchimento do formulário de lote
 */

require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();

$title = 'Importar XML de Nota Fiscal';
include __DIR__.'/layout_start.php';
?>

<div style="max-width: 800px; margin: 0 auto;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2 style="margin: 0;">📄 Importar XML de NF-e</h2>
    <a href="compras_lote.php" class="btn btn-secondary">← Voltar</a>
  </div>
  
  <div class="card">
    <div style="text-align: center; padding: 40px 20px;">
      <div style="font-size: 4em; margin-bottom: 20px;">🚀</div>
      <h3 style="color: #5d4e99; margin-bottom: 15px;">Funcionalidade em Desenvolvimento</h3>
      <p style="color: #666; font-size: 15px; line-height: 1.6; max-width: 500px; margin: 0 auto;">
        Em breve você poderá fazer upload do XML da nota fiscal e o sistema irá:
      </p>
      
      <div style="text-align: left; max-width: 500px; margin: 20px auto; background: #f8f9fa; padding: 20px; border-radius: 8px;">
        <ul style="list-style: none; padding: 0; margin: 0;">
          <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">✅ Ler automaticamente todos os produtos</li>
          <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">✅ Identificar materiais já cadastrados</li>
          <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">✅ Sugerir novos materiais para cadastro</li>
          <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">✅ Preencher quantidades e valores</li>
          <li style="padding: 8px 0;">✅ Lançar tudo com um clique</li>
        </ul>
      </div>
      
      <div style="margin-top: 30px;">
        <a href="compras_lote.php?tab=lote" class="btn btn-primary">
          Por enquanto, use o Lançamento em Lote →
        </a>
      </div>
    </div>
  </div>
  
  <!-- Estrutura preparada para o futuro -->
  <div class="card" style="display: none;">
    <h3>Upload de Arquivo XML</h3>
    
    <form method="post" enctype="multipart/form-data">
      <div style="margin-bottom: 20px;">
        <label>Arquivo XML da Nota Fiscal *</label>
        <input type="file" name="xml_file" accept=".xml" required>
        <small style="color: #666; display: block; margin-top: 5px;">
          Selecione o arquivo XML da NF-e fornecido pelo seu fornecedor
        </small>
      </div>
      
      <button type="submit" class="btn btn-primary">
        📤 Processar XML
      </button>
    </form>
  </div>
</div>

<?php include __DIR__.'/layout_end.php'; ?>
