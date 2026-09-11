<?php

namespace Tests\Feature\LegacyMigration;

use App\Enums\StatusPagamento;
use App\Services\LegacyMigration\LegacyMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Testes do pipeline de migracao de dados do legado (Etapa 4).
 *
 * Usa fixtures 100% sinteticas (ver CreatesLegacyFixture) - NENHUM dado
 * pessoal ou real do sistema legado e usado aqui. O destino (conexao
 * padrao) e o sqlite ':memory:' de testes (RefreshDatabase); a origem
 * (conexao "legacy") e outro sqlite ':memory:' isolado.
 */
class LegacyMigrationPipelineTest extends TestCase
{
    use CreatesLegacyFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLegacyFixture();
        $this->seedLegacyFixture();
    }

    public function test_migracao_preserva_ids_originais(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $this->assertDatabaseHas('clientes', ['id' => 1, 'nome' => 'Cliente A']);
        $this->assertDatabaseHas('produtos', ['id' => 1, 'nome' => 'Caneca Personalizada']);
        $this->assertDatabaseHas('vendas', ['id' => 1]);
        $this->assertDatabaseHas('vendas', ['id' => 2]);
    }

    public function test_venda_sem_cliente_e_preservada(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $this->assertDatabaseHas('vendas', ['id' => 2, 'cliente_id' => null, 'pago' => false]);
    }

    public function test_venda_com_cliente_e_preservada(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $this->assertDatabaseHas('vendas', ['id' => 1, 'cliente_id' => 1, 'pago' => true]);
    }

    public function test_telefone_duplicado_entre_clientes_e_preservado(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $this->assertEquals(2, DB::table('clientes')->where('telefone', '42999990000')->count());
    }

    public function test_produto_migrado_sem_categoria_e_sem_variacao(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $this->assertDatabaseHas('produtos', ['id' => 1, 'categoria_id' => null]);
        $this->assertEquals(0, DB::table('produto_variacoes')->count());
    }

    public function test_item_com_preco_zero_e_preservado(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $this->assertDatabaseHas('venda_itens', ['id' => 2, 'preco_unitario' => 0]);
    }

    public function test_material_migra_estoque_decimal_sem_arredondar(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $material = DB::table('materiais')->where('id', 1)->first();
        $this->assertEquals('47.500', $material->estoque);
    }

    public function test_ajuste_fracionado_vira_stock_movement_com_precisao(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $movimento = DB::table('stock_movements')
            ->where('source_type', 'ajuste_estoque')
            ->where('source_id', 1)
            ->first();

        $this->assertNotNull($movimento);
        $this->assertEquals('-2.990', $movimento->quantity);
        $this->assertEquals('50.49', $movimento->quantity_before);
        $this->assertEquals('47.50', $movimento->quantity_after);
    }

    public function test_pagamento_e_derivado_para_venda_paga(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $pagamento = DB::table('pagamentos')->where('venda_id', 1)->first();
        $this->assertNotNull($pagamento);
        $this->assertEquals(StatusPagamento::Pago->value, $pagamento->status);
        $this->assertNotNull($pagamento->pago_em);
    }

    public function test_pagamento_e_derivado_pendente_para_venda_em_aberto(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $pagamento = DB::table('pagamentos')->where('venda_id', 2)->first();
        $this->assertNotNull($pagamento);
        $this->assertEquals(StatusPagamento::Pendente->value, $pagamento->status);
        $this->assertNull($pagamento->pago_em);
    }

    public function test_stock_movement_de_venda_e_negativo(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $movimento = DB::table('stock_movements')
            ->where('source_type', 'venda')->where('source_id', 1)->first();

        $this->assertNotNull($movimento);
        $this->assertEquals('venda', $movimento->type);
        $this->assertEquals('-2.000', $movimento->quantity);
    }

    public function test_stock_movement_de_perda_e_negativo(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $movimento = DB::table('stock_movements')
            ->where('source_type', 'perda')->where('source_id', 1)->first();

        $this->assertNotNull($movimento);
        $this->assertEquals('perda', $movimento->type);
        $this->assertEquals('-1.000', $movimento->quantity);
    }

    public function test_caixa_movimentos_e_copiado_sem_reprocessar(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $this->assertEquals(2, DB::table('caixa_movimentos')->count());
        $this->assertDatabaseHas('caixa_movimentos', ['id' => 1, 'valor' => 60, 'tipo' => 'venda']);
        $this->assertDatabaseHas('caixa_movimentos', ['id' => 2, 'valor' => -270, 'tipo' => 'compra']);
    }

    public function test_timestamps_historicos_sao_preservados_nao_now(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false);

        $cliente = DB::table('clientes')->where('id', 1)->first();
        $this->assertStringStartsWith('2025-01-01', (string) $cliente->created_at);
    }

    public function test_dry_run_nao_grava_nada(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: true);

        $this->assertEquals(0, DB::table('clientes')->count());
        $this->assertEquals(0, DB::table('vendas')->count());
        $this->assertEquals(0, DB::table('stock_movements')->count());
    }

    public function test_migracao_e_idempotente_ao_rodar_duas_vezes(): void
    {
        $service = app(LegacyMigrationService::class);

        $service->run(dryRun: false);

        $counts1 = $this->snapshotCounts();

        $service->run(dryRun: false);

        $counts2 = $this->snapshotCounts();

        $this->assertEquals($counts1, $counts2, 'A segunda execução não pode alterar nenhuma contagem.');
    }

    public function test_apenas_modulo_clientes_pode_ser_migrado_isoladamente(): void
    {
        app(LegacyMigrationService::class)->run(dryRun: false, only: ['clientes']);

        $this->assertEquals(2, DB::table('clientes')->count());
        $this->assertEquals(0, DB::table('produtos')->count());
        $this->assertEquals(0, DB::table('vendas')->count());
    }

    /** @return array<string, int> */
    private function snapshotCounts(): array
    {
        return [
            'clientes' => DB::table('clientes')->count(),
            'produtos' => DB::table('produtos')->count(),
            'materiais' => DB::table('materiais')->count(),
            'produto_bom' => DB::table('produto_bom')->count(),
            'compras' => DB::table('compras')->count(),
            'vendas' => DB::table('vendas')->count(),
            'venda_itens' => DB::table('venda_itens')->count(),
            'pagamentos' => DB::table('pagamentos')->count(),
            'perdas' => DB::table('perdas')->count(),
            'stock_movements' => DB::table('stock_movements')->count(),
            'investimentos' => DB::table('investimentos')->count(),
            'caixa_movimentos' => DB::table('caixa_movimentos')->count(),
        ];
    }
}
