<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('billing_mode')->default('manual')->after('status');
            $table->string('pending_billing_cycle')->nullable()->after('pending_plan_effective_at');
        });

        // Backfill: restaurants with a plan are in subscription mode
        DB::table('restaurants')
            ->whereNotNull('plan_id')
            ->update(['billing_mode' => 'subscription']);
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['billing_mode', 'pending_billing_cycle']);
        });
    }
};
