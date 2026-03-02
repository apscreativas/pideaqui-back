<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->boolean('allows_delivery')->default(true)->after('is_active');
            $table->boolean('allows_pickup')->default(true)->after('allows_delivery');
            $table->boolean('allows_dine_in')->default(false)->after('allows_pickup');
            $table->string('instagram')->nullable()->after('max_branches');
            $table->string('facebook')->nullable()->after('instagram');
            $table->string('tiktok')->nullable()->after('facebook');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['allows_delivery', 'allows_pickup', 'allows_dine_in', 'instagram', 'facebook', 'tiktok']);
        });
    }
};
