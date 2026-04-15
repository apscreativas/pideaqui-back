<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => Branch::factory(),
            'expense_category_id' => ExpenseCategory::factory(),
            'expense_subcategory_id' => ExpenseSubcategory::factory(),
            'created_by_user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'expense_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
        ];
    }
}
