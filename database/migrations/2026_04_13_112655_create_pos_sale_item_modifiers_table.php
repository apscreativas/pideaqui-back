<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sale_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_option_id')->nullable()->constrained('modifier_options')->nullOnDelete();
            $table->foreignId('modifier_option_template_id')->nullable()->constrained('modifier_option_templates')->nullOnDelete();
            $table->string('modifier_option_name');
            $table->decimal('price_adjustment', 10, 2)->default(0);
            $table->decimal('production_cost', 10, 2)->default(0);
            $table->timestamps();

            $table->index('pos_sale_item_id');
        });

        // CHECK: exactly one of modifier_option_id / modifier_option_template_id must be set.
        DB::statement('ALTER TABLE pos_sale_item_modifiers ADD CONSTRAINT pos_sale_item_modifiers_source_chk CHECK ((modifier_option_id IS NOT NULL)::int + (modifier_option_template_id IS NOT NULL)::int = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_item_modifiers');
    }
};
