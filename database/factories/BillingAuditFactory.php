<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BillingAudit>
 */
class BillingAuditFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'actor_type' => 'system',
            'actor_id' => null,
            'action' => 'restaurant_created',
            'payload' => null,
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
