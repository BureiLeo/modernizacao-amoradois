<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Material;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'produto_id' => null,
            'produto_variacao_id' => null,
            'user_id' => null,
            'type' => StockMovementType::Ajuste->value,
            'quantity' => $this->faker->randomFloat(3, -20, 20),
            'quantity_before' => null,
            'quantity_after' => null,
            'source_type' => null,
            'source_id' => null,
            'observacoes' => null,
        ];
    }
}
