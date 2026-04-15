<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['restaurant_id', 'cancelled_at'], 'orders_restaurant_id_cancelled_at_index');
            $table->index(['restaurant_id', 'status', 'created_at'], 'orders_restaurant_status_created_at_index');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->index(['restaurant_id', 'cancelled_at'], 'pos_sales_restaurant_id_cancelled_at_index');
        });

        Schema::table('pos_payments', function (Blueprint $table) {
            $table->index(['pos_sale_id', 'payment_method_type'], 'pos_payments_sale_method_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_restaurant_id_cancelled_at_index');
            $table->dropIndex('orders_restaurant_status_created_at_index');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropIndex('pos_sales_restaurant_id_cancelled_at_index');
        });

        Schema::table('pos_payments', function (Blueprint $table) {
            $table->dropIndex('pos_payments_sale_method_index');
        });
    }
};
