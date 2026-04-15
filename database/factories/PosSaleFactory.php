<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosSale>
 */
class PosSaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 50, 500);

        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => Branch::factory(),
            'cashier_user_id' => User::factory(),
            'ticket_number' => 'POS-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 4, '0', STR_PAD_LEFT),
            'status' => 'preparing',
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'notes' => null,
        ];
    }

    public function preparing(): static
    {
        return $this->state(['status' => 'preparing']);
    }

    public function ready(): static
    {
        return $this->state(['status' => 'ready', 'prepared_at' => now()]);
    }

    public function paid(): static
    {
        return $this->state(['status' => 'paid', 'prepared_at' => now()->subMinutes(10), 'paid_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cliente desistió',
        ]);
    }
}
