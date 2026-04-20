<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pos_sale_item_modifiers DROP CONSTRAINT IF EXISTS pos_sale_item_modifiers_source_chk');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM pos_sale_item_modifiers WHERE modifier_option_id IS NULL AND modifier_option_template_id IS NULL');
        DB::statement('ALTER TABLE pos_sale_item_modifiers ADD CONSTRAINT pos_sale_item_modifiers_source_chk CHECK ((modifier_option_id IS NOT NULL)::int + (modifier_option_template_id IS NOT NULL)::int = 1)');
    }
};
