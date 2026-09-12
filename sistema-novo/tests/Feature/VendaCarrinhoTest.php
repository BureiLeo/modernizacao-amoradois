<?php

namespace Tests\Feature;

use App\Livewire\Vendas\Create as VendaCreate;
use App\Livewire\Vendas\Edit as VendaEdit;
use App\Models\CaixaMovimento;
use App\Models\Cliente;
use App\Models\Material;
use App\Models\Produto;
use App\Models\ProdutoBom;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VendaCarrinhoTest extends TestCase
{
    use RefreshDatabase;

    private function produtoComBom(string $nome, float $preco, ?Material $material = null, float $consumo = 1): Produto
    {
        $material ??= Material::factory()->create(['estoque' => 1000]);
        $produto = Produto::factory()->create(['nome' => $nome, 'preco_venda' => $preco, 'ativo' => true]);

        ProdutoBom::query()->create([
            'produto_id' => $produto->id,
            'material_id' => $material->id,
            'quantidade' => $consumo,
        ]);

        return $produto;
    }

    public function test_grade_de_produtos_monta_carrinho_com_varios_itens(): void
    {
        $user = User::factory()->create();
        $caneca = $this->produtoComBom('Caneca personalizada', 35);
        $camiseta = $this->produtoComBom('Camiseta', 60);

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->assertSee('Caneca personalizada')
            ->assertSee('Camiseta')
            ->call('adicionar', $caneca->id)
            ->call('adicionar', $caneca->id)
            ->call('adicionar', $camiseta->id)
            ->assertSet('carrinho.p:'.$caneca->id.'.quantidade', 2)
            ->assertSet('carrinho.p:'.$camiseta->id.'.quantidade', 1);
    }

    public function test_stepper_aumenta_diminui_e_remove_o_item(): void
    {
        $user = User::factory()->create();
        $produto = $this->produtoComBom('Caneca', 35);
        $chave = 'p:'.$produto->id;

        $component = Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('adicionar', $produto->id)
            ->call('aumentar', $chave)
            ->call('aumentar', $chave)
            ->assertSet("carrinho.$chave.quantidade", 3)
            ->call('diminuir', $chave)
            ->assertSet("carrinho.$chave.quantidade", 2);

        // Diminuir no ultimo item remove a linha em vez de deixar quantidade zero.
        $component->call('diminuir', $chave)->call('diminuir', $chave)->assertSet('carrinho', []);
    }

    public function test_outros_custos_entram_no_total_sem_baixar_estoque(): void
    {
        $user = User::factory()->create();
        $material = Material::factory()->create(['estoque' => 100]);
        $produto = $this->produtoComBom('Caneca', 30, $material);

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('adicionar', $produto->id)
            ->set('outroDescricao', 'Taxa de personalização')
            ->set('outroValor', '15,50')
            ->set('outroQuantidade', 2)
            ->call('adicionarOutro')
            ->call('revisar')
            ->call('salvar');

        $venda = Venda::query()->latest('id')->firstOrFail();

        $this->assertEquals(61.00, (float) $venda->subtotal); // 30 + (15,50 x 2)
        $this->assertEquals(61.00, (float) $venda->total);
        $this->assertCount(2, $venda->itens);

        $avulso = $venda->itens->firstWhere('produto_id', null);
        $this->assertNotNull($avulso);
        $this->assertSame('Taxa de personalização', $avulso->descricao);

        // Item avulso nao tem BOM: so a caneca consome material.
        $this->assertEquals(99.0, (float) $material->fresh()->estoque);
    }

    public function test_outro_custo_exige_descricao_e_valor(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->set('outroValor', '10')
            ->call('adicionarOutro')
            ->assertHasErrors('outroDescricao')
            ->assertSet('carrinho', [])
            ->set('outroDescricao', 'Embalagem')
            ->set('outroValor', '0')
            ->call('adicionarOutro')
            ->assertHasErrors('outroValor')
            ->assertSet('carrinho', []);
    }

    public function test_frete_soma_ao_total_e_desconto_subtrai(): void
    {
        $user = User::factory()->create();
        $produto = $this->produtoComBom('Caneca', 100);

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('adicionar', $produto->id)
            ->set('frete', '20,00')
            ->set('desconto', '15')
            ->call('revisar')
            ->call('salvar');

        $venda = Venda::query()->latest('id')->firstOrFail();

        $this->assertEquals(100.00, (float) $venda->subtotal);
        $this->assertEquals(20.00, (float) $venda->frete);
        $this->assertEquals(15.00, (float) $venda->desconto);
        $this->assertEquals(105.00, (float) $venda->total);
    }

    public function test_desconto_maior_que_o_total_bloqueia_a_venda(): void
    {
        $user = User::factory()->create();
        $produto = $this->produtoComBom('Caneca', 50);

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('adicionar', $produto->id)
            ->set('desconto', '90')
            ->call('revisar')
            ->assertSet('confirmando', false)
            ->assertSee('não pode deixar o total negativo');

        $this->assertSame(0, Venda::query()->count());
    }

    public function test_venda_paga_gera_caixa_e_pendente_nao(): void
    {
        $user = User::factory()->create();
        $produto = $this->produtoComBom('Caneca', 40);

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('adicionar', $produto->id)
            ->set('pago', false)
            ->call('revisar')
            ->call('salvar');

        $this->assertSame(0, CaixaMovimento::query()->count());

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('adicionar', $produto->id)
            ->set('pago', true)
            ->set('forma_pagamento', 'pix')
            ->call('revisar')
            ->call('salvar');

        $this->assertSame(1, CaixaMovimento::query()->count());
        $this->assertEquals(40.0, (float) CaixaMovimento::query()->sum('valor'));
    }

    public function test_revisar_exige_pelo_menos_um_item(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('revisar')
            ->assertSet('confirmando', false)
            ->assertSee('Adicione pelo menos um item');
    }

    public function test_cliente_e_opcional(): void
    {
        $user = User::factory()->create();
        $produto = $this->produtoComBom('Caneca', 25);

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('adicionar', $produto->id)
            ->call('revisar')
            ->call('salvar');

        $this->assertNull(Venda::query()->latest('id')->first()->cliente_id);
    }

    public function test_estoque_insuficiente_bloqueia_e_nao_salva_nada(): void
    {
        $user = User::factory()->create();
        $material = Material::factory()->create(['estoque' => 1]);
        $produto = $this->produtoComBom('Caneca', 30, $material);

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('adicionar', $produto->id)
            ->call('aumentar', 'p:'.$produto->id)
            ->call('aumentar', 'p:'.$produto->id)
            ->call('revisar')
            ->call('salvar')
            ->assertSee('Não foi possível finalizar');

        $this->assertSame(0, Venda::query()->count());
        $this->assertEquals(1.0, (float) $material->fresh()->estoque);
    }

    public function test_produto_sem_estoque_aparece_cinza_e_nao_entra_no_carrinho(): void
    {
        $user = User::factory()->create();
        $material = Material::factory()->create(['estoque' => 0]);
        $produtoSemEstoque = $this->produtoComBom('Caneca lilás', 35, $material);
        $produtoComEstoque = $this->produtoComBom('Caneca azul', 35);

        $test = Livewire::actingAs($user)->test(VendaCreate::class)
            ->assertSee('Sem estoque')
            ->call('adicionar', $produtoSemEstoque->id)
            ->call('adicionar', $produtoComEstoque->id)
            ->assertSet('carrinho.p:'.$produtoComEstoque->id.'.quantidade', 1);

        $this->assertArrayNotHasKey('p:'.$produtoSemEstoque->id, $test->get('carrinho'));
    }

    public function test_material_de_apoio_sem_estoque_nao_bloqueia_venda(): void
    {
        $user = User::factory()->create();
        $caneca = Material::factory()->create(['estoque' => 10, 'essencial' => true]);
        $embalagem = Material::factory()->create(['estoque' => 0, 'essencial' => false]);
        $produto = Produto::factory()->create(['nome' => 'Caneca com caixa', 'preco_venda' => 35, 'ativo' => true]);

        ProdutoBom::query()->create(['produto_id' => $produto->id, 'material_id' => $caneca->id, 'quantidade' => 1]);
        ProdutoBom::query()->create(['produto_id' => $produto->id, 'material_id' => $embalagem->id, 'quantidade' => 1]);

        // Produto continua disponivel para venda: so o material essencial
        // (a caneca) conta para a disponibilidade exibida na grade.
        $test = Livewire::actingAs($user)->test(VendaCreate::class)
            ->assertDontSee('Sem estoque')
            ->call('adicionar', $produto->id)
            ->call('revisar')
            ->call('salvar');

        $this->assertNull($test->get('erro'), (string) $test->get('erro'));
        $this->assertSame(1, Venda::query()->count());
        $this->assertEquals(9.0, (float) $caneca->fresh()->estoque);
        $this->assertEquals(-1.0, (float) $embalagem->fresh()->estoque);
    }

    // ==================== EDIÇÃO ====================

    public function test_edicao_carrega_todos_os_itens_da_venda(): void
    {
        $user = User::factory()->create();
        $caneca = $this->produtoComBom('Caneca', 30);
        $camiseta = $this->produtoComBom('Camiseta', 70);

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('adicionar', $caneca->id)
            ->call('adicionar', $camiseta->id)
            ->set('outroDescricao', 'Frete extra')
            ->set('outroValor', '9')
            ->call('adicionarOutro')
            ->call('revisar')
            ->call('salvar');

        $venda = Venda::query()->latest('id')->firstOrFail();

        Livewire::actingAs($user)->test(VendaEdit::class, ['venda' => $venda])
            ->assertCount('carrinho', 3)
            ->assertSet('carrinho.p:'.$caneca->id.'.quantidade', 1)
            ->assertSee('Frete extra');
    }

    public function test_editar_quantidade_reajusta_totais_e_estoque(): void
    {
        $user = User::factory()->create();
        $material = Material::factory()->create(['estoque' => 100]);
        $produto = $this->produtoComBom('Caneca', 30, $material);

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('adicionar', $produto->id)
            ->call('aumentar', 'p:'.$produto->id)
            ->call('revisar')
            ->call('salvar');

        $venda = Venda::query()->latest('id')->firstOrFail();
        $this->assertEquals(98.0, (float) $material->fresh()->estoque);

        Livewire::actingAs($user)->test(VendaEdit::class, ['venda' => $venda])
            ->call('diminuir', 'p:'.$produto->id)
            ->call('revisar')
            ->call('salvar');

        $venda->refresh();

        $this->assertEquals(30.0, (float) $venda->total);
        $this->assertCount(1, $venda->itens);
        // Estorna as 2 unidades e consome 1: volta para 99.
        $this->assertEquals(99.0, (float) $material->fresh()->estoque);
    }

    public function test_editar_venda_pendente_para_paga_gera_caixa_uma_vez(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $produto = $this->produtoComBom('Caneca', 45);

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('adicionar', $produto->id)
            ->set('pago', false)
            ->call('revisar')
            ->call('salvar');

        $venda = Venda::query()->latest('id')->firstOrFail();
        $this->assertSame(0, CaixaMovimento::query()->count());

        Livewire::actingAs($user)->test(VendaEdit::class, ['venda' => $venda])
            ->set('cliente_id', $cliente->id)
            ->set('pago', true)
            ->set('forma_pagamento', 'dinheiro')
            ->call('revisar')
            ->call('salvar');

        $venda->refresh();

        $this->assertTrue((bool) $venda->pago);
        $this->assertSame($cliente->id, $venda->cliente_id);
        $this->assertSame(1, CaixaMovimento::query()->count());
        $this->assertEquals(45.0, (float) CaixaMovimento::query()->sum('valor'));

        // Salvar de novo nao pode duplicar a entrada no caixa.
        Livewire::actingAs($user)->test(VendaEdit::class, ['venda' => $venda->fresh()])
            ->call('revisar')
            ->call('salvar');

        $this->assertSame(1, CaixaMovimento::query()->count());
    }

    public function test_editar_venda_sem_itens_nao_apaga_a_venda(): void
    {
        $user = User::factory()->create();
        $produto = $this->produtoComBom('Caneca', 30);

        Livewire::actingAs($user)->test(VendaCreate::class)
            ->call('adicionar', $produto->id)
            ->call('revisar')
            ->call('salvar');

        $venda = Venda::query()->latest('id')->firstOrFail();

        Livewire::actingAs($user)->test(VendaEdit::class, ['venda' => $venda])
            ->call('limpar')
            ->call('salvar')
            ->assertSee('precisa de pelo menos um item');

        $this->assertCount(1, $venda->fresh()->itens);
    }
}
