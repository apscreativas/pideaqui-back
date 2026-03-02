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
            'access_token' => \Illuminate\Support\Str::random(64),
            'is_active' => true,
            'max_monthly_orders' => 500,
            'max_branches' => 3,
        ];
    }
}
