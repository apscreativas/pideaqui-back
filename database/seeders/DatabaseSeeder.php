<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            BillingSeeder::class,
        ]);

        if (app()->environment('local')) {
            $user = \App\Models\User::firstOrCreate(
                ['email' => 'admin@restaurante.com'],
                [
                    'name' => 'Admin Restaurante',
                    'password' => \Illuminate\Support\Facades\Hash::make('password'),
                ]
            );

            $restaurant = \App\Models\Restaurant::firstOrCreate(
                ['name' => 'El Gran Sabor'],
                [
                    'slug' => 'el-gran-sabor',
                    'access_token' => \Illuminate\Support\Str::random(60),
                ]
            );

            $user->update(['restaurant_id' => $restaurant->id]);

            // Seed default payment methods
            \App\Models\PaymentMethod::create([
                'restaurant_id' => $restaurant->id,
                'type' => 'cash',
                'is_active' => true,
            ]);

            \App\Models\PaymentMethod::create([
                'restaurant_id' => $restaurant->id,
                'type' => 'transfer',
                'is_active' => true,
                'bank_name' => 'BBVA Bancomer',
                'account_holder' => 'Administrador GuisoGo',
                'clabe' => '012345678901234567',
            ]);

            $this->call([
                MenuSeeder::class,
            ]);
        }
    }
}
