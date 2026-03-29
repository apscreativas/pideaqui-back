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
            $table->string('primary_color')->nullable()->after('tiktok');
            $table->string('secondary_color')->nullable()->after('primary_color');
            $table->string('default_product_image')->nullable()->after('secondary_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color', 'default_product_image']);
        });
    }
};
