<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('restaurant_id')->constrained()->restrictOnDelete();
            $table->index(['restaurant_id', 'branch_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'branch_id', 'expense_date']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
