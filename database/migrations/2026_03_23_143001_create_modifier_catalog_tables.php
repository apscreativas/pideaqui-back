<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_group_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('selection_type', ['single', 'multiple'])->default('single');
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('max_selections')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->index('restaurant_id');
        });

        Schema::create('modifier_option_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price_adjustment', 10, 2)->default(0);
            $table->decimal('production_cost', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->index('modifier_group_template_id');
        });

        Schema::create('product_modifier_group_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_template_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);

            $table->unique(['product_id', 'modifier_group_template_id'], 'product_template_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_modifier_group_template');
        Schema::dropIfExists('modifier_option_templates');
        Schema::dropIfExists('modifier_group_templates');
    }
};
