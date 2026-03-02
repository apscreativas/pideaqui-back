<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['cash', 'terminal', 'transfer']);
            $table->boolean('is_active')->default(false);
            $table->string('bank_name')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('clabe', 18)->nullable();
            $table->string('alias')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
