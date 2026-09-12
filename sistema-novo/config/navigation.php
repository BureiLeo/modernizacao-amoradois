<?php

/**
 * Fonte unica de verdade da navegacao (sidebar desktop, bottom nav mobile
 * e drawer "Mais"), para nao duplicar a lista de itens em 3 lugares
 * diferentes (Etapa 5 #16/#54).
 *
 * Cada item so aparece habilitado (clicavel) se sua rota realmente
 * existir. Modulos ainda nao implementados aparecem desabilitados com
 * "Em breve" em vez de simplesmente somem, para permitir validar a
 * navegacao completa (sidebar/bottom-nav/drawer) nesta etapa - decisao
 * documentada na entrega da Etapa 5 (alternativa B da secao 54).
 */
return [

    // Itens principais (sidebar desktop + base do bottom nav mobile).
    'primary' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'dashboard'],
        ['key' => 'vendas', 'label' => 'Vendas', 'icon' => 'shopping-bag', 'route' => 'vendas.index'],
        ['key' => 'produtos', 'label' => 'Produtos', 'icon' => 'gift', 'route' => 'produtos.index'],
        ['key' => 'estoque', 'label' => 'Estoque', 'icon' => 'archive', 'route' => 'estoque.index'],
        ['key' => 'compras', 'label' => 'Compras', 'icon' => 'truck', 'route' => 'compras.index'],
        ['key' => 'clientes', 'label' => 'Clientes', 'icon' => 'users', 'route' => 'clientes.index'],
        ['key' => 'fornecedores', 'label' => 'Fornecedores', 'icon' => 'truck', 'route' => 'fornecedores.index'],
        ['key' => 'financeiro', 'label' => 'Financeiro', 'icon' => 'wallet', 'route' => 'financeiro.index'],
        ['key' => 'relatorios', 'label' => 'Relatórios', 'icon' => 'bar-chart-3', 'route' => 'relatorios.index'],
    ],

    // Itens secundarios (rodape da sidebar / fim do drawer "Mais").
    'secondary' => [
        ['key' => 'categorias', 'label' => 'Categorias', 'icon' => 'gift', 'route' => 'categorias.index'],
        ['key' => 'usuarios', 'label' => 'Usuários', 'icon' => 'user', 'route' => 'usuarios.index'],
        ['key' => 'configuracoes', 'label' => 'Configurações', 'icon' => 'settings', 'route' => 'profile'],
    ],

    // Chaves (do array 'primary') exibidas na bottom nav mobile, nesta
    // ordem, ao redor do botao central "+". "Mais" e sempre o ultimo
    // slot e abre o drawer com tudo que nao estiver aqui.
    'bottom_nav' => ['dashboard', 'vendas', 'estoque'],

    // Acao do botao central "+" da bottom nav (Nova Venda, ou preparado
    // sem logica complexa enquanto a rota nao existir - Etapa 5 #12).
    'primary_action' => ['label' => 'Nova Venda', 'route' => 'vendas.create'],
];
