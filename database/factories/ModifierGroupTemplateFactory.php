<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModifierGroupTemplate>
 */
class ModifierGroupTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->words(2, true),
            'selection_type' => fake()->randomElement(['single', 'multiple']),
            'is_required' => fake()->boolean(),
            'max_selections' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 5),
        ];
    }
}
