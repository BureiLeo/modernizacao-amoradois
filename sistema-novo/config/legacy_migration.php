<?php

use App\Enums\CaixaTipo;
use App\Enums\FormaPagamento;
use App\Enums\PerdaTipo;

/**
 * Configuracao central da migracao de dados do sistema legado (Etapa 4).
 *
 * Centraliza aqui (em vez de espalhar pelos Services) tudo que e
 * "regra de mapeamento" pura: contagens esperadas para a validacao
 * pre-migracao, e os mapas campo-antigo -> campo-novo para valores
 * que precisam de transformacao explicita e documentada.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Contagens esperadas na origem (Etapa 2)
    |--------------------------------------------------------------------------
    | Usadas so como alerta informativo antes de migrar - se o dump legado
    | mudar (mais vendas, mais clientes...) isso NAO e um erro fatal, so
    | avisamos a diferenca (ver ValidateOriginCounts).
    */
    'expected_counts' => [
        'users' => 4,
        'clientes' => 35,
        'produtos' => 13,
        'produto_bom' => 72,
        'materiais' => 19,
        'compras' => 39,
        'vendas' => 197,
        'venda_itens' => 203,
        'venda_consumos' => 1203,
        'perdas' => 19,
        'perda_consumos' => 94,
        'ajustes_estoque' => 11,
        'caixa_movimentos' => 266,
        'investimentos' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Forma de pagamento: legado (string livre) -> App\Enums\FormaPagamento
    |--------------------------------------------------------------------------
    | Valores conhecidos no legado (Etapa 2): "PIX", "Dinheiro", "Outro".
    | Mapa case-insensitive. Qualquer valor novo/desconhecido cai no
    | fallback 'outro' e gera um warning (nunca falha silenciosamente).
    */
    'forma_pagamento_map' => [
        'pix' => FormaPagamento::Pix->value,
        'dinheiro' => FormaPagamento::Dinheiro->value,
        'debito' => FormaPagamento::Debito->value,
        'débito' => FormaPagamento::Debito->value,
        'credito' => FormaPagamento::Credito->value,
        'crédito' => FormaPagamento::Credito->value,
        'transferencia' => FormaPagamento::Transferencia->value,
        'transferência' => FormaPagamento::Transferencia->value,
        'outro' => FormaPagamento::Outro->value,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tipo de caixa: legado (VENDA/COMPRA/AJUSTE/OUTRO) -> App\Enums\CaixaTipo
    |--------------------------------------------------------------------------
    */
    'caixa_tipo_map' => [
        'venda' => CaixaTipo::Venda->value,
        'compra' => CaixaTipo::Compra->value,
        'ajuste' => CaixaTipo::Ajuste->value,
        'outro' => CaixaTipo::Outro->value,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tipo de perda: legado (PRODUTO/MATERIAL) -> App\Enums\PerdaTipo
    |--------------------------------------------------------------------------
    */
    'perda_tipo_map' => [
        'produto' => PerdaTipo::Produto->value,
        'material' => PerdaTipo::Material->value,
    ],

    /*
    |--------------------------------------------------------------------------
    | Grupos do --only= do comando legacy:migrate
    |--------------------------------------------------------------------------
    */
    'groups' => [
        'usuarios' => ['users'],
        'clientes' => ['clientes'],
        'produtos' => ['materiais', 'produtos', 'produto_bom'],
        'estoque' => ['stock_movements'],
        'vendas' => ['vendas', 'pagamentos'],
        'perdas' => ['perdas'],
        'financeiro' => ['compras', 'investimentos', 'caixa_movimentos'],
    ],
];
