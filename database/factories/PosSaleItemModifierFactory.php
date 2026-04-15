<?php

namespace Database\Factories;

use App\Models\ModifierOption;
use App\Models\PosSaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosSaleItemModifier>
 */
class PosSaleItemModifierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_sale_item_id' => PosSaleItem::factory(),
            'modifier_option_id' => ModifierOption::factory(),
            'modifier_option_template_id' => null,
            'modifier_option_name' => $this->faker->words(2, true),
            'price_adjustment' => $this->faker->randomFloat(2, 0, 50),
            'production_cost' => $this->faker->randomFloat(2, 0, 20),
        ];
    }
}
