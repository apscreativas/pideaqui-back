<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('address_street', 255)->nullable()->after('distance_km');
            $table->string('address_number', 50)->nullable()->after('address_street');
            $table->string('address_colony', 255)->nullable()->after('address_number');
        });

        // Best-effort: copy existing address into address_street
        DB::table('orders')->whereNotNull('address')->update([
            'address_street' => DB::raw('address'),
        ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('address', 500)->nullable()->after('distance_km');
        });

        DB::table('orders')->whereNotNull('address_street')->update([
            'address' => DB::raw("CONCAT(address_street, COALESCE(CONCAT(' #', address_number), ''), COALESCE(CONCAT(', Col. ', address_colony), ''))"),
        ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['address_street', 'address_number', 'address_colony']);
        });
    }
};
