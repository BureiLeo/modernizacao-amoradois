<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstoqueIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_estoque_lista_materiais_com_status(): void
    {
        $user = User::factory()->create();

        Material::factory()->create(['nome' => 'Caneca branca', 'estoque' => 20, 'estoque_minimo' => 5]);
        Material::factory()->create(['nome' => 'Papel transfer', 'estoque' => 2, 'estoque_minimo' => 5]);
        Material::factory()->create(['nome' => 'Tinta preta', 'estoque' => 0, 'estoque_minimo' => 1]);

        $this->actingAs($user)
            ->get(route('estoque.index'))
            ->assertOk()
            ->assertSee('Caneca branca')
            ->assertSee('Em estoque')
            ->assertSee('Estoque baixo')
            ->assertSee('Sem estoque');
    }

    public function test_materiais_index_redireciona_para_estoque(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/materiais')->assertRedirect('/estoque');
        $this->actingAs($user)->get('/estoque/materiais')->assertRedirect('/estoque');
    }

    public function test_quantidade_inteira_aparece_sem_casas_decimais(): void
    {
        $this->assertSame('12', Format::quantidade(12));
        $this->assertSame('12', Format::quantidade('12.000'));
        $this->assertSame('1.500', Format::quantidade(1500));
        $this->assertSame('0', Format::quantidade(0));
    }

    public function test_quantidade_fracionada_mantem_apenas_as_casas_usadas(): void
    {
        $this->assertSame('12,5', Format::quantidade('12.500'));
        $this->assertSame('0,125', Format::quantidade(0.125));
        $this->assertSame('47,5', Format::quantidade('47.500'));
    }

    public function test_estoque_exibe_quantidade_sem_zeros_a_direita(): void
    {
        $user = User::factory()->create();

        Material::factory()->create(['nome' => 'Caneca branca', 'estoque' => 12, 'estoque_minimo' => 0]);

        $this->actingAs($user)
            ->get(route('estoque.index'))
            ->assertOk()
            ->assertDontSee('12,000');
    }
}
