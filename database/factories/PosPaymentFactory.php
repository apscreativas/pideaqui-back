<?php

namespace Database\Factories;

use App\Models\PosSale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosPayment>
 */
class PosPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_sale_id' => PosSale::factory(),
            'payment_method_type' => 'cash',
            'amount' => $this->faker->randomFloat(2, 50, 500),
            'cash_received' => null,
            'change_given' => null,
            'registered_by_user_id' => User::factory(),
            'created_at' => now(),
        ];
    }

    public function cash(float $amount, float $received): static
    {
        return $this->state([
            'payment_method_type' => 'cash',
            'amount' => $amount,
            'cash_received' => $received,
            'change_given' => round($received - $amount, 2),
        ]);
    }

    public function terminal(float $amount): static
    {
        return $this->state([
            'payment_method_type' => 'terminal',
            'amount' => $amount,
            'cash_received' => null,
            'change_given' => null,
        ]);
    }

    public function transfer(float $amount): static
    {
        return $this->state([
            'payment_method_type' => 'transfer',
            'amount' => $amount,
            'cash_received' => null,
            'change_given' => null,
        ]);
    }
}
