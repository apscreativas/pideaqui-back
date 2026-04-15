<?php

namespace Database\Factories;

use App\Models\PosSale;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosSaleItem>
 */
class PosSaleItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_sale_id' => PosSale::factory(),
            'product_id' => Product::factory(),
            'product_name' => $this->faker->words(3, true),
            'quantity' => $this->faker->numberBetween(1, 5),
            'unit_price' => $this->faker->randomFloat(2, 20, 200),
            'production_cost' => $this->faker->randomFloat(2, 5, 50),
            'notes' => null,
        ];
    }
}
