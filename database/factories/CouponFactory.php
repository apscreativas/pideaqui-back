<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'code' => strtoupper(fake()->unique()->lexify('??????')),
            'discount_type' => 'fixed',
            'discount_value' => fake()->randomFloat(2, 10, 100),
            'max_discount' => null,
            'min_purchase' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'max_uses_per_customer' => null,
            'max_total_uses' => null,
            'is_active' => true,
        ];
    }

    public function fixed(float $value = 50.00): static
    {
        return $this->state([
            'discount_type' => 'fixed',
            'discount_value' => $value,
        ]);
    }

    public function percentage(float $value = 15.00, ?float $maxDiscount = null): static
    {
        return $this->state([
            'discount_type' => 'percentage',
            'discount_value' => $value,
            'max_discount' => $maxDiscount,
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function future(): static
    {
        return $this->state([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(30),
        ]);
    }
}
