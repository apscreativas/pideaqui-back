<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('signup_source', 32)->nullable()->after('billing_mode');
            $table->index('signup_source');
        });

        DB::statement("UPDATE restaurants SET signup_source = 'super_admin' WHERE signup_source IS NULL");
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex(['signup_source']);
            $table->dropColumn('signup_source');
        });
    }
};
