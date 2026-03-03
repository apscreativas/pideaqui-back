<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add production_cost to modifier_options.
        Schema::table('modifier_options', function (Blueprint $table) {
            $table->decimal('production_cost', 10, 2)->default(0)->after('price_adjustment');
        });

        // 2. Add nullable product_id to modifier_groups.
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('restaurant_id');
        });

        // 3. Backfill: assign product_id from the pivot table.
        $pivotRows = DB::table('modifier_group_product')->get();
        $grouped = $pivotRows->groupBy('modifier_group_id');

        foreach ($grouped as $groupId => $rows) {
            $first = true;
            foreach ($rows as $row) {
                if ($first) {
                    // Assign directly to the original group.
                    DB::table('modifier_groups')
                        ->where('id', $groupId)
                        ->update(['product_id' => $row->product_id]);
                    $first = false;
                } else {
                    // Duplicate the group for additional products.
                    $original = DB::table('modifier_groups')->where('id', $groupId)->first();
                    $newGroupId = DB::table('modifier_groups')->insertGetId([
                        'restaurant_id' => $original->restaurant_id,
                        'product_id' => $row->product_id,
                        'name' => $original->name,
                        'selection_type' => $original->selection_type,
                        'is_required' => $original->is_required,
                        'sort_order' => $original->sort_order,
                    ]);

                    // Duplicate options.
                    $options = DB::table('modifier_options')->where('modifier_group_id', $groupId)->get();
                    foreach ($options as $opt) {
                        DB::table('modifier_options')->insert([
                            'modifier_group_id' => $newGroupId,
                            'name' => $opt->name,
                            'price_adjustment' => $opt->price_adjustment,
                            'production_cost' => $opt->production_cost,
                            'sort_order' => $opt->sort_order,
                        ]);
                    }
                }
            }
        }

        // Delete orphan groups that have no product_id (never attached to any product).
        DB::table('modifier_groups')->whereNull('product_id')->delete();

        // 4. Make product_id NOT NULL and add FK.
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        // 5. Drop pivot table.
        Schema::dropIfExists('modifier_group_product');
    }

    public function down(): void
    {
        // Recreate pivot table.
        Schema::create('modifier_group_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unique(['modifier_group_id', 'product_id']);
        });

        // Backfill pivot from product_id.
        $groups = DB::table('modifier_groups')->whereNotNull('product_id')->get();
        foreach ($groups as $group) {
            DB::table('modifier_group_product')->insert([
                'modifier_group_id' => $group->id,
                'product_id' => $group->product_id,
            ]);
        }

        // Drop FK and product_id from modifier_groups.
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        // Drop production_cost from modifier_options.
        Schema::table('modifier_options', function (Blueprint $table) {
            $table->dropColumn('production_cost');
        });
    }
};
