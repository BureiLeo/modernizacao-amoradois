<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Categorias\Index as CategoriasIndex;
use App\Models\Categoria;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoriaCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin->value]);
    }

    public function test_visitante_e_redirecionado_para_login(): void
    {
        $response = $this->get(route('categorias.index'));

        $response->assertRedirect('/login');
    }

    public function test_lista_categorias_cadastradas(): void
    {
        $user = User::factory()->create();
        Categoria::factory()->create(['nome' => 'Canecas']);

        Livewire::actingAs($user)->test(CategoriasIndex::class)
            ->assertSee('Canecas');
    }

    public function test_nenhuma_categoria_cadastrada_mostra_estado_vazio(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(CategoriasIndex::class)
            ->assertSee('Nenhuma categoria');
    }

    public function test_admin_cria_categoria_com_sucesso(): void
    {
        $user = $this->admin();

        Livewire::actingAs($user)->test(CategoriasIndex::class)
            ->call('novaCategoria')
            ->set('form.nome', 'Almofadas')
            ->set('form.descricao', 'Almofadas personalizadas')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categorias', ['nome' => 'Almofadas']);
    }

    public function test_nome_da_categoria_e_obrigatorio(): void
    {
        $user = $this->admin();

        Livewire::actingAs($user)->test(CategoriasIndex::class)
            ->call('novaCategoria')
            ->set('form.nome', '')
            ->call('salvar')
            ->assertHasErrors(['form.nome' => 'required']);
    }

    public function test_admin_edita_categoria_existente(): void
    {
        $user = $this->admin();
        $categoria = Categoria::factory()->create(['nome' => 'Nome Antigo']);

        Livewire::actingAs($user)->test(CategoriasIndex::class)
            ->call('editar', $categoria->id)
            ->set('form.nome', 'Nome Atualizado')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertSame('Nome Atualizado', $categoria->fresh()->nome);
    }

    public function test_admin_inativa_categoria(): void
    {
        $user = $this->admin();
        $categoria = Categoria::factory()->create(['ativo' => true]);

        Livewire::actingAs($user)->test(CategoriasIndex::class)
            ->call('alternarAtivo', $categoria->id);

        $this->assertFalse($categoria->fresh()->ativo);
    }

    public function test_vendedor_nao_pode_criar_categoria(): void
    {
        $user = User::factory()->create(['role' => UserRole::Vendedor->value]);

        // A policy nega a acao (a AuthorizationException e convertida
        // em resposta 403 pelo Livewire durante o teste) antes que
        // qualquer registro seja criado.
        Livewire::actingAs($user)->test(CategoriasIndex::class)
            ->set('form.nome', 'Categoria Nao Autorizada')
            ->call('salvar');

        $this->assertDatabaseCount('categorias', 0);
    }

    public function test_categoria_em_uso_nao_e_apagada_ao_inativar(): void
    {
        $user = $this->admin();
        $categoria = Categoria::factory()->create(['ativo' => true]);
        $produto = Produto::factory()->create(['categoria_id' => $categoria->id]);

        Livewire::actingAs($user)->test(CategoriasIndex::class)
            ->call('alternarAtivo', $categoria->id);

        // Inativar a categoria e uma alteracao logica (ativo=false), o
        // registro continua existindo e o produto continua vinculado.
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
        $this->assertSame($categoria->id, $produto->fresh()->categoria_id);
    }

    public function test_produto_sem_categoria_continua_funcionando(): void
    {
        $produto = Produto::factory()->create(['categoria_id' => null]);

        $this->assertNull($produto->categoria);
    }
}
