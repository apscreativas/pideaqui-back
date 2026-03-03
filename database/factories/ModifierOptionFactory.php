<?php

namespace Database\Factories;

use App\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModifierOption>
 */
class ModifierOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'modifier_group_id' => ModifierGroup::factory(),
            'name' => fake()->words(2, true),
            'price_adjustment' => fake()->randomFloat(2, 0, 50),
            'production_cost' => 0,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
