<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('cancelled_by')
                ->nullable()
                ->after('cancellation_reason')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->foreignId('cancelled_by')
                ->nullable()
                ->after('cancellation_reason')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by');
        });

        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by');
        });
    }
};
