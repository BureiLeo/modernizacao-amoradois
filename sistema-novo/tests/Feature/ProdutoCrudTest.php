<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Produtos\Create as ProdutosCreate;
use App\Livewire\Produtos\Edit as ProdutosEdit;
use App\Livewire\Produtos\Index as ProdutosIndex;
use App\Livewire\Produtos\Show as ProdutosShow;
use App\Models\Categoria;
use App\Models\Material;
use App\Models\Produto;
use App\Models\ProdutoBom;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProdutoCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin->value]);
    }

    // ==================== LISTAGEM / BUSCA / FILTROS ====================

    public function test_visitante_e_redirecionado_para_login(): void
    {
        $response = $this->get(route('produtos.index'));

        $response->assertRedirect('/login');
    }

    public function test_lista_produtos_cadastrados(): void
    {
        $user = User::factory()->create();
        Produto::factory()->create(['nome' => 'Caneca Mágica']);

        Livewire::actingAs($user)->test(ProdutosIndex::class)
            ->assertSee('Caneca Mágica');
    }

    public function test_busca_por_nome(): void
    {
        $user = User::factory()->create();
        Produto::factory()->create(['nome' => 'Almofada Personalizada']);
        Produto::factory()->create(['nome' => 'Chaveiro Acrílico']);

        Livewire::actingAs($user)->test(ProdutosIndex::class)
            ->set('search', 'Almofada')
            ->assertSee('Almofada Personalizada')
            ->assertDontSee('Chaveiro Acrílico');
    }

    public function test_busca_por_sku(): void
    {
        $user = User::factory()->create();
        Produto::factory()->create(['nome' => 'Produto SKU', 'sku' => 'CAN-001']);

        Livewire::actingAs($user)->test(ProdutosIndex::class)
            ->set('search', 'CAN-001')
            ->assertSee('Produto SKU');
    }

    public function test_filtro_por_categoria(): void
    {
        $user = User::factory()->create();
        $categoria = Categoria::factory()->create(['nome' => 'Canecas']);
        Produto::factory()->create(['nome' => 'Produto Com Categoria', 'categoria_id' => $categoria->id]);
        Produto::factory()->create(['nome' => 'Produto Sem Categoria', 'categoria_id' => null]);

        Livewire::actingAs($user)->test(ProdutosIndex::class)
            ->set('categoriaFiltro', (string) $categoria->id)
            ->assertSee('Produto Com Categoria')
            ->assertDontSee('Produto Sem Categoria');
    }

    public function test_filtro_ativos_e_inativos(): void
    {
        $user = User::factory()->create();
        Produto::factory()->create(['nome' => 'Produto Ativo', 'ativo' => true]);
        Produto::factory()->create(['nome' => 'Produto Inativo', 'ativo' => false]);

        Livewire::actingAs($user)->test(ProdutosIndex::class)
            ->set('filtro', 'ativos')
            ->assertSee('Produto Ativo')
            ->assertDontSee('Produto Inativo');
    }

    public function test_produto_sem_categoria_aparece_normalmente(): void
    {
        $user = User::factory()->create();
        Produto::factory()->create(['nome' => 'Produto Orfao', 'categoria_id' => null]);

        Livewire::actingAs($user)->test(ProdutosIndex::class)
            ->assertSee('Produto Orfao')
            ->assertSee('Sem categoria');
    }

    // ==================== CRIACAO ====================

    public function test_criar_produto_com_sucesso(): void
    {
        $user = $this->admin();

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.nome', 'Novo Produto')
            ->set('form.preco_venda', '29,90')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('produtos', ['nome' => 'Novo Produto']);
        $produto = Produto::where('nome', 'Novo Produto')->first();
        $this->assertEquals(29.90, (float) $produto->preco_venda);
    }

    public function test_criar_produto_com_bom(): void
    {
        $user = $this->admin();
        $material = Material::factory()->create(['nome' => 'Caneca Branca']);

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.nome', 'Caneca Personalizada')
            ->set('form.preco_venda', '35,00')
            ->call('adicionarMaterial')
            ->set('form.bom.0.material_id', $material->id)
            ->set('form.bom.0.quantidade', '1,5')
            ->call('salvar')
            ->assertHasNoErrors();

        $produto = Produto::where('nome', 'Caneca Personalizada')->firstOrFail();
        $this->assertDatabaseHas('produto_bom', [
            'produto_id' => $produto->id,
            'material_id' => $material->id,
            'quantidade' => 1.5,
        ]);
    }

    public function test_bom_nao_permite_material_duplicado(): void
    {
        $user = $this->admin();
        $material = Material::factory()->create();

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.nome', 'Produto Duplicado')
            ->set('form.preco_venda', '20,00')
            ->set('form.bom', [
                ['material_id' => $material->id, 'quantidade' => '1'],
                ['material_id' => $material->id, 'quantidade' => '2'],
            ])
            ->call('salvar')
            ->assertHasErrors(['form.bom.1.material_id' => 'distinct']);
    }

    public function test_bom_exige_quantidade_maior_que_zero(): void
    {
        $user = $this->admin();
        $material = Material::factory()->create();

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.nome', 'Produto Sem Quantidade')
            ->set('form.preco_venda', '20,00')
            ->set('form.bom', [
                ['material_id' => $material->id, 'quantidade' => '00,00'],
            ])
            ->call('salvar')
            ->assertHasErrors(['form.bom.0.quantidade']);
    }

    public function test_calcula_e_aplica_preco_sugerido_com_porcentagem(): void
    {
        $user = $this->admin();
        $material = Material::factory()->create(['custo_medio' => 10]);

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.bom', [
                ['material_id' => $material->id, 'quantidade' => '2'],
            ])
            ->set('form.margem_lucro', '50')
            ->assertViewHas('custoBom', 20.0)
            ->assertViewHas('precoSugerido', 30.0)
            ->call('aplicarPrecoSugerido')
            ->assertSet('form.preco_venda', '30,00');
    }

    public function test_produto_sem_imagem_funciona_normalmente(): void
    {
        $user = $this->admin();

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.nome', 'Produto Sem Imagem')
            ->set('form.preco_venda', '10,00')
            ->call('salvar')
            ->assertHasNoErrors();

        $produto = Produto::where('nome', 'Produto Sem Imagem')->first();
        $this->assertNull($produto->imagem);
    }

    public function test_produto_com_imagem_faz_upload_via_storage(): void
    {
        Storage::fake('public');
        $user = $this->admin();
        $arquivo = UploadedFile::fake()->image('produto.jpg');

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.nome', 'Produto Com Imagem')
            ->set('form.preco_venda', '15,00')
            ->set('form.novaImagem', $arquivo)
            ->call('salvar')
            ->assertHasNoErrors();

        $produto = Produto::where('nome', 'Produto Com Imagem')->first();
        $this->assertNotNull($produto->imagem);
        Storage::disk('public')->assertExists($produto->imagem);
    }

    public function test_validacao_de_upload_rejeita_arquivo_invalido(): void
    {
        Storage::fake('public');
        $user = $this->admin();
        $arquivoInvalido = UploadedFile::fake()->create('arquivo.pdf', 100);

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.nome', 'Produto Upload Invalido')
            ->set('form.preco_venda', '15,00')
            ->set('form.novaImagem', $arquivoInvalido)
            ->call('salvar')
            ->assertHasErrors(['form.novaImagem']);
    }

    public function test_preco_aceita_formato_decimal_brasileiro(): void
    {
        $user = $this->admin();

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.nome', 'Produto Preco Virgula')
            ->set('form.preco_venda', '123,45')
            ->call('salvar')
            ->assertHasNoErrors();

        $produto = Produto::where('nome', 'Produto Preco Virgula')->first();
        $this->assertEquals(123.45, (float) $produto->preco_venda);
    }

    public function test_custo_referencia_aceita_formato_decimal_brasileiro(): void
    {
        $user = $this->admin();

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.nome', 'Produto Custo Virgula')
            ->set('form.preco_venda', '50,00')
            ->set('form.custo_referencia', '12,34')
            ->call('salvar')
            ->assertHasNoErrors();

        $produto = Produto::where('nome', 'Produto Custo Virgula')->first();
        $this->assertEquals(12.34, (float) $produto->custo_referencia);
    }

    public function test_nome_obrigatorio(): void
    {
        $user = $this->admin();

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.nome', '')
            ->set('form.preco_venda', '10,00')
            ->call('salvar')
            ->assertHasErrors(['form.nome' => 'required']);
    }

    public function test_preco_obrigatorio(): void
    {
        $user = $this->admin();

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.nome', 'Produto Sem Preco')
            ->set('form.preco_venda', '')
            ->call('salvar')
            ->assertHasErrors(['form.preco_venda']);
    }

    public function test_sku_duplicado_gera_erro_de_validacao(): void
    {
        $user = $this->admin();
        Produto::factory()->create(['sku' => 'DUPLICADO-1']);

        Livewire::actingAs($user)->test(ProdutosCreate::class)
            ->set('form.nome', 'Produto Sku Repetido')
            ->set('form.sku', 'DUPLICADO-1')
            ->set('form.preco_venda', '10,00')
            ->call('salvar')
            ->assertHasErrors(['form.sku' => 'unique']);
    }

    // ==================== EDICAO ====================

    public function test_editar_produto_atualiza_dados(): void
    {
        $user = $this->admin();
        $produto = Produto::factory()->create(['nome' => 'Nome Antigo', 'preco_venda' => 10]);

        Livewire::actingAs($user)->test(ProdutosEdit::class, ['produto' => $produto])
            ->set('form.nome', 'Nome Atualizado')
            ->set('form.preco_venda', '20,00')
            ->call('salvar')
            ->assertHasNoErrors();

        $fresh = $produto->fresh();
        $this->assertSame('Nome Atualizado', $fresh->nome);
        $this->assertEquals(20.00, (float) $fresh->preco_venda);
    }

    public function test_sku_unico_ignora_o_proprio_produto_na_edicao(): void
    {
        $user = $this->admin();
        $produto = Produto::factory()->create(['sku' => 'MEU-SKU']);

        Livewire::actingAs($user)->test(ProdutosEdit::class, ['produto' => $produto])
            ->set('form.nome', $produto->nome)
            ->set('form.sku', 'MEU-SKU')
            ->set('form.preco_venda', (string) $produto->preco_venda)
            ->call('salvar')
            ->assertHasNoErrors();
    }

    public function test_editar_produto_substitui_o_bom(): void
    {
        $user = $this->admin();
        $produto = Produto::factory()->create();
        $materialAntigo = Material::factory()->create();
        $materialNovo = Material::factory()->create();
        ProdutoBom::factory()->create([
            'produto_id' => $produto->id,
            'material_id' => $materialAntigo->id,
            'quantidade' => 1,
        ]);

        Livewire::actingAs($user)->test(ProdutosEdit::class, ['produto' => $produto])
            ->assertSet('form.bom.0.material_id', $materialAntigo->id)
            ->set('form.bom.0.material_id', $materialNovo->id)
            ->set('form.bom.0.quantidade', '2,25')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('produto_bom', [
            'produto_id' => $produto->id,
            'material_id' => $materialAntigo->id,
        ]);
        $this->assertDatabaseHas('produto_bom', [
            'produto_id' => $produto->id,
            'material_id' => $materialNovo->id,
            'quantidade' => 2.25,
        ]);
    }

    // ==================== DETALHES / BOM / VENDAS ====================

    public function test_bom_e_exibido_em_somente_leitura(): void
    {
        $user = User::factory()->create();
        $produto = Produto::factory()->create();
        $material = Material::factory()->create(['nome' => 'Caneca Branca']);
        ProdutoBom::factory()->create([
            'produto_id' => $produto->id,
            'material_id' => $material->id,
            'quantidade' => 1,
        ]);

        Livewire::actingAs($user)->test(ProdutosShow::class, ['produto' => $produto])
            ->assertSee('Caneca Branca')
            ->assertDontSee('wire:click="removerMaterial'); // sem acao de edicao de BOM nesta etapa
    }

    public function test_produto_com_vendas_historicas_mostra_estatisticas(): void
    {
        $user = User::factory()->create();
        $produto = Produto::factory()->create();
        $venda = Venda::factory()->create(['data' => now()]);
        VendaItem::factory()->create([
            'venda_id' => $venda->id,
            'produto_id' => $produto->id,
            'quantidade' => 3,
            'subtotal' => 90,
        ]);

        Livewire::actingAs($user)->test(ProdutosShow::class, ['produto' => $produto])
            ->assertSee('R$ 90,00');
    }

    // ==================== INATIVAR / REATIVAR ====================

    public function test_admin_pode_inativar_produto(): void
    {
        $user = $this->admin();
        $produto = Produto::factory()->create(['ativo' => true]);

        Livewire::actingAs($user)->test(ProdutosShow::class, ['produto' => $produto])
            ->call('alternarAtivo');

        $this->assertFalse($produto->fresh()->ativo);
    }

    public function test_admin_pode_reativar_produto(): void
    {
        $user = $this->admin();
        $produto = Produto::factory()->create(['ativo' => false]);

        Livewire::actingAs($user)->test(ProdutosShow::class, ['produto' => $produto])
            ->call('alternarAtivo');

        $this->assertTrue($produto->fresh()->ativo);
    }

    public function test_vendedor_nao_pode_inativar_produto(): void
    {
        $user = User::factory()->create(['role' => UserRole::Vendedor->value]);
        $produto = Produto::factory()->create(['ativo' => true]);

        Livewire::actingAs($user)->test(ProdutosShow::class, ['produto' => $produto])
            ->call('alternarAtivo');

        $this->assertTrue($produto->fresh()->ativo);
    }

    public function test_inativar_produto_nao_apaga_historico_de_vendas(): void
    {
        $user = $this->admin();
        $produto = Produto::factory()->create(['ativo' => true]);
        $venda = Venda::factory()->create(['data' => now()]);
        $item = VendaItem::factory()->create([
            'venda_id' => $venda->id,
            'produto_id' => $produto->id,
            'subtotal' => 45,
        ]);

        Livewire::actingAs($user)->test(ProdutosShow::class, ['produto' => $produto])
            ->call('alternarAtivo');

        $this->assertDatabaseHas('venda_itens', [
            'id' => $item->id,
            'produto_id' => $produto->id,
            'subtotal' => 45,
        ]);
    }
}
