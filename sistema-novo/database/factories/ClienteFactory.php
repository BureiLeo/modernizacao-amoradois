<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => $this->faker->unique()->numberBetween(1, 999999),
            'nome' => $this->faker->name(),
            'telefone' => $this->faker->optional()->numerify('#############'),
            'email' => $this->faker->optional()->safeEmail(),
            'cpf' => null,
            'rg' => null,
            'cnpj' => null,
            'razao_social' => null,
            'endereco' => $this->faker->optional()->address(),
            'observacoes' => null,
            'ativo' => true,
        ];
    }
}
