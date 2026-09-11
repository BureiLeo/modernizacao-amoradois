<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Produto;
use App\Models\ProdutoBom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProdutoBom>
 */
class ProdutoBomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'produto_id' => Produto::factory(),
            'material_id' => Material::factory(),
            'quantidade' => $this->faker->randomFloat(4, 0.1, 5),
        ];
    }
}
