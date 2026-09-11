# Funcionalidade de Ajuste de Estoque

## 📋 Descrição

Sistema completo para ajustar manualmente o estoque de materiais com registro de histórico.

## 🚀 Instalação

### 1. Criar a tabela no banco de dados

Execute o script SQL fornecido no arquivo `ajustes_estoque.sql`:

```bash
mysql -u seu_usuario -p seu_banco_de_dados < ajustes_estoque.sql
```

Ou copie e cole o conteúdo do arquivo diretamente no phpMyAdmin ou outro cliente MySQL.

### 2. Verificar configuração

Certifique-se de que seu banco de dados tem as tabelas:
- `materiais` (já existente)
- `usuarios` (já existente)  
- `ajustes_estoque` (nova)

## 💡 Como Usar

### Acessar o ajuste de estoque

Existem 3 formas de acessar:

1. **Na listagem de materiais** (`materiais.php`):
   - Clique no botão "⚖️ Ajustar" ao lado de cada material

2. **Na página de edição** (`materiais_editar.php`):
   - Clique no botão "⚖️ Ajustar Estoque" no topo da página

3. **Diretamente pela URL**:
   - Acesse `materiais_ajuste.php?id=ID_DO_MATERIAL`

### Realizar um ajuste

1. **Selecione o tipo de ajuste**:
   - ➕ **Adicionar ao Estoque**: para aumentar a quantidade (ex: correção de inventário, devolução)
   - ➖ **Remover do Estoque**: para diminuir a quantidade (ex: perda, quebra, correção)

2. **Digite a quantidade**: o valor sempre positivo (ex: 10, 5.5)

3. **Informe o motivo**: obrigatório para rastreabilidade
   - Exemplos: "Correção de inventário", "Perda por vencimento", "Devolução de fornecedor"

4. **Clique em "Realizar Ajuste"**

### Visualizar histórico

O histórico dos últimos 20 ajustes é exibido na mesma página, mostrando:
- Tipo de ajuste (adição ou remoção)
- Quantidade ajustada
- Estoque anterior e novo
- Data e hora
- Usuário que realizou o ajuste
- Motivo do ajuste

## 🔒 Segurança

- Apenas usuários autenticados podem fazer ajustes
- Não é permitido estoque negativo
- Todos os ajustes são registrados com data, hora, usuário e motivo
- O histórico é permanente e não pode ser alterado

## 📊 Estrutura da Tabela

```sql
ajustes_estoque
├── id (INT) - Identificador único
├── material_id (INT) - ID do material ajustado
├── tipo (ENUM) - 'adicionar' ou 'remover'
├── quantidade (DECIMAL) - Quantidade do ajuste
├── estoque_anterior (DECIMAL) - Estoque antes do ajuste
├── estoque_novo (DECIMAL) - Estoque após o ajuste
├── motivo (TEXT) - Justificativa do ajuste
├── usuario_id (INT) - Quem realizou o ajuste
└── data_ajuste (DATETIME) - Quando foi realizado
```

## 🎨 Interface

A interface possui:
- Visualização clara do estoque atual
- Botões grandes e intuitivos para selecionar o tipo de ajuste
- Validação em tempo real
- Histórico visual com cores:
  - 🟢 Verde para adições
  - 🔴 Vermelho para remoções
- Design responsivo e moderno

## ⚠️ Notas Importantes

1. **Diferença entre Compras e Ajustes**:
   - Use "Nova Compra" para registrar compras de fornecedores (adiciona estoque + controle de custos)
   - Use "Ajustar Estoque" para correções manuais, perdas, devoluções

2. **Estoque não pode ficar negativo**: o sistema valida e impede ajustes que resultariam em estoque negativo

3. **Histórico permanente**: os ajustes ficam registrados permanentemente para auditoria

4. **Motivo obrigatório**: sempre informe um motivo claro para facilitar futuras auditorias

## 📁 Arquivos Criados

- `materiais_ajuste.php` - Página principal de ajuste de estoque
- `ajustes_estoque.sql` - Script para criar a tabela no banco de dados
- Modificações em `materiais.php` - Botão de acesso rápido
- Modificações em `materiais_editar.php` - Botão de acesso rápido
- Modificações em `app.css` - Estilo para botão btn-success
