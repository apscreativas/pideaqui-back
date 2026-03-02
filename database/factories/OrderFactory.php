<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50, 500);

        return [
            'restaurant_id' => \App\Models\Restaurant::factory(),
            'branch_id' => \App\Models\Branch::factory(),
            'customer_id' => \App\Models\Customer::factory(),
            'delivery_type' => fake()->randomElement(['delivery', 'pickup', 'dine_in']),
            'status' => 'received',
            'subtotal' => $subtotal,
            'delivery_cost' => 0.00,
            'total' => $subtotal,
            'payment_method' => 'cash',
        ];
    }
}
