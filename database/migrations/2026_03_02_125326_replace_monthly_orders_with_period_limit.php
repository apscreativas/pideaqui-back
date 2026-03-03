<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->renameColumn('max_monthly_orders', 'orders_limit');
        });

        Schema::table('restaurants', function (Blueprint $table): void {
            $table->date('orders_limit_start')->nullable()->after('orders_limit');
            $table->date('orders_limit_end')->nullable()->after('orders_limit_start');
        });

        // Backfill: assign current month range to existing restaurants
        DB::table('restaurants')->update([
            'orders_limit_start' => now()->startOfMonth()->toDateString(),
            'orders_limit_end' => now()->endOfMonth()->toDateString(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn(['orders_limit_start', 'orders_limit_end']);
        });

        Schema::table('restaurants', function (Blueprint $table): void {
            $table->renameColumn('orders_limit', 'max_monthly_orders');
        });
    }
};
