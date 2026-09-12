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

Ambiente hospedado no Hostinger. O deploy consiste em:
1. Atualizar o código em `~/repo` (clone do branch `main`) e sincronizar com `rsync` para `~/laravel_app` (fora do `public_html`, por segurança).
2. Rodar `composer install --no-dev`, `php artisan migrate --force` e `php artisan config:cache/route:cache/view:cache`.
3. Publicar o conteúdo de `public/` (incluindo `public/build`, gerado com `npm run build`) em `public_html`, com um `index.php` apontando para o caminho absoluto da aplicação.

### Licença
Projeto interno. Ajuste conforme necessidade.
