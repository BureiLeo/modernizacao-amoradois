<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Produto;
use App\Models\ProdutoBom;
use App\Models\User;
use App\Services\Stock\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesInventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_stock_availability_uses_bom_and_reports_shortage(): void
    {
        $material = Material::factory()->create([
            'nome' => 'Caneca branca',
            'estoque' => 2,
            'estoque_minimo' => 1,
        ]);

        $produto = Produto::factory()->create(['nome' => 'Caneca personalizada']);
        ProdutoBom::factory()->create([
            'produto_id' => $produto->id,
            'material_id' => $material->id,
            'quantidade' => 1,
        ]);

        $result = app(StockService::class)->checkAvailability($produto, 3);

        $this->assertFalse($result['available']);
        $this->assertSame(1.0, round((float) $result['missing'], 1));
        $this->assertSame('Caneca branca', $result['requirements'][0]['material']->nome);
    }

    public function test_sales_inventory_routes_are_available_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/vendas')
            ->assertOk()
            ->assertSee('Vendas');

        $this->actingAs($user)
            ->get('/vendas/create')
            ->assertOk()
            ->assertSee('Nova venda');

        $this->actingAs($user)
            ->get('/estoque')
            ->assertOk()
            ->assertSee('Estoque');
    }
}
