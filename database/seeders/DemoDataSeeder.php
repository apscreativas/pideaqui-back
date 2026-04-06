<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantSchedule;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $restaurants = Restaurant::all();

        foreach ($restaurants as $restaurant) {
            $this->command->info("Poblando: {$restaurant->name} (ID: {$restaurant->id})");

            $this->ensureBranch($restaurant);
            $this->ensureSchedules($restaurant);
            $this->ensurePaymentMethods($restaurant);
            $categories = $this->seedCategories($restaurant);
            $products = $this->seedProducts($restaurant, $categories);
            $this->seedOrders($restaurant, $products);
        }

        $this->command->info('Demo data creada.');
    }

    private function ensureBranch(Restaurant $restaurant): void
    {
        if ($restaurant->branches()->count() === 0) {
            Branch::factory()->create([
                'restaurant_id' => $restaurant->id,
                'name' => 'Sucursal Principal',
            ]);
        }
    }

    private function ensureSchedules(Restaurant $restaurant): void
    {
        if ($restaurant->schedules()->count() >= 7) {
            return;
        }

        $restaurant->schedules()->delete();

        for ($day = 0; $day <= 6; $day++) {
            RestaurantSchedule::factory()->create([
                'restaurant_id' => $restaurant->id,
                'day_of_week' => $day,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
                'is_closed' => $day === 0, // Domingo cerrado
            ]);
        }
    }

    private function ensurePaymentMethods(Restaurant $restaurant): void
    {
        if ($restaurant->paymentMethods()->count() > 0) {
            return;
        }

        PaymentMethod::factory()->cash()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Category> */
    private function seedCategories(Restaurant $restaurant): \Illuminate\Support\Collection
    {
        if ($restaurant->categories()->count() >= 3) {
            return $restaurant->categories;
        }

        $menuData = [
            ['Entradas', 'Antojitos para abrir el apetito'],
            ['Platos fuertes', 'Lo mejor de nuestra cocina'],
            ['Bebidas', 'Refrescos, aguas y más'],
            ['Postres', 'Para el dulce final'],
        ];

        $categories = collect();

        foreach ($menuData as $i => [$name, $desc]) {
            $categories->push(Category::create([
                'restaurant_id' => $restaurant->id,
                'name' => $name,
                'description' => $desc,
                'sort_order' => $i,
                'is_active' => true,
            ]));
        }

        return $categories;
    }

    /** @return \Illuminate\Support\Collection<int, Product> */
    private function seedProducts(Restaurant $restaurant, $categories): \Illuminate\Support\Collection
    {
        if ($restaurant->products()->count() >= 8) {
            return $restaurant->products;
        }

        $productData = [
            // [category_index, name, price, cost]
            [0, 'Guacamole con totopos', 89, 25],
            [0, 'Quesadillas surtidas', 75, 20],
            [0, 'Sopa azteca', 65, 15],
            [1, 'Tacos al pastor (3 pzas)', 95, 28],
            [1, 'Enchiladas suizas', 120, 35],
            [1, 'Burrito de carne asada', 135, 40],
            [1, 'Pollo en mole', 145, 42],
            [1, 'Chilaquiles verdes', 85, 22],
            [2, 'Agua de horchata', 35, 8],
            [2, 'Refresco', 30, 10],
            [2, 'Limonada natural', 40, 10],
            [3, 'Flan napolitano', 55, 15],
            [3, 'Churros con chocolate', 65, 18],
        ];

        $products = collect();

        foreach ($productData as $i => [$catIndex, $name, $price, $cost]) {
            $category = $categories[$catIndex] ?? $categories->first();

            $products->push(Product::create([
                'restaurant_id' => $restaurant->id,
                'category_id' => $category->id,
                'name' => $name,
                'price' => $price,
                'production_cost' => $cost,
                'sort_order' => $i,
                'is_active' => true,
            ]));
        }

        return $products;
    }

    private function seedOrders(Restaurant $restaurant, $products): void
    {
        $branch = $restaurant->branches()->first();

        if (! $branch) {
            return;
        }

        $paymentMethods = ['cash', 'terminal', 'transfer'];
        $deliveryTypes = ['delivery', 'pickup', 'dine_in'];
        $statuses = ['received', 'preparing', 'delivered', 'delivered', 'delivered', 'delivered', 'cancelled'];

        // Crear entre 20 y 40 pedidos distribuidos en los últimos 30 días
        $orderCount = rand(20, 40);

        for ($i = 0; $i < $orderCount; $i++) {
            $daysAgo = rand(0, 29);
            $hoursAgo = rand(8, 21);
            $createdAt = now()->subDays($daysAgo)->setHour($hoursAgo)->setMinute(rand(0, 59));

            $status = $statuses[array_rand($statuses)];
            $deliveryType = $deliveryTypes[array_rand($deliveryTypes)];
            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];

            // Pick 1-4 random products for this order
            $orderProducts = $products->random(rand(1, min(4, $products->count())));
            $subtotal = 0;
            $items = [];

            foreach ($orderProducts as $product) {
                $qty = rand(1, 3);
                $unitPrice = (float) $product->price;
                $subtotal += $unitPrice * $qty;

                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'production_cost' => (float) $product->production_cost,
                ];
            }

            $deliveryCost = $deliveryType === 'delivery' ? rand(25, 60) : 0;
            $total = $subtotal + $deliveryCost;

            $customer = Customer::factory()->create();

            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'delivery_type' => $deliveryType,
                'status' => $status,
                'subtotal' => $subtotal,
                'delivery_cost' => $deliveryCost,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'cash_amount' => $paymentMethod === 'cash' ? ceil($total / 50) * 50 : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'cancelled_at' => $status === 'cancelled' ? $createdAt->copy()->addMinutes(rand(5, 30)) : null,
                'cancellation_reason' => $status === 'cancelled' ? 'Cliente no disponible' : null,
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'production_cost' => $item['production_cost'],
                ]);
            }
        }

        $this->command->info("  → {$orderCount} pedidos creados para {$restaurant->name}");
    }
}
