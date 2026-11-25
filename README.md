## Amor a Dois - Sistema de Gestão

Sistema web para gestão de clientes, produtos, materiais, estoque, compras, vendas, perdas e caixa da loja Amor a Dois Personalizados.

O sistema é uma aplicação **Laravel 13 + Livewire 3**, localizada em [`sistema-novo/`](./sistema-novo).

> O sistema legado em PHP procedural (sem framework) que existia na raiz deste repositório foi descontinuado e removido após a migração completa dos dados e o deploy da versão nova em produção.

### Como rodar localmente

Veja as instruções completas em [`sistema-novo/README.md`](./sistema-novo/README.md).

```
cd sistema-novo
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

### Produção

Configure as variáveis de ambiente e credenciais diretamente no servidor, sem versionar o arquivo `.env`. Mantenha a aplicação fora do diretório público, publique apenas o conteúdo de `public/` e execute as dependências e migrações conforme o processo do provedor de hospedagem.

### Licença
Projeto interno. Ajuste conforme necessidade.
