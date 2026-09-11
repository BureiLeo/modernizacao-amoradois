## Amor a Dois - Sistema de Gestão

Aplicação PHP simples (sem framework) para gestão de produtos, materiais, vendas, perdas, compras e investimentos.

### Requisitos
- PHP 8+
- MySQL 5.7+ ou MariaDB
- Extensão PDO habilitada

### Configuração
1. Copie o arquivo `config.example.php` para `config.php`:
   ```php
   cp config.example.php config.php
   ```
2. Edite `config.php` com suas credenciais de banco.
3. Importe seu schema SQL (crie um dump ou gere manualmente as tabelas necessárias).

### Estrutura principal
| Arquivo | Função |
|---------|--------|
| `layout_start.php` / `layout_end.php` | Template base com sidebar e topo |
| `menu.php` | Navegação lateral |
| `auth.php` | Login / sessão |
| `db.php` | Conexão PDO reutilizável |
| `app.css` | Estilos (tema claro/escuro) |
| `vendas.php` | Listagem de vendas |
| `vendas_nova.php` | Registro de venda |
| `produtos.php` | Produtos |
| `materiais.php` | Materiais |

### Login
Página de login está em `index.php` (tema escuro, responsivo). Crie usuários conforme sua lógica em `auth.php` (ajuste se quiser hash de senha).

### Deploy (cPanel / hospedagem compartilhada)
1. Enviar arquivos (exceto os ignorados no `.gitignore`).
2. Criar banco de dados e usuário.
3. Ajustar `config.php`.
4. Garantir que a pasta pública aponte para onde está `index.php`.

### Segurança / Boas práticas sugeridas
- Usar senhas com hash (`password_hash`).
- Proteger `config.php` (não versionar — já ignorado).
- Validar/filtrar entradas de formulários.
- Adicionar CSRF tokens em ações críticas.

### Desenvolvimento
Para alterar estilos: editar `app.css` e, se necessário, incrementar `?v=` no `layout_start.php` para forçar refresh.

### Licença
Projeto interno. Ajuste conforme necessidade.
