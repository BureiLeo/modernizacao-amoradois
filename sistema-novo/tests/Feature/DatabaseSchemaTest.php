<?php

namespace Tests\Feature;

use App\Enums\CaixaTipo;
use App\Enums\FormaPagamento;
use App\Enums\StatusPagamento;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\CaixaMovimento;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Material;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\ProdutoBom;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes de schema/models da Etapa 3 (modernizacao).
 *
 * Nao testam regras de negocio/servicos (isso ainda nao existe - sera
 * construido em etapas futuras). O objetivo aqui e validar que a
 * estrutura do banco novo (migrations + models + relacionamentos)
 * esta correta e utilizavel.
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_criacao_de_cliente(): void
    {
        $cliente = Cliente::factory()->create([
            'codigo' => 1,
            'nome' => 'Maiuly Sanches',
            'telefone' => '42998162107',
        ]);

        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'codigo' => 1,
            'nome' => 'Maiuly Sanches',
        ]);
        $this->assertTrue($cliente->ativo);
    }

    public function test_cliente_pode_ser_soft_deletado_sem_perder_o_registro(): void
    {
        $cliente = Cliente::factory()->create();

        $cliente->delete();

        $this->assertSoftDeleted($cliente);
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id]);
    }

    public function test_telefone_duplicado_de_clientes_e_permitido(): void
    {
        // Regra confirmada na Etapa 2: o banco legado tem um telefone
        // legitimamente compartilhado por 2 clientes - nao pode haver
        // UNIQUE em telefone.
        Cliente::factory()->create(['telefone' => '42998162107']);
        $segundo = Cliente::factory()->create(['telefone' => '42998162107']);

        $this->assertDatabaseHas('clientes', ['id' => $segundo->id, 'telefone' => '42998162107']);
    }

    public function test_criacao_de_produto_com_categoria(): void
    {
        $categoria = Categoria::factory()->create(['nome' => 'Canecas']);
        $produto = Produto::factory()->create([
            'categoria_id' => $categoria->id,
            'nome' => 'Caneca Branca Normal',
            'preco_venda' => 30,
        ]);

        $this->assertEquals('Canecas', $produto->categoria->nome);
        $this->assertTrue($categoria->produtos->contains($produto));
    }

    public function test_produto_pode_ser_criado_sem_categoria(): void
    {
        $produto = Produto::factory()->create(['categoria_id' => null]);

        $this->assertNull($produto->categoria_id);
        $this->assertDatabaseHas('produtos', ['id' => $produto->id]);
    }

    public function test_bom_relaciona_produto_e_material_com_quantidade_decimal(): void
    {
        $produto = Produto::factory()->create();
        $material = Material::factory()->create(['estoque' => 10]);

        $bom = ProdutoBom::factory()->create([
            'produto_id' => $produto->id,
            'material_id' => $material->id,
            'quantidade' => 0.5,
        ]);

        $this->assertEquals('0.5000', $bom->quantidade);
        $this->assertTrue($produto->bom->contains($bom));
        $this->assertTrue($material->produtoBom->contains($bom));
    }

    public function test_bom_nao_permite_material_duplicado_no_mesmo_produto(): void
    {
        $produto = Produto::factory()->create();
        $material = Material::factory()->create();
        ProdutoBom::factory()->create(['produto_id' => $produto->id, 'material_id' => $material->id]);

        $this->expectException(QueryException::class);

        ProdutoBom::factory()->create(['produto_id' => $produto->id, 'material_id' => $material->id]);
    }

    public function test_material_aceita_estoque_com_casas_decimais(): void
    {
        // Regressao do bug encontrado na Etapa 2 (estoque int perdendo
        // precisao em ajustes fracionados).
        $material = Material::factory()->create(['estoque' => 0]);

        $material->update(['estoque' => 3.00]);
        $material->update(['estoque' => $material->estoque - 2.99]);

        $this->assertEquals('0.010', $material->fresh()->estoque);
    }

    public function test_nao_e_possivel_apagar_material_usado_em_bom(): void
    {
        $produto = Produto::factory()->create();
        $material = Material::factory()->create();
        ProdutoBom::factory()->create(['produto_id' => $produto->id, 'material_id' => $material->id]);

        $this->expectException(QueryException::class);

        $material->forceDelete();
    }

    public function test_venda_pode_ser_criada_com_cliente(): void
    {
        $cliente = Cliente::factory()->create();
        $venda = Venda::factory()->create(['cliente_id' => $cliente->id]);

        $this->assertEquals($cliente->id, $venda->cliente->id);
        $this->assertTrue($cliente->vendas->contains($venda));
    }

    public function test_venda_pode_ser_criada_sem_cliente(): void
    {
        // Regra confirmada na Etapa 2: 96 das 197 vendas legadas nao
        // tem cliente_id - o novo schema precisa continuar permitindo.
        $venda = Venda::factory()->create(['cliente_id' => null]);

        $this->assertNull($venda->cliente_id);
        $this->assertDatabaseHas('vendas', ['id' => $venda->id]);
    }

    public function test_excluir_cliente_nao_apaga_suas_vendas(): void
    {
        $cliente = Cliente::factory()->create();
        $venda = Venda::factory()->create(['cliente_id' => $cliente->id]);

        $cliente->delete(); // soft delete
        $cliente->forceDelete();

        $this->assertDatabaseHas('vendas', ['id' => $venda->id]);
        $this->assertNull($venda->fresh()->cliente_id);
    }

    public function test_venda_com_itens_calcula_relacionamento_e_persiste_totais(): void
    {
        $venda = Venda::factory()->create(['subtotal' => 0, 'total' => 0]);
        $produto = Produto::factory()->create(['preco_venda' => 30]);

        $item = VendaItem::factory()->create([
            'venda_id' => $venda->id,
            'produto_id' => $produto->id,
            'quantidade' => 2,
            'preco_unitario' => 30,
            'subtotal' => 60,
        ]);

        $venda->update(['subtotal' => 60, 'total' => 60]);

        $this->assertCount(1, $venda->itens);
        $this->assertEquals('60.00', $venda->fresh()->total);
        $this->assertEquals($produto->id, $item->produto->id);
    }

    public function test_nao_e_possivel_apagar_produto_ja_vendido(): void
    {
        $produto = Produto::factory()->create();
        $venda = Venda::factory()->create();
        VendaItem::factory()->create(['venda_id' => $venda->id, 'produto_id' => $produto->id]);

        $this->expectException(QueryException::class);

        $produto->forceDelete();
    }

    public function test_pagamento_pertence_a_uma_venda_e_usa_enums(): void
    {
        $venda = Venda::factory()->create();
        $pagamento = Pagamento::factory()->create([
            'venda_id' => $venda->id,
            'forma' => FormaPagamento::Pix->value,
            'status' => StatusPagamento::Pago->value,
            'valor' => 100,
        ]);

        $this->assertTrue($venda->pagamentos->contains($pagamento));
        $this->assertEquals(FormaPagamento::Pix, $pagamento->forma);
        $this->assertEquals(StatusPagamento::Pago, $pagamento->status);
    }

    public function test_stock_movement_registra_entrada_e_saida_de_material(): void
    {
        $material = Material::factory()->create(['estoque' => 10]);

        $entrada = StockMovement::factory()->create([
            'material_id' => $material->id,
            'type' => StockMovementType::Compra->value,
            'quantity' => 5,
            'quantity_before' => 10,
            'quantity_after' => 15,
            'source_type' => 'compra',
            'source_id' => 1,
        ]);

        $saida = StockMovement::factory()->create([
            'material_id' => $material->id,
            'type' => StockMovementType::Venda->value,
            'quantity' => -2,
            'quantity_before' => 15,
            'quantity_after' => 13,
            'source_type' => 'venda',
            'source_id' => 1,
        ]);

        $this->assertEquals(StockMovementType::Compra, $entrada->type);
        $this->assertEquals(StockMovementType::Venda, $saida->type);
        $this->assertCount(2, $material->stockMovements);
        $this->assertEquals('-2.000', $saida->quantity);
    }

    public function test_caixa_movimento_impede_duplicidade_para_mesma_referencia(): void
    {
        CaixaMovimento::factory()->create([
            'tipo' => CaixaTipo::Venda->value,
            'ref_tabela' => 'vendas',
            'ref_id' => 123,
            'valor' => 100,
        ]);

        $this->expectException(QueryException::class);

        CaixaMovimento::factory()->create([
            'tipo' => CaixaTipo::Venda->value,
            'ref_tabela' => 'vendas',
            'ref_id' => 123,
            'valor' => 999,
        ]);
    }

    public function test_caixa_movimento_permite_varios_ajustes_sem_referencia(): void
    {
        CaixaMovimento::factory()->count(3)->create([
            'tipo' => CaixaTipo::Ajuste->value,
            'ref_tabela' => null,
            'ref_id' => null,
        ]);

        $this->assertDatabaseCount('caixa_movimentos', 3);
    }

    public function test_usuario_tem_role_padrao_vendedor_e_pode_ser_admin(): void
    {
        $vendedor = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertEquals(UserRole::Vendedor, $vendedor->role);
        $this->assertEquals(UserRole::Admin, $admin->role);
    }

    public function test_relacionamento_produto_variacoes(): void
    {
        $produto = Produto::factory()->create();
        $variacao = $produto->variacoes()->create([
            'nome' => 'Alça preta',
            'sku' => 'VAR-001',
            'ativo' => true,
        ]);

        $this->assertEquals($produto->id, $variacao->produto->id);
        $this->assertTrue($produto->fresh()->variacoes->contains($variacao));
    }
}
