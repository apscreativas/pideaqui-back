<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => \App\Models\Restaurant::factory(),
            'type' => fake()->randomElement(['cash', 'terminal', 'transfer']),
            'is_active' => false,
        ];
    }

    public function cash(): static
    {
        return $this->state(['type' => 'cash', 'is_active' => true]);
    }

    public function terminal(): static
    {
        return $this->state(['type' => 'terminal', 'is_active' => false]);
    }

    public function transfer(): static
    {
        return $this->state(['type' => 'transfer', 'is_active' => false]);
    }
}
