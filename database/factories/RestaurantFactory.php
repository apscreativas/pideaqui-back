<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.fake()->numerify('###'),
            'logo_path' => null,
            'is_active' => true,
            'orders_limit' => 500,
            'orders_limit_start' => now()->startOfMonth(),
            'orders_limit_end' => now()->endOfMonth(),
            'max_branches' => 3,
            'status' => 'active',
            'billing_mode' => 'manual',
        ];
    }

    public function subscription(): static
    {
        return $this->state(fn () => [
            'billing_mode' => 'subscription',
        ]);
    }

    public function grace(): static
    {
        return $this->state(fn () => [
            'billing_mode' => 'subscription',
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays(14),
            'orders_limit' => 50,
            'max_branches' => 1,
        ]);
    }

    public function selfSignup(): static
    {
        return $this->state(fn () => [
            'signup_source' => 'self_signup',
        ]);
    }
}
