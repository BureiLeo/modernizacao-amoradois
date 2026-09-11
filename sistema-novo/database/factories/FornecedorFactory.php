<?php

namespace Database\Factories;

use App\Models\Fornecedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fornecedor>
 */
class FornecedorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => $this->faker->company(),
            'razao_social' => $this->faker->optional()->company().' LTDA',
            'cnpj' => null,
            'telefone' => $this->faker->optional()->numerify('#############'),
            'email' => $this->faker->optional()->companyEmail(),
            'observacoes' => null,
            'ativo' => true,
        ];
    }
}
