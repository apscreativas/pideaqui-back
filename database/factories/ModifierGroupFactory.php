<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModifierGroup>
 */
class ModifierGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'product_id' => Product::factory(),
            'name' => fake()->words(2, true),
            'selection_type' => fake()->randomElement(['single', 'multiple']),
            'is_required' => fake()->boolean(),
            'sort_order' => fake()->numberBetween(0, 5),
        ];
    }
}
