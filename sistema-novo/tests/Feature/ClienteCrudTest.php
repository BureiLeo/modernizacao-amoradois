<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Clientes\Create as ClientesCreate;
use App\Livewire\Clientes\Edit as ClientesEdit;
use App\Livewire\Clientes\Index as ClientesIndex;
use App\Livewire\Clientes\Show as ClientesShow;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Venda;
use App\Services\Clientes\ClienteCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClienteCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin->value]);
    }

    // ==================== LISTAGEM / BUSCA ====================

    public function test_visitante_e_redirecionado_para_login(): void
    {
        $response = $this->get(route('clientes.index'));

        $response->assertRedirect('/login');
    }

    public function test_lista_clientes_cadastrados(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->create(['nome' => 'Maria da Silva']);

        Livewire::actingAs($user)->test(ClientesIndex::class)
            ->assertSee('Maria da Silva');
    }

    public function test_busca_por_nome(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->create(['nome' => 'Joana Souza']);
        Cliente::factory()->create(['nome' => 'Carlos Pereira']);

        Livewire::actingAs($user)->test(ClientesIndex::class)
            ->set('search', 'Joana')
            ->assertSee('Joana Souza')
            ->assertDontSee('Carlos Pereira');
    }

    public function test_busca_por_telefone_ignora_formatacao(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->create(['nome' => 'Cliente Telefone', 'telefone' => '42999999999']);

        // Buscar com a formatacao completa deve encontrar o telefone
        // salvo somente com digitos (Etapa 6 #6).
        Livewire::actingAs($user)->test(ClientesIndex::class)
            ->set('search', '(42) 99999-9999')
            ->assertSee('Cliente Telefone');
    }

    public function test_busca_por_codigo(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create(['nome' => 'Cliente Codigo', 'codigo' => 777]);

        Livewire::actingAs($user)->test(ClientesIndex::class)
            ->set('search', '777')
            ->assertSee('Cliente Codigo');
    }

    public function test_filtro_ativos_e_inativos(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->create(['nome' => 'Cliente Ativo', 'ativo' => true]);
        Cliente::factory()->create(['nome' => 'Cliente Inativo', 'ativo' => false]);

        Livewire::actingAs($user)->test(ClientesIndex::class)
            ->set('filtro', 'ativos')
            ->assertSee('Cliente Ativo')
            ->assertDontSee('Cliente Inativo');

        Livewire::actingAs($user)->test(ClientesIndex::class)
            ->set('filtro', 'inativos')
            ->assertSee('Cliente Inativo')
            ->assertDontSee('Cliente Ativo');
    }

    public function test_paginacao_nao_carrega_todos_de_uma_vez(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->count(15)->create();

        $component = Livewire::actingAs($user)->test(ClientesIndex::class);

        $this->assertCount(12, $component->viewData('clientes')->items());
    }

    // ==================== CRIACAO / CODIGO ====================

    public function test_criar_cliente_com_sucesso(): void
    {
        $user = $this->admin();

        Livewire::actingAs($user)->test(ClientesCreate::class)
            ->set('form.nome', 'Nova Cliente')
            ->set('form.telefone', '42988887777')
            ->set('form.email', 'nova@example.com')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clientes', [
            'nome' => 'Nova Cliente',
            'email' => 'nova@example.com',
        ]);
    }

    public function test_codigo_e_gerado_automaticamente(): void
    {
        $cliente = app(ClienteCodeGenerator::class)->create([
            'nome' => 'Cliente Auto Codigo',
            'ativo' => true,
        ]);

        $this->assertNotNull($cliente->codigo);
        $this->assertGreaterThan(0, $cliente->codigo);
    }

    public function test_codigo_nao_colide_entre_criacoes_sucessivas(): void
    {
        $generator = app(ClienteCodeGenerator::class);

        $c1 = $generator->create(['nome' => 'Cliente 1', 'ativo' => true]);
        $c2 = $generator->create(['nome' => 'Cliente 2', 'ativo' => true]);
        $c3 = $generator->create(['nome' => 'Cliente 3', 'ativo' => true]);

        $codigos = [$c1->codigo, $c2->codigo, $c3->codigo];

        $this->assertSame($codigos, array_unique($codigos));
    }

    public function test_codigo_nunca_reutiliza_o_de_um_cliente_inativado(): void
    {
        $generator = app(ClienteCodeGenerator::class);

        $c1 = $generator->create(['nome' => 'Cliente Sera Removido', 'ativo' => true]);
        $c1->delete(); // soft delete

        $c2 = $generator->create(['nome' => 'Cliente Novo', 'ativo' => true]);

        $this->assertGreaterThan($c1->codigo, $c2->codigo);
    }

    public function test_telefone_duplicado_e_permitido(): void
    {
        $user = $this->admin();
        Cliente::factory()->create(['telefone' => '42999998888']);

        Livewire::actingAs($user)->test(ClientesCreate::class)
            ->set('form.nome', 'Segundo Cliente Mesmo Telefone')
            ->set('form.telefone', '42999998888')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertSame(2, Cliente::where('telefone', '42999998888')->count());
    }

    public function test_email_pode_ficar_em_branco(): void
    {
        $user = $this->admin();

        Livewire::actingAs($user)->test(ClientesCreate::class)
            ->set('form.nome', 'Cliente Sem Email')
            ->set('form.email', '')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clientes', [
            'nome' => 'Cliente Sem Email',
            'email' => null,
        ]);
    }

    public function test_email_invalido_gera_erro_de_validacao(): void
    {
        $user = $this->admin();

        Livewire::actingAs($user)->test(ClientesCreate::class)
            ->set('form.nome', 'Cliente Email Invalido')
            ->set('form.email', 'nao-e-email')
            ->call('salvar')
            ->assertHasErrors(['form.email']);
    }

    public function test_nome_obrigatorio(): void
    {
        $user = $this->admin();

        Livewire::actingAs($user)->test(ClientesCreate::class)
            ->set('form.nome', '')
            ->call('salvar')
            ->assertHasErrors(['form.nome' => 'required']);
    }

    // ==================== EDICAO ====================

    public function test_editar_cliente_atualiza_dados_cadastrais(): void
    {
        $user = $this->admin();
        $cliente = Cliente::factory()->create(['nome' => 'Nome Antigo']);

        Livewire::actingAs($user)->test(ClientesEdit::class, ['cliente' => $cliente])
            ->set('form.nome', 'Nome Atualizado')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertSame('Nome Atualizado', $cliente->fresh()->nome);
    }

    public function test_editar_cliente_nao_altera_codigo_nem_id(): void
    {
        $user = $this->admin();
        $cliente = Cliente::factory()->create(['nome' => 'Cliente Codigo Fixo', 'codigo' => 555]);

        Livewire::actingAs($user)->test(ClientesEdit::class, ['cliente' => $cliente])
            ->set('form.nome', 'Cliente Codigo Fixo Editado')
            ->call('salvar');

        $fresh = $cliente->fresh();
        $this->assertSame(555, $fresh->codigo);
        $this->assertSame($cliente->id, $fresh->id);
    }

    // ==================== DETALHES / HISTORICO ====================

    public function test_cliente_sem_vendas_mostra_estado_vazio(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        Livewire::actingAs($user)->test(ClientesShow::class, ['cliente' => $cliente])
            ->assertSee($cliente->nome)
            ->assertSee('Nenhuma venda registrada');
    }

    public function test_cliente_com_vendas_mostra_estatisticas(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        Venda::factory()->create(['cliente_id' => $cliente->id, 'total' => 150, 'data' => now()]);
        Venda::factory()->create(['cliente_id' => $cliente->id, 'total' => 50, 'data' => now()]);

        Livewire::actingAs($user)->test(ClientesShow::class, ['cliente' => $cliente])
            ->assertSee('R$ 200,00') // total comprado
            ->assertSee('R$ 100,00'); // ticket medio
    }

    public function test_venda_sem_cliente_nao_interfere_na_listagem_de_clientes(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->create(['nome' => 'Cliente Normal']);
        Venda::factory()->create(['cliente_id' => null, 'total' => 999]);

        Livewire::actingAs($user)->test(ClientesIndex::class)
            ->assertSee('Cliente Normal')
            ->assertDontSee('999');
    }

    // ==================== INATIVAR / REATIVAR ====================

    public function test_admin_pode_inativar_cliente(): void
    {
        $user = $this->admin();
        $cliente = Cliente::factory()->create(['ativo' => true]);

        Livewire::actingAs($user)->test(ClientesShow::class, ['cliente' => $cliente])
            ->call('alternarAtivo');

        $this->assertFalse($cliente->fresh()->ativo);
    }

    public function test_admin_pode_reativar_cliente(): void
    {
        $user = $this->admin();
        $cliente = Cliente::factory()->create(['ativo' => false]);

        Livewire::actingAs($user)->test(ClientesShow::class, ['cliente' => $cliente])
            ->call('alternarAtivo');

        $this->assertTrue($cliente->fresh()->ativo);
    }

    public function test_vendedor_nao_pode_inativar_cliente(): void
    {
        $user = User::factory()->create(['role' => UserRole::Vendedor->value]);
        $cliente = Cliente::factory()->create(['ativo' => true]);

        // A policy nega a acao (AuthorizationException e convertida em
        // resposta 403 pelo proprio Livewire durante o teste), entao o
        // estado do cliente nao deve mudar.
        Livewire::actingAs($user)->test(ClientesShow::class, ['cliente' => $cliente])
            ->call('alternarAtivo');

        $this->assertTrue($cliente->fresh()->ativo);
    }

    public function test_inativar_cliente_nao_apaga_historico_de_vendas(): void
    {
        $user = $this->admin();
        $cliente = Cliente::factory()->create(['ativo' => true]);
        $venda = Venda::factory()->create(['cliente_id' => $cliente->id, 'total' => 300]);

        Livewire::actingAs($user)->test(ClientesShow::class, ['cliente' => $cliente])
            ->call('alternarAtivo');

        $this->assertDatabaseHas('vendas', [
            'id' => $venda->id,
            'cliente_id' => $cliente->id,
            'total' => 300,
        ]);
    }
}
