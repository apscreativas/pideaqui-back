<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_name')->default('')->after('product_id');
            $table->decimal('production_cost', 10, 2)->default(0)->after('unit_price');
        });

        // Backfill from products table
        DB::statement('UPDATE order_items SET product_name = (SELECT name FROM products WHERE products.id = order_items.product_id), production_cost = (SELECT production_cost FROM products WHERE products.id = order_items.product_id)');

        Schema::table('order_item_modifiers', function (Blueprint $table) {
            $table->string('modifier_option_name')->default('')->after('modifier_option_id');
            $table->decimal('production_cost', 10, 2)->default(0)->after('price_adjustment');
        });

        // Backfill from modifier_options table
        DB::statement('UPDATE order_item_modifiers SET modifier_option_name = (SELECT name FROM modifier_options WHERE modifier_options.id = order_item_modifiers.modifier_option_id), production_cost = (SELECT production_cost FROM modifier_options WHERE modifier_options.id = order_item_modifiers.modifier_option_id)');
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'production_cost']);
        });

        Schema::table('order_item_modifiers', function (Blueprint $table) {
            $table->dropColumn(['modifier_option_name', 'production_cost']);
        });
    }
};
