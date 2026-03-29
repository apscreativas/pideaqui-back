<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RestaurantSpecialDate>
 */
class RestaurantSpecialDateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'type' => 'closed',
            'opens_at' => null,
            'closes_at' => null,
            'label' => fake()->word(),
            'is_recurring' => false,
        ];
    }

    public function holiday(string $label = 'Día festivo'): static
    {
        return $this->state(fn () => [
            'type' => 'closed',
            'opens_at' => null,
            'closes_at' => null,
            'label' => $label,
        ]);
    }

    public function specialHours(string $opens = '10:00', string $closes = '15:00'): static
    {
        return $this->state(fn () => [
            'type' => 'special',
            'opens_at' => $opens,
            'closes_at' => $closes,
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn () => [
            'is_recurring' => true,
        ]);
    }
}
