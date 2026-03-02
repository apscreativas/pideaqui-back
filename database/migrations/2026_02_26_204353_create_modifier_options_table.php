<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price_adjustment', 10, 2)->default(0);
            $table->integer('sort_order')->default(0);

            $table->index('modifier_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_options');
    }
};
