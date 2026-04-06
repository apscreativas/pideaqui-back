<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => $name,
            'description' => fake()->sentence(),
            'orders_limit' => 500,
            'max_branches' => 3,
            'monthly_price' => 999.00,
            'yearly_price' => 9990.00,
            'is_default_grace' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function grace(): static
    {
        return $this->state(fn () => [
            'name' => 'Gracia',
            'slug' => 'gracia',
            'description' => 'Plan temporal para restaurantes nuevos',
            'orders_limit' => 50,
            'max_branches' => 1,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'is_default_grace' => true,
            'is_active' => false,
        ]);
    }

    public function basico(): static
    {
        return $this->state(fn () => [
            'name' => 'Básico',
            'slug' => 'basico',
            'description' => 'Ideal para empezar',
            'orders_limit' => 300,
            'max_branches' => 1,
            'monthly_price' => 499.00,
            'yearly_price' => 4990.00,
            'sort_order' => 1,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn () => [
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Para restaurantes en crecimiento',
            'orders_limit' => 1000,
            'max_branches' => 3,
            'monthly_price' => 999.00,
            'yearly_price' => 9990.00,
            'sort_order' => 2,
        ]);
    }

    public function enterprise(): static
    {
        return $this->state(fn () => [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'Para cadenas y alto volumen',
            'orders_limit' => 5000,
            'max_branches' => 10,
            'monthly_price' => 2499.00,
            'yearly_price' => 24990.00,
            'sort_order' => 3,
        ]);
    }
}
