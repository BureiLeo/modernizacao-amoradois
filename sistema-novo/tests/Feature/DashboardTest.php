<?php

namespace Tests\Feature;

use App\Enums\StatusPagamento;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\CaixaMovimento;
use App\Models\Cliente;
use App\Models\Material;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_e_redirecionado_para_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_usuario_autenticado_consegue_abrir_o_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_nome_do_usuario_aparece_no_layout(): void
    {
        $user = User::factory()->create(['name' => 'Maiuly Sanches']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('Maiuly Sanches');
    }

    public function test_dashboard_funciona_sem_nenhuma_venda(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('R$ 0,00');
        // Ticket medio nao pode gerar erro de divisao por zero.
        $response->assertDontSee('Division by zero');
    }

    public function test_dashboard_funciona_com_venda_sem_cliente(): void
    {
        $user = User::factory()->create();
        $venda = Venda::factory()->create([
            'cliente_id' => null,
            'data' => now(),
            'total' => 100,
            'pago' => true,
        ]);
        VendaItem::factory()->create(['venda_id' => $venda->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Sem cliente');
    }

    public function test_indicadores_calculam_corretamente(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $cliente = Cliente::factory()->create();

        Venda::factory()->create([
            'cliente_id' => $cliente->id,
            'data' => now(),
            'subtotal' => 100,
            'total' => 100,
            'custo_total' => 40,
            'lucro_total' => 60,
            'pago' => true,
        ]);
        $vendaPendente = Venda::factory()->create([
            'cliente_id' => $cliente->id,
            'data' => now(),
            'subtotal' => 200,
            'total' => 200,
            'custo_total' => 80,
            'lucro_total' => 120,
            'pago' => false,
        ]);

        // Vinculado a uma venda ja existente para nao criar uma 3a venda
        // "fantasma" via o factory de Pagamento (que por padrao gera sua
        // propria Venda::factory() se venda_id nao for informado).
        Pagamento::factory()->create([
            'venda_id' => $vendaPendente->id,
            'status' => StatusPagamento::Pendente->value,
            'valor' => 200,
        ]);

        Livewire::test(DashboardIndex::class)
            ->assertSee('R$ 300,00') // faturamento (100+200)
            ->assertSee('R$ 150,00') // ticket medio (300/2)
            ->assertSee('R$ 180,00') // lucro estimado (60+120)
            ->assertSee('R$ 200,00'); // valor pendente
    }

    public function test_material_com_estoque_abaixo_do_minimo_aparece_no_alerta(): void
    {
        $user = User::factory()->create();
        Material::factory()->create([
            'nome' => 'Caneca Branca',
            'estoque' => 2,
            'estoque_minimo' => 5,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('Caneca Branca');
    }

    public function test_estoque_em_dia_mostra_empty_state(): void
    {
        $user = User::factory()->create();
        Material::factory()->create(['estoque' => 50, 'estoque_minimo' => 5]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('Estoque em dia');
    }

    public function test_filtro_de_periodo_atualiza_os_indicadores(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Venda::factory()->create([
            'data' => now()->subDays(20),
            'total' => 500,
            'pago' => true,
        ]);

        $component = Livewire::test(DashboardIndex::class)
            ->set('period', 'hoje');

        $component->assertSee('R$ 0,00');

        $component->set('period', '30dias');
        $component->assertSee('R$ 500,00');
    }

    public function test_produtos_mais_vendidos_aparece_no_dashboard(): void
    {
        $user = User::factory()->create();
        $produto = Produto::factory()->create(['nome' => 'Caneca Personalizada']);
        $venda = Venda::factory()->create(['data' => now()]);
        VendaItem::factory()->create([
            'venda_id' => $venda->id,
            'produto_id' => $produto->id,
            'quantidade' => 3,
            'subtotal' => 90,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('Caneca Personalizada');
    }

    public function test_saldo_em_caixa_soma_os_movimentos_com_sinal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        CaixaMovimento::factory()->create(['valor' => 500]);
        CaixaMovimento::factory()->create(['valor' => -120]);

        Livewire::test(DashboardIndex::class)
            ->assertSee('Saldo em caixa')
            ->assertSee('R$ 380,00');
    }

    public function test_saldo_em_caixa_pode_ser_ocultado_e_a_preferencia_persiste(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        CaixaMovimento::factory()->create(['valor' => 380]);

        Livewire::test(DashboardIndex::class)
            ->assertSee('R$ 380,00')
            ->call('toggleSaldoCaixa')
            ->assertDontSee('R$ 380,00')
            ->assertSee('••••••');

        // Reabrindo o componente (nova instancia): a preferencia de
        // ocultar deve ter sido persistida na sessao do usuario.
        Livewire::test(DashboardIndex::class)
            ->assertDontSee('R$ 380,00')
            ->assertSee('••••••');
    }
}
