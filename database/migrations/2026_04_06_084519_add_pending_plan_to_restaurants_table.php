<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->foreignId('pending_plan_id')->nullable()->after('plan_id')->constrained('plans')->nullOnDelete();
            $table->timestamp('pending_plan_effective_at')->nullable()->after('pending_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_plan_id');
            $table->dropColumn('pending_plan_effective_at');
        });
    }
};
