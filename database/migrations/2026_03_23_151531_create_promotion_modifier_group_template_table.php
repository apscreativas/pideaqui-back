<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_modifier_group_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_template_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);

            $table->unique(['promotion_id', 'modifier_group_template_id'], 'promotion_template_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_modifier_group_template');
    }
};
