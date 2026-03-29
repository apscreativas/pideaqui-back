<?php

namespace Database\Factories;

use App\Models\ModifierGroupTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModifierOptionTemplate>
 */
class ModifierOptionTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'modifier_group_template_id' => ModifierGroupTemplate::factory(),
            'name' => fake()->words(2, true),
            'price_adjustment' => fake()->randomFloat(2, 0, 50),
            'production_cost' => 0,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
