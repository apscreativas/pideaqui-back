<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_special_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['closed', 'special'])->default('closed');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->string('label')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();

            $table->unique(['restaurant_id', 'date']);
            $table->index('restaurant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_special_dates');
    }
};
