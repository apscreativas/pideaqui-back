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
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['status', 'grace_period_ends_at', 'subscription_ends_at']);
        });
    }
};
