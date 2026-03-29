<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_required');
            $table->unsignedInteger('max_selections')->nullable()->after('is_active');
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('production_cost');
        });
    }

    public function down(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'max_selections']);
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
