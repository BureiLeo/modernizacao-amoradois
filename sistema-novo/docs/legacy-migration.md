# Migração de Dados do Sistema Legado (Etapa 4)

Este documento descreve o pipeline que migra os dados históricos do sistema
antigo (PHP procedural, banco `u323377136_loja`) para o novo schema Laravel,
preservando o estado histórico sem reprocessar regras de negócio.

## Objetivo

Migrar os dados de forma **segura, repetível, idempotente, auditável,
testável e reversível**. O banco legado é sempre a referência de origem e
**nunca é alterado** por este processo (conexão `legacy` é somente leitura).

## Como configurar a conexão `legacy`

No `.env` (nunca versionado), preencha:

```env
LEGACY_DB_HOST=<host do MySQL/MariaDB de origem>
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=<nome do banco legado>
LEGACY_DB_USERNAME=<usuário>
LEGACY_DB_PASSWORD=<senha>
```

Para desenvolvimento/teste local, a estratégia recomendada (e a usada para
validar esta etapa) é **importar o dump legado (`u323377136_loja.sql`) para
um banco MariaDB local**, em vez de usar o parser de SQL ou conectar direto
na produção:

```powershell
# Exemplo (MariaDB local instalado via winget nesta máquina):
mysql -u seu_usuario -p -e "CREATE DATABASE amoradois_legacy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u seu_usuario -p amoradois_legacy < u323377136_loja.sql
```

Depois aponte `LEGACY_DB_DATABASE=amoradois_legacy` (e demais credenciais
locais) no `.env`. **Nunca** aponte `LEGACY_DB_*` para o banco de produção
sem necessidade real — para esta etapa, produção não foi tocada em nenhum
momento.

O banco de **destino** (conexão padrão do Laravel, `DB_CONNECTION`) pode
continuar em SQLite para o dia a dia das etapas anteriores, mas a migração
completa **deve** também ser validada contra MariaDB/MySQL local antes de
qualquer migração real, para garantir compatibilidade de decimais, FKs,
collations e constraints. Nesta etapa isso foi feito com um segundo banco
local (`amoradois_novo`).

## Comandos

### `php artisan legacy:migrate`

Executa a migração completa, na ordem de dependência correta.

```powershell
# Simula sem gravar nada (recomendado sempre rodar primeiro):
php artisan legacy:migrate --dry-run

# Migração real, com confirmação interativa:
php artisan legacy:migrate

# Migração real, sem prompt de confirmação (scripts/CI):
php artisan legacy:migrate --force

# Migrar só um módulo/grupo (para depuração):
php artisan legacy:migrate --only=clientes --force
php artisan legacy:migrate --only=vendas --force   # inclui vendas + pagamentos
```

Grupos disponíveis em `--only=`: `usuarios`, `clientes`, `produtos`
(materiais+produtos+bom), `estoque` (stock_movements), `vendas`
(vendas+pagamentos), `perdas`, `financeiro` (compras+investimentos+caixa).
Também aceita chaves de módulo individuais (ex.: `--only=materiais`).

O comando verifica as duas conexões antes de começar, mostra qual banco de
origem/destino será usado, e pede confirmação antes de gravar (a menos que
`--force` seja passado).

### `php artisan legacy:validate`

Compara origem e destino após a migração: contagens, stock_movements
convertidos, pagamentos derivados, financeiro (bruto e persistido), saldo de
caixa, estoque material por material, amostra de IDs preservados,
relacionamentos órfãos e anomalias conhecidas. Retorna código de saída `0`
(aprovado) ou `1` (divergências encontradas).

## Ordem de migração (dependências)

```
1. users
2. clientes
3. materiais
4. produtos
5. produto_bom
6. compras
7. vendas (+ venda_itens)
8. pagamentos (derivado de vendas)
9. perdas
10. stock_movements (derivado de venda_consumos + perda_consumos + ajustes_estoque)
11. investimentos
12. caixa_movimentos
```

Cada módulo roda em sua própria transação — se um módulo falhar, os
anteriores já commitados permanecem, e o erro é reportado com o nome do
módulo, log em `storage/logs/legacy-migration.log` (nunca grava senhas).

## Preservação de IDs e AUTO_INCREMENT

Os IDs originais são preservados 1:1 em todas as tabelas com correspondência
direta no legado (`updateOrInsert(['id' => $id], ...)`). Após cada módulo,
`App\Services\LegacyMigration\Support\AutoIncrementResetter` ajusta o próximo
`AUTO_INCREMENT` da tabela para `MAX(id)+1` **no banco de destino apenas**
(no-op em SQLite, usado só pelos testes automatizados).

`pagamentos` e `stock_movements` são tabelas **novas** (sem equivalente 1:1
no legado) — não têm ID original a preservar; a idempotência dessas duas usa
outras estratégias (ver seção "Idempotência" abaixo).

## Idempotência

Rodar `legacy:migrate` mais de uma vez **não duplica nada**:

- Tabelas com ID preservado: `updateOrInsert` por `id`.
- `pagamentos`: 1 registro por `venda_id` (upsert por `venda_id`).
- `stock_movements`: chave lógica é o texto de `observacoes`, que embute o
  ID original único da linha de origem (ex.: `"venda_consumos (legado) #123"`
  ou `"... (ajustes_estoque legado #7)"`). Isso foi necessário porque o
  legado tem pares `(venda_id, material_id)` **legitimamente duplicados**
  em `venda_consumos` (vendas editadas mais de uma vez no sistema antigo) —
  usar só `source_type+source_id+material_id+type` como chave colapsaria
  linhas de histórico distintas, perdendo dados (bug real encontrado e
  corrigido durante esta etapa).

Testado e comprovado: ver seção "Teste de idempotência" abaixo.

## Transformações e mapeamentos

Centralizados em `config/legacy_migration.php` e
`App\Services\LegacyMigration\Support\LegacyValueMapper`:

| Legado | Novo | Observação |
|---|---|---|
| `forma_pagamento` (string livre: "PIX", "Dinheiro", "Outro") | `App\Enums\FormaPagamento` (`pix`, `dinheiro`, `outro`...) | Mapa explícito case-insensitive; valor desconhecido cai em `outro` + warning |
| `caixa_movimentos.tipo` (ENUM legado) | `App\Enums\CaixaTipo` | Idem |
| `perdas.tipo` (`PRODUTO`/`MATERIAL`) | `App\Enums\PerdaTipo` | Idem |

## Decisões documentadas (não regras inventadas)

- **users**: e-mail vazio no legado → sintetizado como
  `{username}@legado.amoradois.local` (não colide com o `UNIQUE` de e-mail
  do Breeze). `password_hash` copiado como está (já é bcrypt, compatível com
  `Hash::check`) — **senhas não foram redefinidas**. Todos os usuários
  migrados recebem `role=admin`, porque o legado não tinha nenhum sistema de
  papéis (qualquer usuário logado tinha acesso total) — essa é a regra mais
  fiel ao comportamento real do sistema antigo.
- **clientes**: `codigo` preservado exatamente como veio (não recalculado).
  Telefone/e-mail **não são únicos** — o duplicado legítimo identificado na
  Etapa 2 é preservado.
- **categorias/produto_variacoes**: nenhuma categoria ou variação é
  inventada. `produtos.categoria_id = null` para todos; `produto_variacoes`
  fica vazia.
- **materiais**: `estoque` copiado direto (sem arredondar/recalcular) — é a
  referência oficial da migração, não reconstruído a partir do histórico.
- **compras**: `fornecedor_id = null`, `desconto = 0` (campos novos, sem
  equivalente legado). Custo médio não é recalculado.
- **vendas**: `subtotal`/`total` calculados a partir da soma histórica de
  `venda_itens` (o legado nunca persistia um total). `custo_total`/
  `lucro_total` usam os valores **já congelados** em `venda_itens`
  (`custo_unit_calculado`/`lucro_unit`), nunca recalculados pela BOM atual.
  `status = concluida` para todas (legado não tinha cancelamento).
- **pagamentos** (tabela nova): derivada do estado `pago`/`total` de cada
  venda — paga → `status=pago`, `pago_em` = data da venda (**inferida**,
  documentada); em aberto → `status=pendente`, `pago_em=null`.
- **stock_movements** (ledger novo): conversão de histórico de
  `venda_consumos` (saída, `type=venda`), `perda_consumos` (saída,
  `type=perda`) e `ajustes_estoque` (`type=ajuste`, sinal confirmado lendo
  `materiais_ajuste.php`: `adicionar` = positivo, `remover` = negativo).
  `quantity_before`/`quantity_after` só são preenchidos quando o legado
  realmente guardava esses valores (caso de `ajustes_estoque`); para
  consumos de venda/perda ficam `null` (não inventados).
- **caixa_movimentos**: cópia direta, **não** regerado a partir de
  vendas/compras/investimentos (evita duplicar lançamentos).

## Anomalias conhecidas (preservadas, não corrigidas)

1. 96 de 197 vendas sem `cliente_id`.
2. 1 item de venda com `preco_unit = 0,00` (venda_itens #110, venda #100).
3. 1 telefone duplicado entre 2 clientes.
4. 3 ajustes de estoque com quantidade fracionada.
5. **Descoberta nesta etapa**: 5 lançamentos de `caixa_movimentos` com
   `tipo=''` (string vazia). Causa raiz: `investimentos.php` no legado chama
   `caixa_lancar(..., 'INVESTIMENTO', ...)`, mas a coluna
   `caixa_movimentos.tipo` é um `ENUM('VENDA','COMPRA','AJUSTE','OUTRO')` —
   `'INVESTIMENTO'` não existe nesse ENUM. Em modo não-estrito, o
   MySQL/MariaDB aceitou o INSERT e gravou silenciosamente uma string vazia
   em vez de rejeitar. O valor financeiro (`valor`) está correto e intacto;
   apenas a categorização por `tipo` já nascia inválida no legado. Mapeado
   para `outro` na migração.

Nenhuma dessas é "corrigida" pela migração — todas são preservadas e
reportadas tanto pelo `legacy:migrate` (warnings) quanto pelo
`legacy:validate` (seção "Anomalias conhecidas preservadas").

## Como refazer o teste local do zero

```powershell
# 1. Recriar o schema do banco de destino (local, MariaDB ou SQLite):
php artisan migrate:fresh --force

# 2. Dry-run (conferir antes de gravar):
php artisan legacy:migrate --dry-run

# 3. Migração real:
php artisan legacy:migrate --force

# 4. Validar:
php artisan legacy:validate

# 5. Testar idempotência (rodar de novo, counts não podem mudar):
php artisan legacy:migrate --force
php artisan legacy:validate
```

## Testes automatizados

`tests/Feature/LegacyMigration/LegacyMigrationPipelineTest.php` usa fixtures
100% sintéticas (nenhum dado real/pessoal) em uma conexão `legacy` SQLite
`:memory:` isolada. Cobre: preservação de ID, venda com/sem cliente,
telefone duplicado, produto sem categoria/variação, item com preço zero,
estoque decimal, ajuste fracionado, pagamento derivado (pago e pendente),
stock movement de venda/perda, cópia de caixa, timestamps históricos,
dry-run, idempotência e migração por módulo (`--only`).

## Riscos conhecidos / pontos em aberto

- A conexão `legacy` real de produção (host além de `localhost`) ainda não
  foi confirmada como acessível a partir de fora do ambiente de hospedagem
  (ver Etapa 1/2) — a migração real para produção dependerá de resolver
  esse acesso (Remote MySQL do cPanel, túnel SSH, ou exportar/importar o
  dump manualmente) antes de ser executada de fato.
- `pagamentos` usa uma estratégia de idempotência simples (1 registro por
  `venda_id`): se, no futuro, o sistema novo já em produção permitir vários
  pagamentos manuais por venda **antes** desta migração ser re-executada,
  rodar `legacy:migrate` de novo sobrescreveria esses pagamentos manuais.
  Esta migração deve ser tratada como uma operação "de carga inicial", não
  para ser re-executada livremente depois que o sistema novo já estiver em
  uso real com pagamentos próprios.
