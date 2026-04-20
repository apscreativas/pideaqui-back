<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pos_sale_items DROP CONSTRAINT pos_sale_items_product_id_foreign');
        DB::statement('ALTER TABLE pos_sale_items ALTER COLUMN product_id DROP NOT NULL');
        DB::statement('ALTER TABLE pos_sale_items ADD CONSTRAINT pos_sale_items_product_id_foreign
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pos_sale_items DROP CONSTRAINT pos_sale_items_product_id_foreign');
        DB::statement('DELETE FROM pos_sale_items WHERE product_id IS NULL');
        DB::statement('ALTER TABLE pos_sale_items ALTER COLUMN product_id SET NOT NULL');
        DB::statement('ALTER TABLE pos_sale_items ADD CONSTRAINT pos_sale_items_product_id_foreign
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT');
    }
};
