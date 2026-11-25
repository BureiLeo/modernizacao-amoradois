<?php

namespace Tests\Feature;

use App\Livewire\Financeiro;
use App\Livewire\Vendas\Pdv;
use App\Models\CaixaFeira;
use App\Models\CaixaMovimento;
use App\Models\Material;
use App\Models\Produto;
use App\Models\ProdutoBom;
use App\Models\User;
use App\Models\Venda;
use App\Services\Compras\CompraService;
use App\Services\Vendas\VendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PdvTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdv_registra_venda_paga_e_reenvio_nao_duplica_baixa_ou_caixa(): void
    {
        $this->withoutVite();
        $this->get(route('vendas.pdv'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('vendas.pdv'))->assertOk()->assertSee('PDV da feira');
        $this->get(route('financeiro.index'))->assertOk();
        $financeiro = Livewire::test(Financeiro::class)
            ->set('nome', 'Feira de novembro')
            ->set('troco', '20,00')
            ->call('abrir')->assertHasNoErrors();

        $material = Material::factory()->create(['estoque' => 5, 'essencial' => true]);
        $produto = Produto::factory()->create(['nome' => 'Caneca da feira', 'sku' => 'FEIRA-01', 'preco_venda' => 25, 'ativo' => true]);
        $peca = Produto::factory()->create(['nome' => 'Peça 3D', 'conta' => 'impressao_3d', 'preco_venda' => 15, 'ativo' => true]);
        ProdutoBom::factory()->create([
            'produto_id' => $produto->id,
            'material_id' => $material->id,
            'quantidade' => 1,
        ]);
        ProdutoBom::factory()->create([
            'produto_id' => $peca->id,
            'material_id' => $material->id,
            'quantidade' => 1,
        ]);

        $pdv = Livewire::test(Pdv::class)
            ->set('busca', 'FEIRA-01')
            ->assertSee('Caneca da feira')
            ->call('adicionar', $produto->id)
            ->call('adicionar', $peca->id)
            ->call('adicionar', $produto->id)
            ->call('finalizar')
            ->assertHasErrors(['forma' => 'required'])
            ->set('forma', 'dinheiro')
            ->set('recebido', '49,99')
            ->call('finalizar')
            ->assertHasErrors('recebido')
            ->set('recebido', '75,50')
            ->assertSee('10,50')
            ->set('forma', 'credito');

        $reenvio = clone $pdv;

        $pdv->call('finalizar')->assertHasNoErrors()->assertSet('carrinho', []);
        $venda = Venda::query()->sole();
        $reenvio->call('finalizar')->assertHasNoErrors()->assertSet('vendaRegistrada', $venda->id);

        $this->assertDatabaseCount('vendas', 1);
        $this->assertDatabaseCount('pagamentos', 1);
        $this->assertDatabaseCount('caixa_movimentos', 1);
        $this->assertDatabaseHas('vendas', [
            'id' => $venda->id,
            'cliente_id' => null,
            'user_id' => $user->id,
            'canal' => 'feira',
            'pago' => true,
            'forma_pagamento' => 'credito',
            'total' => 65,
        ]);
        $this->assertDatabaseHas('caixa_movimentos', ['ref_id' => $venda->id, 'valor' => 65, 'conta' => 'feira']);
        $this->assertEquals(2, $material->fresh()->estoque);

        $novaVenda = Livewire::test(Pdv::class)->call('adicionar', $peca->id)->set('forma', 'pix');
        $financeiro->set('dinheiroContado', '20,00')->call('fechar')->assertHasNoErrors()
            ->assertSee('Relatório de fechamento')->assertSee('R$ 50,00 em vendas')->assertSee('R$ 15,00 em vendas');
        $novaVenda->call('finalizar')->assertHasErrors('carrinho');

        $financeiro->set('destino', 'impressao_3d')->set('valorRepasse', '16,00')->call('transferir')
            ->assertHasErrors('valorRepasse');
        $financeiro->set('valorRepasse', '14,50')->set('taxaRepasse', '0,50');
        $reenvioRepasse = clone $financeiro;
        $financeiro->call('transferir')->assertHasNoErrors();
        $reenvioRepasse->call('transferir')->assertHasNoErrors();
        $financeiro->set('destino', 'canecas')->set('valorRepasse', '50,00')->call('transferir')->assertHasNoErrors();

        $this->assertDatabaseCount('transferencias_feira', 2);
        $this->assertEquals(0, CaixaMovimento::query()->where('conta', 'feira')->sum('valor'));
        $this->assertEquals(50, CaixaMovimento::query()->where('conta', 'canecas')->sum('valor'));
        $this->assertEquals(14.50, CaixaMovimento::query()->where('conta', 'impressao_3d')->sum('valor'));
        $this->assertEquals(CaixaFeira::query()->sole()->id, $venda->caixa_feira_id);

        $financeiro->set('nome', 'Segundo dia')->call('abrir')->assertHasNoErrors()
            ->call('selecionar', $venda->caixa_feira_id)->assertSee('Relatório de fechamento');
    }

    public function test_compra_de_filamento_pendente_e_estorno_usam_a_conta_selecionada(): void
    {
        $service = app(CompraService::class);
        $compra = $service->registerBatch([
            'conta' => 'impressao_3d',
            'itens' => [['nome' => 'Filamento PLA', 'unidade_base' => 'g', 'quantidade' => 1000, 'valor_unitario' => 0.08]],
        ])->sole();

        $this->assertDatabaseCount('caixa_movimentos', 0);
        $this->assertEquals(1000, $compra->material->estoque);

        $service->markAsPaid($compra->lote_id, 'pix');
        $this->assertDatabaseHas('caixa_movimentos', ['conta' => 'impressao_3d', 'tipo' => 'compra', 'valor' => -80]);

        $service->reverseBatch($compra->lote_id);
        $this->assertDatabaseHas('caixa_movimentos', ['conta' => 'impressao_3d', 'tipo' => 'ajuste', 'valor' => 80]);
    }

    public function test_venda_mista_fora_da_feira_rateia_o_total_entre_as_duas_contas(): void
    {
        $material = Material::factory()->create(['estoque' => 10]);
        $caneca = Produto::factory()->create(['preco_venda' => 60]);
        $peca = Produto::factory()->create(['conta' => 'impressao_3d', 'preco_venda' => 40]);

        foreach ([$caneca, $peca] as $produto) {
            ProdutoBom::factory()->create([
                'produto_id' => $produto->id,
                'material_id' => $material->id,
                'quantidade' => 1,
            ]);
        }

        app(VendaService::class)->create([
            'itens' => [['produto_id' => $caneca->id], ['produto_id' => $peca->id]],
            'desconto' => 5,
            'pago' => true,
        ]);

        $this->assertDatabaseHas('caixa_movimentos', ['conta' => 'canecas', 'valor' => 57]);
        $this->assertDatabaseHas('caixa_movimentos', ['conta' => 'impressao_3d', 'valor' => 38]);
        $this->assertDatabaseCount('pagamentos', 1);
    }
}
