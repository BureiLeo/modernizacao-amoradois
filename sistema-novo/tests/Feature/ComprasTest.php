<?php

namespace Tests\Feature;

use App\Models\CaixaMovimento;
use App\Models\Compra;
use App\Models\Material;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Compras\CompraService;
use App\Services\Compras\NfeXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprasTest extends TestCase
{
    use RefreshDatabase;

    public function test_lote_de_compra_soma_estoque_e_recalcula_custo_medio(): void
    {
        $material = Material::factory()->create([
            'estoque' => 10,
            'custo_medio' => 2,
        ]);

        $lote = app(CompraService::class)->registerBatch([
            'data' => now()->toDateString(),
            'itens' => [
                ['material_id' => $material->id, 'quantidade' => 10, 'valor_unitario' => 4],
            ],
        ]);

        $material->refresh();

        $this->assertCount(1, $lote);
        $this->assertEquals(20, (float) $material->estoque);
        // (10 * 2 + 10 * 4) / 20 = 3
        $this->assertEquals(3, (float) $material->custo_medio);
        $this->assertDatabaseHas('stock_movements', [
            'material_id' => $material->id,
            'type' => 'compra',
            'source_type' => 'compra',
        ]);
    }

    public function test_item_sem_material_cadastra_material_novo(): void
    {
        app(CompraService::class)->registerBatch([
            'data' => now()->toDateString(),
            'itens' => [
                ['nome' => 'Caneca branca 325ml', 'unidade_base' => 'un', 'quantidade' => 12, 'valor_unitario' => 5.50],
            ],
        ]);

        $material = Material::query()->where('nome', 'Caneca branca 325ml')->first();

        $this->assertNotNull($material);
        $this->assertEquals(12, (float) $material->estoque);
        $this->assertEquals(5.5, (float) $material->custo_medio);
    }

    public function test_frete_e_rateado_entre_os_itens_sem_perder_centavos(): void
    {
        $a = Material::factory()->create(['estoque' => 0, 'custo_medio' => 0]);
        $b = Material::factory()->create(['estoque' => 0, 'custo_medio' => 0]);

        $lote = app(CompraService::class)->registerBatch([
            'data' => now()->toDateString(),
            'frete' => 10,
            'itens' => [
                ['material_id' => $a->id, 'quantidade' => 1, 'valor_unitario' => 100],
                ['material_id' => $b->id, 'quantidade' => 1, 'valor_unitario' => 200],
            ],
        ]);

        $this->assertEquals(10.0, round((float) $lote->sum('frete'), 2));
        $this->assertEquals(300.0, round((float) $lote->sum('valor'), 2));
        $this->assertEquals(103.33, round((float) $lote->first()->custo_unitario, 2));
    }

    public function test_itens_do_mesmo_lote_compartilham_o_lote_id(): void
    {
        $a = Material::factory()->create();
        $b = Material::factory()->create();

        $lote = app(CompraService::class)->registerBatch([
            'data' => now()->toDateString(),
            'numero_nota' => '12345',
            'itens' => [
                ['material_id' => $a->id, 'quantidade' => 2, 'valor_unitario' => 3],
                ['material_id' => $b->id, 'quantidade' => 5, 'valor_unitario' => 1],
            ],
        ]);

        $this->assertCount(1, $lote->pluck('lote_id')->unique());
        $this->assertNotNull($lote->first()->lote_id);
    }

    public function test_estorno_devolve_o_estoque_e_remove_as_linhas(): void
    {
        $material = Material::factory()->create(['estoque' => 5, 'custo_medio' => 1]);

        $service = app(CompraService::class);

        $lote = $service->registerBatch([
            'data' => now()->toDateString(),
            'itens' => [
                ['material_id' => $material->id, 'quantidade' => 7, 'valor_unitario' => 2],
            ],
        ]);

        $this->assertEquals(12, (float) $material->fresh()->estoque);

        $service->reverseBatch((string) $lote->first()->lote_id);

        $this->assertEquals(5, (float) $material->fresh()->estoque);
        $this->assertDatabaseCount('compras', 0);
        // O historico de movimentacao nunca e apagado: a devolucao vira
        // um novo movimento negativo.
        $this->assertSame(2, StockMovement::query()->where('material_id', $material->id)->count());
    }

    public function test_compra_com_desconto_maior_que_o_total_e_bloqueada(): void
    {
        $material = Material::factory()->create(['estoque' => 0]);

        $this->expectException(\InvalidArgumentException::class);

        app(CompraService::class)->registerBatch([
            'data' => now()->toDateString(),
            'desconto' => 500,
            'itens' => [
                ['material_id' => $material->id, 'quantidade' => 1, 'valor_unitario' => 10],
            ],
        ]);
    }

    public function test_xml_da_nfe_e_lido_com_itens_e_cabecalho(): void
    {
        $dados = app(NfeXmlParser::class)->parse($this->xmlExemplo());

        $this->assertSame('4321', $dados['numero_nota']);
        $this->assertSame('Fornecedor Teste LTDA', $dados['fornecedor_nome']);
        $this->assertCount(2, $dados['itens']);
        $this->assertSame('CANECA BRANCA 325ML', $dados['itens'][0]['descricao']);
        $this->assertEquals(12, $dados['itens'][0]['quantidade']);
        $this->assertEquals(5.5, $dados['itens'][0]['valor_unitario']);
        $this->assertEquals(15.0, $dados['frete']);
    }

    public function test_compra_paga_lanca_saida_no_caixa(): void
    {
        $material = Material::factory()->create(['estoque' => 0, 'custo_medio' => 0]);

        $lote = app(CompraService::class)->registerBatch([
            'data' => now()->toDateString(),
            'numero_nota' => '777',
            'frete' => 10,
            'pago' => true,
            'forma_pagamento' => 'PIX',
            'itens' => [
                ['material_id' => $material->id, 'quantidade' => 2, 'valor_unitario' => 45],
            ],
        ]);

        $this->assertDatabaseHas('caixa_movimentos', [
            'tipo' => 'compra',
            'ref_tabela' => 'compras',
            'ref_id' => $lote->first()->id,
            'valor' => -100.00,
        ]);
    }

    public function test_compra_pendente_nao_mexe_no_caixa(): void
    {
        $material = Material::factory()->create(['estoque' => 0]);

        app(CompraService::class)->registerBatch([
            'data' => now()->toDateString(),
            'pago' => false,
            'itens' => [
                ['material_id' => $material->id, 'quantidade' => 2, 'valor_unitario' => 45],
            ],
        ]);

        $this->assertDatabaseCount('caixa_movimentos', 0);
    }

    public function test_marcar_compra_como_paga_lanca_no_caixa_sem_mexer_no_estoque(): void
    {
        $material = Material::factory()->create(['estoque' => 0, 'custo_medio' => 0]);

        $service = app(CompraService::class);

        $lote = $service->registerBatch([
            'data' => now()->toDateString(),
            'itens' => [
                ['material_id' => $material->id, 'quantidade' => 3, 'valor_unitario' => 10],
            ],
        ]);

        $estoqueAntes = (float) $material->fresh()->estoque;
        $movimentosAntes = StockMovement::query()->count();

        $service->markAsPaid((string) $lote->first()->lote_id, 'PIX');

        $this->assertTrue((bool) $lote->first()->fresh()->pago);
        $this->assertSame('PIX', $lote->first()->fresh()->forma_pagamento);
        $this->assertDatabaseHas('caixa_movimentos', ['tipo' => 'compra', 'valor' => -30.00]);
        // Pagamento nao controla estoque.
        $this->assertEquals($estoqueAntes, (float) $material->fresh()->estoque);
        $this->assertSame($movimentosAntes, StockMovement::query()->count());
    }

    public function test_marcar_como_paga_duas_vezes_nao_duplica_o_caixa(): void
    {
        $material = Material::factory()->create(['estoque' => 0]);

        $service = app(CompraService::class);

        $lote = $service->registerBatch([
            'data' => now()->toDateString(),
            'itens' => [
                ['material_id' => $material->id, 'quantidade' => 1, 'valor_unitario' => 50],
            ],
        ]);

        $service->markAsPaid((string) $lote->first()->lote_id, 'PIX');
        $service->markAsPaid((string) $lote->first()->lote_id, 'Dinheiro');

        $this->assertDatabaseCount('caixa_movimentos', 1);
    }

    public function test_estorno_de_compra_paga_devolve_o_dinheiro_ao_caixa(): void
    {
        $material = Material::factory()->create(['estoque' => 0]);

        $service = app(CompraService::class);

        $lote = $service->registerBatch([
            'data' => now()->toDateString(),
            'pago' => true,
            'itens' => [
                ['material_id' => $material->id, 'quantidade' => 1, 'valor_unitario' => 80],
            ],
        ]);

        $service->reverseBatch((string) $lote->first()->lote_id);

        // Os dois lancamentos ficam no historico e se anulam no saldo.
        $this->assertDatabaseCount('caixa_movimentos', 2);
        $this->assertEquals(0.0, (float) CaixaMovimento::query()->sum('valor'));
    }

    public function test_rotas_de_compra_exigem_autenticacao(): void
    {
        $this->get(route('compras.index'))->assertRedirect(route('login'));
        $this->get(route('compras.create'))->assertRedirect(route('login'));
    }

    public function test_telas_de_compra_abrem_para_usuario_logado(): void
    {
        $user = User::factory()->create();
        $material = Material::factory()->create();

        $lote = app(CompraService::class)->registerBatch([
            'data' => now()->toDateString(),
            'numero_nota' => '999',
            'itens' => [
                ['material_id' => $material->id, 'quantidade' => 1, 'valor_unitario' => 10],
            ],
        ], $user->id);

        $this->actingAs($user)->get(route('compras.index'))->assertOk();
        $this->actingAs($user)->get(route('compras.create'))->assertOk();
        $this->actingAs($user)
            ->get(route('compras.show', ['lote' => $lote->first()->lote_id]))
            ->assertOk()
            ->assertSee('NF 999');
    }

    public function test_compra_legada_sem_lote_continua_abrindo(): void
    {
        $user = User::factory()->create();
        $material = Material::factory()->create();

        $compra = Compra::query()->create([
            'data' => '2023-05-10',
            'material_id' => $material->id,
            'quantidade_embalagem' => 1,
            'quantidade' => 3,
            'valor' => 30,
            'custo_unitario' => 10,
            'pago' => true,
        ]);

        $this->actingAs($user)
            ->get(route('compras.show', ['lote' => $compra->id]))
            ->assertOk()
            ->assertSee('Compra #'.$compra->id);
    }

    private function xmlExemplo(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
  <NFe>
    <infNFe Id="NFe35240512345678000199550010000043211000043215" versao="4.00">
      <ide>
        <nNF>4321</nNF>
        <dhEmi>2024-05-12T10:30:00-03:00</dhEmi>
      </ide>
      <emit>
        <CNPJ>12345678000199</CNPJ>
        <xNome>Fornecedor Teste LTDA</xNome>
      </emit>
      <det nItem="1">
        <prod>
          <cProd>CAN325</cProd>
          <xProd>CANECA BRANCA 325ML</xProd>
          <uCom>UN</uCom>
          <qCom>12.0000</qCom>
          <vUnCom>5.5000000000</vUnCom>
          <vProd>66.00</vProd>
        </prod>
      </det>
      <det nItem="2">
        <prod>
          <cProd>PAPT</cProd>
          <xProd>PAPEL TRANSFER A4</xProd>
          <uCom>PCT</uCom>
          <qCom>2.0000</qCom>
          <vUnCom>40.0000000000</vUnCom>
          <vProd>80.00</vProd>
        </prod>
      </det>
      <total>
        <ICMSTot>
          <vFrete>15.00</vFrete>
          <vDesc>0.00</vDesc>
          <vNF>161.00</vNF>
        </ICMSTot>
      </total>
    </infNFe>
  </NFe>
</nfeProc>
XML;
    }
}
