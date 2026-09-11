<?php

namespace Tests\Feature\LegacyMigration;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria e popula um schema "legado" SINTETICO (fixture, NENHUM dado
 * pessoal/real) em uma conexao sqlite ':memory:' separada chamada
 * "legacy", para testar o pipeline de migracao sem depender de MariaDB
 * ou do dump real.
 */
trait CreatesLegacyFixture
{
    protected function setUpLegacyFixture(): void
    {
        Config::set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('legacy');

        $schema = Schema::connection('legacy');

        $schema->create('users', function ($t) {
            $t->id();
            $t->string('nome');
            $t->string('username');
            $t->string('email')->nullable();
            $t->string('password_hash');
            $t->timestamp('created_at')->nullable();
        });

        $schema->create('clientes', function ($t) {
            $t->id();
            $t->unsignedInteger('codigo');
            $t->string('nome');
            $t->string('telefone')->nullable();
            $t->string('email')->nullable();
            $t->string('cpf')->nullable();
            $t->string('rg')->nullable();
            $t->string('cnpj')->nullable();
            $t->string('razao_social')->nullable();
            $t->text('endereco')->nullable();
            $t->text('obs')->nullable();
            $t->timestamp('criado_em')->nullable();
        });

        $schema->create('materiais', function ($t) {
            $t->id();
            $t->string('nome');
            $t->string('unidade_base');
            $t->unsignedInteger('fator_embalagem')->default(1);
            $t->decimal('estoque', 12, 3);
            $t->decimal('estoque_min', 12, 3)->default(0);
            $t->decimal('custo_medio', 12, 6)->default(0);
            $t->text('obs')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        $schema->create('produtos', function ($t) {
            $t->id();
            $t->string('nome');
            $t->string('sku')->nullable();
            $t->decimal('preco_sugerido', 12, 2)->default(0);
            $t->boolean('ativo')->default(1);
            $t->timestamp('created_at')->nullable();
        });

        $schema->create('produto_bom', function ($t) {
            $t->id();
            $t->unsignedBigInteger('produto_id');
            $t->unsignedBigInteger('material_id');
            $t->decimal('qtd_por_unidade', 12, 4);
        });

        $schema->create('compras', function ($t) {
            $t->id();
            $t->date('data');
            $t->unsignedBigInteger('material_id');
            $t->unsignedInteger('qtd_embalagem')->default(1);
            $t->decimal('qtd_base', 12, 3);
            $t->decimal('custo_total', 12, 2);
            $t->decimal('custo_unit', 12, 6);
            $t->decimal('frete', 12, 2)->nullable();
            $t->string('forma_pagamento')->nullable();
            $t->boolean('pago')->default(0);
            $t->string('anexo')->nullable();
            $t->string('obs')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        $schema->create('vendas', function ($t) {
            $t->id();
            $t->dateTime('data');
            $t->unsignedBigInteger('cliente_id')->nullable();
            $t->string('canal')->nullable();
            $t->string('forma_pagamento')->nullable();
            $t->boolean('pago')->default(0);
            $t->text('obs')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        $schema->create('venda_itens', function ($t) {
            $t->id();
            $t->unsignedBigInteger('venda_id');
            $t->unsignedBigInteger('produto_id');
            $t->unsignedInteger('qtd');
            $t->decimal('preco_unit', 12, 2);
            $t->decimal('custo_unit_calculado', 12, 6)->default(0);
            $t->decimal('lucro_unit', 12, 6)->default(0);
        });

        $schema->create('venda_consumos', function ($t) {
            $t->id();
            $t->unsignedBigInteger('venda_id');
            $t->unsignedBigInteger('material_id');
            $t->unsignedInteger('qtd_consumida');
            $t->decimal('custo_unit_no_momento', 12, 4);
            $t->timestamp('created_at')->nullable();
        });

        $schema->create('perdas', function ($t) {
            $t->id();
            $t->dateTime('data');
            $t->string('tipo');
            $t->unsignedBigInteger('produto_id')->nullable();
            $t->unsignedBigInteger('material_id')->nullable();
            $t->unsignedInteger('qtd');
            $t->string('motivo')->nullable();
            $t->string('obs')->nullable();
            $t->decimal('custo_total', 12, 2)->default(0);
            $t->timestamp('created_at')->nullable();
        });

        $schema->create('perda_consumos', function ($t) {
            $t->id();
            $t->unsignedBigInteger('perda_id');
            $t->unsignedBigInteger('material_id');
            $t->unsignedInteger('qtd_consumida');
            $t->decimal('custo_unit_no_momento', 12, 4);
            $t->timestamp('created_at')->nullable();
        });

        $schema->create('ajustes_estoque', function ($t) {
            $t->id();
            $t->unsignedBigInteger('material_id');
            $t->string('tipo');
            $t->decimal('quantidade', 10, 2);
            $t->decimal('estoque_anterior', 10, 2);
            $t->decimal('estoque_novo', 10, 2);
            $t->text('motivo');
            $t->unsignedBigInteger('usuario_id')->nullable();
            $t->dateTime('data_ajuste');
        });

        $schema->create('investimentos', function ($t) {
            $t->id();
            $t->string('nome');
            $t->string('categoria')->nullable();
            $t->date('data');
            $t->decimal('valor', 12, 2);
            $t->boolean('quitado')->default(0);
            $t->text('obs')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        $schema->create('caixa_movimentos', function ($t) {
            $t->id();
            $t->dateTime('data');
            $t->string('tipo');
            $t->string('ref_tabela')->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->string('descricao')->nullable();
            $t->decimal('valor', 12, 2);
            $t->timestamp('created_at')->nullable();
        });
    }

    /**
     * Popula o fixture com dados sinteticos cobrindo os casos exigidos
     * pela Etapa 4 (secao 63): ID preservado, venda sem cliente,
     * telefone duplicado, produto sem categoria/variacao, item preco
     * zero, decimal em material, ajuste fracionado, venda paga/pendente,
     * stock movement de venda/perda, caixa, timestamps.
     */
    protected function seedLegacyFixture(): void
    {
        $legacy = DB::connection('legacy');

        $legacy->table('users')->insert([
            'id' => 1, 'nome' => 'Admin Teste', 'username' => 'admin', 'email' => '',
            'password_hash' => password_hash('segredo', PASSWORD_BCRYPT), 'created_at' => '2025-01-01 10:00:00',
        ]);

        // Telefone duplicado legitimo entre dois clientes distintos.
        $legacy->table('clientes')->insert([
            ['id' => 1, 'codigo' => 1, 'nome' => 'Cliente A', 'telefone' => '42999990000', 'criado_em' => '2025-01-01 10:00:00'],
            ['id' => 2, 'codigo' => 2, 'nome' => 'Cliente B', 'telefone' => '42999990000', 'criado_em' => '2025-01-02 10:00:00'],
        ]);

        // Material com estoque fracionado (decimal).
        $legacy->table('materiais')->insert([
            'id' => 1, 'nome' => 'Caneca Branca', 'unidade_base' => 'un', 'fator_embalagem' => 1,
            'estoque' => 47.5, 'estoque_min' => 5, 'custo_medio' => 7.5, 'created_at' => '2025-01-01 10:00:00',
        ]);

        $legacy->table('produtos')->insert([
            'id' => 1, 'nome' => 'Caneca Personalizada', 'sku' => '0001', 'preco_sugerido' => 30, 'ativo' => 1,
            'created_at' => '2025-01-01 10:00:00',
        ]);

        $legacy->table('produto_bom')->insert([
            'id' => 1, 'produto_id' => 1, 'material_id' => 1, 'qtd_por_unidade' => 1,
        ]);

        $legacy->table('compras')->insert([
            'id' => 1, 'data' => '2025-01-01', 'material_id' => 1, 'qtd_embalagem' => 1, 'qtd_base' => 36,
            'custo_total' => 270, 'custo_unit' => 7.5, 'forma_pagamento' => 'PIX', 'pago' => 1,
            'created_at' => '2025-01-01 10:00:00',
        ]);

        // Venda #1: COM cliente, PAGA.
        $legacy->table('vendas')->insert([
            'id' => 1, 'data' => '2025-01-05 10:00:00', 'cliente_id' => 1, 'forma_pagamento' => 'PIX',
            'pago' => 1, 'created_at' => '2025-01-05 10:00:00',
        ]);
        // Venda #2: SEM cliente, PENDENTE (anomalia conhecida: cliente_id null).
        $legacy->table('vendas')->insert([
            'id' => 2, 'data' => '2025-01-06 10:00:00', 'cliente_id' => null, 'forma_pagamento' => 'PIX',
            'pago' => 0, 'created_at' => '2025-01-06 10:00:00',
        ]);

        $legacy->table('venda_itens')->insert([
            ['id' => 1, 'venda_id' => 1, 'produto_id' => 1, 'qtd' => 2, 'preco_unit' => 30, 'custo_unit_calculado' => 7.5, 'lucro_unit' => 22.5],
            // Item com preco_unit = 0 (anomalia conhecida preservada).
            ['id' => 2, 'venda_id' => 2, 'produto_id' => 1, 'qtd' => 1, 'preco_unit' => 0, 'custo_unit_calculado' => 7.5, 'lucro_unit' => -7.5],
        ]);

        $legacy->table('venda_consumos')->insert([
            ['id' => 1, 'venda_id' => 1, 'material_id' => 1, 'qtd_consumida' => 2, 'custo_unit_no_momento' => 7.5, 'created_at' => '2025-01-05 10:00:00'],
            ['id' => 2, 'venda_id' => 2, 'material_id' => 1, 'qtd_consumida' => 1, 'custo_unit_no_momento' => 7.5, 'created_at' => '2025-01-06 10:00:00'],
        ]);

        $legacy->table('perdas')->insert([
            'id' => 1, 'data' => '2025-01-07 10:00:00', 'tipo' => 'MATERIAL', 'material_id' => 1, 'qtd' => 1,
            'motivo' => 'quebra', 'custo_total' => 7.5, 'created_at' => '2025-01-07 10:00:00',
        ]);
        $legacy->table('perda_consumos')->insert([
            'id' => 1, 'perda_id' => 1, 'material_id' => 1, 'qtd_consumida' => 1, 'custo_unit_no_momento' => 7.5,
            'created_at' => '2025-01-07 10:00:00',
        ]);

        // Ajuste de estoque fracionado.
        $legacy->table('ajustes_estoque')->insert([
            'id' => 1, 'material_id' => 1, 'tipo' => 'remover', 'quantidade' => 2.99,
            'estoque_anterior' => 50.49, 'estoque_novo' => 47.5, 'motivo' => 'correção',
            'data_ajuste' => '2025-01-08 10:00:00',
        ]);

        $legacy->table('investimentos')->insert([
            'id' => 1, 'nome' => 'Impressora', 'categoria' => 'Equipamento', 'data' => '2025-01-01',
            'valor' => 500, 'quitado' => 1, 'created_at' => '2025-01-01 10:00:00',
        ]);

        $legacy->table('caixa_movimentos')->insert([
            ['id' => 1, 'data' => '2025-01-05 10:00:00', 'tipo' => 'VENDA', 'ref_tabela' => 'vendas', 'ref_id' => 1, 'valor' => 60, 'created_at' => '2025-01-05 10:00:00'],
            ['id' => 2, 'data' => '2025-01-01 10:00:00', 'tipo' => 'COMPRA', 'ref_tabela' => 'compras', 'ref_id' => 1, 'valor' => -270, 'created_at' => '2025-01-01 10:00:00'],
        ]);
    }
}
